<?php
/*
 * Plugin Name:       Clinic patients appointments
 * Description:       A custom plugin made for allowing appointments on the website
 * Version:           1.0.4
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Yellow Polar
 * Author URI:        https://kazmiwebwhiz.com/
 * Text Domain:       clinic-patients-appointments
 */

if (!defined('ABSPATH')) {
    exit;
}

// Include necessary files
require_once plugin_dir_path(__FILE__) . 'includes/constants.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin-page.php';
require_once plugin_dir_path(__FILE__) . 'includes/clinic-slots-page.php';
include_once(plugin_dir_path(__FILE__) . 'includes/patient-appointments.php'); 
require_once plugin_dir_path(__FILE__) . 'includes/emails.php';

// Function to create the database tables
function create_clinic_appointments_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Table for patient appointments
    $appointments_table = $wpdb->prefix . 'clinic_patient_appointments';
    $appointments_sql = "CREATE TABLE $appointments_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        clinic_id mediumint(9) NOT NULL,
        patient_id mediumint(9) NOT NULL,
        appointment_datetime datetime NOT NULL,
        doctor_name varchar(255) NOT NULL,
        appointment_interval varchar(255) NOT NULL,
        status varchar(50) DEFAULT '" . STATUS_PENDING . "' NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    // Execute the SQL queries
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($appointments_sql);

    // Log the SQL queries to debug
    error_log("Appointments SQL: $appointments_sql");
}
register_activation_hook(__FILE__, 'create_clinic_appointments_tables');

// Define the shortcode for the "Book Appointment" button
function book_appointment_button_shortcode() {
    if (is_singular('clinic')) {
        return '<button id="book-appointment-button">' . esc_html__('Book Appointment', 'clinic-patients-appointments') . '</button>';
    }
}
add_shortcode('book_appointment_button', 'book_appointment_button_shortcode');


// Get available slots
function handle_get_available_slots() {
    global $wpdb;
    $clinic_id = intval($_POST['clinic_id']);
    $date = sanitize_text_field($_POST['date']);
    $day_of_week = date('l', strtotime($date));

    // Table names
    $slots_table = $wpdb->prefix . 'clinics_available_slots';
    $appointments_table = $wpdb->prefix . 'clinic_patient_appointments';

    // Get all slots for the given clinic and day of week
    $slots = $wpdb->get_results($wpdb->prepare("SELECT * FROM $slots_table WHERE clinic_id = %d AND day_of_week = %s", $clinic_id, $day_of_week));
    // Get all booked appointments for the given clinic and date
    $appointments = $wpdb->get_results($wpdb->prepare("SELECT * FROM $appointments_table WHERE clinic_id = %d AND DATE(appointment_datetime) = %s", $clinic_id, $date));

    // Convert appointments to a more easily searchable format
    $booked_slots = [];
    foreach ($appointments as $appointment) {
        $booked_slots[] = date('H:i:s', strtotime($appointment->appointment_datetime));
    }

    // Filter out the booked slots from the available slots
    $available_slots = [];
    foreach ($slots as $slot) {
        $start_time = strtotime($slot->start_time);
        $end_time = strtotime($slot->end_time);
        $interval = strtotime('+' . $slot->slot_interval, 0);

        while ($start_time < $end_time) {
            $slot_time = date('H:i:s', $start_time);
            if (!in_array($slot_time, $booked_slots)) {
                $available_slots[] = [
                    'start_time' => $slot_time,
                    'end_time' => date('H:i:s', $start_time + $interval),
                    'interval' => $slot->slot_interval
                ];
            }
            $start_time += $interval;
        }
    }

    echo json_encode($available_slots);
    wp_die();
}
add_action('wp_ajax_get_available_slots', 'handle_get_available_slots');
add_action('wp_ajax_nopriv_get_available_slots', 'handle_get_available_slots');

add_action('wp_ajax_get_slots_for_console', 'handle_get_slots_for_console');
add_action('wp_ajax_nopriv_get_slots_for_console', 'handle_get_slots_for_console');

function handle_get_slots_for_console() {
    global $wpdb;
    $clinic_id = intval($_POST['clinic_id']);
    $day_of_week = sanitize_text_field($_POST['day_of_week']);
    
    $slots_table = $wpdb->prefix . 'clinics_available_slots';
    $slots = $wpdb->get_results($wpdb->prepare("SELECT * FROM $slots_table WHERE clinic_id = %d AND day_of_week = %s", $clinic_id, $day_of_week));

    // Send the result back as JSON
    wp_send_json($slots);
    wp_die(); // this is required to terminate immediately and return a proper response
}

