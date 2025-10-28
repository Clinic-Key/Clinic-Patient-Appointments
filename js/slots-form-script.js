jQuery(document).ready(function($) {
    function roundTimeToInterval(time, interval) {
        var [hours, minutes] = time.split(':').map(Number);
        var totalMinutes = hours * 60 + minutes;
        var intervalMinutes = {
            '15 minutes': 15,
            '30 minutes': 30,
            '45 minutes': 45,
            '1 hour': 60,
            '1 hour 15 minutes': 75,
            '1 hour 30 minutes': 90
        }[interval];

        var roundedMinutes = Math.round(totalMinutes / intervalMinutes) * intervalMinutes;
        var roundedHours = Math.floor(roundedMinutes / 60);
        var roundedRemainderMinutes = roundedMinutes % 60;

        return [
            String(roundedHours).padStart(2, '0'),
            String(roundedRemainderMinutes).padStart(2, '0')
        ].join(':');
    }

    $('#appointment-interval').change(function() {
        var interval = $(this).val();
        $('input[type="time"]').each(function() {
            var time = $(this).val();
            if (time) {
                $(this).val(roundTimeToInterval(time, interval));
            }
        });
    });

    $('input[type="time"]').change(function() {
        var interval = $('#appointment-interval').val();
        var time = $(this).val();
        $(this).val(roundTimeToInterval(time, interval));
    });

    $('#slots-form').on('submit', function(e) {
        e.preventDefault();

        var isValid = true;
        var message = '';

        $('.day-row').each(function() {
            var day = $(this).data('day');
            var startTime = $(this).find('input[name="' + day + '_start"]').val();
            var endTime = $(this).find('input[name="' + day + '_end"]').val();

            if (startTime && endTime && startTime >= endTime) {
                isValid = false;
                message += 'Invalid time slot for ' + day + '. Start time must be less than end time.\n';
            }
        });

        if (!isValid) {
            alert(message);
            return;
        }

        var formData = $(this).serialize();
        $.post(ajax_object.ajax_url, formData, function(response) {
            $('#slots-message').html(response);
        });
    });
});

// handle the cancellation via AJAX:
jQuery(document).ready(function($) {
    $(document).on('click', '.cancel-request-button', function() {
        var $button = $(this);
        var appointmentId = $button.closest('.appointment-card').data('appointment-id');

        if (confirm('Are you sure you want to request cancellation for this appointment?')) {
            $.post(ajax_object.ajax_url, {
                action: 'cancel_request',
                appointment_id: appointmentId
            }, function(response) {
                if (response === 'success') {
                    $button.text('Request Sent').prop('disabled', true);
                } else {
                    alert('Failed to request cancellation. Please try again.');
                }
            });
        }
    });
});
