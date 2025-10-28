jQuery(document).ready(function ($) {
  let isSubmitting = false; // Flag to track form submission status
  var availableDates = [];
  var flatpickrInstance; // To store the flatpickr instance

  function updateAvailableTimes() {
    var selectedDate = $("#appointment-date").val();
    var clinicId = $('input[name="clinic_id"]').val();

    if (selectedDate) {
      $("#loading-message").show();
      $.post(
        ajax_object.ajax_url,
        {
          action: "get_available_slots",
          clinic_id: clinicId,
          date: selectedDate,
        },
        function (response) {
          $("#loading-message").hide();
          var slots = JSON.parse(response);
          console.log(slots); // Log the slots data

          var $timeContainer = $("#appointment-time-container");
          $timeContainer.empty();

          if (slots.length > 0) {
            slots.forEach(function (slot) {
              var startTime = moment(slot.start_time, "HH:mm:ss");
              var endTime = moment(slot.end_time, "HH:mm:ss");
              var interval = parseSlotInterval(slot.interval);

              while (startTime < endTime) {
                var endTimeChip = moment(startTime).add(interval, "minutes");
                if (endTimeChip > endTime) break;

                var timeChip = $('<div class="time-chip"></div>')
                  .text(
                    startTime.format("h:mm A") +
                      " - " +
                      endTimeChip.format("h:mm A")
                  )
                  .attr("data-time", startTime.format("HH:mm:ss"))
                  .attr("data-interval", slot.interval);
                $timeContainer.append(timeChip);

                startTime.add(interval, "minutes");
              }
            });
          } else {
            $timeContainer.append("<div>No available times</div>");
          }
        }
      );
    }
  }

  function parseSlotInterval(interval) {
    var parts = interval.split(" ");
    var minutes = 0;
    for (var i = 0; i < parts.length; i += 2) {
      var value = parseInt(parts[i]);
      var unit = parts[i + 1];
      if (unit.includes("hour")) {
        minutes += value * 60;
      } else if (unit.includes("minute")) {
        minutes += value;
      }
    }
    return minutes;
  }

  // Fetch available slots for a range of dates on page load
  function fetchAvailableSlotsForRange() {
    var clinicId = $('input[name="clinic_id"]').val();
    console.log("clinicID::", clinicId);
    var today = new Date();
    var startDate = today.toISOString().split("T")[0];
    var endDate = new Date(today.getFullYear(), today.getMonth() + 2, 0)
      .toISOString()
      .split("T")[0]; // End of the next month
    console.log("end date::", endDate);
    $.post(
      ajax_object.ajax_url,
      {
        action: "get_available_slots_for_range",
        clinic_id: clinicId,
        start_date: startDate,
        end_date: endDate,
      },
      function (response) {
        availableDates = JSON.parse(response).map(
          (dateStr) => new Date(dateStr)
        );
        console.log("Days with available slots:", availableDates);
        initializeFlatpickr();
      }
    );
  }

  function initializeFlatpickr() {
    if (flatpickrInstance) {
      flatpickrInstance.destroy();
    }
    flatpickrInstance = $("#appointment-date").flatpickr({
      minDate: "today",
      enable: availableDates,
      dateFormat: "Y-m-d",
      placeholder: "Select a date",
      onChange: function (selectedDates, dateStr, instance) {
        updateAvailableTimes();
      },
    });
  }

  fetchAvailableSlotsForRange();

  $(document).on("click", ".time-chip", function () {
    $(".time-chip").removeClass("selected");
    $(this).addClass("selected");
    var selectedDate = $("#appointment-date").val();
    var selectedTime = $(this).attr("data-time");
    var selectedInterval = $(this).attr("data-interval");
    $("#appointment-time").val(selectedDate + " " + selectedTime);
    $("#appointment-interval").val(selectedInterval);
  });

  $("#appointment-form").on("submit", function (e) {
    e.preventDefault();

    if (isSubmitting) {
      console.log("Form is already being submitted, skipping...");
      return; // Prevent multiple submissions
    }

    var appointmentTime = $("#appointment-time").val();
    if (!appointmentTime) {
      alert("Please select a time slot before booking.");
      return; // Prevent form submission if time slot is not selected
    }

    console.log("Form submission prevented, processing via AJAX");

    isSubmitting = true; // Set flag to true to indicate submission is in progress

    var $submitButton = $(this).find('button[type="submit"]');
    $submitButton.prop("disabled", true); // Disable the submit button
    console.log("Submit button disabled");

    var formData = $(this).serialize();
    console.log("Form data:", formData);

    $.post(ajax_object.ajax_url, formData, function (response) {
      console.log("Server response:", response);

      if (response === "success") {
        alert("Appointment booked successfully!");
        $("#appointment-form")[0].reset();
        $("#appointment-time-container").empty();
        $("#appointment-popup").fadeOut(function () {
          $(this).css("opacity", "0").css("transform", "translate(-50%, -40%)");
        });
        $("#appointment-overlay").fadeOut();
      } else {
        alert("Failed to book appointment. Please try again.");
      }

      isSubmitting = false; // Reset flag
      $submitButton.prop("disabled", false); // Enable the submit button
      console.log("Submit button enabled");
    });
  });

  // Handle popup open and close
  $(document).on("click", "#appointment-overlay, .close", function () {
    $("#appointment-popup").fadeOut(function () {
      $(this).css("opacity", "0").css("transform", "translate(-50%, -40%)");
    });
    $("#appointment-overlay").fadeOut();
    if (flatpickrInstance) {
      flatpickrInstance.close(); // Close the Flatpickr calendar if open
    }
  });

  $(document).on("click", "#open-appointment-popup", function () {
    $("#appointment-overlay").fadeIn();
    $("#appointment-popup").fadeIn(function () {
      $(this).css("opacity", "1").css("transform", "translate(-50%, -50%)");
      if (flatpickrInstance) {
        flatpickrInstance.open(); // Open the Flatpickr calendar if it exists
      }
    });
  });
});

