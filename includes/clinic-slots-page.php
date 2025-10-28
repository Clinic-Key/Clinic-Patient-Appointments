<?php
if (!defined('ABSPATH')) {
    exit;
}

// Shortcode to display the slots form
function add_clinic_slots_form() {
    // Ensure only clinic owners can see the form
    if (!current_user_can('clinic_owner')) {
        return 'You do not have sufficient permissions to access this page.';
    }

    // Get the clinic ID associated with the current user
    $user_id = get_current_user_id();
    $clinic_id = false;

    $clinics = get_posts(array(
        'post_type' => 'clinic',
        'author' => $user_id,
        'posts_per_page' => 1
    ));

    if (!empty($clinics)) {
        $clinic_id = $clinics[0]->ID;
    } else {
        return 'You are not associated with any clinic.';
    }

    // Retrieve the translated clinic ID if available
    $translated_clinic_id = pll_get_post($clinic_id, 'ar'); // Assuming 'ar' is the language code for Arabic

    // Retrieve existing slots
    global $wpdb;
    $slots_table = $wpdb->prefix . 'clinics_available_slots';
    $settings_table = $wpdb->prefix . 'clinic_settings';
    $existing_slots = $wpdb->get_results($wpdb->prepare("SELECT * FROM $slots_table WHERE clinic_id = %d", $clinic_id));
    $existing_settings = $wpdb->get_row($wpdb->prepare("SELECT * FROM $settings_table WHERE clinic_id = %d", $clinic_id));

    $selected_interval = $existing_settings ? $existing_settings->appointment_interval : '';

    ob_start();
    ?>
    <div id="clinic-slots-form">
        <h2>Add Available Slots</h2>
        <form id="slots-form">
            <input type="hidden" name="action" value="add_clinic_slot">
            <input type="hidden" name="clinic_id" value="<?php echo esc_attr($clinic_id); ?>">
            <?php if ($translated_clinic_id): ?>
                <input type="hidden" name="translated_clinic_id" value="<?php echo esc_attr($translated_clinic_id); ?>">
            <?php endif; ?>

            <label for="appointment-interval">Select Appointment Interval:</label>
            <select id="appointment-interval" name="appointment_interval" required>
                <?php
                $intervals = ['15 minutes', '30 minutes', '45 minutes', '1 hour', '1 hour 15 minutes', '1 hour 30 minutes'];
                foreach ($intervals as $interval) {
                    echo '<option value="' . $interval . '"' . selected($interval, $selected_interval, false) . '>' . $interval . '</option>';
                }
                ?>
            </select>

            <?php
            $days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            foreach ($days_of_week as $day) {
                $day_lower = strtolower($day);
                $start_time = '';
                $end_time = '';
                foreach ($existing_slots as $slot) {
                    if (strtolower($slot->day_of_week) == $day_lower) {
                        $start_time = $slot->start_time;
                        $end_time = $slot->end_time;
                    }
                }
                ?>
                <div class="day-row" data-day="<?php echo $day_lower; ?>">
                    <label><?php echo $day; ?></label>
                    <div class="time-slot">
                        <input type="time" name="<?php echo $day_lower; ?>_start" value="<?php echo $start_time; ?>" placeholder="Start Time">
                        <input type="time" name="<?php echo $day_lower; ?>_end" value="<?php echo $end_time; ?>" placeholder="End Time">
                    </div>
                </div>
                <?php
            }
            ?>
            <button type="submit">Add Slots</button>
        </form>
        <div id="slots-message"></div>
    </div>
    <?php
    return ob_get_clean();
}

add_shortcode('clinic_slots_form', 'add_clinic_slots_form');

// Handle form submission
function handle_add_clinic_slot() {
    global $wpdb;
    $slots_table = $wpdb->prefix . 'clinics_available_slots';
    $settings_table = $wpdb->prefix . 'clinic_settings';

    $clinic_id = intval($_POST['clinic_id']);
    $translated_clinic_id = isset($_POST['translated_clinic_id']) ? intval($_POST['translated_clinic_id']) : false;
    $appointment_interval = sanitize_text_field($_POST['appointment_interval']);
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    // Retrieve the translated clinic ID for both languages (English and Arabic)
    $clinic_id_ar = pll_get_post($clinic_id, 'ar');
    $clinic_id_en = pll_get_post($clinic_id, 'en');

    // Function to update slots and settings for a clinic ID
    function update_slots_and_settings($clinic_id, $appointment_interval, $days, $slots_table, $settings_table) {
        global $wpdb;
        
        // Save appointment interval
        $existing_settings = $wpdb->get_row($wpdb->prepare("SELECT * FROM $settings_table WHERE clinic_id = %d", $clinic_id));
        if ($existing_settings) {
            $wpdb->update($settings_table, array('appointment_interval' => $appointment_interval), array('clinic_id' => $clinic_id));
        } else {
            $wpdb->insert($settings_table, array('clinic_id' => $clinic_id, 'appointment_interval' => $appointment_interval));
        }

        // Delete existing slots for the clinic
        $wpdb->delete($slots_table, array('clinic_id' => $clinic_id));

        foreach ($days as $day) {
            $start_time = sanitize_text_field($_POST[strtolower($day) . '_start']);
            $end_time = sanitize_text_field($_POST[strtolower($day) . '_end']);

            if ($start_time && $end_time) {
                $wpdb->insert($slots_table, array(
                    'clinic_id' => $clinic_id,
                    'day_of_week' => $day,
                    'start_time' => $start_time,
                    'end_time' => $end_time,
                    'slot_interval' => $appointment_interval
                ));
            }
        }
    }

    // Update slots and settings for both the original and translated clinic
    if ($clinic_id_ar) {
        update_slots_and_settings($clinic_id_ar, $appointment_interval, $days, $slots_table, $settings_table);
    }
    if ($clinic_id_en) {
        update_slots_and_settings($clinic_id_en, $appointment_interval, $days, $slots_table, $settings_table);
    }

    echo 'Slots added successfully';
    wp_die();
}

add_action('wp_ajax_add_clinic_slot', 'handle_add_clinic_slot');
