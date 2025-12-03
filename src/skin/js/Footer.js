// i18next initialization is now handled by locale-loader.js

// Wait for both DOM ready AND locales loaded before initializing
function initializeApp() {
    $(".multiSearch").select2({
        language: window.CRM.shortLocale,
        minimumInputLength: 2,
        ajax: {
            url: function (params) {
                return window.CRM.root + "/api/search/" + params.term;
            },
            dataType: "json",
            delay: 250,
            data: "",
            processResults: function (data, params) {
                return { results: data };
            },
            cache: true,
            beforeSend: function (jqXHR, settings) {
                jqXHR.url = settings.url;
            },
            error: window.CRM.system.handlejQAJAXError,
        },
    });
    $(".multiSearch").on("select2:select", function (e) {
        window.location.href = e.params.data.uri;
    });

    window.onkeyup = function (e) {
        // listen for "?" keypress for quick access to the select2 search box.
        if (e.shiftKey && e.key === "?") {
            $(".multiSearch").select2("open");
        }
    };

    window.CRM.system.runTimerJobs();

    $(".date-picker").datepicker({
        format: window.CRM.datePickerformat,
        language: window.CRM.lang,
    });

    $(".maxUploadSize").text(window.CRM.maxUploadSize);

    // Note: Cart event handlers are now in cart.js module
    // The CartManager class handles all cart button clicks and notifications

    // Initialize just-validate for all forms with data-validate attribute
    initializeFormValidation();

    window.CRM.dashboard.refresh();
    DashboardRefreshTimer = setInterval(window.CRM.dashboard.refresh, window.CRM.iDashboardServiceIntervalTime * 1000);

    window.CRM.APIRequest({
        path: "system/notification",
    }).done(function (data) {
        data.notifications.forEach(function (item) {
            window.CRM.notify(item.title, {
                delay: item.delay,
                type: item.type,
            });
        });
    });

    // Initialize FAB buttons with localized labels
    initializeFAB();
}

// Helper function to run initialization code after locales are loaded
// Usage: window.CRM.onLocalesReady(function() { /* your init code */ });
window.CRM.onLocalesReady = function (callback) {
    if (window.CRM.localesLoaded) {
        callback();
    } else {
        window.addEventListener("CRM.localesReady", callback, { once: true });
    }
};

// Wait for both DOM and locales to be ready
$(document).ready(function () {
    window.CRM.onLocalesReady(initializeApp);
});

function showGlobalMessage(message, callOutClass) {
    window.CRM.notify(message, {
        delay: 5000,
        type: callOutClass,
    });
}

/**
 * Initialize form validation for all forms with data-validate attribute
 * Uses just-validate library with Bootstrap 4 styling
 */
function initializeFormValidation() {
    document.querySelectorAll("form[data-validate]").forEach(function (form) {
        const validator = new window.JustValidate(form, {
            errorFieldCssClass: "is-invalid",
            successFieldCssClass: "is-valid",
            errorLabelCssClass: "invalid-feedback",
            focusInvalidField: true,
            lockForm: true,
        });

        // Auto-add validation rules based on HTML5 attributes
        form.querySelectorAll("input, select, textarea").forEach(function (field) {
            const rules = [];

            if (field.hasAttribute("required")) {
                rules.push({
                    rule: "required",
                    errorMessage: i18next.t("This field is required"),
                });
            }

            if (field.type === "email") {
                rules.push({
                    rule: "email",
                    errorMessage: i18next.t("Please enter a valid email address"),
                });
            }

            if (field.type === "url") {
                rules.push({
                    rule: "customRegexp",
                    value: /^https?:\/\/.+/,
                    errorMessage: i18next.t("Please enter a valid URL"),
                });
            }

            if (field.hasAttribute("pattern")) {
                rules.push({
                    rule: "customRegexp",
                    value: new RegExp(field.getAttribute("pattern")),
                    errorMessage: field.getAttribute("title") || i18next.t("Invalid format"),
                });
            }

            if (field.hasAttribute("minlength")) {
                rules.push({
                    rule: "minLength",
                    value: parseInt(field.getAttribute("minlength")),
                    errorMessage: i18next.t("Minimum length is") + " " + field.getAttribute("minlength"),
                });
            }

            if (field.hasAttribute("maxlength")) {
                rules.push({
                    rule: "maxLength",
                    value: parseInt(field.getAttribute("maxlength")),
                    errorMessage: i18next.t("Maximum length is") + " " + field.getAttribute("maxlength"),
                });
            }

            if (rules.length > 0 && field.name) {
                rules.forEach(function (rule) {
                    validator.addField(field.id ? "#" + field.id : '[name="' + field.name + '"]', [rule]);
                });
            }
        });
    });
}

/**
 * Initialize Floating Action Buttons (FAB)
 * - Sets localized labels
 * - Handles scroll behavior to hide buttons on scroll
 * - Auto-hides after 5 seconds
 * - Hides global FAB if page-specific FAB exists
 */
function initializeFAB() {
    const fabContainer = $("#fab-container");
    const fabPersonLabel = $("#fab-person-label");
    const fabFamilyLabel = $("#fab-family-label");

    // Hide global FAB if a page-specific FAB exists
    if (
        $("#fab-person-view").length > 0 ||
        $("#fab-person-editor").length > 0 ||
        $("#fab-family-editor").length > 0 ||
        $("#fab-family-view").length > 0
    ) {
        fabContainer.hide();
        return;
    }

    // Set localized labels
    fabPersonLabel.text(i18next.t("Add New Person"));
    fabFamilyLabel.text(i18next.t("Add New Family"));

    // Auto-hide FAB after 5 seconds
    setTimeout(function () {
        fabContainer.addClass("hidden");
    }, 5000);

    // Hide FAB on any scroll to prevent blocking content
    $(window).on("scroll", function () {
        const currentScroll = $(this).scrollTop();

        // Hide FAB once user scrolls past 50px
        if (currentScroll > 50) {
            fabContainer.addClass("hidden");
        }
    });
}
