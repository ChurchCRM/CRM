require("./setup.css");

const $ = require("jquery");
window.$ = $;
window.jQuery = $;

const bootstrap = require("bootstrap");
window.bootstrap = bootstrap;

// Helper: get or create a Bootstrap 5 Modal instance
function getModal(id) {
  return bootstrap.Modal.getOrCreateInstance(document.getElementById(id));
}

const i18next = require("i18next");
window.i18next = i18next;

const JustValidate = require("just-validate");
window.JustValidate = JustValidate;

const moment = require("moment");
window.moment = moment;

const Stepper = require("bs-stepper");
window.Stepper = Stepper;

(() => {
  // Get root path from global CRM config and ensure it's properly formatted
  let rootPath = window.CRM?.root ? window.CRM.root : "";

  console.log("Setup.js - Original rootPath from window.CRM.root:", window.CRM?.root);

  // Ensure rootPath doesn't end with slash (we'll add it in URLs)
  rootPath = rootPath.replace(/\/$/, "");

  // Ensure rootPath starts with slash for absolute URLs (unless it's empty for root install)
  if (rootPath && !rootPath.startsWith("/")) {
    rootPath = `/${rootPath}`;
  }

  console.log("Setup.js - Processed rootPath:", rootPath);
  console.log("Setup.js - Will use URL:", `${rootPath}/setup/SystemPrerequisiteCheck`);

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
  const validators = {};

  function skipCheck() {
    $("#prerequisites-help").addClass("d-none");
    $("#prerequisites-next-btn").prop("disabled", false);
    $("#prerequisites-force-btn").addClass("d-none");
    $("#prerequisites-force-help").addClass("d-none");
    state.prerequisitesStatus = true;

    updateStatusBanner(
      "warning",
      "Continuing with items still to fix",
      "ChurchCRM will install. Ask your web host to fix the items above when you can.",
    );

    // Automatically advance to next step
    if (setupStepper) {
      setupStepper.next();
    }
  }

  function updateStatusBanner(status, title, detail) {
    const $banner = $("#status-banner");
    $banner.removeClass("status-checking status-success status-warning status-error");
    $banner.addClass(`status-${status}`);

    let icon = "";
    switch (status) {
      case "success":
        icon = '<i class="fa-solid fa-circle-check"></i>';
        break;
      case "warning":
        icon = '<i class="fa-solid fa-exclamation-triangle"></i>';
        break;
      case "error":
        icon = '<i class="fa-solid fa-circle-xmark"></i>';
        break;
      default:
        icon = '<i class="fa-solid fa-spinner fa-spin"></i>';
    }

    $banner.find(".status-icon").html(icon);
    $banner.find(".status-text strong").text(title);
    $banner.find(".status-detail").text(detail);
  }

  function updatePrerequisitesUI() {
    // Recalculate prerequisites status based on actual checks
    if (state.checksComplete) {
      let allPassed = true;
      for (const key in state.prerequisites) {
        if (Object.hasOwn(state.prerequisites, key)) {
          if (state.prerequisites[key] !== true) {
            allPassed = false;
            break;
          }
        }
      }
      state.prerequisitesStatus = allPassed;
    }

    if (state.prerequisitesStatus) {
      $("#prerequisites-help").addClass("d-none");
      $("#prerequisites-next-btn").prop("disabled", false);
      $("#prerequisites-force-btn, #prerequisites-force-help").addClass("d-none");
      if (state.checksComplete) {
        updateStatusBanner("success", "System check passed", "Your server is ready. Continue to connect the database.");
      }
    } else if (state.checksComplete) {
      $("#prerequisites-next-btn").prop("disabled", true);
      $("#prerequisites-force-btn, #prerequisites-force-help").removeClass("d-none");
      const help = volunteerHelp();
      const $help = $("#prerequisites-help");
      $help.empty();
      help.forEach((line) => {
        $help.append($("<li>").text(line));
      });
      $help.toggleClass("d-none", help.length === 0);
      updateStatusBanner(
        "warning",
        "ChurchCRM can still be installed",
        "Read the notes below, then continue. Ask your web host to fix what is listed.",
      );
    } else {
      $("#prerequisites-help").addClass("d-none");
      $("#prerequisites-next-btn").prop("disabled", true);
      $("#prerequisites-force-btn, #prerequisites-force-help").addClass("d-none");
    }
  }

  function volunteerHelp() {
    const lines = [];
    let needsPhp = false;
    let needsFolders = false;
    Object.entries(state.prerequisites).forEach(([key, passed]) => {
      if (passed === true || key.startsWith("integrity-")) {
        return;
      }
      if (key.startsWith("filesystem-")) {
        needsFolders = true;
      } else if (key.startsWith("php-")) {
        needsPhp = true;
      }
    });
    if (needsPhp) {
      lines.push("Open PHP Extensions. Each row with an X is something to ask your web host to turn on.");
    }
    if (needsFolders) {
      lines.push(
        "Open File Permissions. Ask your web host to make those folders writable so ChurchCRM can save pictures.",
      );
    }
    const missing = state.missingFiles || [];
    const changed = state.changedFiles || [];
    if (missing.length > 0) {
      lines.push(
        "Some ChurchCRM files were not uploaded. Upload the release again, or continue and fix this with your web host.",
      );
    }
    if (changed.length > 0) {
      lines.push(
        "Some files on the server do not match the ChurchCRM release. Upload the release again if you did not change them on purpose.",
      );
    }
    if (state.integrityMessage && missing.length === 0 && changed.length === 0) {
      lines.push(state.integrityMessage);
    }
    return lines;
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
    if (prerequisite.Satisfied === true || prerequisite.Satisfied === 1 || prerequisite.Satisfied === "1") {
      normalizedStatus = true;
      storedValue = true;
    } else if (prerequisite.Satisfied === false || prerequisite.Satisfied === 0 || prerequisite.Satisfied === "0") {
      normalizedStatus = false;
      storedValue = false;
    }

    const td =
      normalizedStatus === true
        ? statusConfig.true
        : normalizedStatus === false
          ? statusConfig.false
          : statusConfig.pending;

    const sanitizedName = prerequisite.Name ? prerequisite.Name.replace(/[^A-Za-z0-9]/g, "") : "Prerequisite";
    const rowId = `${group}-${sanitizedName}`;

    state.prerequisites[rowId] = storedValue;

    const $prerequisiteRow = $("<tr>", { id: rowId })
      .append($("<td>", { text: prerequisite.Name }))
      .append($("<td>", td));

    const $existing = $(`#${rowId}`);
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
    if (!groupConfig?.collapse) {
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
      cell.append($("<div>").addClass("mt-2 text-muted small").text(message));
    }

    return cell;
  }

  function appendIntegrityDetails(tableSelector, baseRowId, payload) {
    const detailRowId = `${baseRowId}-details`;
    $(`#${detailRowId}`).remove();

    if (!payload || !Array.isArray(payload.files) || payload.files.length === 0) {
      return;
    }

    const missing = fileList(payload.missingFiles);
    const changed = fileList(payload.changedFiles);
    const combined = missing.length + changed.length > 0 ? [] : fileList(payload.files);
    if (missing.length + changed.length + combined.length === 0) {
      return;
    }

    const detailRow = $("<tr>", { id: detailRowId, class: "integrity-details-row" });
    const detailCell = $("<td>", { colspan: 2 });
    const maxFiles = 8;
    const addList = (heading, items) => {
      if (items.length === 0) {
        return;
      }
      detailCell.append($("<div>").addClass("integrity-details-header").text(`${heading} (${items.length})`));
      const list = $("<ul>").addClass("integrity-files-list");
      items.slice(0, maxFiles).forEach((fileName) => {
        list.append($("<li>").text(fileName));
      });
      if (items.length > maxFiles) {
        list.append(
          $("<li>")
            .addClass("integrity-files-more")
            .text(`and ${items.length - maxFiles} more`),
        );
      }
      detailCell.append(list);
    };
    addList("These files were not uploaded", missing);
    addList("These files do not match the release", changed);
    addList("Files to review", combined);
    detailRow.append(detailCell);
    $(tableSelector).append(detailRow);
  }

  function fileList(files) {
    if (!Array.isArray(files)) {
      return [];
    }
    return files.filter((file) => typeof file === "string" && file.trim().length > 0).map((file) => file.trim());
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
    $(config.status).html(`<span class="badge bg-danger-lt text-danger">${orphanedItems.length}</span>`);

    // Render orphaned files list
    const $table = $(config.table);
    $table.empty();

    orphanedItems.forEach((fileName) => {
      const $row = $("<tr>").append($("<td>").addClass("text-danger").text(fileName));
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
      .append($("<td>", { text: "Signature Validation" }))
      .append($("<td>", statusConfig.pending));
    $(`#${rowId}`).remove();
    $(config.table).append(pendingRow);
    state.prerequisites[rowId] = "pending";
    updateGroupStatus(groupKey);

    $.ajax({
      url: `${rootPath}/setup/SystemIntegrityCheck`,
      method: "GET",
    })
      .done((data) => {
        const status = data && typeof data === "object" && data.status ? String(data.status).toLowerCase() : "";
        const satisfied = status === "success";
        const statusInfo = satisfied ? statusConfig.true : statusConfig.false;
        const message =
          data && typeof data === "object" && data.message
            ? data.message
            : satisfied
              ? "Integrity check passed"
              : "Integrity check failed";

        const resultRow = $("<tr>", {
          id: rowId,
        })
          .append($("<td>", { text: "Signature Validation" }))
          .append(buildStatusCell(statusInfo, message));

        $(`#${rowId}`).replaceWith(resultRow);
        state.prerequisites[rowId] = satisfied;
        state.missingFiles = fileList(data?.missingFiles);
        state.changedFiles = fileList(data?.changedFiles);
        state.integrityMessage = satisfied ? "" : message;

        appendIntegrityDetails(config.table, rowId, data);

        // Render orphaned files in separate section
        renderOrphanedFiles(data);

        // Mark checks as complete
        state.checksComplete = true;
        updatePrerequisitesUI();
        updateGroupStatus(groupKey);
      })
      .fail(() => {
        const resultRow = $("<tr>", {
          id: rowId,
        })
          .append($("<td>", { text: "Signature Validation" }))
          .append(
            buildStatusCell(statusConfig.false, "Unable to contact integrity endpoint. Check web server error logs."),
          );

        $(`#${rowId}`).replaceWith(resultRow);
        state.prerequisites[rowId] = false;
        state.missingFiles = [];
        state.changedFiles = [];
        state.integrityMessage =
          "ChurchCRM could not check its files. You can continue, then ask your web host if pages fail to open.";
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

    Object.keys(GROUP_CONFIG).forEach((key) => {
      const config = GROUP_CONFIG[key];
      $(config.table).empty();
      $(config.status).html('<i class="fa-solid fa-spinner fa-spin text-muted"></i>');
    });

    $.ajax({
      url: `${rootPath}/setup/SystemPrerequisiteCheck`,
      method: "GET",
      contentType: "application/json",
    })
      .done((data) => {
        $.each(data, (_index, prerequisite) => {
          renderPrerequisite(prerequisite, "php");
        });
        checkFilesystem();
      })
      .fail(() => {
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
      url: `${rootPath}/setup/SystemFilesystemCheck`,
      method: "GET",
      contentType: "application/json",
    })
      .done((data) => {
        $.each(data, (_index, prerequisite) => {
          renderPrerequisite(prerequisite, "filesystem");
        });
        checkLocales();
      })
      .fail(() => {
        renderPrerequisite(
          {
            Name: "Unable to verify filesystem permissions",
            Satisfied: false,
          },
          "filesystem",
        );
        checkLocales();
      });
  }

  function checkLocales() {
    $.ajax({
      url: `${rootPath}/setup/SystemLocaleCheck`,
      method: "GET",
      contentType: "application/json",
    })
      .done((data) => {
        renderLocaleInfo(data);
        checkIntegrity();
      })
      .fail(() => {
        renderLocaleInfoError();
        checkIntegrity();
      });
  }

  function renderLocaleInfo(localeData) {
    const $table = $("#locale-support-table");
    const $summary = $("#locale-support-summary");
    const $status = $("#locale-support-status");

    $table.empty();

    // Display summary
    const summary = localeData.systemLocaleSupportSummary || "System locale support unknown";
    const summaryClass =
      localeData.systemLocaleDetected && localeData.supportedLocales ? "alert-success" : "alert-warning";

    $summary.attr("class", `alert ${summaryClass} mb-2`);
    $summary.html(`<i class="fa-solid fa-check-circle"></i> ${summary}`);

    // Update status icon
    $status.html(
      localeData.systemLocaleDetected
        ? '<i class="fa-solid fa-check text-success"></i>'
        : '<i class="fa-solid fa-exclamation-triangle text-warning"></i>',
    );

    // Render locale table
    if (localeData.supportedLocales && localeData.supportedLocales.length > 0) {
      const locales = localeData.supportedLocales;

      // Create table header
      const $thead = $("<thead>").append(
        $("<tr>")
          .append($("<th>").text("ChurchCRM Supported Locales"))
          .append($("<th style='text-align: center; width: 100px;'>").text("System Support")),
      );
      $table.append($thead);

      // Create table body
      const $tbody = $("<tbody>");
      locales.forEach((locale) => {
        const statusBadge = locale.systemAvailable
          ? `<span class="badge bg-success text-white"><i class="fa-solid fa-check me-1"></i>Available</span>`
          : `<span class="badge bg-secondary text-white"><i class="fa-solid fa-xmark me-1"></i>Not Available</span>`;

        const $row = $("<tr>")
          .append(
            $("<td>").html(
              `<div style="font-weight: 500;">${locale.name}</div>` +
                `<small class="text-muted" style="display: block; font-size: 0.85em; margin-top: 2px;">${locale.locale}</small>`,
            ),
          )
          .append($("<td style='text-align: center; vertical-align: middle;'>").html(statusBadge));

        $tbody.append($row);
      });
      $table.append($tbody);
    } else {
      const $row = $("<tr>").append(
        $("<td colspan='2'>").html(
          `<i class='fa-solid fa-exclamation-triangle text-warning me-2'></i>Unable to determine available locales`,
        ),
      );
      $table.append($row);
    }
  }

  function renderLocaleInfoError() {
    const $table = $("#locale-support-table");
    const $summary = $("#locale-support-summary");
    const $status = $("#locale-support-status");

    $table.empty();
    $summary.attr("class", "alert alert-danger mb-2");
    $summary.html(`<i class='fa-solid fa-circle-xmark'></i> Unable to detect system locales`);
    $status.html('<i class="fa-solid fa-xmark text-danger"></i>');

    const $errorRow = $("<tr>").append(
      $("<td>").html(
        `<i class='fa-solid fa-exclamation-circle text-danger me-2'></i>Unable to load locale information`,
      ),
    );
    $table.append($errorRow);
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
    stepElement.querySelectorAll("input, select, textarea").forEach((field) => {
      if (!field.id || !field.name) return;

      const errorContainer = field.parentElement.querySelector(".invalid-feedback");
      const rules = [];

      // Add required rule
      if (field.hasAttribute("required")) {
        let errorMessage;
        switch (field.name) {
          case "DB_SERVER_NAME":
            errorMessage = "Database server hostname or IP address is required (e.g., localhost or 127.0.0.1)";
            break;
          case "DB_SERVER_PORT":
            errorMessage = "Database server port is required (e.g., 3306)";
            break;
          case "DB_NAME":
            errorMessage = "Database name is required";
            break;
          case "DB_USER":
            errorMessage = "Database username is required";
            break;
          case "URL":
            errorMessage = "Base URL is required";
            break;
          default:
            errorMessage = "This field is required";
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
            "Must start with / if not empty; no trailing slash. Only letters, numbers, underscore, dash, dot and / allowed.",
          );
        } else if (field.name === "DB_SERVER_PORT") {
          errorMessage = i18next.t("Must be a valid port number (e.g., 3306)");
        } else {
          errorMessage = field.getAttribute("title") || i18next.t("Invalid format");
        }

        rules.push({
          rule: "customRegexp",
          value: new RegExp(field.getAttribute("pattern")),
          errorMessage: errorMessage,
        });
      }

      // Add URL validation for Base URL field
      if (field.name === "URL") {
        rules.push({
          validator: (value) => {
            try {
              const url = new URL(value);
              return url.protocol === "http:" || url.protocol === "https:";
            } catch (_e) {
              return false;
            }
          },
          errorMessage: i18next.t("Must be a valid URL starting with http:// or https://"),
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

  const DB_CONNECTION_FIELDS = ["DB_SERVER_NAME", "DB_SERVER_PORT", "DB_NAME", "DB_USER", "DB_PASSWORD"];
  let connectionTestToken = 0;
  let connectionTestTimer = 0;
  let passedConnectionFingerprint = null;
  let connectionSupported = true;

  function connectionFingerprint() {
    return JSON.stringify(DB_CONNECTION_FIELDS.map((id) => document.getElementById(id).value));
  }

  function connectionFieldsComplete() {
    return DB_CONNECTION_FIELDS.every((id) => document.getElementById(id).value !== "");
  }

  // Install needs a passed test for the values on the form, and an acknowledgment when the server is unsupported.
  function updateInstallButton() {
    const passed = passedConnectionFingerprint !== null && passedConnectionFingerprint === connectionFingerprint();
    const acknowledged = connectionSupported || document.getElementById("db-version-ack").checked;
    document.getElementById("submit-setup").disabled = !(passed && acknowledged);
  }

  function showVersionWarning(message) {
    document.getElementById("db-version-message").textContent = message;
    document.getElementById("db-version-warning").classList.remove("d-none");
  }

  function hideVersionWarning() {
    document.getElementById("db-version-warning").classList.add("d-none");
    document.getElementById("db-version-ack").checked = false;
  }

  function closeSetupModal() {
    bootstrap.Modal.getInstance(document.getElementById("setupModal"))?.hide();
    setTimeout(() => {
      $("#setup-progress").removeClass("d-none");
      $("#setup-success").addClass("d-none");
      $("#setup-error").addClass("d-none");
      $("#setup-footer").addClass("d-none");
    }, 500);
  }

  function showConnectionResult(kind, message) {
    const box = document.getElementById("db-connection-result");
    box.classList.remove("d-none", "alert-success", "alert-danger", "alert-info");
    box.classList.add(kind === "ok" ? "alert-success" : kind === "pending" ? "alert-info" : "alert-danger");
    box.textContent = message;
  }

  function hideConnectionResult() {
    const box = document.getElementById("db-connection-result");
    box.classList.add("d-none");
    box.textContent = "";
  }

  function voidConnectionPass() {
    passedConnectionFingerprint = null;
    connectionSupported = true;
    connectionTestToken += 1;
    hideVersionWarning();
    updateInstallButton();
  }

  function scheduleConnectionTest() {
    voidConnectionPass();
    window.clearTimeout(connectionTestTimer);
    if (!connectionFieldsComplete()) {
      hideConnectionResult();
      return;
    }
    const token = connectionTestToken;
    const fingerprint = connectionFingerprint();
    showConnectionResult("pending", i18next.t("Checking the database connection..."));
    connectionTestTimer = window.setTimeout(() => testDatabaseConnection(token, fingerprint), 400);
  }

  function testDatabaseConnection(token, fingerprint) {
    const formData = {};
    const data = new FormData(document.getElementById("setup-form"));
    for (const [key, value] of data.entries()) {
      formData[key] = value || "";
    }
    formData.DB_CONNECTION_TEST = true;

    $.ajax({
      url: `${rootPath}/setup/`,
      method: "POST",
      data: JSON.stringify(formData),
      contentType: "application/json",
    })
      .done((response) => {
        if (token !== connectionTestToken || fingerprint !== connectionFingerprint()) {
          return;
        }
        if (response?.tested === true && response?.correct === true) {
          passedConnectionFingerprint = fingerprint;
          connectionSupported = response.supported !== false;
          showConnectionResult("ok", response.message);
          if (!connectionSupported) {
            showVersionWarning(response.versionMessage);
          }
          updateInstallButton();
          return;
        }
        document.getElementById("submit-setup").disabled = true;
        showConnectionResult(
          "fail",
          response?.message || i18next.t("ChurchCRM could not connect to the database with these settings."),
        );
      })
      .fail(() => {
        if (token !== connectionTestToken) {
          return;
        }
        document.getElementById("submit-setup").disabled = true;
        showConnectionResult("fail", i18next.t("ChurchCRM could not connect to the database with these settings."));
      });
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
    getModal("setupModal").show();

    $.ajax({
      url: `${rootPath}/setup/`,
      method: "POST",
      data: JSON.stringify(formData),
      contentType: "application/json",
    })
      .done((response) => {
        // A connection probe answers on this URL too. It must not look like a finished install.
        if (response?.tested === true) {
          return;
        }

        if (response?.correct === false || response?.supported === false) {
          $("#setup-progress").addClass("d-none");
          $("#setup-error").removeClass("d-none");
          $("#setup-footer").removeClass("d-none");
          $("#setup-error-message").text(
            response.message || i18next.t("ChurchCRM could not connect to the database with these settings."),
          );
          $("#continue-to-login").text("Close").off("click").on("click", closeSetupModal);
          document.getElementById("submit-setup").disabled = true;
          showConnectionResult(
            "fail",
            response.message || i18next.t("ChurchCRM could not connect to the database with these settings."),
          );
          return;
        }

        // Check if response contains errors (backend bug workaround)
        if (response?.errors) {
          // Treat as failure
          $("#setup-progress").addClass("d-none");
          $("#setup-error").removeClass("d-none");
          $("#setup-footer").removeClass("d-none");

          let errorMessage = "<ul class='mb-0'>";
          for (const [field, error] of Object.entries(response.errors)) {
            errorMessage += `<li><strong>${field}:</strong> ${error}</li>`;
          }
          errorMessage += "</ul>";
          $("#setup-error-message").html(errorMessage);

          $("#continue-to-login").text("Close").off("click").on("click", closeSetupModal);
          return;
        }

        // Hide progress, show success
        $("#setup-progress").addClass("d-none");
        $("#setup-success").removeClass("d-none");
        $("#setup-footer").removeClass("d-none");

        // Handle Continue to Login button
        $("#continue-to-login")
          .off("click")
          .on("click", () => {
            location.replace(`${rootPath}/session/begin`);
          });
      })
      .fail((xhr) => {
        // Hide progress, show error
        $("#setup-progress").addClass("d-none");
        $("#setup-error").removeClass("d-none");
        $("#setup-footer").removeClass("d-none");

        // Parse error message
        let errorMessage = "An unknown error occurred.";
        if (xhr.responseJSON?.errors) {
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

        $("#setup-error-message").html(errorMessage);

        // Change button to "Try Again"
        $("#continue-to-login").text("Close").off("click").on("click", closeSetupModal);
      });
  }

  // Note: skipCheck is intentionally NOT exposed globally to prevent accidental calls
  // It should only be called via the Force Install confirmation flow

  document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("setup-form");
    const stepperElement = document.getElementById("setup-stepper");

    // Prevent form submission (we handle it via AJAX)
    form.addEventListener("submit", (event) => {
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
    validators["step-database"] = initializeStepValidation("step-database");

    // Custom navigation logic with validation
    stepperElement.addEventListener("show.bs-stepper", (event) => {
      const currentStep = event.detail.from;
      const nextStep = event.detail.to;

      // Only validate when moving forward
      if (nextStep <= currentStep) {
        return; // Allow backward navigation
      }

      // Check prerequisites when leaving step 0
      if (currentStep === 0 && !state.prerequisitesStatus) {
        event.preventDefault();
        window.CRM.notify("Please ensure all prerequisites are met before continuing.", {
          type: "warning",
          delay: 3000,
        });
        return;
      }
    });

    // Update UI when steps are shown (after navigation completes)
    stepperElement.addEventListener("shown.bs-stepper", (event) => {
      const shownStep = event.detail.to;

      // If returning to prerequisites step, update UI
      if (shownStep === 0) {
        updatePrerequisitesUI();
        updateGroupStatus();
      }
    });

    document.getElementById("submit-setup").disabled = true;
    for (const id of DB_CONNECTION_FIELDS) {
      document.getElementById(id).addEventListener("input", scheduleConnectionTest);
      document.getElementById(id).addEventListener("change", () => {
        if (passedConnectionFingerprint !== connectionFingerprint()) {
          scheduleConnectionTest();
        }
      });
    }
    document.getElementById("db-version-ack").addEventListener("change", updateInstallButton);

    // Handle finish button
    document.getElementById("submit-setup").addEventListener("click", (event) => {
      event.preventDefault(); // Prevent any default behavior

      if (document.getElementById("submit-setup").disabled || passedConnectionFingerprint !== connectionFingerprint()) {
        return;
      }

      // Validate database step before submission
      if (validators["step-database"]) {
        validators["step-database"].revalidate().then((isValid) => {
          if (isValid) {
            submitSetupData();
          } else {
            window.CRM.notify("Please fill in all required fields correctly.", {
              type: "danger",
              delay: 3000,
            });
          }
        });
      } else {
        submitSetupData();
      }
    });

    // Handle prerequisites Next button
    document.getElementById("prerequisites-next-btn").addEventListener("click", () => {
      if (setupStepper) {
        setupStepper.next();
      }
    });

    // Handle database step Previous button
    document.getElementById("database-prev-btn").addEventListener("click", () => {
      if (setupStepper) {
        setupStepper.previous();
      }
    });

    // Handle Force Install button click - show confirmation modal
    const forceBtn = document.getElementById("prerequisites-force-btn");
    if (forceBtn) {
      forceBtn.addEventListener("click", (e) => {
        e.preventDefault();
        e.stopPropagation();
        getModal("forceInstallModal").show();
      });
    }

    // Handle Force Install confirmation
    const confirmBtn = document.getElementById("confirm-force-install");
    if (confirmBtn) {
      confirmBtn.addEventListener("click", (e) => {
        e.preventDefault();
        bootstrap.Modal.getInstance(document.getElementById("forceInstallModal"))?.hide();
        // Wait for modal to hide before proceeding
        setTimeout(() => {
          skipCheck();
        }, 300);
      });
    }

    // Initialize prerequisite checks - integrity check will run after prerequisites load
    checkPrerequisites();
  });
})();
