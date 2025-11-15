(function () {
    "use strict";

    // Get root path from global CRM config
    const rootPath = window.CRM && window.CRM.root ? window.CRM.root : "";

    // Setup state
    const state = {
        prerequisites: {},
        prerequisitesStatus: false,
        checksComplete: false, // Track if all checks are done
        validatedNavigation: null, // Track validated navigation to prevent loops
    };

    let setupStepper;
    let validators = {};

    function skipCheck() {
        $("#prerequisites-war").hide();
        $("#prerequisites-next-btn").prop("disabled", false);
        $("#prerequisites-force-btn").hide();
        state.prerequisitesStatus = true;

        // Automatically advance to next step
        if (setupStepper) {
            setupStepper.next();
        }
    }

    function updatePrerequisitesUI() {
        // Recalculate prerequisites status based on actual checks
        if (state.checksComplete) {
            let allPassed = true;
            for (const key in state.prerequisites) {
                if (
                    Object.prototype.hasOwnProperty.call(
                        state.prerequisites,
                        key,
                    )
                ) {
                    if (state.prerequisites[key] !== true) {
                        allPassed = false;
                        break;
                    }
                }
            }
            state.prerequisitesStatus = allPassed;
        }

        if (state.prerequisitesStatus) {
            $("#prerequisites-war").hide();
            $("#prerequisites-next-btn").prop("disabled", false);
            $("#prerequisites-force-btn").hide();
        } else if (state.checksComplete) {
            // All checks are done but some failed
            $("#prerequisites-war").show();
            $("#prerequisites-next-btn").prop("disabled", true);
            $("#prerequisites-force-btn").show();
        } else {
            // Checks still running
            $("#prerequisites-war").hide();
            $("#prerequisites-next-btn").prop("disabled", true);
            $("#prerequisites-force-btn").hide();
        }
    }

    function renderPrerequisite(prerequisite) {
        const statusConfig = {
            true: { class: "text-success", html: "&check;" },
            pending: {
                class: "text-warning",
                html: '<i class="fa-solid fa-spinner fa-spin"></i>',
            },
            false: { class: "text-danger", html: "&#x2717;" },
        };

        const td = statusConfig[prerequisite.Satisfied] || statusConfig[false];
        const id = prerequisite.Name.replace(/[^A-Za-z0-9]/g, "");

        state.prerequisites[id] = prerequisite.Satisfied;

        const $prerequisiteRow = $("<tr>", { id: id })
            .append($("<td>", { text: prerequisite.Name }))
            .append($("<td>", td));

        const $existing = $("#" + id);
        if ($existing.length) {
            $existing.replaceWith($prerequisiteRow);
        } else {
            $("#php-extensions").append($prerequisiteRow);
        }

        // Update group status after rendering
        updateGroupStatus();
    }

    function updateGroupStatus() {
        // Update PHP Extensions group status
        const phpExtensionRows = $("#php-extensions tr");
        if (phpExtensionRows.length > 0) {
            let allPassed = true;
            let anyPending = false;

            phpExtensionRows.each(function () {
                const statusCell = $(this).find("td:last");
                if (statusCell.hasClass("text-danger")) {
                    allPassed = false;
                } else if (statusCell.find(".fa-spinner").length > 0) {
                    anyPending = true;
                }
            });

            const phpStatus = $("#php-extensions-status");
            if (anyPending) {
                phpStatus.html(
                    '<i class="fa-solid fa-spinner fa-spin text-muted"></i>',
                );
                $("#php-extensions-body").collapse("show");
            } else if (allPassed) {
                phpStatus.html(
                    '<i class="fa-solid fa-check-circle text-success"></i>',
                );
                $("#php-extensions-body").collapse("hide");
            } else {
                phpStatus.html(
                    '<i class="fa-solid fa-exclamation-circle text-danger"></i>',
                );
                $("#php-extensions-body").collapse("show");
            }
        }

        // Update File Integrity group status
        const integrityRows = $("#integrity-checks tr");
        if (integrityRows.length > 0) {
            let allPassed = true;
            let anyPending = false;

            integrityRows.each(function () {
                const statusCell = $(this).find("td:last");
                if (statusCell.hasClass("text-danger")) {
                    allPassed = false;
                } else if (statusCell.find(".fa-spinner").length > 0) {
                    anyPending = true;
                }
            });

            const integrityStatus = $("#integrity-status");
            if (anyPending) {
                integrityStatus.html(
                    '<i class="fa-solid fa-spinner fa-spin text-muted"></i>',
                );
                $("#integrity-body").collapse("show");
            } else if (allPassed) {
                integrityStatus.html(
                    '<i class="fa-solid fa-check-circle text-success"></i>',
                );
                $("#integrity-body").collapse("hide");
            } else {
                integrityStatus.html(
                    '<i class="fa-solid fa-exclamation-circle text-danger"></i>',
                );
                $("#integrity-body").collapse("show");
            }
        }
    }

    function checkIntegrity() {
        const statusConfig = {
            true: { class: "text-success", html: "&check;" },
            pending: {
                class: "text-warning",
                html: '<i class="fa-solid fa-spinner fa-spin"></i>',
            },
            false: { class: "text-danger", html: "&#x2717;" },
        };

        // Show pending state
        const pendingRow = $("<tr>", { id: "ChurchCRMFileIntegrityCheck" })
            .append($("<td>", { text: "ChurchCRM File Integrity Check" }))
            .append($("<td>", statusConfig.pending));
        $("#integrity-checks").append(pendingRow);
        updateGroupStatus();

        $.ajax({
            url: rootPath + "/setup/SystemIntegrityCheck",
            method: "GET",
        })
            .done(function (data) {
                const satisfied = data === "success";
                const td = satisfied ? statusConfig.true : statusConfig.false;

                const resultRow = $("<tr>", {
                    id: "ChurchCRMFileIntegrityCheck",
                })
                    .append(
                        $("<td>", { text: "ChurchCRM File Integrity Check" }),
                    )
                    .append($("<td>", td));

                $("#ChurchCRMFileIntegrityCheck").replaceWith(resultRow);
                state.prerequisites["ChurchCRMFileIntegrityCheck"] = satisfied;

                // Mark checks as complete
                state.checksComplete = true;

                if (data === "success") {
                    $("#prerequisites-war").hide();
                    state.prerequisitesStatus = true;
                    updatePrerequisitesUI();
                } else {
                    state.prerequisitesStatus = false;
                    updatePrerequisitesUI();
                }

                updateGroupStatus();
            })
            .fail(function () {
                const resultRow = $("<tr>", {
                    id: "ChurchCRMFileIntegrityCheck",
                })
                    .append(
                        $("<td>", { text: "ChurchCRM File Integrity Check" }),
                    )
                    .append($("<td>", statusConfig.false));

                $("#ChurchCRMFileIntegrityCheck").replaceWith(resultRow);
                state.prerequisites["ChurchCRMFileIntegrityCheck"] = false;

                // Mark checks as complete even on failure
                state.checksComplete = true;
                state.prerequisitesStatus = false;
                updatePrerequisitesUI();
                updateGroupStatus();
            });
    }

    function checkPrerequisites() {
        $.ajax({
            url: rootPath + "/setup/SystemPrerequisiteCheck",
            method: "GET",
            contentType: "application/json",
        }).done(function (data) {
            $.each(data, function (index, prerequisite) {
                renderPrerequisite(prerequisite);
            });
            // Run integrity check after prerequisites are rendered
            checkIntegrity();
        });
    }

    function initializeStepValidation(stepId) {
        const stepElement = document.getElementById(stepId);
        if (!stepElement) return null;

        const validator = new window.JustValidate(`#${stepId}`, {
            errorFieldCssClass: "is-invalid",
            successFieldCssClass: "is-valid",
            errorLabelCssClass: "invalid-feedback",
            focusInvalidField: true,
            lockForm: false,
            errorFieldStyle: {
                border: "1px solid #dc3545",
            },
        });

        let hasFields = false;

        // Process all input fields and build validation rules
        stepElement
            .querySelectorAll("input, select, textarea")
            .forEach(function (field) {
                if (!field.id || !field.name) return;

                const errorContainer =
                    field.parentElement.querySelector(".invalid-feedback");
                const rules = [];

                // Add required rule
                if (field.hasAttribute("required")) {
                    let errorMessage;
                    switch (field.name) {
                        case "DB_SERVER_NAME":
                            errorMessage = i18next.t(
                                "Database server hostname or IP address is required (e.g., localhost or 127.0.0.1)",
                            );
                            break;
                        case "DB_SERVER_PORT":
                            errorMessage = i18next.t(
                                "Database server port is required (e.g., 3306)",
                            );
                            break;
                        case "DB_NAME":
                            errorMessage = i18next.t(
                                "Database name is required",
                            );
                            break;
                        case "DB_USER":
                            errorMessage = i18next.t(
                                "Database username is required",
                            );
                            break;
                        case "URL":
                            errorMessage = i18next.t("Base URL is required");
                            break;
                        default:
                            errorMessage = i18next.t("This field is required");
                    }

                    rules.push({
                        rule: "required",
                        errorMessage: errorMessage,
                    });
                }

                // Add URL validation for name="URL"
                if (field.name === "URL") {
                    // Simple URL validation that requires http:// or https:// and a host
                    rules.push({
                        rule: "customRegexp",
                        value: /^https?:\/\/.+/,
                        errorMessage: i18next.t(
                            "Must be a valid URL starting with http:// or https:// (e.g., http://localhost or https://domain.com)",
                        ),
                    });
                }

                // Add pattern validation
                if (field.getAttribute("pattern")) {
                    let errorMessage;
                    if (field.name === "ROOT_PATH") {
                        errorMessage = i18next.t(
                            "Must start with / if not empty, no trailing slash. Only letters, numbers, _, -, ., / allowed.",
                        );
                    } else if (field.name === "DB_SERVER_PORT") {
                        errorMessage = i18next.t(
                            "Must be a valid port number (e.g., 3306)",
                        );
                    } else {
                        errorMessage =
                            field.getAttribute("title") ||
                            i18next.t("Invalid format");
                    }

                    rules.push({
                        rule: "customRegexp",
                        value: new RegExp(field.getAttribute("pattern")),
                        errorMessage: errorMessage,
                    });
                }

                // Add password matching validation for DB_PASSWORD_CONFIRM
                if (field.name === "DB_PASSWORD_CONFIRM") {
                    const matchField = field.getAttribute("data-match");
                    if (matchField) {
                        rules.push({
                            validator: (value) => {
                                const password =
                                    document.querySelector(matchField);
                                return value === password?.value;
                            },
                            errorMessage: i18next.t("Passwords do not match"),
                        });
                    }
                }

                // Add URL validation for Base URL field
                if (field.name === "URL") {
                    rules.push({
                        validator: (value) => {
                            try {
                                const url = new URL(value);
                                return (
                                    url.protocol === "http:" ||
                                    url.protocol === "https:"
                                );
                            } catch (e) {
                                return false;
                            }
                        },
                        errorMessage: i18next.t(
                            "Must be a valid URL starting with http:// or https://",
                        ),
                    });
                }

                // Add number pattern validation for numeric fields
                if (field.getAttribute("pattern") === "[0-9]+") {
                    rules.push({
                        rule: "number",
                        errorMessage: i18next.t("Please enter a valid number"),
                    });
                }

                // If we have any rules, add the field to validation
                if (rules.length > 0) {
                    if (errorContainer) {
                        validator.addField(`#${field.id}`, rules, {
                            errorsContainer: errorContainer,
                        });
                    } else {
                        validator.addField(`#${field.id}`, rules);
                    }
                    hasFields = true;
                }
            });

        return hasFields ? validator : null;
    }

    function submitSetupData() {
        const form = document.getElementById("setup-form");
        const formData = {};

        // Use native FormData API
        const data = new FormData(form);
        for (const [key, value] of data.entries()) {
            formData[key] = value || "";
        }

        // Show the setup modal
        $("#setupModal").modal("show");

        $.ajax({
            url: rootPath + "/setup/",
            method: "POST",
            data: JSON.stringify(formData),
            contentType: "application/json",
        })
            .done(function (response) {
                // Check if response contains errors (backend bug workaround)
                if (response && response.errors) {
                    // Treat as failure
                    $("#setup-progress").hide();
                    $("#setup-error").show();
                    $("#setup-footer").show();

                    let errorMessage = "<ul class='mb-0'>";
                    for (const [field, error] of Object.entries(
                        response.errors,
                    )) {
                        errorMessage += `<li><strong>${field}:</strong> ${error}</li>`;
                    }
                    errorMessage += "</ul>";
                    $("#setup-error-message").html(errorMessage);

                    $("#continue-to-login")
                        .text("Close")
                        .off("click")
                        .on("click", function () {
                            $("#setupModal").modal("hide");
                            setTimeout(function () {
                                $("#setup-progress").show();
                                $("#setup-success").hide();
                                $("#setup-error").hide();
                                $("#setup-footer").hide();
                            }, 500);
                        });
                    return;
                }

                // Hide progress, show success
                $("#setup-progress").hide();
                $("#setup-success").show();
                $("#setup-footer").show();

                // Handle Continue to Login button
                $("#continue-to-login")
                    .off("click")
                    .on("click", function () {
                        location.replace(rootPath + "/");
                    });
            })
            .fail(function (xhr) {
                // Hide progress, show error
                $("#setup-progress").hide();
                $("#setup-error").show();
                $("#setup-footer").show();

                // Parse error message
                let errorMessage = "An unknown error occurred.";
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    errorMessage = "<ul class='mb-0'>";
                    for (const [field, error] of Object.entries(
                        xhr.responseJSON.errors,
                    )) {
                        errorMessage += `<li><strong>${field}:</strong> ${error}</li>`;
                    }
                    errorMessage += "</ul>";
                } else if (xhr.responseText) {
                    errorMessage = xhr.responseText;
                } else if (xhr.statusText) {
                    errorMessage = xhr.statusText;
                }

                $("#setup-error-message").html(errorMessage);

                // Change button to "Try Again"
                $("#continue-to-login")
                    .text("Close")
                    .off("click")
                    .on("click", function () {
                        $("#setupModal").modal("hide");
                        // Reset modal state for next attempt
                        setTimeout(function () {
                            $("#setup-progress").show();
                            $("#setup-success").hide();
                            $("#setup-error").hide();
                            $("#setup-footer").hide();
                        }, 500);
                    });
            });
    }

    // Note: skipCheck is intentionally NOT exposed globally to prevent accidental calls
    // It should only be called via the Force Install confirmation flow

    document.addEventListener("DOMContentLoaded", function () {
        const form = document.getElementById("setup-form");
        const stepperElement = document.getElementById("setup-stepper");

        // Prevent form submission (we handle it via AJAX)
        form.addEventListener("submit", function (event) {
            event.preventDefault();
            return false;
        });

        setupStepper = new Stepper(stepperElement, {
            linear: true,
            animation: true,
            selectors: {
                steps: ".step",
                trigger: ".step-trigger",
                stepper: ".bs-stepper",
            },
        });

        // Store globally for onclick handlers
        window.setupStepper = setupStepper;

        // Initialize validators for steps that need validation
        validators["step-location"] = initializeStepValidation("step-location");
        validators["step-database"] = initializeStepValidation("step-database");

        // Custom navigation logic with validation
        stepperElement.addEventListener("show.bs-stepper", function (event) {
            const currentStep = event.detail.from;
            const nextStep = event.detail.to;

            // Only validate when moving forward
            if (nextStep <= currentStep) {
                return; // Allow backward navigation
            }

            // Check prerequisites when leaving step 0
            if (currentStep === 0 && !state.prerequisitesStatus) {
                event.preventDefault();
                window.CRM.notify(
                    "Please ensure all prerequisites are met before continuing.",
                    {
                        type: "warning",
                        delay: 3000,
                    },
                );
                return;
            }
        });

        // Update UI when steps are shown (after navigation completes)
        stepperElement.addEventListener("shown.bs-stepper", function (event) {
            const shownStep = event.detail.to;

            // If returning to prerequisites step, update UI
            if (shownStep === 0) {
                updatePrerequisitesUI();
                updateGroupStatus();
            }
        });

        // Handle finish button
        document
            .getElementById("submit-setup")
            .addEventListener("click", function (event) {
                event.preventDefault(); // Prevent any default behavior

                // Validate database step before submission
                if (validators["step-database"]) {
                    validators["step-database"]
                        .revalidate()
                        .then(function (isValid) {
                            if (isValid) {
                                submitSetupData();
                            } else {
                                window.CRM.notify(
                                    "Please fill in all required fields correctly.",
                                    {
                                        type: "danger",
                                        delay: 3000,
                                    },
                                );
                            }
                        });
                } else {
                    submitSetupData();
                }
            });

        // Handle prerequisites Next button
        document
            .getElementById("prerequisites-next-btn")
            .addEventListener("click", function () {
                if (setupStepper) {
                    setupStepper.next();
                }
            });

        // Handle location step buttons
        document
            .getElementById("location-prev-btn")
            .addEventListener("click", function () {
                if (setupStepper) {
                    setupStepper.previous();
                }
            });

        document
            .getElementById("location-next-btn")
            .addEventListener("click", function (event) {
                event.preventDefault();
                // Validate the location step before proceeding
                if (validators["step-location"]) {
                    validators["step-location"]
                        .revalidate()
                        .then(function (isValid) {
                            if (isValid && setupStepper) {
                                setupStepper.next();
                            } else if (!isValid) {
                                window.CRM.notify(
                                    i18next.t(
                                        "Please correct the validation errors before continuing.",
                                    ),
                                    {
                                        type: "warning",
                                        delay: 3000,
                                    },
                                );
                            }
                        });
                } else if (setupStepper) {
                    setupStepper.next();
                }
            });

        // Handle database step Previous button
        document
            .getElementById("database-prev-btn")
            .addEventListener("click", function () {
                if (setupStepper) {
                    setupStepper.previous();
                }
            });

        // Handle Force Install button click - show confirmation modal
        const forceBtn = document.getElementById("prerequisites-force-btn");
        if (forceBtn) {
            forceBtn.addEventListener("click", function (e) {
                e.preventDefault();
                e.stopPropagation();
                $("#forceInstallModal").modal("show");
            });
        }

        // Handle Force Install confirmation
        const confirmBtn = document.getElementById("confirm-force-install");
        if (confirmBtn) {
            confirmBtn.addEventListener("click", function (e) {
                e.preventDefault();
                $("#forceInstallModal").modal("hide");
                // Wait for modal to hide before proceeding
                setTimeout(function () {
                    skipCheck();
                }, 300);
            });
        }

        // Initialize prerequisite checks - integrity check will run after prerequisites load
        checkPrerequisites();
    });
})();
