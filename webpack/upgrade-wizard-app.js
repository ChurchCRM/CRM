/**
 * Upgrade Wizard Application Logic
 * Handles the upgrade wizard workflow using bs-stepper
 *
 * Step indices:
 *   0 - Pre-flight
 *   1 - Backup
 *   2 - What's New / What you'll gain
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

// Stores the version the user wants to download.
// null  = "use latest" — download-latest-release is called without ?version param.
// "X.Y.Z" = explicit version chosen via the advanced picker or prerelease reinstall.
let selectedTargetVersion = null;

// Stores the changelog URL for the installed version after upgrade completes.
let installedChangelogUrl = null;

// Set to true when the admin triggers a force-reinstall so the What's New step
// keeps the proceed button visible even when the system is already up to date.
let forceReinstallMode = false;

// Stores the stable version to reinstall when running a prerelease build ahead of latest stable.
let prereleaseTargetVersion = null;

// Ensure AdminAPIRequest is available — fallback to regular APIRequest if not defined.
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
 * Initialize the upgrade wizard when DOM is ready.
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
    // Mark all previous steps as completed.
    for (let i = 0; i < event.detail.to; i++) {
      stepElements[i].classList.add("completed");
      const circle = stepElements[i].querySelector(".bs-stepper-circle");
      if (circle) {
        circle.innerHTML = '<i class="fa fa-check"></i>';
      }
    }

    // What you'll gain step (index 2): fetch preview.
    if (event.detail.to === 2) {
      setTimeout(() => fetchUpgradePreview(), 300);
    }

    // Download & Apply step (index 3): auto-download.
    if (event.detail.to === 3) {
      setTimeout(() => autoDownloadUpdate(), 300);
    }
  });
});

/**
 * Set up navigation button handlers.
 */
function setupNavigationHandlers() {
  $("#acceptWarnings").click(() => {
    upgradeStepper.next();
  });

  $("#backup-next").click(() => {
    upgradeStepper.next();
  });

  // "Download & Apply" — resolve the selected version then advance to the download step.
  // selectedTargetVersion is managed by the version-select change handler throughout the step.
  // For the prerelease reinstall case there is no version selector, so fall through to
  // prereleaseTargetVersion if selectedTargetVersion has not been set explicitly.
  $("#proceedToDownload").click(() => {
    if (selectedTargetVersion === null && prereleaseTargetVersion !== null) {
      selectedTargetVersion = prereleaseTargetVersion;
    }
    upgradeStepper.next();
  });

  // "Continue Anyway" on error state.
  $("#skipWhatsNew").click(() => {
    upgradeStepper.next();
  });
}

/**
 * Set up handlers for each step's actions.
 */
function setupStepHandlers() {
  setupBackupStep();
  setupApplyStep();
}

/**
 * Set up database backup step.
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
          errorMessage = `<strong>${i18next.t("Failed to create backup.")}</strong><br>${escapeHtml(xhr.responseJSON.message)}`;
        } else if (xhr.responseText) {
          try {
            const response = JSON.parse(xhr.responseText);
            if (response.message) {
              errorMessage = `<strong>${i18next.t("Failed to create backup.")}</strong><br>${escapeHtml(response.message)}`;
            }
          } catch (_e) {
            errorMessage = `<strong>${i18next.t("Failed to create backup.")}</strong><br>${xhr.status}: ${xhr.statusText}`;
          }
        } else if (error) {
          errorMessage = `<strong>${i18next.t("Failed to create backup.")}</strong><br>${error}`;
        }

        $backupStatus.html(`<div class="alert alert-danger">
                <i class="fa-solid fa-circle-xmark me-2"></i>${errorMessage}
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
 * Fetch upgrade preview data and render the What you'll gain step.
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
 * Build a single version release-notes block with deep-link anchor, type badge,
 * optional full-release-notes link, and rendered markdown notes.
 *
 * @param {string}      version      Semver version string (e.g. "7.6.0")
 * @param {string|null} type         Release type: "major" | "minor" | "patch" | null
 * @param {string}      notes        Markdown release notes
 * @param {string|null} changelogUrl Link to the full release notes on GitHub
 */
