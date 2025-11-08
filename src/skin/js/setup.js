(function() {
    'use strict';
    
    // Get root path from global CRM config
    const rootPath = window.CRM && window.CRM.root ? window.CRM.root : '';
    
    // Setup state
    const state = {
        prerequisites: {},
        prerequisitesStatus: false
    };

    let setupStepper;

    function skipCheck() {
        $("#prerequisites-war").hide();
        state.prerequisitesStatus = true;
    }

    function renderPrerequisite(prerequisite) {
        const statusConfig = {
            true: { class: "text-primary", html: "&check;" },
            pending: { class: "text-warning", html: '<i class="fa-solid fa-spinner fa-spin"></i>' },
            false: { class: "text-danger", html: "&#x2717;" }
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
            $("#prerequisites").append($prerequisiteRow);
        }
    }

    function checkIntegrity() {
        renderPrerequisite({
            Name: "ChurchCRM File Integrity Check",
            WikiLink: "",
            Satisfied: "pending",
        });

        $.ajax({
            url: rootPath + "/setup/SystemIntegrityCheck",
            method: "GET",
        })
        .done(function (data) {
            renderPrerequisite({
                Name: "ChurchCRM File Integrity Check",
                WikiLink: "",
                Satisfied: data === "success",
            });
            
            if (data === "success") {
                $("#prerequisites-war").hide();
                state.prerequisitesStatus = true;
            }
        })
        .fail(function () {
            renderPrerequisite({
                Name: "ChurchCRM File Integrity Check",
                WikiLink: "",
                Satisfied: false,
            });
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
        });
    }

    function submitSetupData() {
        const form = document.getElementById('setup-form');
        const formData = {};
        
        // Use native FormData API
        const data = new FormData(form);
        for (const [key, value] of data.entries()) {
            formData[key] = value || "";
        }

        $.ajax({
            url: rootPath + "/setup/",
            method: "POST",
            data: JSON.stringify(formData),
            contentType: "application/json",
        })
        .done(function () {
            location.replace(rootPath + "/");
        })
        .fail(function (xhr) {
            console.error("Setup failed:", xhr);
            $.notify("Setup failed. Please check the console for details.", {
                type: 'danger',
                delay: 5000
            });
        });
    }

    // Expose functions globally
    window.skipCheck = skipCheck;
    window.setupStepper = null;

    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('setup-form');
        const stepperElement = document.getElementById('setup-stepper');
        
        setupStepper = new Stepper(stepperElement, {
            linear: true,
            animation: true,
            selectors: {
                steps: '.step',
                trigger: '.step-trigger',
                stepper: '.bs-stepper'
            }
        });

        // Store globally for onclick handlers
        window.setupStepper = setupStepper;

        // BS-stepper native validation on show event
        stepperElement.addEventListener('show.bs-stepper', function (event) {
            // Prevent navigation if form is invalid
            event.preventDefault();
            
            const currentStep = event.detail.from;
            const nextStep = event.detail.to;
            
            // Check prerequisites on step 1
            if (currentStep === 0) {
                if (!state.prerequisitesStatus) {
                    $.notify("Please ensure all prerequisites are met before continuing.", {
                        type: 'warning',
                        delay: 3000
                    });
                    return;
                }
            }
            
            // Validate form inputs for steps 3 and 4 (indices 2 and 3)
            if (currentStep >= 2) {
                const currentContent = stepperElement.querySelector(`#step-${['prerequisites', 'serverinfo', 'location', 'database'][currentStep]}`);
                const inputs = currentContent.querySelectorAll('input[required], input[pattern]');
                
                let isValid = true;
                inputs.forEach(input => {
                    if (!input.checkValidity()) {
                        isValid = false;
                        input.classList.add('is-invalid');
                        
                        // Show validation message
                        const helpBlock = input.nextElementSibling;
                        if (helpBlock && helpBlock.classList.contains('help-block')) {
                            helpBlock.textContent = input.validationMessage;
                            helpBlock.style.display = 'block';
                        }
                    } else {
                        input.classList.remove('is-invalid');
                        const helpBlock = input.nextElementSibling;
                        if (helpBlock && helpBlock.classList.contains('help-block')) {
                            helpBlock.textContent = '';
                            helpBlock.style.display = 'none';
                        }
                    }
                });
                
                if (!isValid) {
                    return;
                }
            }
            
            // Allow navigation
            setupStepper.to(nextStep);
        });

        // Handle finish button
        document.getElementById('submit-setup').addEventListener('click', function() {
            // Validate final step
            const databaseStep = document.getElementById('step-database');
            const inputs = databaseStep.querySelectorAll('input[required], input[pattern]');
            
            let isValid = true;
            inputs.forEach(input => {
                if (!input.checkValidity()) {
                    isValid = false;
                    input.classList.add('is-invalid');
                }
            });
            
            if (isValid) {
                submitSetupData();
            } else {
                $.notify("Please fill in all required fields correctly.", {
                    type: 'danger',
                    delay: 3000
                });
            }
        });

        // Clear validation on input change
        form.addEventListener('input', function(e) {
            if (e.target.classList.contains('is-invalid')) {
                e.target.classList.remove('is-invalid');
                const helpBlock = e.target.nextElementSibling;
                if (helpBlock && helpBlock.classList.contains('help-block')) {
                    helpBlock.textContent = '';
                    helpBlock.style.display = 'none';
                }
            }
        });

        // Initialize prerequisite checks
        checkIntegrity();
        checkPrerequisites();
    });
})();