// Popup
jQuery(document).ready(function ($) {
  $("#book-appointment-button").on("click", function () {
    // pick the target; change this if you need a different one
    const $popup = $(".appointment-inline").first();
    if (!$popup.length) return;

    const tempId = "appointment-popup";

    // ensure only one element has this ID
    $('[id="' + tempId + '"]').removeAttr("id");
    $popup.attr("id", tempId);

    // show overlay & popup
    $("#appointment-overlay").stop(true, true).fadeIn(150);
    $popup
      .css({ opacity: 1, transform: "translate(-50%, -50%)" })
      .stop(true, true)
      .fadeIn(150)
      .find("> span.close") // the span under .appointment-inline
      .addClass("show");

    $popup
      .find("> h2.heading") // the span under .appointment-inline
      .addClass("show");

    console.log("Popup opened");
  });

  // Close popup
  $(document).on(
    "click",
    "#appointment-popup .close, #appointment-overlay",
    function () {
      $("#appointment-popup").fadeOut(function () {
        // Reset styles after closing
        $(this)
          .css({
            opacity: "0",
            transform: "translate(-50%, -40%)",
          })
          .removeAttr("id") // Remove dynamic ID
          .find("> span.close")
          .removeClass("show"); // Remove the 'show' class
      });

      $("#appointment-overlay").fadeOut();
      console.log("Popup closed");
    }
  );

  $("#appointment-form").on("submit", function (e) {
    e.preventDefault();

    if (isSubmitting) {
      console.log("Form is already being submitted, skipping...");
      return; // Prevent multiple submissions
    }

    var appointmentTime = $("#appointment-time").val();
    if (!appointmentTime) {
      alert("Please select a time slot before booking.");
      return; // Prevent form submission if time slot is not selected
    }

    console.log("Form submission prevented, processing via AJAX");

    isSubmitting = true; // Set flag to true to indicate submission is in progress

    var $submitButton = $(this).find('button[type="submit"]');
    $submitButton.prop("disabled", true); // Disable the submit button
    console.log("Submit button disabled");

    var formData = $(this).serialize();
    console.log("Form data:", formData);

    $.post(ajax_object.ajax_url, formData, function (response) {
      console.log("Server response:", response);
      alert("Appointment booked successfully!");
      $("#appointment-popup").fadeOut(function () {
        $(this).css("opacity", "0").css("transform", "translate(-50%, -40%)");
      });
      $("#appointment-overlay").fadeOut();
      isSubmitting = false; // Reset flag
      $submitButton.prop("disabled", false); // Enable the submit button
      console.log("Submit button enabled");
    });
  });
});

