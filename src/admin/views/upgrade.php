<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

// Integrity data — files are plain strings (filenames), not objects
$failingFiles = $integrityCheckData['files'] ?? [];
$orphanedCount = count($integrityCheckData['orphanedFiles'] ?? []);
?>

<div class="row">
    <div class="col-12">
        <!-- Version Information -->
        <div class="card mb-3">
            <div class="card-body py-3">
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-secondary"><?= gettext('Installed') ?></span>
                        <span class="badge bg-primary-lt fs-5"><?= InputUtils::escapeHTML($currentVersion) ?></span>
                    </div>
                    <?php if ($latestGitHubVersion !== null): ?>
                        <i class="fa fa-arrow-right text-secondary"></i>
                        <div class="d-flex align-items-center gap-2">
                            <span class="text-secondary"><?= gettext('Latest') ?></span>
                            <?php if ($isUpdateAvailable): ?>
                                <span class="badge bg-success-lt fs-5"><?= InputUtils::escapeHTML($latestGitHubVersion) ?></span>
                                <span class="badge bg-success"><?= gettext('Update Available') ?></span>
                            <?php else: ?>
                                <span class="badge bg-primary-lt fs-5"><?= InputUtils::escapeHTML($latestGitHubVersion) ?></span>
                                <span class="badge bg-success-lt text-success"><?= gettext('Up to Date') ?></span>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <span class="badge bg-secondary-lt"><?= gettext('Latest version unknown') ?></span>
                    <?php endif; ?>
                    <div class="ms-auto">
                        <button type="button" class="btn btn-ghost-primary btn-sm" id="refreshFromGitHub">
                            <i class="fa fa-sync me-1"></i><?= gettext('Refresh') ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upgrade Wizard -->
        <div class="card" id="upgrade-wizard-card">
            <div class="card-header">
                <h3 class="card-title mb-0">
                    <i class="fa fa-arrow-up-right-dots me-2"></i><?= gettext('System Upgrade Wizard') ?>
                </h3>
            </div>
            <div class="card-body p-0">
                <div id="upgrade-stepper" class="bs-stepper">
                    <div class="bs-stepper-header" role="tablist">
                        <div class="step<?= $hasWarnings ? ' warning-step' : ' ok-step' ?>" data-bs-target="#step-warnings">
                            <button type="button" class="step-trigger" role="tab" aria-controls="step-warnings" id="step-warnings-trigger">
                                <span class="bs-stepper-circle">
                                    <?php if ($hasWarnings): ?>
                                        <i class="fa fa-triangle-exclamation"></i>
                                    <?php else: ?>
                                        <i class="fa fa-circle-check"></i>
                                    <?php endif; ?>
                                </span>
                                <span class="bs-stepper-label"><?= gettext('Pre-flight') ?></span>
                            </button>
                        </div>
                        <div class="line"></div>
                        <div class="step" data-bs-target="#step-backup">
                            <button type="button" class="step-trigger" role="tab" aria-controls="step-backup" id="step-backup-trigger">
                                <span class="bs-stepper-circle"><i class="fa fa-database"></i></span>
                                <span class="bs-stepper-label"><?= gettext('Backup') ?></span>
                            </button>
                        </div>
                        <div class="line"></div>
                        <div class="step" data-bs-target="#step-apply">
                            <button type="button" class="step-trigger" role="tab" aria-controls="step-apply" id="step-apply-trigger">
                                <span class="bs-stepper-circle"><i class="fa fa-cloud-arrow-down"></i></span>
                                <span class="bs-stepper-label"><?= gettext('Download & Apply') ?></span>
                            </button>
                        </div>
                        <div class="line"></div>
                        <div class="step" data-bs-target="#step-complete">
                            <button type="button" class="step-trigger" role="tab" aria-controls="step-complete" id="step-complete-trigger">
                                <span class="bs-stepper-circle"><i class="fa fa-check"></i></span>
                                <span class="bs-stepper-label"><?= gettext('Complete') ?></span>
                            </button>
                        </div>
                    </div>

                    <div class="bs-stepper-content">
                        <!-- Step 1: Pre-flight Checks -->
                        <div id="step-warnings" class="content p-4" role="tabpanel" aria-labelledby="step-warnings-trigger">
                            <?php if ($integrityCheckFailed): ?>
                                <div class="alert alert-warning mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fa fa-circle-exclamation fa-lg me-2"></i>
                                        <div class="flex-fill">
                                            <strong><?= gettext('Signature Mismatch') ?></strong>
                                            <span class="badge bg-warning-lt text-warning ms-1"><?= count($failingFiles) ?></span>
                                            — <?= gettext("Modified files will be reverted to the official version.") ?>
                                            <div class="text-secondary mt-1 small">
                                                <i class="fa fa-lightbulb me-1"></i><?= gettext("Back up modified files before upgrading if you want to keep your changes.") ?>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-outline-warning btn-sm ms-3 text-nowrap" id="forceReinstall">
                                            <i class="fa fa-redo me-1"></i><?= gettext('Force Re-install') ?>
                                        </button>
                                    </div>
                                </div>

                                <?php if (count($failingFiles) > 0): ?>
                                    <div class="mb-3">
                                        <a href="#collapseModifiedFiles" data-bs-toggle="collapse" class="text-warning text-decoration-none fw-medium">
                                            <i class="fa fa-pen-to-square me-1"></i><?= gettext('Affected Files') ?> (<?= count($failingFiles) ?>)
                                            <i class="fa fa-chevron-down ms-1 small"></i>
                                        </a>
                                    </div>
                                    <div id="collapseModifiedFiles" class="collapse mb-3">
                                        <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                                            <table class="table table-sm table-vcenter mb-0">
                                                <tbody>
                                                    <?php foreach ($failingFiles as $file): ?>
                                                        <tr><td><code class="small"><?= InputUtils::escapeHTML($file) ?></code></td></tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php if ($orphanedCount > 0): ?>
                                <div class="alert alert-danger mb-3">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div>
                                            <i class="fa fa-triangle-exclamation me-1"></i>
                                            <strong><?= gettext('Orphaned Files') ?></strong>
                                            <span class="badge bg-danger-lt text-danger ms-1"><?= $orphanedCount ?></span>
                                            — <?= gettext("Files not part of the official release were found on your server.") ?>
                                        </div>
                                        <a href="<?= SystemURLs::getRootPath() ?>/admin/system/orphaned-files" class="btn btn-outline-danger btn-sm ms-3 text-nowrap">
                                            <i class="fa fa-external-link me-1"></i><?= gettext('Review & Delete') ?>
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!$hasWarnings): ?>
                                <div class="alert alert-success mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fa fa-circle-check fa-lg me-2"></i>
                                        <div>
                                            <strong><?= gettext('All Checks Passed') ?></strong>
                                            — <?= gettext('No issues found. You may proceed with the upgrade.') ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <button class="btn btn-primary" id="acceptWarnings">
                                <?= gettext('Continue') ?> <i class="fa fa-arrow-right ms-1"></i>
                            </button>
                        </div>

                        <!-- Step 2: Database Backup -->
                        <div id="step-backup" class="content p-4" role="tabpanel" aria-labelledby="step-backup-trigger">
                            <p class="text-secondary mb-3"><?= gettext('Create a database backup before applying the update. This is strongly recommended.') ?></p>

                            <div id="backupStatus"></div>
                            <div id="resultFiles" class="mb-3"></div>

                            <div class="d-flex flex-wrap gap-2" id="backupActions">
                                <button class="btn btn-primary" id="doBackup">
                                    <i class="fa fa-database me-1"></i><?= gettext('Create Backup') ?>
                                </button>
                                <button class="btn btn-ghost-secondary" id="skipBackup">
                                    <?= gettext('Skip, Continue Without Backup') ?> <i class="fa fa-arrow-right ms-1"></i>
                                </button>
                                <button class="btn btn-primary d-none" id="backup-next">
                                    <?= gettext('Continue') ?> <i class="fa fa-arrow-right ms-1"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Step 3: Download and Apply Update -->
                        <div id="step-apply" class="content p-4" role="tabpanel" aria-labelledby="step-apply-trigger">
                            <p class="text-secondary mb-3"><?= gettext('Download the latest release and apply it to your installation.') ?></p>

                            <div id="downloadStatus"></div>

                            <div id="updateDetails" class="d-none mb-3">
                                <div class="datagrid mb-3">
                                    <div class="datagrid-item">
                                        <div class="datagrid-title"><?= gettext('File Name') ?></div>
                                        <div class="datagrid-content" id="updateFileName"></div>
                                    </div>
                                    <div class="datagrid-item">
                                        <div class="datagrid-title">SHA1 Hash</div>
                                        <div class="datagrid-content"><code id="updateSHA1"></code></div>
                                    </div>
                                    <div class="datagrid-item">
                                        <div class="datagrid-title"><?= gettext('Full Path') ?></div>
                                        <div class="datagrid-content"><code id="updateFullPath" class="small"></code></div>
                                    </div>
                                </div>
                                <div>
                                    <h4 class="mb-2"><?= gettext('Release Notes') ?></h4>
                                    <div id="releaseNotes" class="release-notes p-3 border rounded"></div>
                                </div>
                            </div>

                            <div id="applyStatus"></div>

                            <div class="d-none" id="applyButtonContainer">
                                <button class="btn btn-danger" id="applyUpdate">
                                    <i class="fa fa-bolt me-1"></i><?= gettext('Apply Update Now') ?>
                                </button>
                            </div>
                        </div>

                        <!-- Step 4: Complete -->
                        <div id="step-complete" class="content p-4" role="tabpanel" aria-labelledby="step-complete-trigger">
                            <div class="empty py-5">
                                <div class="empty-icon"><i class="fa fa-circle-check text-success fa-4x"></i></div>
                                <p class="empty-title h2"><?= gettext('Upgrade Complete!') ?></p>
                                <p class="empty-subtitle text-secondary"><?= gettext('Your ChurchCRM installation has been successfully upgraded.') ?></p>
                                <div class="alert alert-info text-start mx-auto mt-3" style="max-width: 480px;">
                                    <ul class="mb-0">
                                        <li><?= gettext('Application files updated to latest version') ?></li>
                                        <li><?= gettext('Database schema upgraded automatically') ?></li>
                                        <li><?= gettext('Orphaned files from previous versions cleaned up') ?></li>
                                    </ul>
                                </div>
                                <div class="mt-4 text-secondary">
                                    <div class="spinner-border spinner-border-sm me-1" role="status"></div>
                                    <span id="upgradeRedirectCountdown"><?= gettext('Redirecting in') ?> <strong>5</strong> <?= gettext('seconds...') ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Force Re-install Confirmation Modal -->
