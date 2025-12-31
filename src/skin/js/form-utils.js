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
        var currentVal = input.val();

        if (checkbox.is(":checked")) {
            // Remove input mask to allow free-form entry
            try {
                input.inputmask("remove");
            } catch (e) {
                // ignore if no mask initialized
            }
            input.val(currentVal);
        } else {
            // Reapply the mask by reinitializing from data-inputmask attribute
            try {
                // Remove any existing mask first
                input.inputmask("remove");
            } catch (e) {
                // ignore
            }

            // Ensure data-mask attribute is present so inputmask() will initialize
            if (!input.is("[data-mask]")) {
                input.attr("data-mask", "");
            }

            // Reinitialize from data-inputmask attribute
            try {
                input.inputmask();
            } catch (e) {
                console.error("Error reapplying mask:", e);
            }

            // Restore value to trigger mask formatting
            input.val(currentVal);
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

/**
 * Automatically initialize all phone mask toggles on the page
 * Looks for checkboxes with names ending in 'noformat' and their corresponding input fields
 */
export function initializeAllPhoneMaskToggles() {
    // Find all potential phone 'no format' checkboxes.
    // Support both old suffix pattern (e.g. myfieldnoformat) and prefix pattern (e.g. NoFormat_MyField)
    $('input[type="checkbox"][name^="NoFormat_"], input[type="checkbox"][name$="noformat"]').each(function () {
        var checkbox = $(this);
        var checkboxName = checkbox.attr("name");

        var inputName = null;

        // If checkbox name starts with NoFormat_ (common server-side naming), strip that prefix
        if (/^NoFormat_/i.test(checkboxName)) {
            inputName = checkboxName.replace(/^NoFormat_/i, "");
        } else {
            // Fallback: if the name ends with 'noformat' (case-insensitive), strip that suffix
            inputName = checkboxName.replace(/noformat$/i, "");
        }

        // Trim any leading/trailing underscores that may remain
        inputName = inputName.replace(/^_+|_+$/g, "");

        // Check if the corresponding input field exists (try both name and lower-cased variants)
        var input = $('input[name="' + inputName + '"]');
        if (input.length === 0) {
            // Try common casing variant (lower-first) e.g., familyHomePhone vs FamilyHomePhone
            var altName = inputName.charAt(0).toLowerCase() + inputName.slice(1);
            input = $('input[name="' + altName + '"]');
        }

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