/**
 * Accept only http(s) changelog URLs; reject everything else (javascript:, data:, etc.).
 * Returns the URL unchanged if valid, null otherwise.
 */
function sanitizeChangelogUrl(url) {
  return url && /^https?:\/\//i.test(url) ? url : null;
}

function buildVersionBlock(version, type, notes, changelogUrl) {
  const anchor = `v${version.replace(/\./g, "-")}`;
  const typeHtml = type ? ` ${badgeForType(type)}` : "";
  const notesHtml = marked.parse(notes || "");
  // Use sanitizeChangelogUrl so the same http(s)-only guard applies everywhere.
  const safeUrl = sanitizeChangelogUrl(changelogUrl);
  const changelogLink = safeUrl
    ? `<a href="${escapeHtml(safeUrl)}" target="_blank" rel="noopener noreferrer" class="btn btn-ghost-secondary btn-sm flex-shrink-0">
         <i class="fa fa-external-link me-1"></i>${i18next.t("Full release notes")}
       </a>`
    : "";
  return `<div class="version-notes-block mb-3">
    <div id="${escapeHtml(anchor)}" class="d-flex align-items-center justify-content-between mb-1 flex-wrap gap-1">
      <h5 class="mb-0">${escapeHtml(version)}${typeHtml}</h5>
      ${changelogLink}
    </div>
    <div class="release-notes p-3 border rounded">${notesHtml}</div>
  </div>`;
}

/**
 * Render stacked version blocks (newest-first) into #whatsNewNotes,
 * filtered to versions <= targetVersion.
 * upgradePath arrives ascending from the API; we reverse after filtering.
 */
function renderGainStack(upgradePath, targetVersion) {
  const relevant = [...upgradePath].filter((e) => semverCompare(e.version, targetVersion) <= 0).reverse(); // newest first

  if (relevant.length === 0) {
    $("#whatsNewNotes").html(
      `<p class="text-secondary">${i18next.t("No release notes available for this version.")}</p>`,
    );
    return;
  }

  const html = relevant.map((e) => buildVersionBlock(e.version, e.type, e.notes, e.changelogUrl)).join("");
  $("#whatsNewNotes").html(html);
}

/**
 * Simple semver comparison for "X.Y.Z" strings.
 * Returns negative if a < b, 0 if equal, positive if a > b.
 */
function semverCompare(a, b) {
  // F3: use parseInt so pre-release suffixes like "3-rc1" parse as 3 rather than NaN.
  const pa = String(a)
    .split(".")
    .map((s) => Number.parseInt(s, 10) || 0);
  const pb = String(b)
    .split(".")
    .map((s) => Number.parseInt(s, 10) || 0);
  for (let i = 0; i < 3; i++) {
    const diff = (pa[i] || 0) - (pb[i] || 0);
    if (diff !== 0) return diff;
  }
  return 0;
}

/**
 * Render the What you'll gain step from preview API response.
 *
 * Three cases:
 *   Case 1 — isAheadOfStable: running a prerelease/dev build ahead of latest stable.
 *   Case 2 — releasesAhead === 0: system is fully up to date.
 *   Case 3 — normal upgrade: one or more releases ahead.
 */
