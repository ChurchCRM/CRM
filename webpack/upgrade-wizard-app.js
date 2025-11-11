/**
 * Upgrade Wizard Application Logic
 * Handles the upgrade wizard workflow using bs-stepper
 */

import Stepper from 'bs-stepper';
import 'bs-stepper/dist/css/bs-stepper.min.css';

let upgradeStepper;

/**
 * Initialize the upgrade wizard when DOM is ready
 */
$(document).ready(function () {
    // Initialize bs-stepper
    upgradeStepper = new Stepper(document.querySelector('#upgrade-stepper'), {
        linear: true,
        animation: true
    });

    // Set up event handlers
    setupNavigationHandlers();
    setupStepHandlers();
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

    // Fetch step navigation
    $("#fetch-previous").click(function () {
        upgradeStepper.previous();
    });

    $("#fetch-next").click(function () {
        upgradeStepper.next();
    });

    // Apply step navigation
    $("#apply-previous").click(function () {
        upgradeStepper.previous();
    });

    $("#apply-next").click(function () {
        upgradeStepper.next();
    });
}

/**
 * Set up handlers for each step's actions
 */
function setupStepHandlers() {
    setupBackupStep();
    setupFetchStep();
    setupApplyStep();
}

/**
 * Set up database backup step
 */
function setupBackupStep() {
    $("#doBackup").click(function () {
        const $button = $(this);
        const $statusIcon = $("#status-backup");
        const $backupStatus = $("#backupStatus");
        const $resultFiles = $("#resultFiles");
        const $navButtons = $("#backupNavButtons");

        // Show loading state
        $statusIcon.html('<i class="fa-solid fa-circle-notch fa-spin text-primary"></i>');
        $button.prop('disabled', true);

        window.CRM.APIRequest({
            method: 'POST',
            path: 'database/backup',
            data: JSON.stringify({
                'BackupType': 3
            })
        })
            .done(function (data) {
                const downloadButton = `<button class="btn btn-success btn-lg btn-block" id="downloadbutton" role="button" onclick="window.UpgradeWizard.downloadBackup('${data.BackupDownloadFileName}')" style="max-width: 500px;">
                <i class="fa-solid fa-download mr-2"></i>${data.BackupDownloadFileName}
            </button>`;

                $backupStatus.html(`<div class="alert alert-success" style="background-color: #d4edda; border-color: #c3e6cb; color: #155724;">
                <i class="fa-solid fa-check-circle mr-2"></i><strong>${i18next.t('Backup Complete, Ready for Download.')}</strong>
            </div>`);
                $resultFiles.html(downloadButton);
                $statusIcon.html('<i class="fa-solid fa-check text-success"></i>');
                $navButtons.show();

                // Handle download button click
                $("#downloadbutton").click(function () {
                    $(this).prop('disabled', true).html(`<i class="fa-solid fa-check mr-2"></i>${i18next.t('Downloaded')}`);
                });
            })
            .fail(function (xhr, status, error) {
                let errorMessage = i18next.t('Failed to create backup.');
                
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = '<strong>' + i18next.t('Failed to create backup.') + '</strong><br>' + xhr.responseJSON.message;
                } else if (xhr.responseText) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.message) {
                            errorMessage = '<strong>' + i18next.t('Failed to create backup.') + '</strong><br>' + response.message;
                        }
                    } catch (e) {
                        errorMessage = '<strong>' + i18next.t('Failed to create backup.') + '</strong><br>' + xhr.status + ': ' + xhr.statusText;
                    }
                } else if (error) {
                    errorMessage = '<strong>' + i18next.t('Failed to create backup.') + '</strong><br>' + error;
                }

                $backupStatus.html(`<div class="alert alert-danger" style="background-color: #f8d7da; border-color: #f5c6cb; color: #721c24;">
                <i class="fa-solid fa-times-circle mr-2"></i>${errorMessage}
            </div>`);
                $statusIcon.html('<i class="fa-solid fa-times text-danger"></i>');
                $button.prop('disabled', false);
            });
    });
}

/**
 * Set up fetch update step
 */
