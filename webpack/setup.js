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
// Setup has no locale files yet; with no resources each key renders as its English text.
i18next.init({
  lng: "en",
  resources: {},
  nsSeparator: false,
  keySeparator: false,
  pluralSeparator: false,
  contextSeparator: false,
  fallbackLng: false,
  interpolation: { escapeValue: false },
  initAsync: false,
});

const moment = require("moment");
window.moment = moment;

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
      warning: true,
    },
    orphaned: {
      table: "#orphaned-checks",
      status: "#orphaned-status",
      collapse: "#orphaned-collapse",
      section: "#orphaned-files-section",
      warning: true,
    },
  };

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

    if (!state.checksComplete) {
      return;
    }
    const blocking = Object.entries(state.prerequisites).some(
      ([key, passed]) => passed !== true && (key.startsWith("php-") || key.startsWith("filesystem-")),
    );
    if (state.prerequisitesStatus) {
      updateStatusBanner("success", i18next.t("Ready"), "");
    } else if (blocking) {
      updateStatusBanner(
        "error",
        i18next.t("Your web host needs to fix something"),
        i18next.t("Send them the list below."),
      );
      bootstrap.Collapse.getOrCreateInstance(document.getElementById("server-details"), { toggle: false }).show();
    } else {
      updateStatusBanner(
        "warning",
        i18next.t("Ready"),
        i18next.t("Some ChurchCRM files are missing or changed. Upload ChurchCRM again if a page does not load."),
      );
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
            .text(i18next.t("and {{count}} more", { count: items.length - maxFiles })),
        );
      }
      detailCell.append(list);
    };
    addList(i18next.t("These files were not uploaded"), missing);
    addList(i18next.t("These files do not match the release"), changed);
    addList(i18next.t("Files to review"), combined);
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
      $status.html(
        config.warning
          ? '<i class="fa-solid fa-exclamation-triangle text-warning"></i>'
          : '<i class="fa-solid fa-exclamation-circle text-danger"></i>',
      );
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
      .append($("<td>", { text: i18next.t("Release check") }))
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
              ? i18next.t("Integrity check passed")
              : i18next.t("Integrity check failed");

        const resultRow = $("<tr>", {
          id: rowId,
        })
          .append($("<td>", { text: i18next.t("Release check") }))
          .append(buildStatusCell(statusInfo, message));

        $(`#${rowId}`).replaceWith(resultRow);
        state.prerequisites[rowId] = satisfied;

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
          .append($("<td>", { text: i18next.t("Release check") }))
          .append(
            buildStatusCell(
              statusConfig.false,
              i18next.t("ChurchCRM could not check its files. Ask your web host to look at the server error log."),
            ),
          );

        $(`#${rowId}`).replaceWith(resultRow);
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
            Name: i18next.t("ChurchCRM could not check the server software"),
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
            Name: i18next.t("ChurchCRM could not check folder permissions"),
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
    }).always(checkIntegrity);
  }

  const DB_CONNECTION_FIELDS = ["DB_SERVER_NAME", "DB_SERVER_PORT", "DB_NAME", "DB_USER", "DB_PASSWORD"];
  const INSTALL_LABEL = () => i18next.t("Install ChurchCRM");

  // One install request at a time: field edits cannot re-enable Install while one is in flight.
  let installInFlight = false;

  function setInstalling(installing) {
    installInFlight = installing;
    const button = document.getElementById("submit-setup");
    button.disabled = installing;
    button.textContent = installing ? i18next.t("Connecting to your database…") : INSTALL_LABEL();
  }

  function showSetupProblem(message) {
    const box = document.getElementById("db-connection-result");
    box.textContent = message;
    box.classList.remove("d-none");
    box.scrollIntoView({ behavior: "smooth", block: "center" });
  }

  function showVersionWarning(message) {
    document.getElementById("db-version-message").textContent = message;
    document.getElementById("db-version-warning").classList.remove("d-none");
    document.getElementById("db-version-ack").checked = false;
    document.getElementById("submit-setup").disabled = true;
    document.getElementById("db-version-warning").scrollIntoView({ behavior: "smooth", block: "center" });
  }

  // The answer belonged to the values that were submitted; editing them clears it.
  function clearSetupProblem() {
    document.getElementById("db-connection-result").classList.add("d-none");
    document.getElementById("db-version-warning").classList.add("d-none");
    document.getElementById("db-version-ack").checked = false;
    document.getElementById("submit-setup").disabled = installInFlight;
    for (const field of document.querySelectorAll("#setup-form .is-invalid")) {
      field.classList.remove("is-invalid");
    }
    for (const feedback of document.querySelectorAll("#setup-form .invalid-feedback.d-block")) {
      feedback.classList.remove("d-block");
    }
  }

  // Outline each field the server rejected, open the web address fields if one is there, and list the fixes.
  function showFieldErrors(errors) {
    for (const [id, message] of Object.entries(errors)) {
      const field = document.getElementById(id);
      const feedback = field?.closest(".mb-3, .mb-0")?.querySelector(".invalid-feedback");
      field?.classList.add("is-invalid");
      if (feedback) {
        feedback.textContent = message;
        feedback.classList.add("d-block");
      }
    }
    if (errors.URL || errors.ROOT_PATH) {
      bootstrap.Collapse.getOrCreateInstance(document.getElementById("advanced-settings-collapse"), {
        toggle: false,
      }).show();
    }
    document.getElementById(Object.keys(errors)[0])?.focus();
  }

  function submitSetupData() {
    const formData = {};
    for (const [key, value] of new FormData(document.getElementById("setup-form")).entries()) {
      formData[key] = value || "";
    }

    setInstalling(true);
    $.ajax({
      url: `${rootPath}/setup/`,
      method: "POST",
      data: JSON.stringify(formData),
      contentType: "application/json",
    })
      .done((response) => {
        setInstalling(false);
        if (response?.correct === false) {
          showSetupProblem(
            response.message || i18next.t("ChurchCRM could not connect to the database with these settings."),
          );
          return;
        }
        if (response?.supported === false) {
          showVersionWarning(response.message);
          return;
        }
        if (response?.errors) {
          showFieldErrors(response.errors);
          return;
        }

        installInFlight = true;
        document.getElementById("submit-setup").disabled = true;
        $("#continue-to-login")
          .off("click")
          .on("click", () => {
            location.replace(`${rootPath}/session/begin`);
          });
        getModal("setupModal").show();
      })
      .fail((xhr) => {
        setInstalling(false);
        if (xhr.responseJSON?.errors) {
          showFieldErrors(xhr.responseJSON.errors);
          return;
        }
        showSetupProblem(
          i18next.t(
            "ChurchCRM could not finish the install. Try again, or ask your web host to check the server error log.",
          ),
        );
      });
  }

  document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("setup-form");

    // Prevent form submission (we handle it via AJAX)
    form.addEventListener("submit", (event) => {
      event.preventDefault();
      return false;
    });

    for (const id of [...DB_CONNECTION_FIELDS, "URL", "ROOT_PATH"]) {
      document.getElementById(id).addEventListener("input", clearSetupProblem);
    }
    document.getElementById("db-version-ack").addEventListener("change", (event) => {
      document.getElementById("submit-setup").disabled = !event.target.checked;
    });

    document.getElementById("toggle-db-password").addEventListener("click", (event) => {
      const field = document.getElementById("DB_PASSWORD");
      const showing = field.type === "text";
      field.type = showing ? "password" : "text";
      event.currentTarget.textContent = showing ? i18next.t("Show") : i18next.t("Hide");
      event.currentTarget.setAttribute("aria-pressed", String(!showing));
    });

    document.getElementById("URL").addEventListener("input", (event) => {
      document.getElementById("setup-url-display").textContent = event.target.value;
    });

    document.getElementById("submit-setup").addEventListener("click", (event) => {
      event.preventDefault();
      if (!installInFlight) {
        submitSetupData();
      }
    });

    // Initialize prerequisite checks - integrity check will run after prerequisites load
    checkPrerequisites();
  });
})();