// Limits the start time to be less than end time and vice versa
jQuery(document).ready(function ($) {
  function roundTimeToInterval(time, interval) {
    var [hours, minutes] = time.split(":").map(Number);
    var totalMinutes = hours * 60 + minutes;
    var intervalMinutes = {
      "15 minutes": 15,
      "30 minutes": 30,
      "45 minutes": 45,
      "1 hour": 60,
      "1 hour 15 minutes": 75,
      "1 hour 30 minutes": 90,
    }[interval];

    var roundedMinutes =
      Math.round(totalMinutes / intervalMinutes) * intervalMinutes;
    var roundedHours = Math.floor(roundedMinutes / 60);
    var roundedRemainderMinutes = roundedMinutes % 60;

    return [
      String(roundedHours).padStart(2, "0"),
      String(roundedRemainderMinutes).padStart(2, "0"),
    ].join(":");
  }

  $("#appointment-interval").change(function () {
    var interval = $(this).val();
    $('input[type="time"]').each(function () {
      var time = $(this).val();
      if (time) {
        $(this).val(roundTimeToInterval(time, interval));
      }
    });
  });

  $('input[type="time"]').change(function () {
    var interval = $("#appointment-interval").val();
    var time = $(this).val();
    $(this).val(roundTimeToInterval(time, interval));
  });

  $("#slots-form").on("submit", function (e) {
    e.preventDefault();

    var isValid = true;
    var message = "";

    $(".day-row").each(function () {
      var day = $(this).data("day");
      var startTime = $(this)
        .find('input[name="' + day + '_start"]')
        .val();
      var endTime = $(this)
        .find('input[name="' + day + '_end"]')
        .val();

      if (startTime && endTime && startTime >= endTime) {
        isValid = false;
        message +=
          "Invalid time slot for " +
          day +
          ". Start time must be less than end time.\n";
      }
    });

    if (!isValid) {
      alert(message);
      return;
    }

    var formData = $(this).serialize();
    $.post(ajax_object.ajax_url, formData, function (response) {
      $("#slots-message").html(response);
    });
  });

  $("#appointment-date").change(function () {
    updateAvailableTimes();
  });

  function updateAvailableTimes() {
    var selectedDate = $("#appointment-date").val();
    var clinicId = $('input[name="clinic_id"]').val();

    if (selectedDate) {
      $("#loading-message").show();
      $.post(
        ajax_object.ajax_url,
        {
          action: "get_available_slots",
          clinic_id: clinicId,
          date: selectedDate,
        },
        function (response) {
          $("#loading-message").hide();
          var slots = JSON.parse(response);
          console.log(slots); // Log the slots data
          var $timeContainer = $("#appointment-time-container");
          $timeContainer.empty();

          if (slots.length > 0) {
            slots.forEach(function (slot) {
              var startTime = moment(slot.start_time, "HH:mm:ss");
              var endTime = moment(slot.end_time, "HH:mm:ss");
              var interval = parseSlotInterval(slot.interval);

              while (startTime < endTime) {
                var endTimeChip = moment(startTime).add(interval, "minutes");
                if (endTimeChip > endTime) break;

                var timeChip = $('<div class="time-chip"></div>')
                  .text(
                    startTime.format("h:mm A") +
                      " - " +
                      endTimeChip.format("h:mm A")
                  )
                  .attr("data-time", startTime.format("HH:mm:ss"))
                  .attr("data-interval", slot.interval);
                $timeContainer.append(timeChip);

                startTime.add(interval, "minutes");
              }
            });
          } else {
            $timeContainer.append("<div>No available times</div>");
          }
        }
      );
    }
  }

  function parseSlotInterval(interval) {
    var parts = interval.split(" ");
    var minutes = 0;
    for (var i = 0; i < parts.length; i += 2) {
      var value = parseInt(parts[i]);
      var unit = parts[i + 1];
      if (unit.includes("hour")) {
        minutes += value * 60;
      } else if (unit.includes("minute")) {
        minutes += value;
      }
    }
    return minutes;
  }

  $(document).on("click", ".time-chip", function () {
    $(".time-chip").removeClass("selected");
    $(this).addClass("selected");
    var selectedDate = $("#appointment-date").val();
    var selectedTime = $(this).attr("data-time");
    var selectedInterval = $(this).attr("data-interval");
    $("#appointment-time").val(selectedDate + " " + selectedTime);
    $("#appointment-interval").val(selectedInterval);
  });

  $("#appointment-form").on("submit", function (e) {
    e.preventDefault();
    var formData = $(this).serialize();
    $.post(ajax_object.ajax_url, formData, function (response) {
      if (response === "success") {
        alert("Appointment booked successfully!");
        $("#appointment-form")[0].reset();
        $("#appointment-time-container").empty();
      } else {
        alert("Failed to book appointment. Please try again.");
      }
    });
  });
});
