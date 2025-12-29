/**
 * Event utility functions for ChurchCRM event management
 */

window.CRM = window.CRM || {};
window.CRM.EventUtils = window.CRM.EventUtils || {};

/**
 * Populate a select dropdown with time options (24 hours, 15-minute increments)
 * @param {string} selectId - The ID of the select element to populate
 * @param {number} defaultHour - Default hour to select (0-23), default 9
 * @param {number} defaultMin - Default minute to select (0, 15, 30, 45), default 0
 */
window.CRM.EventUtils.populateTimeDropdown = function (selectId, defaultHour = 9, defaultMin = 0) {
    const select = document.getElementById(selectId);
    if (!select) return;

    // Clear existing options
    select.innerHTML = "";

    for (let hour = 0; hour < 24; hour++) {
        for (let min = 0; min < 60; min += 15) {
            const time24 = String(hour).padStart(2, "0") + ":" + String(min).padStart(2, "0") + ":00";
            const displayHour = hour % 12 || 12;
            const period = hour < 12 ? "AM" : "PM";
            const displayTime = displayHour + ":" + String(min).padStart(2, "0") + " " + period;

            const option = document.createElement("option");
            option.value = time24;
            option.textContent = displayTime;
            if (hour === defaultHour && min === defaultMin) {
                option.selected = true;
            }
            select.appendChild(option);
        }
    }
};

/**
 * Initialize time picker dropdowns (hour, minute, period) from a time string
 * @param {string} timeString - Time in format "h:mm A" (e.g., "9:30 AM")
 * @param {string} hourSelectId - ID of hour select element
 * @param {string} minuteSelectId - ID of minute select element
 * @param {string} periodSelectId - ID of period (AM/PM) select element
 */
window.CRM.EventUtils.initializeTimePicker = function (timeString, hourSelectId, minuteSelectId, periodSelectId) {
    const timePattern = /^(\d{1,2}):(\d{2}) (AM|PM)$/i;
    const match = timeString.match(timePattern);

    if (match) {
        const hourSelect = document.getElementById(hourSelectId);
        const minuteSelect = document.getElementById(minuteSelectId);
        const periodSelect = document.getElementById(periodSelectId);

        if (hourSelect) hourSelect.value = parseInt(match[1]);
        if (minuteSelect) minuteSelect.value = match[2];
        if (periodSelect) periodSelect.value = match[3].toUpperCase();
    }
};

/**
 * Convert separate hour/minute/period values to a time string
 * @param {number|string} hour - Hour value (1-12)
 * @param {number|string} minute - Minute value (00, 15, 30, 45)
 * @param {string} period - Period (AM/PM)
 * @returns {string} Time string in format "h:mm A"
 */
window.CRM.EventUtils.formatTime12Hour = function (hour, minute, period) {
    return hour + ":" + String(minute).padStart(2, "0") + " " + period;
};

/**
 * Setup auto-submit on time picker change
 * @param {string} formSelector - jQuery selector for the form
 * @param {string} hourSelectId - ID of hour select element
 * @param {string} minuteSelectId - ID of minute select element
 * @param {string} periodSelectId - ID of period (AM/PM) select element
 * @param {string} hiddenInputId - ID of hidden input to store combined time
 * @param {string} originalTime - Original time value to compare against
 */
window.CRM.EventUtils.setupTimePickerAutoSubmit = function (
    formSelector,
    hourSelectId,
    minuteSelectId,
    periodSelectId,
    hiddenInputId,
    originalTime,
) {
    const updateTimeAndSubmit = function () {
        const hour = $("#" + hourSelectId).val();
        const minute = $("#" + minuteSelectId).val();
        const period = $("#" + periodSelectId).val();
        const timeString = window.CRM.EventUtils.formatTime12Hour(hour, minute, period);

        $("#" + hiddenInputId).val(timeString);

        // Only submit if the time actually changed
        if (timeString !== originalTime) {
            $(formSelector).submit();
        }
    };

    $("#" + hourSelectId + ", #" + minuteSelectId + ", #" + periodSelectId).on("change", updateTimeAndSubmit);
};