<div class="modal fade" id="forceReinstallModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-status bg-warning"></div>
            <div class="modal-body text-center py-4">
                <i class="fa fa-triangle-exclamation fa-3x text-warning mb-3"></i>
                <h3><?= gettext('Force Re-install?') ?></h3>
                <p class="text-secondary"><?= gettext('This will re-download and re-apply the current version. It can fix corrupted or modified files.') ?></p>
            </div>
            <div class="modal-footer">
                <div class="w-100">
                    <div class="row">
                        <div class="col">
                            <button type="button" class="btn w-100" data-bs-dismiss="modal"><?= gettext('Cancel') ?></button>
                        </div>
                        <div class="col">
                            <button type="button" class="btn btn-warning w-100" id="confirmForceReinstall"><?= gettext('Re-install') ?></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Full Page Spinner Overlay -->
<div id="upgradeSpinner">
    <div class="spinner-content">
        <i class="fa fa-cog fa-spin spinner-icon"></i>
        <h3><?= gettext('Applying System Update...') ?></h3>
        <p><?= gettext('Please do not close this window or refresh the page.') ?></p>
        <p class="text-body-secondary"><?= gettext('This may take several minutes.') ?></p>
    </div>
</div>

<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/upgrade-wizard.min.css') ?>">
<script src="<?= SystemURLs::assetVersioned('/skin/v2/upgrade-wizard.min.js') ?>"></script>

<!-- System Settings Panel Component -->
<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.css') ?>">
<script src="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
$(document).ready(function() {
    window.CRM.settingsPanel.init({
        container: '#upgradeSettingsPanel',
        title: <?= json_encode(gettext('Upgrade Settings'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        icon: 'fa-solid fa-sliders',
        settings: [{ name: 'bAllowPrereleaseUpgrade', type: 'boolean', label: <?= json_encode(gettext('Allow Pre-release Upgrades'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, tooltip: <?= json_encode(gettext("Allow system upgrades to releases marked as 'pre release' on GitHub"), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?> }],
        onSave: function() {
            window.CRM.notify(i18next.t('Settings saved. Refreshing upgrade info...'), { type: 'success', delay: 2000 });
            window.CRM.AdminAPIRequest({
                method: 'POST',
                path: 'upgrade/refresh-upgrade-info'
            }).always(function() {
                setTimeout(function() { window.location.reload(); }, 1500);
            });
        }
    });
});
</script>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
?>
