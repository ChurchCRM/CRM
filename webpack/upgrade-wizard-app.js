/**
 * Upgrade Wizard Application Logic
 * Handles the upgrade wizard workflow using bs-stepper
 */

import Stepper from "bs-stepper";
import "bs-stepper/dist/css/bs-stepper.min.css";
import { marked } from "marked";

let upgradeStepper;

// Ensure AdminAPIRequest is available - fallback to regular APIRequest if not defined
if (window.CRM && !window.CRM.AdminAPIRequest) {
  window.CRM.AdminAPIRequest = function (options) {
    // Fallback: if AdminAPIRequest is not defined, assume it's the same as APIRequest
    // The path should already be prefixed with admin/api/
    if (!options.method) {
      options.method = "GET";
    } else {
      options.dataType = "json";
    }
    options.url = window.CRM.root + "/admin/api/" + options.path;
    options.contentType = "application/json";
    options.beforeSend = function (jqXHR, settings) {
      jqXHR.url = settings.url;
    };
    options.error = function (jqXHR, textStatus, errorThrown) {
      if (window.CRM.system && window.CRM.system.handlejQAJAXError) {
        window.CRM.system.handlejQAJAXError(jqXHR, textStatus, errorThrown, options.suppressErrorDialog);
      }
    };
    return $.ajax(options);
  };
}

/**
 * Initialize the upgrade wizard when DOM is ready
 */
$(document).ready(function () {
  // Verify AdminAPIRequest is available
  if (!window.CRM || !window.CRM.AdminAPIRequest) {
    console.error("AdminAPIRequest not available - upgrade wizard cannot proceed");
    return;
  }

  // Initialize bs-stepper
  upgradeStepper = new Stepper(document.querySelector("#upgrade-stepper"), {
    linear: true,
    animation: true,
  });

  // Set up event handlers
  setupNavigationHandlers();
  setupStepHandlers();
  setupRefreshButton();
  setupForceReinstallButton();

  // Listen for step changes to auto-download when reaching apply step
  document.querySelector("#upgrade-stepper").addEventListener("show.bs-stepper", function (event) {
    // Auto-download when entering the apply step
    if (event.detail.to === 2) {
      // Index 2 is now the apply step (0: warnings, 1: backup, 2: apply)
      setTimeout(function () {
        autoDownloadUpdate();
      }, 300);
    }
  });
});

/**
 * Set up navigation button handlers
 */
function setupNavigationHandlers() {
  // Warning step - accept and continue
  $("#acceptWarnings").click(function () {
    upgradeStepper.next();
  });

  // Backup step navigation
  $("#backup-next").click(function () {
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
    const $navButtons = $("#backupNavButtons");

    // Show loading state
    $button.prop("disabled", true).html(`<span class="spinner-border spinner-border-sm me-1"></span>${i18next.t("Creating Backup...")}`);

    window.CRM.APIRequest({
      method: "POST",
      path: "database/backup",
      data: JSON.stringify({
        BackupType: 3,
      }),
    })
      .done(function (data) {
        $backupStatus.html(`<div class="alert alert-success">
                <div class="d-flex align-items-center">
                    <i class="fa-solid fa-check-circle fa-lg me-2"></i>
                    <div><strong>${i18next.t("Backup Complete")}</strong></div>
                </div>
            </div>`);
        $resultFiles.html(`<button class="btn btn-outline-success" id="downloadbutton" role="button" onclick="window.UpgradeWizard.downloadBackup('${data.BackupDownloadFileName}')">
                <i class="fa-solid fa-download me-1"></i>${data.BackupDownloadFileName}
            </button>`);
        $button.html(`<i class="fa fa-check me-1"></i>${i18next.t("Backup Created")}`);
        $navButtons.removeClass("d-none");

        $("#downloadbutton").click(function () {
          $(this).prop("disabled", true).html(`<i class="fa-solid fa-check me-1"></i>${i18next.t("Downloaded")}`);
        });
      })
      .fail(function (xhr, status, error) {
        let errorMessage = i18next.t("Failed to create backup.");

        if (xhr.responseJSON && xhr.responseJSON.message) {
          errorMessage =
            "<strong>" + i18next.t("Failed to create backup.") + "</strong><br>" + xhr.responseJSON.message;
        } else if (xhr.responseText) {
          try {
            const response = JSON.parse(xhr.responseText);
            if (response.message) {
              errorMessage = "<strong>" + i18next.t("Failed to create backup.") + "</strong><br>" + response.message;
            }
          } catch (e) {
            errorMessage =
              "<strong>" + i18next.t("Failed to create backup.") + "</strong><br>" + xhr.status + ": " + xhr.statusText;
          }
        } else if (error) {
          errorMessage = "<strong>" + i18next.t("Failed to create backup.") + "</strong><br>" + error;
        }

        $backupStatus.html(`<div class="alert alert-danger">
                <i class="fa-solid fa-times-circle me-2"></i>${errorMessage}
            </div>`);
        $button.prop("disabled", false).html(`<i class="fa fa-database me-1"></i>${i18next.t("Create Backup")}`);
      });
  });

  // Skip Backup button handler
  $("#skipBackup").click(function () {
    const $backupStatus = $("#backupStatus");
    const $navButtons = $("#backupNavButtons");

    $backupStatus.html(`<div class="alert alert-warning">
            <div class="d-flex align-items-center">
                <i class="fa-solid fa-forward fa-lg me-2"></i>
                <div>
                    <strong>${i18next.t("Backup Skipped")}</strong>
                    <div class="text-secondary">${i18next.t("It is strongly recommended to have a backup before proceeding.")}</div>
                </div>
            </div>
        </div>`);
    $(this).addClass("d-none");
    $navButtons.removeClass("d-none");
  });
}

