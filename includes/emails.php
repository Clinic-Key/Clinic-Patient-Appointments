<?php
if (!defined('ABSPATH')) {
    exit;
}

function send_appointment_email($recipient, $subject, $message) {
    wp_mail($recipient, $subject, $message);
}

function get_email_message($type, $appointment, $patient, $clinic, $clinic_owner, $recipient_type) {
    $message = "";

    switch ($type) {
        case 'booking':
            if ($recipient_type == 'patient') {
                $message .= "Dear " . $patient->display_name . ",\n\n";
                $message .= "Thank you for booking an appointment at " . $clinic->post_title . ". Here are your appointment details:\n\n";
            } elseif ($recipient_type == 'clinic_owner') {
                $message .= "Dear " . $clinic_owner->display_name . ",\n\n";
                $message .= "A new appointment has been booked at your clinic " . $clinic->post_title . ". Here are the details:\n\n";
            } elseif ($recipient_type == 'admin') {
                $message .= "Dear Admin,\n\n";
                $message .= "A new appointment has been booked at " . $clinic->post_title . ". Here are the details:\n\n";
            }
            $message .= "Clinic: " . $clinic->post_title . "\n";
            $message .= "Patient: " . $patient->display_name . " (" . $patient->user_email . ")\n";
            $message .= "Appointment Date and Time: " . $appointment->appointment_datetime . "\n";
            $message .= "Doctor: " . $appointment->doctor_name . "\n";
            $message .= "Interval: " . $appointment->appointment_interval . "\n";
            $message .= "Status: " . STATUS_PENDING . "\n";
            break;

        case 'approval':
            if ($recipient_type == 'patient') {
                $message .= "Dear " . $patient->display_name . ",\n\n";
                $message .= "Your appointment at " . $clinic->post_title . " has been approved. Here are your appointment details:\n\n";
            } elseif ($recipient_type == 'clinic_owner') {
                $message .= "Dear " . $clinic_owner->display_name . ",\n\n";
                $message .= "An appointment at your clinic " . $clinic->post_title . " has been approved. Here are the details:\n\n";
            } elseif ($recipient_type == 'admin') {
                $message .= "Dear Admin,\n\n";
                $message .= "An appointment at " . $clinic->post_title . " has been approved. Here are the details:\n\n";
            }
            $message .= "Clinic: " . $clinic->post_title . "\n";
            $message .= "Patient: " . $patient->display_name . " (" . $patient->user_email . ")\n";
            $message .= "Appointment Date and Time: " . $appointment->appointment_datetime . "\n";
            $message .= "Doctor: " . $appointment->doctor_name . "\n";
            $message .= "Interval: " . $appointment->appointment_interval . "\n";
            $message .= "Status: " . STATUS_APPROVED . "\n";
            break;

        case 'cancellation':
            if ($recipient_type == 'clinic_owner') {
                $message .= "Dear " . $clinic_owner->display_name . ",\n\n";
                $message .= "A request to cancel an appointment at your clinic " . $clinic->post_title . " has been made. Here are the details:\n\n";
            } elseif ($recipient_type == 'admin') {
                $message .= "Dear Admin,\n\n";
                $message .= "A request to cancel an appointment at " . $clinic->post_title . " has been made. Here are the details:\n\n";
            }
            $message .= "Clinic: " . $clinic->post_title . "\n";
            $message .= "Patient: " . $patient->display_name . " (" . $patient->user_email . ")\n";
            $message .= "Appointment Date and Time: " . $appointment->appointment_datetime . "\n";
            $message .= "Doctor: " . $appointment->doctor_name . "\n";
            $message .= "Interval: " . $appointment->appointment_interval . "\n";
            $message .= "Status: " . STATUS_REQUESTED . "\n";
            break;

        default:
            $message = "";
    }
    
    $message .= "\nBest Regards,\n";
    $message .= "Clinic Management Team";

    return $message;
}

function send_booking_emails($appointment) {
    $patient = get_userdata($appointment->patient_id);
    $clinic = get_post($appointment->clinic_id);
    $clinic_owner = get_userdata($clinic->post_author);
    $admin_email = get_option('admin_email');

    $subject = "New Appointment Booking";
    $message_patient = get_email_message('booking', $appointment, $patient, $clinic, $clinic_owner, 'patient');
    $message_clinic_owner = get_email_message('booking', $appointment, $patient, $clinic, $clinic_owner, 'clinic_owner');
    $message_admin = get_email_message('booking', $appointment, $patient, $clinic, $clinic_owner, 'admin');

    send_appointment_email($patient->user_email, $subject, $message_patient);
    send_appointment_email($clinic_owner->user_email, $subject, $message_clinic_owner);
    send_appointment_email($admin_email, $subject, $message_admin);
}

function send_approval_emails($appointment) {
    $patient = get_userdata($appointment->patient_id);
    $clinic = get_post($appointment->clinic_id);
    $clinic_owner = get_userdata($clinic->post_author);
    $admin_email = get_option('admin_email');

    $subject = "Appointment Approved";
    $message_patient = get_email_message('approval', $appointment, $patient, $clinic, $clinic_owner, 'patient');
    $message_clinic_owner = get_email_message('approval', $appointment, $patient, $clinic, $clinic_owner, 'clinic_owner');
    $message_admin = get_email_message('approval', $appointment, $patient, $clinic, $clinic_owner, 'admin');

    send_appointment_email($patient->user_email, $subject, $message_patient);
    send_appointment_email($clinic_owner->user_email, $subject, $message_clinic_owner);
    send_appointment_email($admin_email, $subject, $message_admin);
}

function send_cancellation_request_emails($appointment) {
    $patient = get_userdata($appointment->patient_id);
    $clinic = get_post($appointment->clinic_id);
    $clinic_owner = get_userdata($clinic->post_author);
    $admin_email = get_option('admin_email');

    $subject = "Appointment Cancellation Requested";
    $message_clinic_owner = get_email_message('cancellation', $appointment, $patient, $clinic, $clinic_owner, 'clinic_owner');
    $message_admin = get_email_message('cancellation', $appointment, $patient, $clinic, $clinic_owner, 'admin');

    send_appointment_email($clinic_owner->user_email, $subject, $message_clinic_owner);
    send_appointment_email($admin_email, $subject, $message_admin);
}
?>
