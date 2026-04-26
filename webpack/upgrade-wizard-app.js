/**
 * Upgrade Wizard Application Logic
 * Handles the upgrade wizard workflow using bs-stepper
 *
 * Step indices:
 *   0 - Pre-flight
 *   1 - Backup
 *   2 - What's New
 *   3 - Download & Apply
 *   4 - Complete
 */

import Stepper from "bs-stepper";
import "bs-stepper/dist/css/bs-stepper.min.css";
import { marked } from "marked";

// Configure marked: strip raw HTML to prevent XSS from release notes
marked.use({
  breaks: true,
  renderer: {
    html() {
      return "";
    },
    link({ href, text }) {
      const safeHref = href && /^https?:\/\//i.test(href) ? href : "#";
      return `<a href="${safeHref}" target="_blank" rel="noopener noreferrer">${text}</a>`;
    },
  },
});

let upgradeStepper;

// Stores the version the user wants to download (may be overridden by target selector)
let selectedTargetVersion = null;

// Stores changelog URL for the installed version after upgrade completes
let installedChangelogUrl = null;

// Ensure AdminAPIRequest is available - fallback to regular APIRequest if not defined
if (window.CRM && !window.CRM.AdminAPIRequest) {
  window.CRM.AdminAPIRequest = (options) => {
    if (!options.method) {
      options.method = "GET";
    } else {
      options.dataType = "json";
    }
    options.url = `${window.CRM.root}/admin/api/${options.path}`;
    options.contentType = "application/json";
    options.beforeSend = (jqXHR, settings) => {
      jqXHR.url = settings.url;
    };
    options.error = (jqXHR, textStatus, errorThrown) => {
      if (window.CRM.system?.handlejQAJAXError) {
        window.CRM.system.handlejQAJAXError(jqXHR, textStatus, errorThrown, options.suppressErrorDialog);
      }
    };
    return $.ajax(options);
  };
}

/**
 * Initialize the upgrade wizard when DOM is ready
 */
$(document).ready(() => {
  if (!window.CRM?.AdminAPIRequest) {
    console.error("AdminAPIRequest not available - upgrade wizard cannot proceed");
    return;
  }

  upgradeStepper = new Stepper(document.querySelector("#upgrade-stepper"), {
    linear: true,
    animation: false,
  });

  setupNavigationHandlers();
  setupStepHandlers();
  setupRefreshButton();
  setupForceReinstallButton();

  const stepElements = document.querySelectorAll("#upgrade-stepper .step");
  document.querySelector("#upgrade-stepper").addEventListener("show.bs-stepper", (event) => {
    // Mark all previous steps as completed
    for (let i = 0; i < event.detail.to; i++) {
      stepElements[i].classList.add("completed");
      const circle = stepElements[i].querySelector(".bs-stepper-circle");
      if (circle) {
        circle.innerHTML = '<i class="fa fa-check"></i>';
      }
    }

    // What's New step (index 2): fetch preview
    if (event.detail.to === 2) {
      setTimeout(() => fetchUpgradePreview(), 300);
    }

    // Download & Apply step (index 3): auto-download
    if (event.detail.to === 3) {
      setTimeout(() => autoDownloadUpdate(), 300);
    }
  });
});

/**
 * Set up navigation button handlers
 */
function setupNavigationHandlers() {
  $("#acceptWarnings").click(() => {
    upgradeStepper.next();
  });

  $("#backup-next").click(() => {
    upgradeStepper.next();
  });

  // "Download & Install" — stores the selected version then advances to download step
  $("#proceedToDownload").click(() => {
    const sel = $("#targetVersionSelect").val();
    selectedTargetVersion = sel && sel !== "" ? sel : null;
    upgradeStepper.next();
  });

  // "Continue Anyway" on error state
  $("#skipWhatsNew").click(() => {
    upgradeStepper.next();
  });
}

/**
 * Set up handlers for each step's actions
 */
function setupStepHandlers() {
  setupBackupStep();
  setupApplyStep();
}

/**
 * Set up database backup step
 */
