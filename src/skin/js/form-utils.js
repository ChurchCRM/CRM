/**
 * Form utility functions for ChurchCRM
 */

/**
 * Toggle input mask based on checkbox state for phone number fields
 * @param {string} checkboxName - Name of the checkbox input
 * @param {string} inputName - Name of the phone input field
 */
export function togglePhoneMask(checkboxName, inputName) {
    var checkbox = $('input[name="' + checkboxName + '"]');
    var input = $('input[name="' + inputName + '"]');

    function updateMask() {
        if (checkbox.is(":checked")) {
            // Remove input mask to allow free-form entry
            input.inputmask("remove");
        } else {
            // Re-apply input mask
            input.inputmask();
        }
    }

    // Set initial state
    updateMask();

    // Listen for checkbox changes
    checkbox.change(updateMask);
}

/**
 * Initialize phone mask toggles for multiple phone fields
 * @param {Array} phoneFields - Array of objects with checkboxName and inputName
 */
export function initializePhoneMaskToggles(phoneFields) {
    phoneFields.forEach(function (field) {
        togglePhoneMask(field.checkboxName, field.inputName);
    });
}

// Attach to window.CRM for legacy script access
if (typeof window !== "undefined") {
    if (!window.CRM) {
        window.CRM = {};
    }
    if (!window.CRM.formUtils) {
        window.CRM.formUtils = {};
    }
    window.CRM.formUtils.togglePhoneMask = togglePhoneMask;
    window.CRM.formUtils.initializePhoneMaskToggles = initializePhoneMaskToggles;
}