function renderWhatsNew(data) {
  const {
    nextVersion,
    nextReleaseNotes,
    nextChangelogUrl,
    releasesAhead,
    upgradePath,
    isAheadOfStable,
    latestVersion,
  } = data;

  // --- Reset stale UI state before every render ---
  $("#whatsNewChangelogLink").addClass("d-none").attr("href", "#");
  $("#advancedVersionPanel").addClass("d-none");
  $("#securityRecommendationCallout").addClass("d-none");
  $("#recommendedBadge").addClass("d-none");
  $("#advancedWarningBanner").addClass("d-none");
  $("#targetVersionSelect").empty();
  $("#whatsNewNotes").empty();
  $("#whatsNewVersion").text("");
  selectedTargetVersion = null;
  prereleaseTargetVersion = null;
  // Remove any banners injected by a previous render (e.g. wizard re-entry).
  // IMPORTANT: use .js-dynamic-banner — do NOT sweep .alert-warning or .alert-success
  // broadly, as #securityRecommendationCallout is a permanent static element that must
  // survive across renders (it is shown/hidden via the d-none toggle, never removed).
  $("#whatsNewContent .js-dynamic-banner").remove();
  $("#proceedToDownload")
    .removeClass("d-none")
    .html(`<i class="fa fa-cloud-arrow-down me-1"></i>${i18next.t("Download & Apply")}`);

  // ── Case 1: Running a prerelease / dev build ahead of latest stable ──────────
  if (isAheadOfStable && nextVersion) {
    prereleaseTargetVersion = nextVersion;
    $("#whatsNewVersion").text(nextVersion);
    const safeNextChangelogUrl = sanitizeChangelogUrl(nextChangelogUrl);
    if (safeNextChangelogUrl) {
      $("#whatsNewChangelogLink").attr("href", safeNextChangelogUrl).removeClass("d-none");
    } else {
      $("#whatsNewChangelogLink").addClass("d-none");
    }
    installedChangelogUrl = safeNextChangelogUrl;

    // Render the stable-release notes as a single block.
    $("#whatsNewNotes").html(buildVersionBlock(nextVersion, null, nextReleaseNotes, nextChangelogUrl));

    $("#whatsNewContent").prepend(
      `<div class="alert alert-warning d-flex align-items-center gap-2 mb-3 js-dynamic-banner">
        <i class="fa fa-triangle-exclamation fa-lg"></i>
        <span>
          <strong>${i18next.t("You are running a pre-release version.")}</strong>
          ${i18next.t("The latest stable release is {{version}}. You can reinstall it below.", { version: escapeHtml(nextVersion) })}
        </span>
      </div>`,
    );
    $("#proceedToDownload").html(
      `<i class="fa fa-cloud-arrow-down me-1"></i>${i18next.t("Install stable {{version}}", { version: escapeHtml(nextVersion) })}`,
    );
    return;
  }

  // ── Case 2: Truly up to date — no releases ahead, not a prerelease ───────────
  if (releasesAhead === 0) {
    const latestNotes = data.latestReleaseNotes || "";
    const latestUrl = data.latestChangelogUrl || null;
    const latestVer = data.latestVersion || "";

    $("#whatsNewVersion").text(latestVer);
    const safeLatestUrl = sanitizeChangelogUrl(latestUrl);
    if (safeLatestUrl) {
      $("#whatsNewChangelogLink").attr("href", safeLatestUrl).removeClass("d-none");
    } else {
      $("#whatsNewChangelogLink").addClass("d-none");
    }
    installedChangelogUrl = safeLatestUrl;

    // Remove any stale up-to-date banner from a previous render.
    $("#whatsNewContent .js-uptodate-banner").remove();
    const upToDateBanner = `<div class="alert alert-success d-flex align-items-center gap-2 mb-3 js-uptodate-banner js-dynamic-banner">
      <i class="fa fa-circle-check fa-lg"></i>
      <span><strong>${i18next.t("You're up to date!")}</strong> ${i18next.t("No upgrades are available for your current version.")}</span>
    </div>`;
    $("#whatsNewContent").prepend(upToDateBanner);

    if (latestNotes) {
      $("#whatsNewNotes").html(`<div class="release-notes p-3 border rounded">${marked.parse(latestNotes)}</div>`);
    } else {
      $("#whatsNewNotes").html(
        `<p class="text-secondary">${i18next.t("No release notes available for this version.")}</p>`,
      );
    }

    if (forceReinstallMode) {
      // Allow the admin to proceed with reinstalling the current version.
      $("#proceedToDownload").text(i18next.t("Re-install current version")).removeClass("d-none");
    } else {
      $("#proceedToDownload").addClass("d-none");
    }
    return;
  }

  // ── Case 3: Normal upgrade — one or more releases ahead ─────────────────────
  // F2: guard against a missing or empty upgradePath (API may omit the field).
  if (!upgradePath || upgradePath.length === 0) {
    $("#whatsNewNotes").html(`<p class="text-secondary">${i18next.t("No release notes available.")}</p>`);
    return;
  }

  // The latest version is always the recommended target.  The backend already
  // supports jumping from any installed version to any target directly.
  const latest = latestVersion || upgradePath[upgradePath.length - 1].version;
  const latestEntry = upgradePath.find((e) => e.version === latest);
  const latestChangelog = latestEntry?.changelogUrl || nextChangelogUrl || null;

  // Set target to latest.
  $("#whatsNewVersion").text(latest || "");
  const safeLatestChangelog = sanitizeChangelogUrl(latestChangelog);
  if (safeLatestChangelog) {
    $("#whatsNewChangelogLink").attr("href", safeLatestChangelog).removeClass("d-none");
  } else {
    $("#whatsNewChangelogLink").addClass("d-none");
  }
  installedChangelogUrl = safeLatestChangelog;

  // Show security callout and the green "Recommended" badge.
  $("#securityRecommendationCallout").removeClass("d-none");
  $("#recommendedBadge").removeClass("d-none");

  // CTA reads "Download & Apply X.Y.Z" for clarity.
  $("#proceedToDownload").html(
    `<i class="fa fa-cloud-arrow-down me-1"></i>${i18next.t("Download & Apply {{version}}", { version: escapeHtml(latest || "") })}`,
  );

  // Stack ALL version notes newest-first by default.
  renderGainStack(upgradePath, latest);

  // Populate the advanced version picker.
  // Only reveal when there is at least one non-latest option to offer.
  if (upgradePath.length > 1) {
    renderVersionSelector(upgradePath, latest);
    $("#advancedVersionPanel").removeClass("d-none");
  }
}