function setupBackupStep() {
  $("#doBackup").click(function () {
    const $button = $(this);
    const $backupStatus = $("#backupStatus");
    const $resultFiles = $("#resultFiles");

    $button
      .prop("disabled", true)
      .html(`<span class="spinner-border spinner-border-sm me-1"></span>${i18next.t("Creating Backup...")}`);

    window.CRM.AdminAPIRequest({
      method: "POST",
      path: "database/backup",
      data: JSON.stringify({
        BackupType: 3,
      }),
    })
      .done((data) => {
        $backupStatus.html(`<div class="alert alert-success">
                <div class="d-flex align-items-center">
                    <i class="fa-solid fa-check-circle fa-lg me-2"></i>
                    <div><strong>${i18next.t("Backup Complete")}</strong></div>
                </div>
            </div>`);
        $resultFiles.html(`<button class="btn btn-primary" id="downloadbutton" role="button" onclick="window.UpgradeWizard.downloadBackup('${data.BackupDownloadFileName}')">
                <i class="fa-solid fa-download me-1"></i>${i18next.t("Download Backup & Continue")}
            </button>`);
        $button.addClass("d-none");
        $("#skipBackup").addClass("d-none");

        $("#downloadbutton").click(function () {
          $(this)
            .prop("disabled", true)
            .html(`<i class="fa-solid fa-check me-1"></i>${i18next.t("Downloaded")}`);
          setTimeout(() => {
            upgradeStepper.next();
          }, 1000);
        });
      })
      .fail((xhr, _status, error) => {
        let errorMessage = i18next.t("Failed to create backup.");

        if (xhr.responseJSON?.message) {
          errorMessage = `<strong>${i18next.t("Failed to create backup.")}</strong><br>${xhr.responseJSON.message}`;
        } else if (xhr.responseText) {
          try {
            const response = JSON.parse(xhr.responseText);
            if (response.message) {
              errorMessage = `<strong>${i18next.t("Failed to create backup.")}</strong><br>${response.message}`;
            }
          } catch (_e) {
            errorMessage = `<strong>${i18next.t("Failed to create backup.")}</strong><br>${xhr.status}: ${xhr.statusText}`;
          }
        } else if (error) {
          errorMessage = `<strong>${i18next.t("Failed to create backup.")}</strong><br>${error}`;
        }

        $backupStatus.html(`<div class="alert alert-danger">
                <i class="fa-solid fa-times-circle me-2"></i>${errorMessage}
            </div>`);
        $button.prop("disabled", false).html(`<i class="fa fa-database me-1"></i>${i18next.t("Create Backup")}`);
      });
  });

  $("#skipBackup").click(function () {
    $("#backupStatus").html(`<div class="alert alert-warning">
            <div class="d-flex align-items-center">
                <i class="fa-solid fa-forward fa-lg me-2"></i>
                <div>
                    <strong>${i18next.t("Backup Skipped")}</strong>
                    <div class="text-secondary">${i18next.t("It is strongly recommended to have a backup before proceeding.")}</div>
                </div>
            </div>
        </div>`);
    $(this).addClass("d-none");
    $("#doBackup").addClass("d-none");
    setTimeout(() => {
      upgradeStepper.next();
    }, 300);
  });
}

/**
 * Fetch upgrade preview data and render the What's New step
 */
function fetchUpgradePreview() {
  const $loading = $("#whatsNewLoading");
  const $content = $("#whatsNewContent");
  const $error = $("#whatsNewError");

  $loading.removeClass("d-none");
  $content.addClass("d-none");
  $error.addClass("d-none");

  window.CRM.AdminAPIRequest({
    method: "GET",
    path: "upgrade/preview",
  })
    .done((data) => {
      $loading.addClass("d-none");
      renderWhatsNew(data);
      $content.removeClass("d-none");
    })
    .fail((xhr) => {
      $loading.addClass("d-none");
      let msg = i18next.t("Could not load release information.");
      if (xhr.responseJSON?.message) {
        msg = xhr.responseJSON.message;
      }
      $("#whatsNewErrorMsg").text(msg);
      $error.removeClass("d-none");
    });
}

/**
 * Render the What's New content from preview API response
 */
