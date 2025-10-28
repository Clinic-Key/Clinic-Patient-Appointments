<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'constants.php';

// Add a menu item to the WordPress admin menu
function clinic_appointments_admin_menu() {
    add_menu_page(
        'Clinic Appointments',
        'Clinic Appointments',
        'manage_options',
        'clinic-appointments',
        'clinic_appointments_page_html',
        'dashicons-schedule',
        20
    );
}
add_action('admin_menu', 'clinic_appointments_admin_menu');

// Handle form submission to update the status
function update_appointment_status() {
    if (isset($_POST['appointment_id']) && isset($_POST['status'])) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'clinic_patient_appointments';
        $appointment_id = intval($_POST['appointment_id']);
        $new_status = sanitize_text_field($_POST['status']);
        
        $wpdb->update(
            $table_name,
            array('status' => $new_status),
            array('id' => $appointment_id)
        );

        // Send email notifications
        $appointment = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}clinic_patient_appointments WHERE id = %d", $appointment_id));
        $patient = get_userdata($appointment->patient_id);
        $clinic = get_post($appointment->clinic_id);
        $clinic_owner = get_userdata($clinic->post_author);
        $admin_email = get_option('admin_email');
        
        $subject = "Appointment Status Update";
        $message = "The status of the following appointment has been updated.\n\n";
        $message .= "Clinic: " . get_the_title($appointment->clinic_id) . "\n";
        $message .= "Patient: " . $patient->display_name . " (" . $patient->user_email . ")\n";
        $message .= "Appointment Date and Time: " . $appointment->appointment_datetime . "\n";
        $message .= "Doctor: " . $appointment->doctor_name . "\n";
        $message .= "Interval: " . $appointment->appointment_interval . "\n";
        $message .= "New Status: " . $new_status . "\n";

        // Send to patient
        wp_mail($patient->user_email, $subject, $message);

        // Send to clinic owner
        wp_mail($clinic_owner->user_email, $subject, $message);

        // Send to admin
        wp_mail($admin_email, $subject, $message);

        // Redirect to avoid form resubmission
        wp_redirect(admin_url('admin.php?page=clinic-appointments'));
        exit;
    }
}
add_action('admin_post_update_appointment_status', 'update_appointment_status');