/**
 * Populate the advanced version picker.
 * Latest version is the default selected option, labelled "(Recommended)".
 * Non-latest selection triggers a red security warning banner.
 */
function renderVersionSelector(upgradePath, latestVersion) {
  const $select = $("#targetVersionSelect").empty();

  // Latest option — selected by default.
  $select.append(
    `<option value="${escapeHtml(latestVersion)}">${escapeHtml(latestVersion)} (${i18next.t("Recommended")})</option>`,
  );

  // All other versions — newest-first so the picker order matches the notes stack.
  // upgradePath arrives ascending from the API; reverse to get newest-first.
  const nonLatest = upgradePath.filter((e) => e.version !== latestVersion).reverse();
  nonLatest.forEach((entry) => {
    const friendlyLabel = { major: i18next.t("Major"), minor: i18next.t("Feature"), patch: i18next.t("Bug Fix") };
    const typeLabel = friendlyLabel[entry.type] || entry.type;
    $select.append(
      `<option value="${escapeHtml(entry.version)}">${escapeHtml(entry.version)} \u2014 ${escapeHtml(typeLabel)}</option>`,
    );
  });

  // Deregister any stale listener before attaching a fresh one.
  $select.off("change").on("change", function () {
    const chosen = $(this).val();

    if (chosen === latestVersion) {
      // ── Reverted to latest — restore default state ───────────────────────────
      $("#advancedWarningBanner").addClass("d-none");
      $("#recommendedBadge").removeClass("d-none");

      const latestEntry = upgradePath.find((e) => e.version === latestVersion);
      const safeLatestHref = sanitizeChangelogUrl(latestEntry?.changelogUrl);
      if (safeLatestHref) {
        $("#whatsNewChangelogLink").attr("href", safeLatestHref).removeClass("d-none");
      } else {
        $("#whatsNewChangelogLink").addClass("d-none");
      }
      installedChangelogUrl = safeLatestHref;

      $("#whatsNewVersion").text(latestVersion);
      $("#proceedToDownload").html(
        `<i class="fa fa-cloud-arrow-down me-1"></i>${i18next.t("Download & Apply {{version}}", { version: escapeHtml(latestVersion) })}`,
      );
      renderGainStack(upgradePath, latestVersion);
      // null → download-latest-release called without ?version param (downloads latest)
      selectedTargetVersion = null;
    } else {
      // ── Non-latest chosen — show security warning ─────────────────────────────
      // Use .text() — version strings are plain semver (no HTML), no escaping needed.
      const warningMsg = i18next.t(
        "Warning: {{chosen}} is missing security fixes included in {{latest}}. Only proceed if you have a specific reason.",
        { chosen, latest: latestVersion },
      );
      $("#advancedWarningText").text(warningMsg);
      $("#advancedWarningBanner").removeClass("d-none");
      $("#recommendedBadge").addClass("d-none");

      const chosenEntry = upgradePath.find((e) => e.version === chosen);
      const safeChosenHref = sanitizeChangelogUrl(chosenEntry?.changelogUrl);
      if (safeChosenHref) {
        $("#whatsNewChangelogLink").attr("href", safeChosenHref).removeClass("d-none");
      } else {
        $("#whatsNewChangelogLink").addClass("d-none");
      }
      installedChangelogUrl = safeChosenHref;

      $("#whatsNewVersion").text(chosen);
      $("#proceedToDownload").html(
        `<i class="fa fa-cloud-arrow-down me-1"></i>${i18next.t("Download & Apply {{version}}", { version: escapeHtml(chosen) })}`,
      );
      renderGainStack(upgradePath, chosen);
      selectedTargetVersion = chosen;
    }
  });
}