// Function to get available slots for a range of dates
function handle_get_available_slots_for_range() {
    global $wpdb;
    $clinic_id = intval($_POST['clinic_id']);
    $start_date = sanitize_text_field($_POST['start_date']);
    $end_date = sanitize_text_field($_POST['end_date']);

    // Table names
    $slots_table = $wpdb->prefix . 'clinics_available_slots';
    $appointments_table = $wpdb->prefix . 'clinic_patient_appointments';

    $dates_with_slots = [];

    // Get all slots for the given clinic and date range
    $dates = new DatePeriod(
        new DateTime($start_date),
        new DateInterval('P1D'),
        new DateTime($end_date)
    );

    foreach ($dates as $date) {
        $day_of_week = $date->format('l');
        $current_date = $date->format('Y-m-d');

        // Get all slots for the given clinic and day of week
        $slots = $wpdb->get_results($wpdb->prepare("SELECT * FROM $slots_table WHERE clinic_id = %d AND day_of_week = %s", $clinic_id, $day_of_week));

        // Get all booked appointments for the given clinic and date
        $appointments = $wpdb->get_results($wpdb->prepare("SELECT * FROM $appointments_table WHERE clinic_id = %d AND DATE(appointment_datetime) = %s", $clinic_id, $current_date));

        // Convert appointments to a more easily searchable format
        $booked_slots = [];
        foreach ($appointments as $appointment) {
            $booked_slots[] = date('H:i:s', strtotime($appointment->appointment_datetime));
        }

        // Filter out the booked slots from the available slots
        $available_slots = [];
        foreach ($slots as $slot) {
            $start_time = strtotime($slot->start_time);
            $end_time = strtotime($slot->end_time);
            $interval = strtotime('+' . $slot->slot_interval, 0);

            while ($start_time < $end_time) {
                $slot_time = date('H:i:s', $start_time);
                if (!in_array($slot_time, $booked_slots)) {
                    $available_slots[] = [
                        'start_time' => $slot_time,
                        'end_time' => date('H:i:s', $start_time + $interval),
                        'interval' => $slot->slot_interval
                    ];
                }
                $start_time += $interval;
            }
        }

        if (!empty($available_slots)) {
            $dates_with_slots[] = $current_date;
        }
    }

    echo json_encode($dates_with_slots);
    wp_die();
}
add_action('wp_ajax_get_available_slots_for_range', 'handle_get_available_slots_for_range');
add_action('wp_ajax_nopriv_get_available_slots_for_range', 'handle_get_available_slots_for_range');

// Add the popup HTML to the footer
// Add the booking form HTML to the footer (renders inline into #book-appointment)
function add_appointment_popup() {
    if (is_singular('clinic')) {
        ?>
        <div id="appointment-overlay"></div>
        <template id="appointment-template">
            <div class="appointment-inline">
             <span class="close">×</span> 
             <h2 class=heading><?php esc_html_e('Book an Appointment', 'clinic-patients-appointments'); ?></h2>
            <form id="appointment-form">
                <input type="hidden" name="action" value="book_appointment">
                <input type="hidden" name="clinic_id" value="<?php echo esc_attr( get_the_ID() ); ?>">
                <label for="appointment-date"><?php esc_html_e('Select Date', 'clinic-patients-appointments'); ?>:</label>
                <input type="text" id="appointment-date" name="appointment_date" placeholder="<?php esc_html_e('Select a date', 'clinic-patients-appointments'); 
   ?>"required readonly>
                <label for="appointment-time"><?php esc_html_e('Select Time', 'clinic-patients-appointments'); ?>:</label>
                <div id="loading-message" style="display: none;"><?php esc_html_e('Loading available slots', 'clinic-patients-appointments'); ?>...</div>
                <div id="appointment-time-container"></div>
                <input type="hidden" id="appointment-time" name="appointment_time" required>
                <input type="hidden" id="appointment-interval" name="appointment_interval" required>
                <label for="doctor"><?php esc_html_e('Select Doctor', 'clinic-patients-appointments'); ?>:</label>
                <select id="doctor" name="doctor">
                    <option value=""><?php esc_html_e('No Preference', 'clinic-patients-appointments'); ?></option>
                    <?php
                    global $post;
                    $doctors = get_field('doctors', $post->ID);
                    if ($doctors) {
                        foreach ($doctors as $doctor) {
                            $name = isset($doctor['drname']) ? $doctor['drname'] : '';
                            echo '<option value="' . esc_attr($name) . '">' . esc_html($name) . '</option>';
                        }
                    }
                    ?>
                </select>
                <button type="submit"><?php esc_html_e('Book', 'clinic-patients-appointments'); ?></button>
            </form>
        </div>
        </template>

        <script>
        // Move the template content into the Elementor container so it's visible inline.
        (function() {
            var mount = document.getElementById('book-appointment'); // Elementor container
            var tpl   = document.getElementById('appointment-template');
            if (!mount || !tpl) return;

            mount.innerHTML = tpl.innerHTML; // inject the form
            tpl.parentNode.removeChild(tpl); // cleanup
        })();
        </script>
    <?php
    }
}
add_action('wp_footer', 'add_appointment_popup', 5);

