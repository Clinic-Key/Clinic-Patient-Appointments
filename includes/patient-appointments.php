<?php
// Ensure the file is included only once
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once plugin_dir_path(__FILE__) . 'constants.php';

function display_patient_appointments() {
    // Check if user is logged in
    if (!is_user_logged_in()) {
        return '<p>Please log in to view your appointments.</p>';
    }

    // Get current user info
    $current_user = wp_get_current_user();
    $user_id = $current_user->ID;

    global $wpdb;
    $table_name = $wpdb->prefix . 'clinic_patient_appointments';
    $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE patient_id = %d ORDER BY appointment_datetime ASC", $user_id));

    if (empty($results)) {
        return '<p>You have no appointments.</p>';
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
            background: #06C0D8;
            color: #fff;
            padding: 10px;
            text-align: center;
            border-radius: 8px 8px 0 0;
            margin: -20px -20px 20px;
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

        // Get clinic name and URL
        $clinic_id = $row->clinic_id;
        $clinic_name = get_the_title($clinic_id);
        $clinic_url = get_permalink($clinic_id);

        ?>
        <div class="appointment-card <?php echo $card_class; ?>" style="<?php echo $is_past ? 'background-color: #f0f0f0;' : 'background-color: #fff;'; ?>" data-appointment-id="<?php echo esc_attr($row->id); ?>" data-status="<?php echo esc_attr($row->status); ?>" data-date="<?php echo esc_attr($row->appointment_datetime); ?>" data-time="<?php echo $card_class; ?>">
            <div class="card-header">Appointment</div>
            <div class="card-body">
                <div class="appointment-detail appointment-date"><strong>Appointment Date and Time:</strong> <span><?php echo esc_html(date("F j, Y, g:i a", strtotime($row->appointment_datetime))); ?></span></div>
                <div class="appointment-detail"><strong>Doctor Name:</strong> <span><?php echo esc_html($row->doctor_name ?: 'Not selected'); ?></span></div>
                <div class="appointment-detail"><strong>Interval:</strong> <span><?php echo esc_html($row->appointment_interval); ?></span></div>
                <div class="appointment-detail"><strong>Clinic Name:</strong> <span><?php echo esc_html($clinic_name); ?></span></div>
                <div class="appointment-detail"><strong>Clinic URL:</strong> <a href="<?php echo esc_url($clinic_url); ?>" target="_blank">View Clinic</a></div>
                <div class="appointment-detail"><strong>Status:</strong> <span><?php echo esc_html($row->status == STATUS_PENDING || $row->status == STATUS_REQUESTED ? 'Waiting for clinic to approve' : ($row->status == STATUS_APPROVED ? 'Confirmed' : 'Cancelled by clinic')); ?></span></div>
            </div>
        </div>
        <?php
    }
    echo '</div>';
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
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
            });
        });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('patient_appointments', 'display_patient_appointments');
?>
