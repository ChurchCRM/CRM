/**
 * Form utility functions for ChurchCRM
 */

/**
 * Toggle input mask based on checkbox state for phone number fields
 *
 * Logic:
 * - "No format" CHECKED: No mask applied, user can enter any format
 * - "No format" UNCHECKED: Mask is applied using the format from data-phone-mask attribute
 *
 * @param {string} checkboxName - Name of the checkbox input
 * @param {string} inputName - Name of the phone input field
 */
export function togglePhoneMask(checkboxName, inputName) {
    var checkbox = $('input[name="' + checkboxName + '"]');
    var input = $('input[name="' + inputName + '"]');

    if (input.length === 0 || checkbox.length === 0) {
        return;
    }

    function updateMask() {
        var currentVal = input.val();

        // Always remove any existing mask first
        try {
            input.inputmask("remove");
        } catch (e) {
            // ignore if no mask was initialized
        }

        if (checkbox.is(":checked")) {
            // "No format" is checked - leave field without mask
            // Just restore the value
            input.val(currentVal);
        } else {
            // "No format" is unchecked - apply the mask
            // Get mask format from data-phone-mask attribute (NOT data-inputmask to avoid auto-init)
            var maskConfig = input.attr("data-phone-mask");
            if (maskConfig) {
                try {
                    // Parse the JSON config - it's already valid JSON like {"mask": "(999) 999-9999"}
                    var config = JSON.parse(maskConfig);
                    input.inputmask(config);
                } catch (e) {
                    console.error("Error parsing mask config:", e, maskConfig);
                }
            }
            // Restore value to trigger mask formatting
            input.val(currentVal);
        }
    }

    // Set initial state on page load
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

/**
 * Automatically initialize all phone mask toggles on the page
 * Looks for checkboxes with names starting with 'NoFormat_' or ending with 'noformat'
 * and their corresponding input fields
 */
export function initializeAllPhoneMaskToggles() {
    // Find all potential phone 'no format' checkboxes
    $('input[type="checkbox"][name^="NoFormat_"], input[type="checkbox"][name$="noformat"]').each(function () {
        var checkbox = $(this);
        var checkboxName = checkbox.attr("name");
        var inputName = null;

        // Determine input field name based on checkbox naming convention
        if (/^NoFormat_/i.test(checkboxName)) {
            // NoFormat_HomePhone -> HomePhone
            inputName = checkboxName.replace(/^NoFormat_/i, "");
        } else {
            // c1noformat -> c1
            inputName = checkboxName.replace(/noformat$/i, "");
        }

        // Clean up any extra underscores
        inputName = inputName.replace(/^_+|_+$/g, "");

        // Find the corresponding input field
        var input = $('input[name="' + inputName + '"]');

        if (input.length > 0) {
            togglePhoneMask(checkboxName, inputName);
        }
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
    window.CRM.formUtils.initializeAllPhoneMaskToggles = initializeAllPhoneMaskToggles;
}
