(function () {
    "use strict";

    const rootPath = window.CRM && window.CRM.root ? window.CRM.root : "";
    let registrationStepper;
    let validators = {};
    let state = {
        validatedNavigation: null,
    };

    function buildFamilyObject() {
        const family = {};
        family["Name"] = $("#familyName").val();
        family["Address1"] = $("#familyAddress1").val();
        family["City"] = $("#familyCity").val();
        family["State"] = $("#familyState").val();
        family["Country"] = $("#familyCountry").val();
        family["Zip"] = $("#familyZip").val();
        family["HomePhone"] = $("#familyHomePhone").val();
        family["people"] = [];

        const familyCount = $("#familyCount").val();
        for (let i = 1; i <= familyCount; i++) {
            const person = {
                role: $("#memberRole-" + i).val(),
                gender: $("#memberGender-" + i).val(),
                firstName: $("#memberFirstName-" + i).val(),
                lastName: $("#memberLastName-" + i).val(),
                email: $("#memberEmail-" + i).val(),
                birthday: $("#memberBirthday-" + i).val(),
                hideAge: $("#memberHideAge-" + i).prop("checked"),
            };

            const phoneType = $("#memberPhoneType-" + i).val();
            const phoneNumber = $("#memberPhone-" + i).val();
            if (phoneType === "mobile") {
                person["cellPhone"] = phoneNumber;
            } else if (phoneType === "work") {
                person["workPhone"] = phoneNumber;
            } else if (phoneType === "home") {
                person["homePhone"] = phoneNumber;
            }
            family["people"].push(person);
        }
        return family;
    }

    function updateMemberBoxes() {
        const familyCount = $("#familyCount").val();
        const familyName = $("#familyName").val();

        // Update the badge display in step 2 header
        $("#member-count-display").text(
            familyCount +
                " " +
                i18next.t(familyCount === "1" ? "member" : "members"),
        );

        for (let i = 1; i <= 8; i++) {
            const boxName = "#memberBox" + i;
            if (i <= familyCount) {
                $(boxName).show();
                // Pre-fill last name with family name if not already set
                if (!$("#memberLastName-" + i).val()) {
                    $("#memberLastName-" + i).val(familyName);
                }
            } else {
                $(boxName).hide();
            }
        }

        // Reinitialize member step validator with visible fields
        initializeMemberValidator();
    }

    function initializeFamilyInfoValidator() {
        if (validators["step-family-info"]) {
            validators["step-family-info"].destroy();
        }

        const validator = new window.JustValidate("#step-family-info", {
            errorFieldCssClass: "is-invalid",
            successFieldCssClass: "is-valid",
            errorLabelCssClass: "invalid-feedback",
            focusInvalidField: true,
            lockForm: false,
        });

        validator.addField("#familyName", [
            {
                rule: "required",
                errorMessage: i18next.t("Family name is required"),
            },
            {
                rule: "minLength",
                value: 2,
                errorMessage: i18next.t(
                    "Family name must be at least 2 characters",
                ),
            },
        ]);

        validator.addField("#familyAddress1", [
            {
                rule: "required",
                errorMessage: i18next.t("Address is required"),
            },
        ]);

        validator.addField("#familyCity", [
            {
                rule: "required",
                errorMessage: i18next.t("City is required"),
            },
        ]);

        validator.addField("#familyZip", [
            {
                rule: "required",
                errorMessage: i18next.t("Zip code is required"),
            },
        ]);

        validator.addField("#familyHomePhone", [
            {
                rule: "required",
                errorMessage: i18next.t("Home phone is required"),
            },
            {
                rule: "minLength",
                value: 7,
                errorMessage: i18next.t("Please enter a valid phone number"),
            },
        ]);

        validators["step-family-info"] = validator;
    }

    function initializeMemberValidator() {
        if (validators["step-members"]) {
            validators["step-members"].destroy();
        }

        const validator = new window.JustValidate("#step-members", {
            errorFieldCssClass: "is-invalid",
            successFieldCssClass: "is-valid",
            errorLabelCssClass: "invalid-feedback",
            focusInvalidField: true,
            lockForm: false,
        });

        const familyCount = parseInt($("#familyCount").val());

        for (let i = 1; i <= familyCount; i++) {
            validator.addField("#memberFirstName-" + i, [
                {
                    rule: "required",
                    errorMessage: i18next.t("First name is required"),
                },
                {
                    rule: "minLength",
                    value: 2,
                    errorMessage: i18next.t(
                        "First name must be at least 2 characters",
                    ),
                },
            ]);

            validator.addField("#memberLastName-" + i, [
                {
                    rule: "required",
                    errorMessage: i18next.t("Last name is required"),
                },
                {
                    rule: "minLength",
                    value: 2,
                    errorMessage: i18next.t(
                        "Last name must be at least 2 characters",
                    ),
                },
            ]);

            // Email validation (optional but must be valid if provided)
            const emailField = document.getElementById("memberEmail-" + i);
            if (emailField && emailField.value.trim() !== "") {
                validator.addField("#memberEmail-" + i, [
                    {
                        rule: "email",
                        errorMessage: i18next.t(
                            "Please enter a valid email address (e.g., name@example.com)",
                        ),
                    },
                ]);
            }
        }

        validators["step-members"] = validator;
    }

    function displayReviewSummary() {
        const family = buildFamilyObject();
        $("#displayFamilyName").text(family.Name);

        const familyAddress =
            family.Address1 +
            ", " +
            family.City +
            ", " +
            family.State +
            " " +
            family.Zip +
            " " +
            family.Country;
        $("#displayFamilyAddress").text(familyAddress);
        $("#displayFamilyPhone").text(family.HomePhone);

        const familyCount = $("#familyCount").val();
        for (let i = parseInt(familyCount) + 1; i <= 8; i++) {
            $("#displayFamilyPerson" + i).hide();
        }

        let num = 1;
        family.people.forEach(function (person) {
            $("#displayFamilyPersonFName" + num).text(person.firstName);
            $("#displayFamilyPersonLName" + num).text(person.lastName);
            $("#displayFamilyPersonEmail" + num).text(person.email);
            $("#displayFamilyPersonPhone" + num).text(
                person.cellPhone || person.workPhone || person.homePhone || "",
            );
            $("#displayFamilyPersonBDay" + num).text(person.birthday);
            num++;
        });
    }

    function submitRegistration() {
        $.ajax({
            url: rootPath + "/api/public/register/family",
            type: "POST",
            dataType: "json",
            contentType: "application/json",
            data: JSON.stringify(buildFamilyObject()),
        })
            .done(function (data) {
                bootbox.dialog({
                    title: i18next.t("Registration Complete"),
                    message: i18next.t("Thank you for registering your family"),
                    buttons: {
                        new: {
                            label: i18next.t("Register another family!"),
                            className: "btn-default",
                            callback: function () {
                                window.location.href =
                                    rootPath + "/external/register/";
                            },
                        },
                        done: {
                            label: i18next.t("Done, show me the homepage!"),
                            className: "btn-info",
                            callback: function () {
                                window.location.href = window.CRM.churchWebSite;
                            },
                        },
                    },
                });
            })
            .fail(function (xhr) {
                console.error("Registration failed:", xhr);

                // Parse error message
                let errorMessage = i18next.t(
                    "Sorry, we are unable to process your request at this point in time.",
                );

                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                } else if (xhr.responseJSON && xhr.responseJSON.errors) {
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

                bootbox.alert({
                    title: i18next.t("Registration Error"),
                    message: errorMessage,
                });
            });
    }

    // Expose globally for onclick handlers
    window.registrationStepper = null;

    document.addEventListener("DOMContentLoaded", function () {
        const form = document.getElementById("registration-form");
        const stepperElement = document.getElementById("registration-stepper");

        // Prevent form submission (we handle it via AJAX)
        if (form) {
            form.addEventListener("submit", function (event) {
                event.preventDefault();
                return false;
            });
        }

        registrationStepper = new Stepper(stepperElement, {
            linear: true,
            animation: true,
            selectors: {
                steps: ".step",
                trigger: ".step-trigger",
                stepper: ".bs-stepper",
            },
        });

        // Store globally for onclick handlers
        window.registrationStepper = registrationStepper;

        // Initialize validators
        initializeFamilyInfoValidator();
        initializeMemberValidator();

        // Validation on step change
        stepperElement.addEventListener("show.bs-stepper", function (event) {
            const currentStep = event.detail.from;
            const nextStep = event.detail.to;

            // Check if this navigation was already validated
            if (
                state.validatedNavigation &&
                state.validatedNavigation.from === currentStep &&
                state.validatedNavigation.to === nextStep
            ) {
                state.validatedNavigation = null;
                // Continue with navigation setup
            } else if (nextStep > currentStep) {
                // Forward navigation without validation - prevent it
                // (validation should happen in button handlers)
                event.preventDefault();
                return;
            }

            // Prepare members step when navigating to it
            if (nextStep === 1) {
                updateMemberBoxes();
            }

            // Prepare review step when navigating to it
            if (nextStep === 2) {
                displayReviewSummary();
            }
        });

        // Handle form submission
        document
            .getElementById("submit-registration")
            .addEventListener("click", function () {
                submitRegistration();
            });

        // Navigation button event listeners (CSP compliant - no inline onclick)
        document
            .getElementById("family-info-next")
            .addEventListener("click", function (e) {
                e.preventDefault();
                if (validators["step-family-info"]) {
                    validators["step-family-info"]
                        .revalidate()
                        .then(function (isValid) {
                            if (isValid) {
                                state.validatedNavigation = { from: 0, to: 1 };
                                registrationStepper.next();
                            } else {
                                $.notify(
                                    i18next.t(
                                        "Please fill in all required fields correctly.",
                                    ),
                                    {
                                        type: "warning",
                                        delay: 3000,
                                    },
                                );
                            }
                        });
                } else {
                    registrationStepper.next();
                }
            });

        document
            .getElementById("members-previous")
            .addEventListener("click", function () {
                registrationStepper.previous();
            });

        document
            .getElementById("members-next")
            .addEventListener("click", function (e) {
                e.preventDefault();
                if (validators["step-members"]) {
                    validators["step-members"]
                        .revalidate()
                        .then(function (isValid) {
                            if (isValid) {
                                state.validatedNavigation = { from: 1, to: 2 };
                                registrationStepper.next();
                            } else {
                                $.notify(
                                    i18next.t(
                                        "Please fill in all required fields correctly.",
                                    ),
                                    {
                                        type: "warning",
                                        delay: 3000,
                                    },
                                );
                            }
                        });
                } else {
                    registrationStepper.next();
                }
            });

        document
            .getElementById("review-previous")
            .addEventListener("click", function () {
                registrationStepper.previous();
            });

        // Update member last names when family name changes
        $("#familyName").on("change", function () {
            const familyName = $(this).val();
            const familyCount = $("#familyCount").val();
            for (let i = 1; i <= familyCount; i++) {
                if (!$("#memberLastName-" + i).val()) {
                    $("#memberLastName-" + i).val(familyName);
                }
            }
        });

        // Update member boxes when family count changes
        $("#familyCount").on("change", updateMemberBoxes);

        // Load countries
        $.ajax({
            type: "GET",
            url: rootPath + "/api/public/data/countries",
        }).done(function (data) {
            const familyCountry = $("#familyCountry");
            $.each(data, function (idx, country) {
                const selected =
                    familyCountry.data("system-default") === country.name;
                familyCountry.append(
                    new Option(country.name, country.code, selected, selected),
                );
            });
            familyCountry.change();
        });

        // Initialize select2 and country/state handling
        $("#familyCountry").select2();

        $("#familyCountry").change(function () {
            const $container = $("#familyStateContainer");
            const defaultState =
                $container.find("#familyState").data("default") || "";

            $.ajax({
                type: "GET",
                url:
                    rootPath +
                    "/api/public/data/countries/" +
                    this.value.toLowerCase() +
                    "/states",
            }).done(function (data) {
                if (Object.keys(data).length > 0) {
                    // Country has states - replace with dropdown
                    let selectHtml =
                        '<select id="familyState" name="familyState" class="form-control">';
                    $.each(data, function (code, name) {
                        const selected =
                            defaultState == code ? " selected" : "";
                        selectHtml +=
                            '<option value="' +
                            code +
                            '"' +
                            selected +
                            ">" +
                            name +
                            "</option>";
                    });
                    selectHtml += "</select>";

                    $container.html(selectHtml);
                    $("#familyState").select2();
                } else {
                    // Country has no states - replace with text input
                    const stateValue = defaultState || "";
                    const inputHtml =
                        '<input id="familyState" name="familyState" class="form-control" placeholder="' +
                        i18next.t("State") +
                        '" value="' +
                        stateValue +
                        '" data-default="' +
                        stateValue +
                        '">';
                    $container.html(inputHtml);
                }
            });
        });

        // Initialize date pickers and input masks
        $(".inputDatePicker").datepicker({
            autoclose: true,
        });

        // Initialize input masks with error handling
        $("[data-mask]").each(function () {
            try {
                $(this).inputmask();
            } catch (e) {
                console.error("Failed to initialize inputmask:", e);
            }
        });
    });
})();