// Function to fetch and display clinic analytics
function get_clinic_analytics($order_by = 'total_appointments', $order = 'DESC') {
    global $wpdb;

    $valid_order_by = array('total_appointments', 'approved_appointments', 'cancelled_appointments');
    if (!in_array($order_by, $valid_order_by)) {
        $order_by = 'total_appointments';
    }

    $valid_order = array('ASC', 'DESC');
    if (!in_array($order, $valid_order)) {
        $order = 'DESC';
    }

    $clinics = $wpdb->get_results($wpdb->prepare("
        SELECT
            c.ID as clinic_id,
            c.post_title as clinic_name,
            COUNT(a.id) as total_appointments,
            SUM(CASE WHEN a.status = %s THEN 1 ELSE 0 END) as approved_appointments,
            SUM(CASE WHEN a.status = %s THEN 1 ELSE 0 END) as cancelled_appointments
        FROM
            {$wpdb->prefix}clinic_patient_appointments a
        JOIN
            {$wpdb->prefix}posts c ON a.clinic_id = c.ID
        WHERE
            c.post_type = 'clinic'
        GROUP BY
            c.ID
        ORDER BY
            $order_by $order
    ", STATUS_APPROVED, STATUS_CANCELLED));

    foreach ($clinics as $clinic) {
        $clinic->clinic_url = get_permalink($clinic->clinic_id);
    }

    return $clinics;
}

// Display the contents of the table on the admin page
function clinic_appointments_page_html() {
    global $wpdb;

    $order_by = isset($_GET['order_by']) ? sanitize_text_field($_GET['order_by']) : 'total_appointments';
    $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'DESC';

    $table_name = $wpdb->prefix . 'clinic_patient_appointments';
    $results = $wpdb->get_results("SELECT * FROM $table_name");

    echo '<div class="wrap">';
    echo '<h1>Clinic Patient Appointments</h1>';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>ID</th><th>Clinic ID</th><th>Patient ID</th><th>Appointment Date and Time</th><th>Doctor Name</th><th>Interval</th><th>Clinic URL</th><th>Status</th><th>Actions</th></tr></thead>';
    echo '<tbody>';
    foreach ($results as $row) {
        $clinic_url = get_permalink($row->clinic_id);
        echo '<tr>';
        echo '<td>' . esc_html($row->id) . '</td>';
        echo '<td>' . esc_html($row->clinic_id) . '</td>';
        echo '<td>' . esc_html($row->patient_id) . '</td>';
        echo '<td>' . esc_html($row->appointment_datetime) . '</td>';
        echo '<td>' . esc_html($row->doctor_name) . '</td>';
        echo '<td>' . esc_html($row->appointment_interval) . '</td>';
        echo '<td><a href="' . esc_url($clinic_url) . '" target="_blank">View Clinic</a></td>';
        echo '<td>' . esc_html($row->status) . '</td>';
        echo '<td>';
        if ($row->status == STATUS_REQUESTED) {
            echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
            echo '<input type="hidden" name="action" value="update_appointment_status">';
            echo '<input type="hidden" name="appointment_id" value="' . esc_attr($row->id) . '">';
            echo '<input type="hidden" name="status" value="' . STATUS_CANCELLED . '">';
            echo '<input type="submit" value="Approve Cancellation" style="font-size:12px; padding: 0px; border: none; white-space: normal; word-wrap: break-word; cursor: pointer;">';
            echo '</form>';
        } elseif ($row->status == STATUS_CANCELLED) {
            echo 'Cancelled';
        }
        if ($row->status == STATUS_PENDING) {
            echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
            echo '<input type="hidden" name="action" value="update_appointment_status">';
            echo '<input type="hidden" name="appointment_id" value="' . esc_attr($row->id) . '">';
            echo '<input type="hidden" name="status" value="' . STATUS_APPROVED . '">';
            echo '<input type="submit" value="Approve Appointment" style="margin-top: 5px; font-size:12px; padding: 0px; border: none; white-space: normal; word-wrap: break-word; cursor: pointer;">';
            echo '</form>';
        } elseif ($row->status == STATUS_APPROVED) {
            echo 'Approved';
        }
        echo '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';

    // Display clinic analytics
    $clinics = get_clinic_analytics($order_by, $order);
    echo '<h2>Clinic Performance Analytics</h2>';
    echo '<form method="GET" action="">';
    echo '<input type="hidden" name="page" value="clinic-appointments" />';
    echo '<label for="order_by">Sort by:</label>';
    echo '<select name="order_by" id="order_by">';
    echo '<option value="total_appointments"' . selected($order_by, 'total_appointments', false) . '>Total Appointments</option>';
    echo '<option value="approved_appointments"' . selected($order_by, 'approved_appointments', false) . '>Approved Appointments</option>';
    echo '<option value="cancelled_appointments"' . selected($order_by, 'cancelled_appointments', false) . '>Cancelled Appointments</option>';
    echo '</select>';
    echo '<select name="order" id="order">';
    echo '<option value="ASC"' . selected($order, 'ASC', false) . '>Ascending</option>';
    echo '<option value="DESC"' . selected($order, 'DESC', false) . '>Descending</option>';
    echo '</select>';
    echo '<input type="submit" value="Sort" class="button button-primary" />';
    echo '</form>';

    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>Clinic ID</th><th>Clinic Name</th><th>Clinic URL</th><th>Total Appointments</th><th>Approved Appointments</th><th>Cancelled Appointments</th></tr></thead>';
    echo '<tbody>';
    foreach ($clinics as $clinic) {
        echo '<tr>';
        echo '<td>' . esc_html($clinic->clinic_id) . '</td>';
        echo '<td>' . esc_html($clinic->clinic_name) . '</td>';
        echo '<td><a href="' . esc_url($clinic->clinic_url) . '" target="_blank">View Clinic</a></td>';
        echo '<td>' . esc_html($clinic->total_appointments) . '</td>';
        echo '<td>' . esc_html($clinic->approved_appointments) . '</td>';
        echo '<td>' . esc_html($clinic->cancelled_appointments) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    echo '</div>';
}
?>