function setupFetchStep() {
    $("#fetchUpdate").click(function () {
        const $button = $(this);
        const $statusIcon = $("#status-fetch");
        const $fetchStatus = $("#fetchStatus");
        const $nextButton = $("#fetch-next");

        // Show loading state
        $statusIcon.html('<i class="fa-solid fa-circle-notch fa-spin text-primary"></i>');
        $button.prop('disabled', true);

        window.CRM.APIRequest({
            type: 'GET',
            path: 'systemupgrade/downloadlatestrelease',
        })
            .done(function (data) {
                $statusIcon.html('<i class="fa-solid fa-check text-success"></i>');
                window.CRM.updateFile = data;

                $fetchStatus.html(`<div class="alert alert-success">
                <i class="fa-solid fa-check-circle mr-2"></i>${i18next.t('Update package downloaded successfully.')}
            </div>`);
                $nextButton.show();

                // Auto-advance to next step after fetch
                setTimeout(function () {
                    upgradeStepper.next();

                    // Show update details
                    $("#updateFileName").text(data.fileName);
                    $("#updateFullPath").text(data.fullPath);
                    $("#releaseNotes").text(data.releaseNotes);
                    $("#updateSHA1").text(data.sha1);
                    $("#updateDetails").show();
                }, 500);
            })
            .fail(function (xhr, status, error) {
                let errorMessage = i18next.t('Failed to fetch update package.');
                
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = '<strong>' + i18next.t('Failed to fetch update package.') + '</strong><br>' + xhr.responseJSON.message;
                } else if (xhr.responseText) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.message) {
                            errorMessage = '<strong>' + i18next.t('Failed to fetch update package.') + '</strong><br>' + response.message;
                        }
                    } catch (e) {
                        errorMessage = '<strong>' + i18next.t('Failed to fetch update package.') + '</strong><br>' + xhr.status + ': ' + xhr.statusText;
                    }
                } else if (error) {
                    errorMessage = '<strong>' + i18next.t('Failed to fetch update package.') + '</strong><br>' + error;
                }

                $fetchStatus.html(`<div class="alert alert-danger" style="background-color: #f8d7da; border-color: #f5c6cb; color: #721c24;">
                <i class="fa-solid fa-times-circle mr-2"></i>${errorMessage}
            </div>`);
                $statusIcon.html('<i class="fa-solid fa-times text-danger"></i>');
                $button.prop('disabled', false);
            });
    });
}

/**
 * Set up apply update step
 */
function setupApplyStep() {
    $("#applyUpdate").click(function () {
        const $button = $(this);
        const $statusIcon = $("#status-apply");
        const $applyStatus = $("#applyStatus");
        const $nextButton = $("#apply-next");

        // Show loading state
        $statusIcon.html('<i class="fa-solid fa-circle-notch fa-spin text-primary"></i>');
        $button.prop('disabled', true);

        window.CRM.APIRequest({
            method: 'POST',
            path: 'systemupgrade/doupgrade',
            data: JSON.stringify({
                fullPath: window.CRM.updateFile.fullPath,
                sha1: window.CRM.updateFile.sha1
            })
        })
            .done(function (data) {
                $statusIcon.html('<i class="fa-solid fa-check text-success"></i>');
                $applyStatus.html(`<div class="alert alert-success" style="background-color: #d4edda; border-color: #c3e6cb; color: #155724;">
                <i class="fa-solid fa-check-circle mr-2"></i><strong>${i18next.t('System upgrade completed successfully!')}</strong>
            </div>`);
                $nextButton.show();

                // Auto-advance to final step
                setTimeout(function () {
                    upgradeStepper.next();
                }, 1000);
            })
            .fail(function (xhr, status, error) {
                let errorMessage = i18next.t('Upgrade failed. Please check the logs.');
                
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = '<strong>' + i18next.t('Upgrade failed.') + '</strong><br>' + xhr.responseJSON.message;
                } else if (xhr.responseText) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.message) {
                            errorMessage = '<strong>' + i18next.t('Upgrade failed.') + '</strong><br>' + response.message;
                        }
                    } catch (e) {
                        errorMessage = '<strong>' + i18next.t('Upgrade failed.') + '</strong><br>' + xhr.status + ': ' + xhr.statusText;
                    }
                } else if (error) {
                    errorMessage = '<strong>' + i18next.t('Upgrade failed.') + '</strong><br>' + error;
                }

                $applyStatus.html(`<div class="alert alert-danger" style="background-color: #f8d7da; border-color: #f5c6cb; color: #721c24;">
                <i class="fa-solid fa-times-circle mr-2"></i>${errorMessage}
            </div>`);
                $statusIcon.html('<i class="fa-solid fa-times text-danger"></i>');
                $button.prop('disabled', false);
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
        <i class="fa-solid fa-info-circle mr-2"></i>${i18next.t('Backup Downloaded, Copy on server removed')}
    </div>`);
}

// Export functions to global scope for onclick handlers
window.UpgradeWizard = {
    downloadBackup
};