// Handle AJAX request
function handle_book_appointment() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'clinic_patient_appointments';

    $clinic_id = intval($_POST['clinic_id']);
    $patient_id = get_current_user_id();
    $appointment_datetime = sanitize_text_field($_POST['appointment_time']);
    $doctor_name = sanitize_text_field($_POST['doctor']);
    $appointment_interval = sanitize_text_field($_POST['appointment_interval']);

    // Insert booking for the current language
    $wpdb->insert($table_name, array(
        'clinic_id' => $clinic_id,
        'patient_id' => $patient_id,
        'appointment_datetime' => $appointment_datetime,
        'doctor_name' => $doctor_name,
        'appointment_interval' => $appointment_interval,
        'status' => STATUS_PENDING
    ));

    // Get the ID of the associated clinic in the other language
    $current_language = pll_current_language();
    $other_language = ($current_language == 'en') ? 'ar' : 'en'; // Adjust based on your language codes
    $translated_clinic_id = pll_get_post($clinic_id, $other_language);

    if ($translated_clinic_id) {
        // Insert booking for the other language
        $wpdb->insert($table_name, array(
            'clinic_id' => $translated_clinic_id,
            'patient_id' => $patient_id,
            'appointment_datetime' => $appointment_datetime,
            'doctor_name' => $doctor_name,
            'appointment_interval' => $appointment_interval,
            'status' => STATUS_PENDING
        ));
    }

    // Send email notifications (optional, if needed for both languages)
    $appointment = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}clinic_patient_appointments WHERE id = %d", $wpdb->insert_id));
    send_booking_emails($appointment);

    echo 'success';
    wp_die();
}
add_action('wp_ajax_book_appointment', 'handle_book_appointment');
add_action('wp_ajax_nopriv_book_appointment', 'handle_book_appointment');


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

