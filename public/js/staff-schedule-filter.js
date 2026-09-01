/**
 * Staff Schedule filtering for booking module.
 * Filters the correspondent dropdown based on staff schedules
 * and available time slots.
 */
function updateCorrespondentDropdown() {
    var startTime = $("form#add_booking_form #start_time").val();
    var endTime = $("form#add_booking_form #end_time").val();
    
    if (!startTime || !endTime) {
        return;
    }

    // Extract date and time from datetimepicker values
    var startMoment = moment(startTime, moment_date_format + " " + moment_time_format);
    var endMoment = moment(endTime, moment_date_format + " " + moment_time_format);
    
    if (!startMoment.isValid() || !endMoment.isValid()) {
        return;
    }
    
    var date = startMoment.format("YYYY-MM-DD");
    var start_time = startMoment.format("HH:mm");
    var end_time = endMoment.format("HH:mm");
    
    // Fetch available staff
    $.ajax({
        method: "GET",
        url: "/staff-schedules/available-staff",
        data: {
            date: date,
            start_time: start_time,
            end_time: end_time
        },
        dataType: "json",
        success: function(availableStaff) {
            var correspondentSelect = $("select#correspondent");
            var currentValue = correspondentSelect.val();
            
            // Clear and rebuild options
            correspondentSelect.empty();
            correspondentSelect.append('<option value="">Select correspondent</option>');
            
            $.each(availableStaff, function(index, staff) {
                var optionText = staff.name;
                if (staff.schedule) {
                    optionText += " (" + staff.schedule + ")";
                }
                correspondentSelect.append(
                    $("<option>", {
                        value: staff.id,
                        text: optionText
                    })
                );
            });
            
            // Restore previous selection if still available
            if (currentValue && correspondentSelect.find('option[value="' + currentValue + '"]').length) {
                correspondentSelect.val(currentValue);
            }
            
            correspondentSelect.trigger("change");
            
            // Show message if no staff available
            if (availableStaff.length === 0) {
                toastr.warning("No staff members are available for the selected time slot.");
            }
        }
    });
}

$(document).ready(function() {
    // Update correspondent when start/end time changes
    $(document).on("change", "form#add_booking_form #start_time", function() {
        setTimeout(updateCorrespondentDropdown, 100);
    });
    $(document).on("change", "form#add_booking_form #end_time", function() {
        setTimeout(updateCorrespondentDropdown, 100);
    });
    
    // Also update when modal opens
    $(document).on("shown.bs.modal", "#add_booking_modal", function() {
        setTimeout(updateCorrespondentDropdown, 500);
    });
});
