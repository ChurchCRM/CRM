(function() {
    'use strict';

    const rootPath = window.CRM && window.CRM.root ? window.CRM.root : '';
    let registrationStepper;

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
    }

    function validateMembers() {
        const familyCount = $("#familyCount").val();
        let isValid = true;

        for (let i = 1; i <= familyCount; i++) {
            const firstName = document.getElementById("memberFirstName-" + i);
            const lastName = document.getElementById("memberLastName-" + i);

            if (!firstName.checkValidity()) {
                isValid = false;
                firstName.classList.add('is-invalid');
            }
            if (!lastName.checkValidity()) {
                isValid = false;
                lastName.classList.add('is-invalid');
            }
        }

        if (!isValid) {
            $.notify(i18next.t('Please fill in the first and last name for all family members'), {
                type: 'warning',
                delay: 3000
            });
        }

        return isValid;
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
                person.cellPhone || person.workPhone || person.homePhone || ''
            );
            $("#displayFamilyPersonBDay" + num).text(person.birthday);
            num++;
        });
    }

    function validateCurrentStep(stepIndex) {
        const form = document.getElementById('registration-form');
        const stepIds = ['step-family-info', 'step-members', 'step-review'];
        const currentStep = document.getElementById(stepIds[stepIndex]);
        const inputs = currentStep.querySelectorAll('input[required]');
        
        let isValid = true;
        inputs.forEach(input => {
            if (!input.checkValidity()) {
                isValid = false;
                input.classList.add('is-invalid');
                
                const helpBlock = input.parentElement.querySelector('.help-block');
                if (helpBlock) {
                    helpBlock.textContent = input.validationMessage;
                    helpBlock.style.display = 'block';
                }
            } else {
                input.classList.remove('is-invalid');
                const helpBlock = input.parentElement.querySelector('.help-block');
                if (helpBlock) {
                    helpBlock.textContent = '';
                    helpBlock.style.display = 'none';
                }
            }
        });

        // Special validation for members step
        if (stepIndex === 1) {
            isValid = validateMembers() && isValid;
        }

        return isValid;
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
                message: i18next.t(
                    "Thank you for registering your family",
                ),
                buttons: {
                    new: {
                        label: i18next.t("Register another family!"),
                        className: "btn-default",
                        callback: function () {
                            window.location.href = rootPath + "/external/register/";
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

    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('registration-form');
        const stepperElement = document.getElementById('registration-stepper');
        
        registrationStepper = new Stepper(stepperElement, {
            linear: true,
            animation: true,
            selectors: {
                steps: '.step',
                trigger: '.step-trigger',
                stepper: '.bs-stepper'
            }
        });

        // Store globally for onclick handlers
        window.registrationStepper = registrationStepper;

        // Validation on step change
        stepperElement.addEventListener('show.bs-stepper', function (event) {
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
            if (!validateCurrentStep(currentStep)) {
                return;
            }

            registrationStepper.to(nextStep);
        });

        // Handle form submission
        document.getElementById('submit-registration').addEventListener('click', function() {
            submitRegistration();
        });

        // Clear validation on input change
        form.addEventListener('input', function(e) {
            if (e.target.classList.contains('is-invalid')) {
                e.target.classList.remove('is-invalid');
                const helpBlock = e.target.parentElement.querySelector('.help-block');
                if (helpBlock) {
                    helpBlock.textContent = '';
                    helpBlock.style.display = 'none';
                }
            }
        });

        // Update member last names when family name changes
        $("#familyName").on('change', function() {
            const familyName = $(this).val();
            const familyCount = $("#familyCount").val();
            for (let i = 1; i <= familyCount; i++) {
                if (!$("#memberLastName-" + i).val()) {
                    $("#memberLastName-" + i).val(familyName);
                }
            }
        });

        // Update member boxes when family count changes
        $("#familyCount").on('change', updateMemberBoxes);

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
                url: rootPath + "/api/public/data/countries/" +
                    this.value.toLowerCase() + "/states",
            }).done(function (data) {
                if (Object.keys(data).length > 0) {
                    $("#familyStateSelect").empty();
                    $.each(data, function (code, name) {
                        const selected =
                            $("#familyStateSelect").data("system-default") == code;
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