function display_clinic_appointments() {
    if (!current_user_can('clinic_owner')) {
        return 'You do not have sufficient permissions to access this page.';
    }

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

    global $wpdb;
    $table_name = $wpdb->prefix . 'clinic_patient_appointments';
    $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE clinic_id = %d ORDER BY appointment_datetime ASC", $clinic_id));

    if (empty($results)) {
        return '<p>No appointments found for your clinic.</p>';
    }

    ob_start();
    ?>
    <style>
        .appointments-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        .appointment-card {
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 8px;
            background-color: #fff;
            box-sizing: border-box;
            width: 100%;
            max-width: 100%;
        }
        .appointment-card.past {
            background-color: #f0f0f0 !important;
        }
        .appointment-card.future {
            background-color: #fff !important;
        }
        .card-header {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .card-body {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .filter-container {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .filter-container select,
        .filter-container input {
            padding: 5px;
            border-radius: 4px;
            border: 1px solid #ccc;
        }
        .filter-container button {
            padding: 5px 10px;
            border-radius: 4px;
            border: 1px solid #ccc;
            background-color: #0073aa;
            color: #fff;
        }
        .filter-container button:hover {
            background-color: #005f8d;
        }
        .appointment-detail {
            display: flex;
            justify-content: space-between;
            word-wrap: break-word;
        }
        .appointment-date {
            font-weight: bold;
            color: #333;
        }
        @media (min-width: 1024px) {
            .appointment-card {
                flex: 1 1 calc(50% - 20px);
            }
        }
    </style>
    <div class="filter-container">
        <select id="filter-status">
            <option value="">All Statuses</option>
            <option value="<?php echo STATUS_PENDING; ?>">Pending</option>
            <option value="<?php echo STATUS_REQUESTED; ?>">Requested for Cancellation</option>
            <option value="<?php echo STATUS_CANCELLED; ?>">Cancelled</option>
            <option value="<?php echo STATUS_APPROVED; ?>">Approved</option>
        </select>
        <select id="filter-time">
            <option value="">All Times</option>
            <option value="past">Past Appointments</option>
            <option value="future">Future Appointments</option>
        </select>
        <select id="filter-order">
            <option value="asc">Oldest First</option>
            <option value="desc">Latest First</option>
        </select>
        <button id="filter-button">Filter</button>
    </div>
    <div class="appointments-container" id="appointments-container">
    <?php
    $current_datetime = current_time('mysql');
    foreach ($results as $row) {
        $is_past = $row->appointment_datetime < $current_datetime;
        $card_class = $is_past ? 'past' : 'future';

        // Get patient full name and email
        $patient_id = $row->patient_id;
        $first_name = get_user_meta($patient_id, 'first_name', true);
        $last_name = get_user_meta($patient_id, 'last_name', true);
        $patient_name = $first_name . ' ' . $last_name;
        $patient_email = $wpdb->get_var($wpdb->prepare("SELECT user_email FROM {$wpdb->users} WHERE ID = %d", $patient_id));
        ?>
        <div class="appointment-card <?php echo $card_class; ?>" data-appointment-id="<?php echo esc_attr($row->id); ?>" data-status="<?php echo esc_attr($row->status); ?>" data-date="<?php echo esc_attr($row->appointment_datetime); ?>" data-time="<?php echo $card_class; ?>">
            <div class="card-header">Appointment</div>
            <div class="card-body">
                <div class="appointment-detail"><strong>Patient Name:</strong> <span><?php echo esc_html($patient_name); ?></span></div>
                <div class="appointment-detail"><strong>Patient Email:</strong> <span><?php echo esc_html($patient_email); ?></span></div>
                <div class="appointment-detail appointment-date"><strong>Appointment Date and Time:</strong> <span><?php echo esc_html(date("F j, Y, g:i a", strtotime($row->appointment_datetime))); ?></span></div>
                <div class="appointment-detail"><strong>Doctor Name:</strong> <span><?php echo esc_html($row->doctor_name ?: 'Not selected'); ?></span></div>
                <div class="appointment-detail"><strong>Interval:</strong> <span><?php echo esc_html($row->appointment_interval); ?></span></div>
                <div class="appointment-detail"><strong>Status:</strong> <span><?php echo esc_html($row->status); ?></span></div>
                <?php if ($row->status == STATUS_PENDING) { ?>
                    <button class="cancel-request-button" data-appointment-id="<?php echo esc_attr($row->id); ?>">Request to Cancel</button>
                    <button class="approve-button" data-appointment-id="<?php echo esc_attr($row->id); ?>">Approve</button>
                <?php } elseif ($row->status == STATUS_REQUESTED) { ?>
                    <button class="cancel-request-button" disabled>Request Sent</button>
                <?php } elseif ($row->status == STATUS_CANCELLED) { ?>
                    <button class="cancel-request-button" disabled>Cancelled</button>
                <?php } elseif ($row->status == STATUS_APPROVED) { ?>
                    <button class="approve-button" disabled>Approved</button>
                <?php } ?>
            </div>
        </div>
        <?php
    }
    echo '</div>';
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            function bindApproveButtons() {
                $('.approve-button').click(function() {
                    var appointmentId = $(this).data('appointment-id');
                    var button = $(this);
                    console.log('Approving appointment ID:', appointmentId);
                    $.ajax({
                        url: ajax_object.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'approve_appointment',
                            appointment_id: appointmentId,
                            _ajax_nonce: ajax_object.approve_appointment_nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                console.log('Appointment approved successfully.');
                                button.text('Approved');
                                button.prop('disabled', true);
                                button.siblings('.approve-button').remove();
                                button.siblings('.cancel-request-button').hide(); // Hide the cancel request button
                            } else {
                                console.error('Failed to approve appointment: ', response.data);
                                alert('Failed to approve appointment: ' + response.data);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Error approving appointment:', xhr.responseText);
                            alert('Failed to approve appointment.');
                        }
                    });
                });
            }

            $('#filter-button').click(function() {
                var statusFilter = $('#filter-status').val();
                var timeFilter = $('#filter-time').val();
                var orderFilter = $('#filter-order').val();
                var appointments = $('.appointment-card');

                appointments.each(function() {
                    var card = $(this);
                    var cardStatus = card.data('status');
                    var cardTime = card.data('time');
                    var show = true;
                    if (statusFilter && cardStatus !== statusFilter) {
                        show = false;
                    }
                    if (timeFilter && cardTime !== timeFilter) {
                        show = false;
                    }
                    card.toggle(show);
                });

                if (orderFilter) {
                    var sortedAppointments = appointments.sort(function(a, b) {
                        var dateA = new Date($(a).data('date'));
                        var dateB = new Date($(b).data('date'));
                        return orderFilter === 'asc' ? dateA - dateB : dateB - dateA;
                    });
                    $('#appointments-container').html(sortedAppointments);
                }

                bindApproveButtons(); // Re-bind the approve buttons after filtering and sorting
            });

            bindApproveButtons(); // Initial binding of approve buttons

            $('.cancel-request-button').click(function() {
                var appointmentId = $(this).data('appointment-id');
                var button = $(this);
                $.ajax({
                    url: ajax_object.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'cancel_request',
                        appointment_id: appointmentId
                    },
                    success: function(response) {
                        if (response === 'success') {
                            button.text('Request Sent');
                            button.prop('disabled', true);
                        } else {
                            console.error('Failed to request cancellation: ' + response);
                            alert('Failed to request cancellation.');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error requesting cancellation:', xhr.responseText);
                        alert('Failed to request cancellation.');
                    }
                });
            });
        });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('clinic_appointments', 'display_clinic_appointments');

function handle_approve_appointment() {
    global $wpdb;
    $appointment_id = intval($_POST['appointment_id']);
    
    error_log('Approving appointment ID: ' . $appointment_id);
    
    $updated = $wpdb->update(
        $wpdb->prefix . 'clinic_patient_appointments',
        array('status' => STATUS_APPROVED),
        array('id' => $appointment_id)
    );

    if ($updated !== false) {
        // Send email notifications
        $appointment = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}clinic_patient_appointments WHERE id = %d", $appointment_id));
        send_approval_emails($appointment);

        wp_send_json_success('Appointment approved successfully. ID: ' . $appointment_id);
    } else {
        error_log('Failed to approve appointment. ID: ' . $appointment_id);
        error_log('MySQL Error: ' . $wpdb->last_error);
        wp_send_json_error('Failed to approve appointment. MySQL Error: ' . $wpdb->last_error);
    }
    wp_die();
}
add_action('wp_ajax_approve_appointment', 'handle_approve_appointment');



