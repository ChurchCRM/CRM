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
                // Flatten the grouped results for Select2
                let flatResults = [];
                let totalCount = 0;
                let breakdown = [];

                data.forEach(function (group) {
                    // Count results in this group
                    let resultCount = group.children.length;
                    totalCount += resultCount;

                    // Extract category name (remove any parentheses/counts if present)
                    let categoryName = group.text.split("(")[0].trim();
                    breakdown.push(resultCount + " " + categoryName);

                    // Add a disabled group header
                    flatResults.push({
                        id: "group-" + group.text,
                        text: categoryName,
                        disabled: true,
                        isGroupHeader: true,
                    });
                    // Add items from this group
                    group.children.forEach(function (item) {
                        flatResults.push({
                            id: item.id,
                            text: item.text,
                            uri: item.uri,
                            groupNoun: categoryName,
                        });
                    });
                });

                // Add summary header at the beginning
                if (totalCount > 0) {
                    flatResults.unshift({
                        id: "summary",
                        text: totalCount + " results",
                        disabled: true,
                        isGroupHeader: true,
                        isSummary: true,
                        breakdown: breakdown.join(", "),
                    });
                }

                return { results: flatResults };
            },
            cache: true,
            beforeSend: function (jqXHR, settings) {
                jqXHR.url = settings.url;
            },
            error: window.CRM.system.handlejQAJAXError,
        },
        templateResult: function (data) {
            if (!data.id) {
                return data.text;
            }
            if (data.isSummary) {
                return $(
                    '<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 12px 8px; font-weight: bold; color: white; border-bottom: 3px solid #667eea; margin-bottom: 8px;"><div style="font-size: 14px;">' +
                        data.text +
                        '</div><div style="font-size: 11px; margin-top: 4px; opacity: 0.9;">' +
                        data.breakdown +
                        "</div></div>",
                );
            }
            if (data.isGroupHeader) {
                // Get color and icon based on group type
                let bgColor = "#f8f9fa";
                let icon = "fa-folder";
                let textColor = "#333";

                if (data.text === "Persons") {
                    bgColor = "#e3f2fd";
                    icon = "fa-user";
                    textColor = "#1976d2";
                } else if (data.text === "Families") {
                    bgColor = "#f3e5f5";
                    icon = "fa-people-roof";
                    textColor = "#7b1fa2";
                } else if (data.text === "Groups") {
                    bgColor = "#e8f5e9";
                    icon = "fa-users";
                    textColor = "#388e3c";
                } else if (data.text === "Addresses") {
                    bgColor = "#fff3e0";
                    icon = "fa-map-location-dot";
                    textColor = "#f57c00";
                } else if (data.text.includes("Finance")) {
                    bgColor = "#fce4ec";
                    icon = "fa-money-bill-wave";
                    textColor = "#c2185b";
                } else if (data.text === "Calendar Events") {
                    bgColor = "#ede7f6";
                    icon = "fa-calendar";
                    textColor = "#512da8";
                }

                return $(
                    '<div style="background-color: ' +
                        bgColor +
                        "; padding: 10px 8px; font-weight: bold; color: " +
                        textColor +
                        '; border-bottom: 2px solid #e0e0e0; margin-top: 5px;"><i class="fa-solid ' +
                        icon +
                        '" style="margin-right: 8px; width: 16px;"></i>' +
                        data.text +
                        "</div>",
                );
            }

            // Get icon based on group type
            let icon = "fa-user";
            if (data.groupNoun === "Persons") {
                icon = "fa-user";
            } else if (data.groupNoun === "Families") {
                icon = "fa-people-roof";
            } else if (data.groupNoun === "Groups") {
                icon = "fa-users";
            } else if (data.groupNoun === "Addresses") {
                icon = "fa-map-location-dot";
            } else if (data.groupNoun === "Finance Deposits" || data.groupNoun === "Finance Payments") {
                icon = "fa-money-bill-wave";
            } else if (data.groupNoun === "Calendar Events") {
                icon = "fa-calendar";
            }

            return $(
                '<span><i class="fa-solid ' +
                    icon +
                    '" style="margin-right: 8px; width: 16px; color: #666;"></i>' +
                    data.text +
                    "</span>",
            );
        },
        templateSelection: function (data) {
            if (data.isGroupHeader) {
                return data.text;
            }
            return data.text;
        },
    });
    $(".multiSearch").on("select2:select", function (e) {
        if (!e.params.data.isGroupHeader) {
            window.location.href = e.params.data.uri;
        }
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
    // The CartManager class handles all cart button buttons and notifications

    // Initialize just-validate for all forms with data-validate attribute
    initializeFormValidation();

    // Load event counters once on page load (no polling needed - values only change at midnight)
    window.CRM.dashboard.loadEventCounters();

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
    fabPersonLabel.text(i18next.t("Add New") + " " + i18next.t("Person"));
    fabFamilyLabel.text(i18next.t("Add New") + " " + i18next.t("Family"));

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