function renderWhatsNew(data) {
  const { nextVersion, nextReleaseNotes, nextChangelogUrl, releasesAhead, upgradePath } = data;

  // Version heading
  $("#whatsNewVersion").text(nextVersion || "");

  // Changelog link
  if (nextChangelogUrl) {
    $("#whatsNewChangelogLink").attr("href", nextChangelogUrl).removeClass("d-none");
  }

  // Release notes
  $("#whatsNewNotes").html(marked.parse(nextReleaseNotes || ""));

  // Store the changelog URL for the completion screen (use the version being installed)
  installedChangelogUrl = nextChangelogUrl || null;

  // Upgrade path panel (only when ≥ 2 releases ahead)
  if (releasesAhead >= 2 && upgradePath && upgradePath.length >= 2) {
    const count = upgradePath.length;
    $("#upgradePathSummary").html(
      `${i18next.t("You are")} <strong>${count}</strong> ${i18next.t("releases behind. Here's what you'll gain:")}`,
    );
    renderUpgradePath(upgradePath);
    $("#upgradePathPanel").removeClass("d-none");
  }

  // Target version selector
  renderVersionSelector(upgradePath, nextVersion);
}

/**
 * Render the collapsible upgrade path accordion
 */
function renderUpgradePath(upgradePath) {
  const $accordion = $("#upgradePathAccordion").empty();

  upgradePath.forEach((entry, idx) => {
    const collapseId = `upgradePath-${idx}`;
    const typeBadge = badgeForType(entry.type);
    const isNextBadge = entry.isNext
      ? `<span class="badge bg-primary-lt text-primary ms-1">${i18next.t("installing next")}</span>`
      : "";
    const changelogLink = entry.changelogUrl
      ? `<a href="${entry.changelogUrl}" target="_blank" rel="noopener noreferrer" class="btn btn-ghost-secondary btn-sm ms-auto">
           <i class="fa fa-external-link me-1"></i>${i18next.t("Changelog")}
         </a>`
      : "";

    const notes = marked.parse(entry.notes || "");

    $accordion.append(`
      <div class="upgrade-path-entry">
        <button class="upgrade-path-header collapse-toggle d-flex align-items-center gap-2 w-100 text-start py-2 px-3 border-0 bg-transparent"
            data-bs-toggle="collapse" data-bs-target="#${collapseId}" aria-expanded="false">
          <i class="fa fa-chevron-down upgrade-path-chevron text-secondary small"></i>
          <span class="fw-semibold">${escapeHtml(entry.version)}</span>
          ${typeBadge}
          ${isNextBadge}
          ${changelogLink}
        </button>
        <div id="${collapseId}" class="collapse upgrade-path-notes px-3 pb-2">
          <div class="release-notes p-3 border rounded">${notes}</div>
        </div>
      </div>
    `);
  });
}

/**
 * Render the target version selector
 */
function renderVersionSelector(upgradePath, defaultNextVersion) {
  const $select = $("#targetVersionSelect").empty();
  if (!upgradePath || upgradePath.length === 0) {
    $("#advancedVersionCollapse").closest(".mb-4").addClass("d-none");
    return;
  }

  // Default option
  $select.append(`<option value="">${i18next.t("Recommended")}: ${escapeHtml(defaultNextVersion || "")}</option>`);

  upgradePath.forEach((entry) => {
    const label = `${entry.version} (${entry.type})`;
    $select.append(`<option value="${escapeHtml(entry.version)}">${escapeHtml(label)}</option>`);
  });

  // Update the "What's New" notes when selection changes
  $select.on("change", function () {
    const ver = $(this).val();
    if (!ver) {
      // Reset to default next version notes
      const defaultEntry = upgradePath.find((e) => e.isNext);
      if (defaultEntry) {
        $("#whatsNewVersion").text(defaultEntry.version);
        $("#whatsNewNotes").html(marked.parse(defaultEntry.notes || ""));
        if (defaultEntry.changelogUrl) {
          $("#whatsNewChangelogLink").attr("href", defaultEntry.changelogUrl).removeClass("d-none");
        }
        installedChangelogUrl = defaultEntry.changelogUrl || null;
      }
    } else {
      const entry = upgradePath.find((e) => e.version === ver);
      if (entry) {
        $("#whatsNewVersion").text(entry.version);
        $("#whatsNewNotes").html(marked.parse(entry.notes || ""));
        if (entry.changelogUrl) {
          $("#whatsNewChangelogLink").attr("href", entry.changelogUrl).removeClass("d-none");
        }
        installedChangelogUrl = entry.changelogUrl || null;
      }
    }
  });
}

/**
 * Return a Tabler badge HTML string for a release type label
 */