function handle_cancel_request() {
    global $wpdb;
    $appointment_id = intval($_POST['appointment_id']);
    $wpdb->update(
        $wpdb->prefix . 'clinic_patient_appointments',
        array('status' => STATUS_REQUESTED),
        array('id' => $appointment_id),
        array('%s'),
        array('%d')
    );

    // Send email notifications
    $appointment = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}clinic_patient_appointments WHERE id = %d", $appointment_id));
    send_cancellation_request_emails($appointment);

    echo 'success';
    wp_die();
}
add_action('wp_ajax_cancel_request', 'handle_cancel_request');


function enqueue_appointment_scripts() {
    // Enqueue styles and scripts for the appointment booking page
    if (is_singular('clinic')) {
        wp_enqueue_style('appointment-style', plugin_dir_url(__FILE__) . 'css/appointment-style.css');
        wp_enqueue_script('moment-js', 'https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js', array(), null, true);
        wp_enqueue_script('appointment-script', plugin_dir_url(__FILE__) . 'js/appointment-script.js', array('jquery', 'moment-js'), null, true);
        wp_localize_script('appointment-script', 'ajax_object', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'approve_appointment_nonce' => wp_create_nonce('approve_appointment_nonce')
        ));
    }

    // Enqueue Flatpickr for the date picker
    wp_enqueue_style('flatpickr-css', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css');
    wp_enqueue_script('flatpickr-js', 'https://cdn.jsdelivr.net/npm/flatpickr', array('jquery'), null, true);
    wp_enqueue_script('moment-js', 'https://cdn.jsdelivr.net/npm/moment/min/moment.min.js', array('jquery'), null, true);
}
add_action('wp_enqueue_scripts', 'enqueue_appointment_scripts');

function enqueue_slots_form_scripts() {
    if (current_user_can('clinic_owner')) {
        wp_enqueue_style('slots-form-style', plugin_dir_url(__FILE__) . 'css/slots-form-style.css');
        wp_enqueue_style('clinic-appointment-style', plugin_dir_url(__FILE__) . 'css/clinic-appointment-style.css');
        wp_enqueue_script('slots-form-script', plugin_dir_url(__FILE__) . 'js/slots-form-script.js', array('jquery'), null, true);
        wp_localize_script('slots-form-script', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));
    }
}
add_action('wp_enqueue_scripts', 'enqueue_slots_form_scripts');

?>