(function () {
    "use strict";

    const rootPath = window.CRM && window.CRM.root ? window.CRM.root : "";
    let registrationStepper;
    let validators = {};

    function buildFamilyObject() {
        const family = {};
        family["Name"] = $("#familyName").val();
        family["Address1"] = $("#familyAddress1").val();
        family["City"] = $("#familyCity").val();
        if ($("#familyStateSelect").hasClass("hidden")) {
            family["State"] = $("#familyStateInput").val();
        } else {
            family["State"] = $("#familyStateSelect").val();
        }
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
        ]);

        validator.addField("#familyAddress1", [
            {
                rule: "required",
                errorMessage: i18next.t("Address is required"),
            },
        ]);

        validator.addField("#familyCity", [
            { rule: "required", errorMessage: i18next.t("City is required") },
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
            ]);

            validator.addField("#memberLastName-" + i, [
                {
                    rule: "required",
                    errorMessage: i18next.t("Last name is required"),
                },
            ]);
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
            .fail(function (data) {
                bootbox.alert({
                    title: i18next.t(
                        "Sorry, we are unable to process your request at this point in time.",
                    ),
                    message: data.responseText,
                });
            });
    }

    // Expose globally for onclick handlers
    window.registrationStepper = null;

    document.addEventListener("DOMContentLoaded", function () {
        const form = document.getElementById("registration-form");
        const stepperElement = document.getElementById("registration-stepper");

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
            event.preventDefault();

            const currentStep = event.detail.from;
            const nextStep = event.detail.to;

            // Allow backward navigation
            if (nextStep < currentStep) {
                registrationStepper.to(nextStep);
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

            // Validate before moving forward
            const stepIds = ["step-family-info", "step-members", "step-review"];
            const currentStepId = stepIds[currentStep];

            if (validators[currentStepId]) {
                validators[currentStepId].revalidate().then(function (isValid) {
                    if (isValid) {
                        registrationStepper.to(nextStep);
                    }
                });
            } else {
                // No validation for review step
                registrationStepper.to(nextStep);
            }
        });

        // Handle form submission
        document
            .getElementById("submit-registration")
            .addEventListener("click", function () {
                submitRegistration();
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
            $.ajax({
                type: "GET",
                url:
                    rootPath +
                    "/api/public/data/countries/" +
                    this.value.toLowerCase() +
                    "/states",
            }).done(function (data) {
                if (Object.keys(data).length > 0) {
                    $("#familyStateSelect").empty();
                    $.each(data, function (code, name) {
                        const selected =
                            $("#familyStateSelect").data("system-default") ==
                            code;
                        $("#familyStateSelect").append(
                            new Option(name, code, selected, selected),
                        );
                    });
                    $("#familyStateSelect").change();
                    $("#familyStateInput").addClass("hidden");
                    $("#familyStateSelect").removeClass("hidden");
                } else {
                    $("#familyStateInput").removeClass("hidden");
                    $("#familyStateSelect").addClass("hidden");
                }
            });
        });

        // Initialize date pickers and input masks
        $(".inputDatePicker").datepicker({
            autoclose: true,
        });

        $("[data-mask]").inputmask();
    });
})();