function badgeForType(type) {
  const map = {
    major: "bg-danger-lt text-danger",
    minor: "bg-azure-lt text-azure",
    patch: "bg-secondary-lt text-secondary",
  };
  const cls = map[type] || map.patch;
  return `<span class="badge ${cls}">${escapeHtml(type)}</span>`;
}

/**
 * Minimal HTML escaping for user-supplied version strings
 */
function escapeHtml(str) {
  return String(str)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

/**
 * Auto-download update when the Download & Apply step is shown
 */
function autoDownloadUpdate() {
  const $downloadStatus = $("#downloadStatus");

  if (window.CRM.updateFile) {
    $("#updateDetails").removeClass("d-none");
    $("#applyButtonContainer").removeClass("d-none");
    return;
  }

  $downloadStatus.html(`<div class="alert alert-info">
        <span class="spinner-border spinner-border-sm me-2"></span>${i18next.t("Downloading release from GitHub...")}
    </div>`);

  performDownload();
}

/**
 * Perform the actual download operation, using selectedTargetVersion if set
 */
function performDownload() {
  const $downloadStatus = $("#downloadStatus");

  const path = selectedTargetVersion
    ? `upgrade/download-latest-release?version=${encodeURIComponent(selectedTargetVersion)}`
    : "upgrade/download-latest-release";

  window.CRM.AdminAPIRequest({
    method: "GET",
    path,
  })
    .done((data) => {
      window.CRM.updateFile = data;

      $downloadStatus.html(`<div class="alert alert-success">
            <i class="fa-solid fa-check-circle me-2"></i>${i18next.t("Update package downloaded successfully.")}
        </div>`);

      $("#updateFileName").text(data.fileName);
      $("#updateFullPath").text(data.fullPath);
      $("#releaseNotes").html(marked.parse(data.releaseNotes || ""));
      $("#updateSHA1").text(data.sha1);
      $("#updateDetails").removeClass("d-none");
      $("#applyButtonContainer").removeClass("d-none");
    })
    .fail((xhr, _status, error) => {
      let errorMessage = i18next.t("Failed to download update package.");

      if (xhr.responseJSON?.message) {
        errorMessage = `<strong>${i18next.t("Failed to download update package.")}</strong><br>${xhr.responseJSON.message}`;
      } else if (xhr.responseText) {
        try {
          const response = JSON.parse(xhr.responseText);
          if (response.message) {
            errorMessage = `<strong>${i18next.t("Failed to download update package.")}</strong><br>${response.message}`;
          }
        } catch (_e) {
          errorMessage =
            "<strong>" +
            i18next.t("Failed to download update package.") +
            "</strong><br>" +
            xhr.status +
            ": " +
            xhr.statusText;
        }
      } else if (error) {
        errorMessage = `<strong>${i18next.t("Failed to download update package.")}</strong><br>${error}`;
      }

      $downloadStatus.html(`<div class="alert alert-danger">
            <i class="fa-solid fa-times-circle me-2"></i>${errorMessage}
        </div>`);

      $downloadStatus.append(`<button class="btn btn-warning mt-2" id="retryDownload">
                <i class="fa-solid fa-redo me-2"></i>${i18next.t("Retry Download")}
            </button>`);

      $("#retryDownload").click(function () {
        $(this).remove();
        performDownload();
      });
    });
}

/**
 * Set up apply update step
 */
function setupApplyStep() {
  $("#applyUpdate").click(function () {
    const $button = $(this);
    const $applyStatus = $("#applyStatus");
    const $spinner = $("#upgradeSpinner");

    $spinner.addClass("active");
    $button
      .prop("disabled", true)
      .html(`<span class="spinner-border spinner-border-sm me-1"></span>${i18next.t("Applying...")}`);

    window.CRM.AdminAPIRequest({
      method: "POST",
      path: "upgrade/do-upgrade",
      data: JSON.stringify({
        fullPath: window.CRM.updateFile.fullPath,
        sha1: window.CRM.updateFile.sha1,
      }),
    })
      .done((_data) => {
        $spinner.removeClass("active");

        $applyStatus.html(`<div class="alert alert-success">
                <i class="fa-solid fa-check-circle me-2"></i><strong>${i18next.t("System upgrade completed successfully!")}</strong>
            </div>`);

        setTimeout(() => {
          upgradeStepper.next();

          // Show changelog link for the installed version
          if (installedChangelogUrl) {
            $("#completionChangelogLink").attr("href", installedChangelogUrl).removeClass("d-none");
          }

          $.ajax({ url: `${window.CRM.root}/session/end`, type: "GET" });

          var countdown = 5;
          var countdownInterval = setInterval(() => {
            countdown--;
            $("#upgradeRedirectCountdown strong").text(countdown);
            if (countdown <= 0) {
              clearInterval(countdownInterval);
              window.location.href = `${window.CRM.root}/`;
            }
          }, 1000);
        }, 1000);
      })
      .fail((xhr, _status, error) => {
        $spinner.removeClass("active");

        let errorMessage = i18next.t("Upgrade failed. Please check the logs.");

        if (xhr.responseJSON?.message) {
          errorMessage = `<strong>${i18next.t("Upgrade failed.")}</strong><br>${xhr.responseJSON.message}`;
        } else if (xhr.responseText) {
          try {
            const response = JSON.parse(xhr.responseText);
            if (response.message) {
              errorMessage = `<strong>${i18next.t("Upgrade failed.")}</strong><br>${response.message}`;
            }
          } catch (_e) {
            errorMessage = `<strong>${i18next.t("Upgrade failed.")}</strong><br>${xhr.status}: ${xhr.statusText}`;
          }
        } else if (error) {
          errorMessage = `<strong>${i18next.t("Upgrade failed.")}</strong><br>${error}`;
        }

        $applyStatus.html(`<div class="alert alert-danger">
                <i class="fa-solid fa-times-circle me-2"></i>${errorMessage}
            </div>`);
        $button.prop("disabled", false).html(`<i class="fa fa-bolt me-1"></i>${i18next.t("Apply Update Now")}`);
      });
  });
}

/**
 * Download backup file
 */
function downloadBackup(filename) {
  window.location = `${window.CRM.root}/admin/api/database/download/${filename}`;
  $("#backupStatus").html(`<div class="alert alert-info">
        <i class="fa-solid fa-info-circle me-2"></i>${i18next.t("Backup Downloaded, Copy on server removed")}
    </div>`);
}

window.UpgradeWizard = { downloadBackup };

/**
 * Setup refresh from GitHub button
 */
function setupRefreshButton() {
  $("#refreshFromGitHub").click(function () {
    const $button = $(this);
    const $spinner = $("#upgradeSpinner");
    const $icon = $button.find("i");

    $button.prop("disabled", true);
    $icon.removeClass("fa-sync").addClass("fa-circle-notch fa-spin");
    $spinner.addClass("active");

    window.CRM.AdminAPIRequest({
      method: "POST",
      path: "upgrade/refresh-upgrade-info",
    })
      .done((_data) => {
        $spinner.removeClass("active");
        window.CRM.notify(i18next.t("Upgrade information refreshed. Reloading page..."), {
          type: "success",
          delay: 1500,
        });
        setTimeout(() => {
          window.location.reload();
        }, 1500);
      })
      .fail((xhr, _status, _error) => {
        $spinner.removeClass("active");
        $button.prop("disabled", false);
        $icon.removeClass("fa-circle-notch fa-spin").addClass("fa-sync");

        let errorMessage = i18next.t("Failed to refresh upgrade information from GitHub.");
        if (xhr.responseJSON?.message) {
          errorMessage = xhr.responseJSON.message;
        }
        window.CRM.notify(errorMessage, { type: "error", delay: 5000 });
      });
  });
}

/**
 * Setup force reinstall button
 */
function setupForceReinstallButton() {
  $("#forceReinstall").click(() => {
    const modal = new bootstrap.Modal(document.getElementById("forceReinstallModal"));
    modal.show();
  });

  $("#confirmForceReinstall").click(() => {
    bootstrap.Modal.getInstance(document.getElementById("forceReinstallModal")).hide();

    upgradeStepper.to(0);
    setTimeout(() => {
      upgradeStepper.to(1);
    }, 100);

    $("html, body").animate({ scrollTop: $("#upgrade-wizard-card").offset().top - 20 }, 500);

    window.CRM.notify(i18next.t("Force re-install initiated. Please backup your database before applying."), {
      type: "info",
      delay: 5000,
    });
  });
}
