require('./setup.css');

const $ = require('jquery');
window.$ = $;
window.jQuery = $;

const bootstrap = require('bootstrap');
window.bootstrap = bootstrap;

const i18next = require('i18next');
window.i18next = i18next;

const JustValidate = require('just-validate');
window.JustValidate = JustValidate;

const moment = require('moment');
window.moment = moment;

const Stepper = require('bs-stepper');
window.Stepper = Stepper;

(function () {
	"use strict";

	// Get root path from global CRM config and ensure it's properly formatted
	let rootPath = window.CRM && window.CRM.root ? window.CRM.root : "";

	console.log(
		"Setup.js - Original rootPath from window.CRM.root:",
		window.CRM && window.CRM.root,
	);

	// Ensure rootPath doesn't end with slash (we'll add it in URLs)
	rootPath = rootPath.replace(/\/$/, "");

	// Ensure rootPath starts with slash for absolute URLs (unless it's empty for root install)
	if (rootPath && !rootPath.startsWith("/")) {
		rootPath = "/" + rootPath;
	}

	console.log("Setup.js - Processed rootPath:", rootPath);
	console.log(
		"Setup.js - Will use URL:",
		rootPath + "/setup/SystemPrerequisiteCheck",
	);

	// Setup state
	const state = {
		prerequisites: {},
		prerequisitesStatus: false,
		checksComplete: false, // Track if all checks are done
		validatedNavigation: null, // Track validated navigation to prevent loops
	};

	const GROUP_CONFIG = {
		php: {
			table: "#php-extensions",
			status: "#php-extensions-status",
			collapse: "#php-extensions-collapse",
		},
		filesystem: {
			table: "#filesystem-checks",
			status: "#filesystem-status",
			collapse: "#filesystem-collapse",
		},
		integrity: {
			table: "#integrity-checks",
			status: "#integrity-status",
			collapse: "#integrity-collapse",
		},
		orphaned: {
			table: "#orphaned-checks",
			status: "#orphaned-status",
			collapse: "#orphaned-collapse",
			section: "#orphaned-files-section",
		},
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

	function renderPrerequisite(prerequisite, group = "php") {
		const config = GROUP_CONFIG[group];
		if (!config) {
			return;
		}

		const statusConfig = {
			true: { class: "text-success", html: "&check;" },
			pending: {
				class: "text-warning",
				html: '<i class="fa-solid fa-spinner fa-spin"></i>',
			},
			false: { class: "text-danger", html: "&#x2717;" },
		};

		let normalizedStatus = "pending";
		let storedValue = prerequisite.Satisfied;
		if (
			prerequisite.Satisfied === true ||
			prerequisite.Satisfied === 1 ||
			prerequisite.Satisfied === "1"
		) {
			normalizedStatus = true;
			storedValue = true;
		} else if (
			prerequisite.Satisfied === false ||
			prerequisite.Satisfied === 0 ||
			prerequisite.Satisfied === "0"
		) {
			normalizedStatus = false;
			storedValue = false;
		}

		const td =
			normalizedStatus === true
				? statusConfig.true
				: normalizedStatus === false
				? statusConfig.false
				: statusConfig.pending;

		const sanitizedName = prerequisite.Name
			? prerequisite.Name.replace(/[^A-Za-z0-9]/g, "")
			: "Prerequisite";
		const rowId = `${group}-${sanitizedName}`;

		state.prerequisites[rowId] = storedValue;

		const $prerequisiteRow = $("<tr>", { id: rowId })
			.append($("<td>", { text: prerequisite.Name }))
			.append($("<td>", td));

		const $existing = $("#" + rowId);
		if ($existing.length) {
			$existing.replaceWith($prerequisiteRow);
		} else {
			$(config.table).append($prerequisiteRow);
		}

		// Update group status after rendering
		updateGroupStatus(group);
	}

	function toggleCollapse(group, action) {
		const groupConfig = GROUP_CONFIG[group];
		if (!groupConfig || !groupConfig.collapse) {
			return;
		}
		const $target = $(groupConfig.collapse);
		if ($target.length && typeof $target.collapse === "function") {
			$target.collapse(action);
		}
	}

	function buildStatusCell(statusInfo, message) {
		const cell = $("<td>").addClass(statusInfo.class);
		cell.html(statusInfo.html);
		if (message) {
			cell.append(
				$("<div>")
					.addClass("mt-2 text-muted small")
					.text(message),
			);
		}

		return cell;
	}

       function appendIntegrityDetails(tableSelector, baseRowId, payload) {
              const detailRowId = `${baseRowId}-details`;
              $(`#${detailRowId}`).remove();

              if (!payload || !Array.isArray(payload.files) || payload.files.length === 0) {
                      return;
              }

              const items = payload.files
                      .filter((file) => typeof file === "string" && file.trim().length > 0)
                      .map((file) => file.trim());

              if (items.length === 0) {
                      return;
              }

              const detailRow = $("<tr>", { id: detailRowId });
              const detailCell = $("<td>", { colspan: 2 });
              const heading = $("<div>")
                      .addClass("font-weight-bold mb-2")
                      .text(i18next.t("Files with issues"));
              const list = $("<ul>").addClass("mb-0 pl-3");

              items.forEach(function (fileName) {
                      list.append($("<li>").text(fileName));
              });

              detailCell.append(heading).append(list);
              detailRow.append(detailCell);
              $(tableSelector).append(detailRow);
       }

       function renderOrphanedFiles(payload) {
              const config = GROUP_CONFIG.orphaned;
              if (!config) {
                      return;
              }

              // Check for orphaned files
              const hasOrphanedFiles = Array.isArray(payload.orphanedFiles) && payload.orphanedFiles.length > 0;

              if (!hasOrphanedFiles) {
                      $(config.section).hide();
                      return;
              }

              // Show the orphaned files section
              $(config.section).show();

              const orphanedItems = payload.orphanedFiles
                      .filter((file) => typeof file === "string" && file.trim().length > 0)
                      .map((file) => file.trim());

              if (orphanedItems.length === 0) {
                      $(config.section).hide();
                      return;
              }

              // Update status badge
              $(config.status).html(
                      `<span class="badge badge-danger">${orphanedItems.length}</span>`
              );

              // Render orphaned files list
              const $table = $(config.table);
              $table.empty();

              orphanedItems.forEach(function (fileName) {
                      const $row = $("<tr>")
                              .append($("<td>").addClass("text-danger").text(fileName));
                      $table.append($row);
              });

              // Auto-expand the collapse
              $(config.collapse).collapse("show");
       }

	function updateGroupStatus(group) {
		const config = GROUP_CONFIG[group];
		if (!config) {
			return;
		}

		const $rows = $(`${config.table} tr`);
		const $status = $(config.status);
		if ($rows.length === 0) {
			$status.html('<i class="fa-solid fa-spinner fa-spin text-muted"></i>');
			return;
		}

		let allPassed = true;
		let anyPending = false;

		$rows.each(function () {
			const statusCell = $(this).find("td:last");
			if (statusCell.hasClass("text-danger")) {
				allPassed = false;
			} else if (statusCell.find(".fa-spinner").length > 0) {
				anyPending = true;
			}
		});

		if (anyPending) {
			$status.html('<i class="fa-solid fa-spinner fa-spin text-muted"></i>');
			toggleCollapse(group, "show");
		} else if (allPassed) {
			$status.html('<i class="fa-solid fa-check-circle text-success"></i>');
			toggleCollapse(group, "hide");
		} else {
			$status.html('<i class="fa-solid fa-exclamation-circle text-danger"></i>');
			toggleCollapse(group, "show");
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

		const groupKey = "integrity";
		const config = GROUP_CONFIG[groupKey];
		const rowId = `${groupKey}-ChurchCRMFileIntegrityCheck`;

		// Show pending state
		const pendingRow = $("<tr>", { id: rowId })
			.append($("<td>", { text: "ChurchCRM File Integrity Check" }))
			.append($("<td>", statusConfig.pending));
		$(`#${rowId}`).remove();
		$(config.table).append(pendingRow);
		state.prerequisites[rowId] = "pending";
		updateGroupStatus(groupKey);

		$.ajax({
			url: "./SystemIntegrityCheck",
			method: "GET",
		})
			.done(function (data) {
				const status =
					data && typeof data === "object" && data.status
						? String(data.status).toLowerCase()
						: "";
				const satisfied = status === "success";
				const statusInfo = satisfied
					? statusConfig.true
					: statusConfig.false;
				const message =
					data && typeof data === "object" && data.message
						? data.message
						: satisfied
						? i18next.t("Integrity check passed")
						: i18next.t("Integrity check failed");

				const resultRow = $("<tr>", {
					id: rowId,
				})
					.append(
						$("<td>", { text: "ChurchCRM File Integrity Check" }),
					)
					.append(buildStatusCell(statusInfo, message));

				$("#" + rowId).replaceWith(resultRow);
				state.prerequisites[rowId] = satisfied;

				appendIntegrityDetails(config.table, rowId, data);

				// Render orphaned files in separate section
				renderOrphanedFiles(data);

				// Mark checks as complete
				state.checksComplete = true;
				updatePrerequisitesUI();
				updateGroupStatus(groupKey);
			})
			.fail(function () {
				const resultRow = $("<tr>", {
					id: rowId,
				})
					.append(
						$("<td>", { text: "ChurchCRM File Integrity Check" }),
					)
					.append(
						buildStatusCell(
							statusConfig.false,
							i18next.t(
								"Unable to contact integrity endpoint. Check web server error logs.",
							),
						),
					);

				$("#" + rowId).replaceWith(resultRow);
				state.prerequisites[rowId] = false;
				appendIntegrityDetails(config.table, rowId, null);

				// Mark checks as complete even on failure
				state.checksComplete = true;
				updatePrerequisitesUI();
				updateGroupStatus(groupKey);
			});
	}

	function checkPrerequisites() {
		state.prerequisites = {};
		state.prerequisitesStatus = false;
		state.checksComplete = false;

		Object.keys(GROUP_CONFIG).forEach(function (key) {
			const config = GROUP_CONFIG[key];
			$(config.table).empty();
			$(config.status).html(
				'<i class="fa-solid fa-spinner fa-spin text-muted"></i>',
			);
		});

		$.ajax({
			url: "./SystemPrerequisiteCheck",
			method: "GET",
			contentType: "application/json",
		})
			.done(function (data) {
				$.each(data, function (index, prerequisite) {
					renderPrerequisite(prerequisite, "php");
				});
				checkFilesystem();
			})
			.fail(function () {
				renderPrerequisite(
					{
						Name: "Unable to load PHP prerequisite checks",
						Satisfied: false,
					},
					"php",
				);
				checkFilesystem();
			});
	}

	function checkFilesystem() {
		$.ajax({
			url: "./SystemFilesystemCheck",
			method: "GET",
			contentType: "application/json",
		})
			.done(function (data) {
				$.each(data, function (index, prerequisite) {
					renderPrerequisite(prerequisite, "filesystem");
				});
				checkIntegrity();
			})
			.fail(function () {
				renderPrerequisite(
					{
						Name: "Unable to verify filesystem permissions",
						Satisfied: false,
					},
					"filesystem",
				);
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