/**
 * Return a Tabler badge HTML string for a release type label.
 */
function badgeForType(type) {
  const labelMap = {
    major: i18next.t("Major"),
    minor: i18next.t("Feature"),
    patch: i18next.t("Bug Fix"),
  };
  const map = {
    major: "bg-danger-lt text-danger",
    minor: "bg-azure-lt text-azure",
    patch: "bg-secondary-lt text-secondary",
  };
  const cls = map[type] || map.patch;
  const label = labelMap[type] || escapeHtml(type);
  return `<span class="badge ${cls}">${label}</span>`;
}

/**
 * Minimal HTML escaping for user-supplied strings.
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
 * Auto-download update when the Download & Apply step is shown.
 */
function autoDownloadUpdate() {
  const $downloadStatus = $("#downloadStatus");

  // Update the step description to reflect the selected version (if any).
  if (selectedTargetVersion) {
    $("#downloadStepDescription").text(
      i18next.t("Download version {{version}} and apply it to your installation.", { version: selectedTargetVersion }),
    );
  }

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
 * Perform the actual download operation, using selectedTargetVersion if set.
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
        errorMessage = `<strong>${i18next.t("Failed to download update package.")}</strong><br>${escapeHtml(xhr.responseJSON.message)}`;
      } else if (xhr.responseText) {
        try {
          const response = JSON.parse(xhr.responseText);
          if (response.message) {
            errorMessage = `<strong>${i18next.t("Failed to download update package.")}</strong><br>${escapeHtml(response.message)}`;
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
            <i class="fa-solid fa-circle-xmark me-2"></i>${errorMessage}
        </div>`);

      $downloadStatus.append(`<button class="btn btn-warning mt-2" id="retryDownload">
                <i class="fa-solid fa-arrow-rotate-right me-2"></i>${i18next.t("Retry Download")}
            </button>`);

      $("#retryDownload").click(function () {
        $(this).remove();
        performDownload();
      });
    });
}

/**
 * Set up apply update step.
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

          // Show changelog link for the installed version.
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
          errorMessage = `<strong>${i18next.t("Upgrade failed.")}</strong><br>${escapeHtml(xhr.responseJSON.message)}`;
        } else if (xhr.responseText) {
          try {
            const response = JSON.parse(xhr.responseText);
            if (response.message) {
              errorMessage = `<strong>${i18next.t("Upgrade failed.")}</strong><br>${escapeHtml(response.message)}`;
            }
          } catch (_e) {
            errorMessage = `<strong>${i18next.t("Upgrade failed.")}</strong><br>${xhr.status}: ${xhr.statusText}`;
          }
        } else if (error) {
          errorMessage = `<strong>${i18next.t("Upgrade failed.")}</strong><br>${error}`;
        }

        $applyStatus.html(`<div class="alert alert-danger">
                <i class="fa-solid fa-circle-xmark me-2"></i>${errorMessage}
            </div>`);
        $button.prop("disabled", false).html(`<i class="fa fa-bolt me-1"></i>${i18next.t("Apply Update Now")}`);
      });
  });
}

/**
 * Download backup file.
 */
function downloadBackup(filename) {
  window.location = `${window.CRM.root}/admin/api/database/download/${filename}`;
  $("#backupStatus").html(`<div class="alert alert-info">
        <i class="fa-solid fa-info-circle me-2"></i>${i18next.t("Backup Downloaded, Copy on server removed")}
    </div>`);
}

window.UpgradeWizard = { downloadBackup };

/**
 * Setup refresh from GitHub button.
 */
function setupRefreshButton() {
  $("#refreshFromGitHub").click(function () {
    const $button = $(this);
    const $spinner = $("#upgradeSpinner");
    const $icon = $button.find("i");

    $button.prop("disabled", true);
    $icon.removeClass("fa-arrows-rotate").addClass("fa-circle-notch fa-spin");
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
        $icon.removeClass("fa-circle-notch fa-spin").addClass("fa-arrows-rotate");

        let errorMessage = i18next.t("Failed to refresh upgrade information from GitHub.");
        if (xhr.responseJSON?.message) {
          errorMessage = escapeHtml(xhr.responseJSON.message);
        }
        window.CRM.notify(errorMessage, { type: "error", delay: 5000 });
      });
  });
}

/**
 * Setup force reinstall button.
 */
function setupForceReinstallButton() {
  // Bind both buttons: the integrity-failure button (#forceReinstall) inside the
  // pre-flight step and the version-card button (#forceReinstallCurrent) shown
  // when the system is already up to date.
  $("#forceReinstall, #forceReinstallCurrent").click(() => {
    const modal = new bootstrap.Modal(document.getElementById("forceReinstallModal"));
    modal.show();
  });

  $("#confirmForceReinstall").click(() => {
    // Use getOrCreateInstance to safely close the modal even when the instance
    // was not created via Bootstrap's normal data-API (e.g. in tests).
    bootstrap.Modal.getOrCreateInstance(document.getElementById("forceReinstallModal")).hide();

    // Clear stale download state so the Download & Apply step starts fresh.
    window.CRM.updateFile = null;
    selectedTargetVersion = null;
    prereleaseTargetVersion = null;
    $("#downloadStatus").empty();
    $("#updateDetails").addClass("d-none");
    $("#applyButtonContainer").addClass("d-none");
    $("#applyStatus").empty();

    // Restore the Backup step UI — both buttons may have been hidden by a
    // prior backup/skip action and must be visible for the reinstall pass.
    $("#doBackup")
      .removeClass("d-none")
      .prop("disabled", false)
      .html(`<i class="fa fa-database me-1"></i>${i18next.t("Create Backup")}`);
    $("#skipBackup").removeClass("d-none");
    $("#backupStatus").empty();
    $("#resultFiles").empty();

    // Set forceReinstallMode before navigating so it is already true when
    // show.bs-stepper fires — no setTimeout ordering dependency required.
    forceReinstallMode = true;
    upgradeStepper.to(1);
    setTimeout(() => {
      upgradeStepper.to(2);
    }, 100);

    $("html, body").animate({ scrollTop: $("#upgrade-wizard-card").offset().top - 20 }, 500);

    window.CRM.notify(i18next.t("Force re-install initiated. Please backup your database before applying."), {
      type: "info",
      delay: 5000,
    });
  });
}
