(function () {
    "use strict";

    const rootPath = window.CRM && window.CRM.root ? window.CRM.root : "";
    let registrationStepper;
    let validators = {};
    let state = {
        validatedNavigation: null,
        currentMemberCount: 0,
    };

    // ==================== Member Management ====================

    /**
     * Collapse all member cards except the specified one (accordion behavior)
     */
    function collapseAllExcept(memberIndex) {
        document.querySelectorAll(".member-card").forEach((card) => {
            const index = card.getAttribute("data-member-index");
            if (index !== String(memberIndex)) {
                const body = card.querySelector(".member-card-body");
                const btn = card.querySelector(".member-toggle-btn");
                if (body && body.style.display !== "none") {
                    body.style.display = "none";
                    btn.querySelector("i").classList.remove("fa-chevron-up");
                    btn.querySelector("i").classList.add("fa-chevron-down");
                }
            }
        });
    }

    /**
     * Add a new member card to the members container
     */
    function addMember() {
        // Collapse current expanded card before adding new one
        collapseAllExcept(null);

        state.currentMemberCount++;
        const template = document.getElementById("member-card-template");
        const clone = template.content.cloneNode(true);
        const memberIndex = state.currentMemberCount;

        // Set data attribute
        const memberCard = clone.querySelector(".member-card");
        memberCard.setAttribute("data-member-index", memberIndex);

        // Set unique IDs on all form fields
        const fieldMap = {
            "member-first-name": "firstName",
            "member-last-name": "lastName",
            "member-role": "role",
            "member-gender": "gender",
            "member-email": "email",
            "member-phone": "phone",
            "member-phone-type": "phoneType",
            "member-birthday": "birthday",
            "member-hide-age": "hideAge",
        };

        Object.entries(fieldMap).forEach(([className, fieldName]) => {
            const element = clone.querySelector(`.${className}`);
            if (element) {
                element.id = `${className}-${memberIndex}`;
                element.setAttribute("data-field-name", fieldName);

                // Set the label's "for" attribute if this is a checkbox/radio
                if (element.type === "checkbox" || element.type === "radio") {
                    const label = element.nextElementSibling;
                    if (label && label.classList.contains("custom-control-label")) {
                        label.setAttribute("for", element.id);
                    }
                }
            }
        });

        // Setup collapse toggle button
        const toggleBtn = clone.querySelector(".member-toggle-btn");
        const cardBody = clone.querySelector(".member-card-body");
        const cardHeader = clone.querySelector(".member-card-header-clickable");

        if (!toggleBtn || !cardBody || !cardHeader) {
            console.error("Member card template is missing required elements");
            return;
        }

        // All new cards start expanded for immediate editing
        cardBody.style.display = "block";
        toggleBtn.querySelector("i").classList.remove("fa-chevron-down");
        toggleBtn.querySelector("i").classList.add("fa-chevron-up");

        // Add click handler to toggle form visibility (accordion style)
        toggleBtn.addEventListener("click", (e) => {
            e.preventDefault();
            e.stopPropagation();
            const isCollapsed = cardBody.style.display === "none";
            if (isCollapsed) {
                // Collapse all others and expand this one
                collapseAllExcept(memberIndex);
                cardBody.style.display = "block";
                toggleBtn.querySelector("i").classList.remove("fa-chevron-down");
                toggleBtn.querySelector("i").classList.add("fa-chevron-up");
            } else {
                // Collapse this one
                cardBody.style.display = "none";
                toggleBtn.querySelector("i").classList.add("fa-chevron-down");
                toggleBtn.querySelector("i").classList.remove("fa-chevron-up");
            }
        });

        // Add click handler to card header to toggle (accordion style)
        cardHeader.addEventListener("click", function (e) {
            if (e.target.classList.contains("remove-member-btn") || e.target.closest(".remove-member-btn")) {
                return;
            }
            const isCollapsed = cardBody.style.display === "none";
            if (isCollapsed) {
                // Collapse all others and expand this one
                collapseAllExcept(memberIndex);
                cardBody.style.display = "block";
                toggleBtn.querySelector("i").classList.remove("fa-chevron-down");
                toggleBtn.querySelector("i").classList.add("fa-chevron-up");
            } else {
                // Collapse this one
                cardBody.style.display = "none";
                toggleBtn.querySelector("i").classList.add("fa-chevron-down");
                toggleBtn.querySelector("i").classList.remove("fa-chevron-up");
            }
        });

        // Show remove button only if more than 1 member
        const removeBtn = clone.querySelector(".remove-member-btn");
        if (memberIndex > 1) {
            removeBtn.style.display = "block";
            removeBtn.addEventListener("click", (e) => {
                e.preventDefault();
                e.stopPropagation();
                removeMember(memberIndex);
            });
        }

        // Append to container
        $("#members-container").append(clone);

        // Add event listeners to update display name
        const firstNameInput = $(`#member-first-name-${memberIndex}`);
        const lastNameInput = $(`#member-last-name-${memberIndex}`);
        const displayNameSpan = memberCard.querySelector(".member-display-name");

        const updateDisplayName = function () {
            const firstName = firstNameInput.val() || gettext("Member");
            const lastName = lastNameInput.val() ? ` ${lastNameInput.val()}` : "";
            displayNameSpan.textContent = `${firstName}${lastName}`;
        };

        firstNameInput.on("change keyup", updateDisplayName);
        lastNameInput.on("change keyup", updateDisplayName);

        // Initialize date picker on new birthday field
        const birthdayInput = $(`#member-birthday-${memberIndex}`);
        const hideAgeCheckbox = $(`#member-hide-age-${memberIndex}`);

        birthdayInput.datepicker();

        // Handle Hide Age checkbox - only enable when birthday is selected
        const updateHideAgeState = function () {
            if (birthdayInput.val().trim() === "") {
                // No birthday selected - disable and uncheck
                hideAgeCheckbox.prop("disabled", true);
                hideAgeCheckbox.prop("checked", false);
            } else {
                // Birthday selected - enable checkbox
                hideAgeCheckbox.prop("disabled", false);
            }
        };

        // Update state on date change - Bootstrap Datepicker uses both 'change' and 'changeDate'
        birthdayInput.on("change", updateHideAgeState);
        birthdayInput.on("changeDate", updateHideAgeState);
        birthdayInput.on("input", updateHideAgeState);

        // Initialize state on page load
        updateHideAgeState();

        // Initialize input mask on new phone field
        $(`#member-phone-${memberIndex}`).inputmask();

        // Update display
        updateMemberCount();

        // Destroy and reinitialize validator for step 2
        initializeMemberValidator();
    }

    /**
     * Remove a member card and update validation
     */
    function removeMember(memberIndex) {
        $(`.member-card[data-member-index="${memberIndex}"]`).remove();
        state.currentMemberCount--;

        // Hide remove button if only 1 member left
        if (state.currentMemberCount === 1) {
            $(".remove-member-btn").hide();
        }

        updateMemberCount();
        initializeMemberValidator();
    }

    /**
     * Update member count display
     */
    function updateMemberCount() {
        const plural = state.currentMemberCount === 1 ? "member" : "members";
        $("#member-count-display").text(`${state.currentMemberCount} ${i18next.t(plural)}`);
    }

    // ==================== Data Collection ====================

    /**
     * Collect member data from a specific member card
     * @param {number} memberIndex - Member index to collect data from
     * @returns {Object} Person object with all fields
     */
    function getMemberDataFromCard(memberIndex) {
        const phoneType = $(`#member-phone-type-${memberIndex}`).val();
        const phoneNumber = $(`#member-phone-${memberIndex}`).val();

        const phoneField = {};
        if (phoneNumber) {
            switch (phoneType) {
                case "mobile":
                    phoneField.cellPhone = phoneNumber;
                    break;
                case "work":
                    phoneField.workPhone = phoneNumber;
                    break;
                case "home":
                    phoneField.homePhone = phoneNumber;
                    break;
            }
        }

        return {
            role: $(`#member-role-${memberIndex}`).val(),
            gender: $(`#member-gender-${memberIndex}`).val(),
            firstName: $(`#member-first-name-${memberIndex}`).val(),
            lastName: $(`#member-last-name-${memberIndex}`).val(),
            email: $(`#member-email-${memberIndex}`).val(),
            birthday: $(`#member-birthday-${memberIndex}`).val(),
            hideAge: $(`#member-hide-age-${memberIndex}`).prop("checked"),
            ...phoneField,
        };
    }

    /**
     * Build complete family object from form data
     * @returns {Object} Family object with all members
     */
    function buildFamilyObject() {
        const family = {
            Name: $("#familyName").val(),
            Address1: $("#familyAddress1").val(),
            City: $("#familyCity").val(),
            State: $("#familyState").val(),
            Country: $("#familyCountry").val(),
            Zip: $("#familyZip").val(),
            HomePhone: $("#familyHomePhone").val(),
            people: [],
        };

        // Collect data from all member cards using actual DOM elements to avoid gaps in indices
        document.querySelectorAll(".member-card").forEach((card) => {
            const memberIndex = parseInt(card.getAttribute("data-member-index"));
            family.people.push(getMemberDataFromCard(memberIndex));
        });

        return family;
    }

    // ==================== Validation ====================

    /**
     * Get error container for a field ID
     * @param {string} fieldId - Element ID
     * @returns {Object|null} Error container element or null
     */
    function getErrorContainer(fieldId) {
        const field = document.getElementById(fieldId);
        return field ? field.parentElement.querySelector(".invalid-feedback") : null;
    }

    /**
     * Create JustValidate instance with standard config
     * @param {string} formSelector - Form selector
     * @returns {Object} JustValidate instance
     */
    function createValidator(formSelector) {
        return new window.JustValidate(formSelector, {
            errorFieldCssClass: "is-invalid",
            successFieldCssClass: "is-valid",
            errorLabelCssClass: "invalid-feedback",
            focusInvalidField: true,
            lockForm: false,
            errorFieldStyle: {
                border: "1px solid #dc3545",
            },
        });
    }

    function initializeFamilyInfoValidator() {
        if (validators["step-family-info"]) {
            validators["step-family-info"].destroy();
        }

        const validator = createValidator("#step-family-info");

        // Family Name
        const familyNameContainer = getErrorContainer("familyName");
        validator.addField(
            "#familyName",
            [
                {
                    rule: "required",
                    errorMessage: i18next.t("Family name is required"),
                },
                {
                    rule: "minLength",
                    value: 2,
                    errorMessage: i18next.t("Family name must be at least 2 characters"),
                },
            ],
            familyNameContainer ? { errorsContainer: familyNameContainer } : {},
        );

        // Address
        const address1Container = getErrorContainer("familyAddress1");
        validator.addField(
            "#familyAddress1",
            [
                {
                    rule: "required",
                    errorMessage: i18next.t("Address is required"),
                },
            ],
            address1Container ? { errorsContainer: address1Container } : {},
        );

        // City
        const cityContainer = getErrorContainer("familyCity");
        validator.addField(
            "#familyCity",
            [
                {
                    rule: "required",
                    errorMessage: i18next.t("City is required"),
                },
            ],
            cityContainer ? { errorsContainer: cityContainer } : {},
        );

        // Zip
        const zipContainer = getErrorContainer("familyZip");
        validator.addField(
            "#familyZip",
            [
                {
                    rule: "required",
                    errorMessage: i18next.t("Zip code is required"),
                },
            ],
            zipContainer ? { errorsContainer: zipContainer } : {},
        );

        // Phone
        const phoneContainer = getErrorContainer("familyHomePhone");
        validator.addField(
            "#familyHomePhone",
            [
                {
                    rule: "required",
                    errorMessage: i18next.t("Home phone is required"),
                },
                {
                    rule: "minLength",
                    value: 7,
                    errorMessage: i18next.t("Please enter a valid phone number"),
                },
            ],
            phoneContainer ? { errorsContainer: phoneContainer } : {},
        );

        validators["step-family-info"] = validator;
    }

    function initializeMemberValidator() {
        if (validators["step-members"]) {
            validators["step-members"].destroy();
        }

        const validator = createValidator("#registration-form");

        // Add validation for each currently active member card using actual DOM elements
        document.querySelectorAll(".member-card").forEach((card) => {
            const i = parseInt(card.getAttribute("data-member-index"));

            // First Name
            const firstNameContainer = getErrorContainer(`member-first-name-${i}`);
            validator.addField(
                `#member-first-name-${i}`,
                [
                    {
                        rule: "required",
                        errorMessage: i18next.t("First name is required"),
                    },
                    {
                        rule: "minLength",
                        value: 2,
                        errorMessage: i18next.t("First name must be at least 2 characters"),
                    },
                ],
                firstNameContainer ? { errorsContainer: firstNameContainer } : {},
            );

            // Last Name
            const lastNameContainer = getErrorContainer(`member-last-name-${i}`);
            validator.addField(
                `#member-last-name-${i}`,
                [
                    {
                        rule: "required",
                        errorMessage: i18next.t("Last name is required"),
                    },
                    {
                        rule: "minLength",
                        value: 2,
                        errorMessage: i18next.t("Last name must be at least 2 characters"),
                    },
                ],
                lastNameContainer ? { errorsContainer: lastNameContainer } : {},
            );

            // Email (optional but must be valid if provided)
            const emailContainer = getErrorContainer(`member-email-${i}`);
            validator.addField(
                `#member-email-${i}`,
                [
                    {
                        rule: "email",
                        errorMessage: i18next.t("Please enter a valid email address (e.g., name@example.com)"),
                    },
                ],
                emailContainer ? { errorsContainer: emailContainer } : {},
            );
        });

        validators["step-members"] = validator;
    }

    /**
     * Displays a review summary of the family registration data
     * Uses buildFamilyObject() to collect current form data and populates
     * the review step display fields
     */
    function displayReviewSummary() {
        const family = buildFamilyObject();

        // Display family info
        $("#displayFamilyName").text(family.Name);

        const familyAddress = `${family.Address1}, ${family.City}, ${family.State} ${family.Zip} ${family.Country}`;
        $("#displayFamilyAddress").text(familyAddress);
        $("#displayFamilyPhone").text(family.HomePhone);

        // Clear all member rows first (template shows rows 1-8)
        for (let i = 1; i <= 8; i++) {
            $(`#displayFamilyPerson${i}`).hide();
            $(`#displayFamilyPersonFName${i}`).text("");
            $(`#displayFamilyPersonLName${i}`).text("");
            $(`#displayFamilyPersonEmail${i}`).text("");
            $(`#displayFamilyPersonPhone${i}`).text("");
            $(`#displayFamilyPersonBDay${i}`).text("");
        }

        // Display member details
        family.people.forEach((person, index) => {
            const memberNum = index + 1;
            $(`#displayFamilyPerson${memberNum}`).show();
            $(`#displayFamilyPersonFName${memberNum}`).text(person.firstName);
            $(`#displayFamilyPersonLName${memberNum}`).text(person.lastName);
            $(`#displayFamilyPersonEmail${memberNum}`).text(person.email);

            const displayPhone = person.cellPhone || person.workPhone || person.homePhone || "";
            $(`#displayFamilyPersonPhone${memberNum}`).text(displayPhone);
            $(`#displayFamilyPersonBDay${memberNum}`).text(person.birthday);
        });
    }

    function submitRegistration() {
        const familyData = buildFamilyObject();

        $.ajax({
            url: `${rootPath}/api/public/register/family`,
            type: "POST",
            dataType: "json",
            contentType: "application/json",
            data: JSON.stringify(familyData),
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
                                window.location.href = `${rootPath}/external/register/`;
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

                let errorMessage = i18next.t("Sorry, we are unable to process your request at this point in time.");

                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                    errorMessage = "<ul class='mb-0'>";
                    for (const [field, error] of Object.entries(xhr.responseJSON.errors)) {
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

        // Initialize with 1 member card
        addMember();

        // Initialize validators
        initializeFamilyInfoValidator();
        initializeMemberValidator();

        // Add member button event listener
        document.getElementById("add-member-btn").addEventListener("click", function (e) {
            e.preventDefault();
            addMember();
        });

        // Validation on step change - Enhanced event-based validation
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
                // Continue with navigation - prepare content for new step
                if (nextStep === 2) {
                    setTimeout(function () {
                        displayReviewSummary();
                    }, 50);
                }
                return;
            }

            // Only validate when moving forward
            if (nextStep > currentStep) {
                // Prevent navigation - validation should happen in button handlers
                event.preventDefault();
                return;
            }

            // Handle backward navigation (always allowed)
            if (nextStep < currentStep) {
                return;
            }
        });

        // Handle form submission
        document.getElementById("submit-registration").addEventListener("click", function () {
            submitRegistration();
        });

        // Navigation button event listeners (CSP compliant - no inline onclick)
        document.getElementById("family-info-next").addEventListener("click", function (e) {
            e.preventDefault();
            if (validators["step-family-info"]) {
                validators["step-family-info"].revalidate().then(function (isValid) {
                    if (isValid) {
                        state.validatedNavigation = { from: 0, to: 1 };
                        registrationStepper.next();
                    } else {
                        $.notify(
                            {
                                icon: "fa fa-exclamation-triangle",
                                message: i18next.t("Please fill in all required fields correctly."),
                            },
                            {
                                type: "warning",
                                delay: 4000,
                                placement: {
                                    from: "top",
                                    align: "right",
                                },
                            },
                        );
                    }
                });
            } else {
                registrationStepper.next();
            }
        });

        document.getElementById("members-previous").addEventListener("click", function () {
            registrationStepper.previous();
        });

        document.getElementById("members-next").addEventListener("click", function (e) {
            e.preventDefault();

            // Validate at least 1 member exists
            if (state.currentMemberCount === 0) {
                $.notify(
                    {
                        icon: "fa fa-exclamation-triangle",
                        message: i18next.t("Please add at least one family member."),
                    },
                    {
                        type: "warning",
                        delay: 4000,
                        placement: {
                            from: "top",
                            align: "right",
                        },
                    },
                );
                return;
            }

            if (validators["step-members"]) {
                validators["step-members"].revalidate().then(function (isValid) {
                    if (isValid) {
                        state.validatedNavigation = { from: 1, to: 2 };
                        registrationStepper.next();
                    } else {
                        $.notify(
                            {
                                icon: "fa fa-exclamation-triangle",
                                message: i18next.t("Please fill in all required fields correctly."),
                            },
                            {
                                type: "warning",
                                delay: 4000,
                                placement: {
                                    from: "top",
                                    align: "right",
                                },
                            },
                        );
                    }
                });
            } else {
                registrationStepper.next();
            }
        });

        document.getElementById("review-previous").addEventListener("click", function () {
            registrationStepper.previous();
        });

        // Update member last names when family name changes
        $("#familyName").on("change", function () {
            const familyName = $(this).val();
            // Pre-fill last name for all existing member cards if not set
            $(`.member-card`).each(function () {
                const $lastNameField = $(this).find(".member-last-name");
                if (!$lastNameField.val()) {
                    $lastNameField.val(familyName);
                }
            });
        });

        // Load countries
        $.ajax({
            type: "GET",
            url: rootPath + "/api/public/data/countries",
        }).done(function (data) {
            const familyCountry = $("#familyCountry");
            $.each(data, function (idx, country) {
                const selected = familyCountry.data("system-default") === country.name;
                familyCountry.append(new Option(country.name, country.code, selected, selected));
            });
            familyCountry.change();
        });

        // Initialize select2 and country/state handling
        $("#familyCountry").select2();

        $("#familyCountry").change(function () {
            const $container = $("#familyStateContainer");
            const defaultState = $container.find("#familyState").data("default") || "";

            $.ajax({
                type: "GET",
                url: rootPath + "/api/public/data/countries/" + this.value.toLowerCase() + "/states",
            }).done(function (data) {
                if (Object.keys(data).length > 0) {
                    // Country has states - replace with dropdown
                    const $select = $('<select id="familyState" name="familyState" class="form-control"></select>');
                    $.each(data, function (code, name) {
                        const $option = $("<option></option>").val(code).text(name);
                        if (defaultState == code) {
                            $option.prop("selected", true);
                        }
                        $select.append($option);
                    });

                    $container.html($select);
                    $("#familyState").select2();
                } else {
                    // Country has no states - replace with text input
                    const stateValue = defaultState || "";
                    const input = $("<input>")
                        .attr("id", "familyState")
                        .attr("name", "familyState")
                        .addClass("form-control")
                        .attr("placeholder", i18next.t("State"))
                        .val(stateValue)
                        .attr("data-default", stateValue);
                    $container.html(input);
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
