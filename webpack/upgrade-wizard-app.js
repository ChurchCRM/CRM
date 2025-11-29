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
    setupPrereleaseToggle();
    setupRefreshButton();
    setupForceReinstallButton();
    
    // Listen for step changes to auto-download when reaching apply step
    document.querySelector('#upgrade-stepper').addEventListener('show.bs-stepper', function (event) {
        // Auto-download when entering the apply step
        if (event.detail.to === 2) { // Index 2 is now the apply step (0: warnings, 1: backup, 2: apply)
            setTimeout(function() {
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

    // Skip Backup button handler
    $('#skipBackup').click(function () {
        const $statusIcon = $("#status-backup");
        const $backupStatus = $("#backupStatus");
        const $navButtons = $("#backupNavButtons");
        const $button = $(this);

        $backupStatus.html(`<div class="alert alert-warning" style="background-color: #fff3cd; border-color: #ffeaa7; color: #856404;">
            <i class="fa-solid fa-exclamation-triangle mr-2"></i><strong>${i18next.t('Backup Skipped')}</strong><br>
            ${i18next.t('You have chosen to skip the backup. It is strongly recommended to have a backup before proceeding with the upgrade.')}
        </div>`);
        $statusIcon.html('<i class="fa-solid fa-exclamation-triangle text-warning"></i>');
        $button.hide();
        $navButtons.show();
    });
}

/**
 * Auto-download update when step is shown
 */
function autoDownloadUpdate() {
    const $statusIcon = $("#status-apply");
    const $downloadStatus = $("#downloadStatus");
    
    // Check if already downloaded
    if (window.CRM.updateFile) {
        // Already downloaded, show details and apply button
        $("#updateDetails").show();
        $("#applyButtonContainer").show();
        return;
    }
    
    // Show that auto-download is happening
    $statusIcon.html('<i class="fa-solid fa-circle-notch fa-spin text-primary"></i>');
    $downloadStatus.html(`<div class="alert alert-info">
        <i class="fa-solid fa-cloud-download mr-2"></i>${i18next.t('Downloading latest release from GitHub...')}
    </div>`);
    
    performDownload();
}

/**
 * Perform the actual download operation
 */
function performDownload() {
    const $statusIcon = $("#status-apply");
    const $downloadStatus = $("#downloadStatus");

    window.CRM.APIRequest({
        type: 'GET',
        path: 'systemupgrade/download-latest-release',
    })
        .done(function (data) {
            $statusIcon.html('<i class="fa-solid fa-check text-success"></i>');
            window.CRM.updateFile = data;

            $downloadStatus.html(`<div class="alert alert-success">
            <i class="fa-solid fa-check-circle mr-2"></i>${i18next.t('Update package downloaded successfully.')}
        </div>`);

            // Show update details
            $("#updateFileName").text(data.fileName);
            $("#updateFullPath").text(data.fullPath);
            $("#releaseNotes").text(data.releaseNotes);
            $("#updateSHA1").text(data.sha1);
            $("#updateDetails").show();
            
            // Show apply button after download completes
            $("#applyButtonContainer").show();
        })
        .fail(function (xhr, status, error) {
            let errorMessage = i18next.t('Failed to download update package.');
            
            if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMessage = '<strong>' + i18next.t('Failed to download update package.') + '</strong><br>' + xhr.responseJSON.message;
            } else if (xhr.responseText) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.message) {
                        errorMessage = '<strong>' + i18next.t('Failed to download update package.') + '</strong><br>' + response.message;
                    }
                } catch (e) {
                    errorMessage = '<strong>' + i18next.t('Failed to download update package.') + '</strong><br>' + xhr.status + ': ' + xhr.statusText;
                }
            } else if (error) {
                errorMessage = '<strong>' + i18next.t('Failed to download update package.') + '</strong><br>' + error;
            }

            $downloadStatus.html(`<div class="alert alert-danger" style="background-color: #f8d7da; border-color: #f5c6cb; color: #721c24;">
            <i class="fa-solid fa-times-circle mr-2"></i>${errorMessage}
        </div>`);
            $statusIcon.html('<i class="fa-solid fa-times text-danger"></i>');
            
            // Show manual retry button
            $downloadStatus.append(`<button class="btn btn-warning mt-2" id="retryDownload">
                <i class="fa-solid fa-redo mr-2"></i>${i18next.t('Retry Download')}
            </button>`);
            
            $("#retryDownload").click(function() {
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
        const $statusIcon = $("#status-apply");
        const $applyStatus = $("#applyStatus");
        const $spinner = $("#upgradeSpinner");

        // Show full-page spinner
        $spinner.addClass('active');

        // Show loading state
        $statusIcon.html('<i class="fa-solid fa-circle-notch fa-spin text-primary"></i>');
        $button.prop('disabled', true);

        window.CRM.APIRequest({
            method: 'POST',
            path: 'systemupgrade/do-upgrade',
            data: JSON.stringify({
                fullPath: window.CRM.updateFile.fullPath,
                sha1: window.CRM.updateFile.sha1
            })
        })
            .done(function (data) {
                // Hide spinner
                $spinner.removeClass('active');
                
                $statusIcon.html('<i class="fa-solid fa-check text-success"></i>');
                $applyStatus.html(`<div class="alert alert-success" style="background-color: #d4edda; border-color: #c3e6cb; color: #155724;">
                <i class="fa-solid fa-check-circle mr-2"></i><strong>${i18next.t('System upgrade completed successfully!')}</strong>
            </div>`);

                // Auto-advance to final step
                setTimeout(function () {
                    upgradeStepper.next();
                }, 1000);
            })
            .fail(function (xhr, status, error) {
                // Hide spinner
                $spinner.removeClass('active');
                
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

/**
 * Setup pre-release upgrade toggle
 */
function setupPrereleaseToggle() {
    const $toggle = $('#bAllowPrereleaseUpgrade');
    let isTogglingProgrammatically = false;

    // Initialize bootstrap-toggle (checkbox is already set to correct value from PHP)
    $toggle.bootstrapToggle();

    // Handle toggle change
    $toggle.change(function () {
        if (isTogglingProgrammatically) {
            return;
        }

        const newValue = $(this).prop('checked');
        const $spinner = $("#upgradeSpinner");

        // Show spinner
        $spinner.addClass('active');

        // Save the new value (convert boolean to string '1' or '0')
        window.CRM.APIRequest({
            method: 'POST',
            path: 'system/config/bAllowPrereleaseUpgrade',
            data: JSON.stringify({ value: newValue ? '1' : '0' })
        }).done(function () {
            // Refresh upgrade info from GitHub
            window.CRM.APIRequest({
                method: 'POST',
                path: 'systemupgrade/refresh-upgrade-info'
            }).done(function (data) {
                $spinner.removeClass('active');
                window.CRM.notify(i18next.t('Setting saved. Reloading page...'), {
                    type: 'success',
                    delay: 1500
                });
                
                // Reload the page after a short delay
                setTimeout(function () {
                    window.location.reload();
                }, 1500);
            }).fail(function () {
                $spinner.removeClass('active');
                window.CRM.notify(i18next.t('Failed to refresh upgrade information. Please try again.'), {
                    type: 'error',
                    delay: 5000
                });
                
                // Revert the toggle on failure
                isTogglingProgrammatically = true;
                $toggle.prop('checked', !newValue).change();
                isTogglingProgrammatically = false;
            });
        }).fail(function () {
            $spinner.removeClass('active');
            window.CRM.notify(i18next.t('Failed to save setting. Please try again.'), {
                type: 'error',
                delay: 5000
            });
            
            // Revert the toggle
            isTogglingProgrammatically = true;
            $toggle.prop('checked', !newValue).change();
            isTogglingProgrammatically = false;
        });
    });
}

/**
 * Setup refresh from GitHub button
 */
function setupRefreshButton() {
    $('#refreshFromGitHub').click(function () {
        const $button = $(this);
        const $spinner = $("#upgradeSpinner");
        const $icon = $button.find('i');
        
        // Disable button and show spinner
        $button.prop('disabled', true);
        $icon.removeClass('fa-sync').addClass('fa-circle-notch fa-spin');
        $spinner.addClass('active');

        // Call refresh API
        window.CRM.APIRequest({
            method: 'POST',
            path: 'systemupgrade/refresh-upgrade-info'
        }).done(function (data) {
            $spinner.removeClass('active');
            window.CRM.notify(i18next.t('Upgrade information refreshed. Reloading page...'), {
                type: 'success',
                delay: 1500
            });
            
            // Reload the page after a short delay
            setTimeout(function () {
                window.location.reload();
            }, 1500);
        }).fail(function (xhr, status, error) {
            $spinner.removeClass('active');
            $button.prop('disabled', false);
            $icon.removeClass('fa-circle-notch fa-spin').addClass('fa-sync');
            
            
            let errorMessage = i18next.t('Failed to refresh upgrade information from GitHub.');
            if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMessage = xhr.responseJSON.message;
            }
            
            window.CRM.notify(errorMessage, {
                type: 'error',
                delay: 5000
            });
        });
    });
}

/**
 * Setup force reinstall button - allows re-downloading and applying the current version
 */
function setupForceReinstallButton() {
    $('#forceReinstall').click(function () {
        // Confirm the action
        if (!confirm(i18next.t('This will re-download and re-apply the current version. This can fix corrupted or modified files. Continue?'))) {
            return;
        }

        // Show the upgrade wizard card if hidden
        $('#upgrade-wizard-card').addClass('show');

        // Reset the stepper to the beginning and then navigate to the backup step
        upgradeStepper.to(0);
        
        // Small delay to ensure stepper is ready, then advance to backup step
        setTimeout(function() {
            upgradeStepper.to(1); // Go to backup step
        }, 100);

        // Scroll to the wizard
        $('html, body').animate({
            scrollTop: $('#upgrade-wizard-card').offset().top - 20
        }, 500);

        window.CRM.notify(i18next.t('Force re-install initiated. Please backup your database before applying.'), {
            type: 'info',
            delay: 5000
        });
    });
}