/**
 * Auto-download update when step is shown
 */
function autoDownloadUpdate() {
  const $downloadStatus = $("#downloadStatus");

  // Check if already downloaded
  if (window.CRM.updateFile) {
    $("#updateDetails").removeClass("d-none");
    $("#applyButtonContainer").removeClass("d-none");
    return;
  }

  $downloadStatus.html(`<div class="alert alert-info">
        <span class="spinner-border spinner-border-sm me-2"></span>${i18next.t("Downloading latest release from GitHub...")}
    </div>`);

  performDownload();
}

/**
 * Perform the actual download operation
 */
function performDownload() {
  const $downloadStatus = $("#downloadStatus");

  window.CRM.AdminAPIRequest({
    type: "GET",
    path: "upgrade/download-latest-release",
  })
    .done(function (data) {
      window.CRM.updateFile = data;

      $downloadStatus.html(`<div class="alert alert-success">
            <i class="fa-solid fa-check-circle me-2"></i>${i18next.t("Update package downloaded successfully.")}
        </div>`);

      // Show update details
      $("#updateFileName").text(data.fileName);
      $("#updateFullPath").text(data.fullPath);
      $("#releaseNotes").html(marked.parse(data.releaseNotes || ""));
      $("#updateSHA1").text(data.sha1);
      $("#updateDetails").removeClass("d-none");

      // Show apply button after download completes
      $("#applyButtonContainer").removeClass("d-none");
    })
    .fail(function (xhr, status, error) {
      let errorMessage = i18next.t("Failed to download update package.");

      if (xhr.responseJSON && xhr.responseJSON.message) {
        errorMessage =
          "<strong>" + i18next.t("Failed to download update package.") + "</strong><br>" + xhr.responseJSON.message;
      } else if (xhr.responseText) {
        try {
          const response = JSON.parse(xhr.responseText);
          if (response.message) {
            errorMessage =
              "<strong>" + i18next.t("Failed to download update package.") + "</strong><br>" + response.message;
          }
        } catch (e) {
          errorMessage =
            "<strong>" +
            i18next.t("Failed to download update package.") +
            "</strong><br>" +
            xhr.status +
            ": " +
            xhr.statusText;
        }
      } else if (error) {
        errorMessage = "<strong>" + i18next.t("Failed to download update package.") + "</strong><br>" + error;
      }

      $downloadStatus.html(`<div class="alert alert-danger">
            <i class="fa-solid fa-times-circle me-2"></i>${errorMessage}
        </div>`);

      // Show manual retry button
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
    $button.prop("disabled", true).html(`<span class="spinner-border spinner-border-sm me-1"></span>${i18next.t("Applying...")}`);

    window.CRM.AdminAPIRequest({
      method: "POST",
      path: "upgrade/do-upgrade",
      data: JSON.stringify({
        fullPath: window.CRM.updateFile.fullPath,
        sha1: window.CRM.updateFile.sha1,
      }),
    })
      .done(function (data) {
        // Hide spinner
        $spinner.removeClass("active");

        $applyStatus.html(`<div class="alert alert-success">
                <i class="fa-solid fa-check-circle me-2"></i><strong>${i18next.t("System upgrade completed successfully!")}</strong>
            </div>`);

        // Auto-advance to final step and logout after a brief delay
        setTimeout(function () {
          upgradeStepper.next();

          // Log out the user
          $.ajax({
            url: window.CRM.root + "/session/end",
            type: "GET",
          });

          // Start countdown and redirect to login
          var countdown = 5;
          var countdownInterval = setInterval(function () {
            countdown--;
            $("#upgradeRedirectCountdown strong").text(countdown);

            if (countdown <= 0) {
              clearInterval(countdownInterval);
              window.location.href = window.CRM.root + "/";
            }
          }, 1000);
        }, 1000);
      })
      .fail(function (xhr, status, error) {
        // Hide spinner
        $spinner.removeClass("active");

        let errorMessage = i18next.t("Upgrade failed. Please check the logs.");

        if (xhr.responseJSON && xhr.responseJSON.message) {
          errorMessage = "<strong>" + i18next.t("Upgrade failed.") + "</strong><br>" + xhr.responseJSON.message;
        } else if (xhr.responseText) {
          try {
            const response = JSON.parse(xhr.responseText);
            if (response.message) {
              errorMessage = "<strong>" + i18next.t("Upgrade failed.") + "</strong><br>" + response.message;
            }
          } catch (e) {
            errorMessage =
              "<strong>" + i18next.t("Upgrade failed.") + "</strong><br>" + xhr.status + ": " + xhr.statusText;
          }
        } else if (error) {
          errorMessage = "<strong>" + i18next.t("Upgrade failed.") + "</strong><br>" + error;
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
 * @param {string} filename - The backup filename to download
 */
function downloadBackup(filename) {
  window.location = window.CRM.root + "/api/database/download/" + filename;
  $("#backupStatus").html(`<div class="alert alert-info">
        <i class="fa-solid fa-info-circle me-2"></i>${i18next.t("Backup Downloaded, Copy on server removed")}
    </div>`);
}

// Export functions to global scope for onclick handlers
window.UpgradeWizard = {
  downloadBackup,
};

/**
 * Setup refresh from GitHub button
 */
function setupRefreshButton() {
  $("#refreshFromGitHub").click(function () {
    const $button = $(this);
    const $spinner = $("#upgradeSpinner");
    const $icon = $button.find("i");

    // Disable button and show spinner
    $button.prop("disabled", true);
    $icon.removeClass("fa-sync").addClass("fa-circle-notch fa-spin");
    $spinner.addClass("active");

    // Call refresh API
    window.CRM.AdminAPIRequest({
      method: "POST",
      path: "upgrade/refresh-upgrade-info",
    })
      .done(function (data) {
        $spinner.removeClass("active");
        window.CRM.notify(i18next.t("Upgrade information refreshed. Reloading page..."), {
          type: "success",
          delay: 1500,
        });

        // Reload the page after a short delay
        setTimeout(function () {
          window.location.reload();
        }, 1500);
      })
      .fail(function (xhr, status, error) {
        $spinner.removeClass("active");
        $button.prop("disabled", false);
        $icon.removeClass("fa-circle-notch fa-spin").addClass("fa-sync");

        let errorMessage = i18next.t("Failed to refresh upgrade information from GitHub.");
        if (xhr.responseJSON && xhr.responseJSON.message) {
          errorMessage = xhr.responseJSON.message;
        }

        window.CRM.notify(errorMessage, {
          type: "error",
          delay: 5000,
        });
      });
  });
}

/**
 * Setup force reinstall button - allows re-downloading and applying the current version
 */
function setupForceReinstallButton() {
  $("#forceReinstall").click(function () {
    // Confirm the action
    if (
      !confirm(
        i18next.t(
          "This will re-download and re-apply the current version. This can fix corrupted or modified files. Continue?",
        ),
      )
    ) {
      return;
    }

    // Show the upgrade wizard card if hidden
    $("#upgrade-wizard-card").removeClass("d-none");

    // Reset the stepper to the beginning and then navigate to the backup step
    upgradeStepper.to(0);

    // Small delay to ensure stepper is ready, then advance to backup step
    setTimeout(function () {
      upgradeStepper.to(1); // Go to backup step
    }, 100);

    // Scroll to the wizard
    $("html, body").animate(
      {
        scrollTop: $("#upgrade-wizard-card").offset().top - 20,
      },
      500,
    );

    window.CRM.notify(i18next.t("Force re-install initiated. Please backup your database before applying."), {
      type: "info",
      delay: 5000,
    });
  });
}
