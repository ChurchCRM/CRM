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
            // Preserve the current value to avoid inputmask clearing it on remove
            var currentVal = input.val();
            try {
                input.inputmask("remove");
            } catch (e) {
                // ignore if no mask initialized
            }
            input.val(currentVal);
        } else {
            // Re-apply input mask only if the current value is compatible with the configured mask
            var rawVal = input.val();
            var maskAttr = input.attr("data-inputmask") || input.data("inputmask");
            var maskString = null;
            if (maskAttr) {
                // maskAttr is usually a JSON-ish string like '"mask": "(999) 999-9999"'
                var m = maskAttr.match(/mask\"?\s*:\s*\"([^\"]+)\"/);
                if (m && m[1]) {
                    maskString = m[1];
                } else if (typeof maskAttr === "string" && maskAttr.indexOf("mask") === -1) {
                    // Some inputs may have plain mask string
                    maskString = maskAttr;
                }
            }

            var shouldApplyMask = true;
            if (maskString) {
                var expectedDigitsMatch = maskString.match(/9|0/g);
                var expectedDigits = expectedDigitsMatch ? expectedDigitsMatch.length : 0;
                var digitsCount = (rawVal || "").replace(/\D/g, "").length;
                var hasPlusOrLetters = /\+|[A-Za-z]/.test(rawVal || "");
                if (expectedDigits > 0 && (digitsCount !== expectedDigits || hasPlusOrLetters)) {
                    // Value is not compatible with mask -> do not apply mask to avoid trimming
                    shouldApplyMask = false;
                }
            }

            if (shouldApplyMask) {
                try {
                    input.inputmask();
                } catch (e) {
                    // ignore if inputmask not available
                }
                // Restore the raw value so the mask can format it
                input.val(rawVal);
            } else {
                // Ensure any existing mask is removed to preserve full raw value
                try {
                    input.inputmask("remove");
                } catch (e) {
                    // ignore
                }
                input.val(rawVal);
            }
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
