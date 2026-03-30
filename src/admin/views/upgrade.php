<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="row">
    <div class="col-12">
        <!-- Version Information Card -->
        <div class="card mb-3">
            <div class="card-status-top bg-primary"></div>
            <div class="card-header">
                <h3 class="card-title mb-0">
                    <i class="fa fa-circle-info me-2"></i><?= gettext('Version Information') ?>
                </h3>
            </div>
            <div class="card-body">
                <div class="row g-3 align-items-center mb-3">
                    <div class="col-md-6">
                        <span class="text-secondary"><?= gettext('Current Version') ?></span>
                        <div class="mt-1">
                            <span class="badge bg-primary-lt fs-5"><?= InputUtils::escapeHTML($currentVersion) ?></span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <span class="text-secondary"><?= gettext('Latest GitHub Version') ?></span>
                        <div class="mt-1">
                            <?php if ($latestGitHubVersion !== null): ?>
                                <?php if ($isUpdateAvailable): ?>
                                    <span class="badge bg-success-lt fs-5"><?= InputUtils::escapeHTML($latestGitHubVersion) ?></span>
                                    <span class="badge bg-success ms-1"><?= gettext('Update Available') ?></span>
                                <?php else: ?>
                                    <span class="badge bg-primary-lt fs-5"><?= InputUtils::escapeHTML($latestGitHubVersion) ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge bg-secondary-lt fs-5"><?= gettext('Unknown') ?></span>
                                <small class="text-muted ms-1"><?= gettext('(refresh from GitHub)') ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
                    <button type="button" class="btn btn-ghost-primary btn-sm" id="refreshFromGitHub">
                        <i class="fa fa-sync me-1"></i><?= gettext('Refresh from GitHub') ?>
                    </button>
                </div>

                <?php if ($isUpdateAvailable): ?>
                    <div class="alert alert-info mb-0">
                        <div class="d-flex align-items-center">
                            <i class="fa fa-circle-info fa-lg me-2"></i>
                            <div>
                                <strong><?= gettext('Update Available!') ?></strong>
                                <?= gettext('A new version of ChurchCRM is available for installation.') ?>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-success mb-0">
                        <div class="d-flex align-items-center">
                            <i class="fa fa-circle-check fa-lg me-2"></i>
                            <div>
                                <strong><?= gettext('System Up to Date') ?></strong>
                                <?= gettext('Your ChurchCRM installation is running the latest version.') ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- File Integrity Check Card -->
        <?php
        $failingFiles = $integrityCheckData['files'] ?? [];
        $hasIntegrityIssues = count($failingFiles) > 0;
        $integrityPassed = !$hasIntegrityIssues;
        ?>
        <div class="card mb-3">
            <div class="card-status-top <?= $integrityPassed ? 'bg-success' : 'bg-warning' ?>"></div>
            <div class="card-header">
                <h3 class="card-title mb-0">
                    <i class="fa fa-shield-alt me-2"></i><?= gettext('File Integrity Check') ?>
                    <?php if ($hasIntegrityIssues): ?>
                        <span class="badge bg-warning-lt text-warning ms-2"><?= count($failingFiles) ?></span>
                    <?php endif; ?>
                </h3>
            </div>
            <div class="card-body">
                <?php if ($integrityPassed): ?>
                    <div class="empty py-4">
                        <div class="empty-icon"><i class="fa fa-circle-check text-success fa-3x"></i></div>
                        <p class="empty-title"><?= gettext('All Files Verified') ?></p>
                        <p class="empty-subtitle text-secondary"><?= gettext('All system files match their expected signatures.') ?></p>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning mb-3">
                        <div class="d-flex align-items-center">
                            <i class="fa fa-triangle-exclamation fa-lg me-2"></i>
                            <div>
                                <strong><?= gettext('File Integrity Issues Detected') ?></strong>
                                <?= sprintf(gettext('%d files have been modified or are missing. Use Force Re-install to restore official versions.'), count($failingFiles)) ?>
                            </div>
                        </div>
                    </div>

                    <?php
                    $missingFiles = [];
                    $modifiedFiles = [];
                    foreach ($failingFiles as $file) {
                        if (is_object($file) && isset($file->status) && $file->status === 'File Missing') {
                            $missingFiles[] = $file;
                        } else {
                            $modifiedFiles[] = $file;
                        }
                    }
                    ?>

                    <?php if (count($modifiedFiles) > 0): ?>
                        <div class="mb-2">
                            <a href="#collapseModifiedFiles" data-bs-toggle="collapse" class="text-warning text-decoration-none fw-medium">
                                <i class="fa fa-pen-to-square me-1"></i><?= gettext('Modified Files') ?> (<?= count($modifiedFiles) ?>)
                                <i class="fa fa-chevron-down ms-1 small"></i>
                            </a>
                        </div>
                        <div id="collapseModifiedFiles" class="collapse mb-3">
                            <div class="table-responsive">
                                <table class="table table-sm table-vcenter mb-0">
                                    <thead>
                                        <tr><th><?= gettext('File Name') ?></th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($modifiedFiles as $file): ?>
                                            <tr><td><code class="small"><?= InputUtils::escapeHTML(is_object($file) ? $file->filename : $file) ?></code></td></tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (count($missingFiles) > 0): ?>
                        <div class="mb-2">
                            <a href="#collapseMissingFilesCard" data-bs-toggle="collapse" class="text-danger text-decoration-none fw-medium">
                                <i class="fa fa-circle-xmark me-1"></i><?= gettext('Missing Files') ?> (<?= count($missingFiles) ?>)
                                <i class="fa fa-chevron-down ms-1 small"></i>
                            </a>
                        </div>
                        <div id="collapseMissingFilesCard" class="collapse mb-3">
                            <div class="table-responsive">
                                <table class="table table-sm table-vcenter mb-0">
                                    <thead>
                                        <tr><th><?= gettext('File Name') ?></th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($missingFiles as $file): ?>
                                            <tr><td><code class="small text-danger"><?= InputUtils::escapeHTML(is_object($file) ? $file->filename : $file) ?></code></td></tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>

                    <hr>
                    <button type="button" class="btn btn-warning" id="forceReinstall">
                        <i class="fa fa-redo me-1"></i><?= gettext('Force Re-install') ?>
                    </button>
                    <span class="text-secondary ms-2"><?= gettext('Re-download and re-apply the current version to restore all files to their official state') ?></span>
                <?php endif; ?>
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
                        <div class="step<?= $hasWarnings ? ' warning-step' : '' ?>" data-target="#step-warnings">
                            <button type="button" class="step-trigger" role="tab" aria-controls="step-warnings" id="step-warnings-trigger">
                                <span class="bs-stepper-circle"><i class="fa fa-triangle-exclamation"></i></span>
                                <span class="bs-stepper-label"><?= gettext('Warnings') ?></span>
                            </button>
                        </div>
                        <div class="line"></div>
                        <div class="step" data-target="#step-backup">
                            <button type="button" class="step-trigger" role="tab" aria-controls="step-backup" id="step-backup-trigger">
                                <span class="bs-stepper-circle"><i class="fa fa-database"></i></span>
                                <span class="bs-stepper-label"><?= gettext('Database Backup') ?></span>
                            </button>
                        </div>
                        <div class="line"></div>
                        <div class="step" data-target="#step-apply">
                            <button type="button" class="step-trigger" role="tab" aria-controls="step-apply" id="step-apply-trigger">
                                <span class="bs-stepper-circle"><i class="fa fa-cloud-arrow-down"></i></span>
                                <span class="bs-stepper-label"><?= gettext('Download & Apply') ?></span>
                            </button>
                        </div>
                        <div class="line"></div>
                        <div class="step" data-target="#step-complete">
                            <button type="button" class="step-trigger" role="tab" aria-controls="step-complete" id="step-complete-trigger">
                                <span class="bs-stepper-circle"><i class="fa fa-check"></i></span>
                                <span class="bs-stepper-label"><?= gettext('Complete') ?></span>
                            </button>
                        </div>
                    </div>

                    <div class="bs-stepper-content">
                        <!-- Step 1: Pre-Upgrade Checks -->
                        <div id="step-warnings" class="content p-4" role="tabpanel" aria-labelledby="step-warnings-trigger">
                            <?php if ($integrityCheckFailed): ?>
                                <div class="alert alert-warning mb-3">
                                    <h4 class="alert-title">
                                        <i class="fa fa-circle-exclamation me-1"></i><?= gettext('Signature Mismatch') ?>
                                    </h4>
                                    <p class="mt-2 mb-2">
                                        <?= gettext("Some system files have been modified since the last installation.") ?>
                                        <strong><?= gettext("This upgrade will revert them to the official version.") ?></strong>
                                    </p>
                                    <p class="mb-0 text-secondary">
                                        <i class="fa fa-lightbulb me-1"></i><?= gettext("To keep your changes, back up the modified files before upgrading and restore them afterward.") ?>
                                    </p>
                                </div>

                                <?php if (count($integrityCheckData['files']) > 0): ?>
                                    <?php
                                    $missingFiles = [];
                                    $modifiedFiles = [];
                                    foreach ($integrityCheckData['files'] as $file) {
                                        if ($file->status === 'File Missing') {
                                            $missingFiles[] = $file;
                                        } else {
                                            $modifiedFiles[] = $file;
                                        }
                                    }
                                    ?>

                                    <?php if (count($missingFiles) > 0): ?>
                                        <div class="mb-2">
                                            <a href="#collapseMissingFiles" data-bs-toggle="collapse" class="text-danger text-decoration-none fw-medium">
                                                <i class="fa fa-circle-xmark me-1"></i><?= gettext('Files Missing') ?> (<?= count($missingFiles) ?>)
                                                <i class="fa fa-chevron-down ms-1 small"></i>
                                            </a>
                                        </div>
                                        <div id="collapseMissingFiles" class="collapse mb-3">
                                            <div class="table-responsive">
                                                <table class="table table-sm table-vcenter mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th><?= gettext('File Name') ?></th>
                                                            <th><?= gettext('Expected Hash') ?></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($missingFiles as $file): ?>
                                                            <tr>
                                                                <td><code class="small"><?= InputUtils::escapeHTML($file->filename) ?></code></td>
                                                                <td><small class="text-secondary"><?= InputUtils::escapeHTML($file->expectedhash) ?></small></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (count($modifiedFiles) > 0): ?>
                                        <div class="mb-2">
                                            <a href="#collapseModifiedFiles" data-bs-toggle="collapse" class="text-warning text-decoration-none fw-medium">
                                                <i class="fa fa-pen-to-square me-1"></i><?= gettext('Files Modified') ?> (<?= count($modifiedFiles) ?>)
                                                <i class="fa fa-chevron-down ms-1 small"></i>
                                            </a>
                                        </div>
                                        <div id="collapseModifiedFiles" class="collapse mb-3">
                                            <div class="table-responsive">
                                                <table class="table table-sm table-vcenter mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th><?= gettext('File Name') ?></th>
                                                            <th><?= gettext('Expected Hash') ?></th>
                                                            <th><?= gettext('Actual Hash') ?></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($modifiedFiles as $file): ?>
                                                            <tr>
                                                                <td><code class="small"><?= InputUtils::escapeHTML($file->filename) ?></code></td>
                                                                <td><small class="text-secondary"><?= InputUtils::escapeHTML($file->expectedhash) ?></small></td>
                                                                <td><small class="text-secondary"><?= InputUtils::escapeHTML($file->actualhash) ?></small></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php if ($hasOrphanedFiles && isset($integrityCheckData['orphanedFiles']) && count($integrityCheckData['orphanedFiles']) > 0): ?>
                                <div class="alert alert-danger mb-3">
                                    <h4 class="alert-title">
                                        <i class="fa fa-triangle-exclamation me-1"></i><?= gettext('Orphaned Files Detected') ?>
                                    </h4>
                                    <p class="mt-2 mb-0">
                                        <?= gettext("Files exist on your server that are not part of the official release. They may be leftover from previous versions.") ?>
                                        <strong><?= gettext("Review and delete these files before or after the upgrade.") ?></strong>
                                    </p>
                                </div>

                                <div class="mb-2">
                                    <a href="#collapseOrphanedFiles" data-bs-toggle="collapse" class="text-danger text-decoration-none fw-medium">
                                        <i class="fa fa-trash me-1"></i><?= gettext('Orphaned Files') ?> (<?= count($integrityCheckData['orphanedFiles']) ?>)
                                        <i class="fa fa-chevron-down ms-1 small"></i>
                                    </a>
                                </div>
                                <div id="collapseOrphanedFiles" class="collapse mb-3">
                                    <div class="table-responsive">
                                        <table class="table table-sm table-vcenter mb-0">
                                            <thead>
                                                <tr><th><?= gettext('File Path') ?></th></tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($integrityCheckData['orphanedFiles'] as $orphanedFile): ?>
                                                    <tr><td><code class="small"><?= InputUtils::escapeHTML($orphanedFile) ?></code></td></tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!$hasWarnings): ?>
                                <div class="alert alert-success mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fa fa-circle-check fa-lg me-2"></i>
                                        <div>
                                            <strong><?= gettext('All Checks Passed') ?></strong>
                                            <?= gettext('No warnings found. You may proceed with the upgrade.') ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <button class="btn btn-primary" id="acceptWarnings">
                                <?php if ($hasWarnings): ?>
                                    <?= gettext('I Understand, Continue') ?> <i class="fa fa-arrow-right ms-1"></i>
                                <?php else: ?>
                                    <?= gettext('Continue to Backup') ?> <i class="fa fa-arrow-right ms-1"></i>
                                <?php endif; ?>
                            </button>
                        </div>

                        <!-- Step 2: Backup Database -->
                        <div id="step-backup" class="content p-4" role="tabpanel" aria-labelledby="step-backup-trigger">
                            <p class="text-secondary mb-3"><?= gettext('Create a database backup before applying the update. This is strongly recommended.') ?></p>

                            <div id="backupStatus"></div>
                            <div id="resultFiles"></div>

                            <div class="d-flex flex-wrap gap-2 mt-3" id="backupActions">
                                <button class="btn btn-primary" id="doBackup">
                                    <i class="fa fa-database me-1"></i><?= gettext('Create Backup') ?>
                                </button>
                                <button class="btn btn-ghost-secondary" id="skipBackup">
                                    <i class="fa fa-forward me-1"></i><?= gettext('Skip Backup') ?>
                                </button>
                            </div>

                            <div id="backupNavButtons" class="mt-3 d-none">
                                <button class="btn btn-primary" id="backup-next">
                                    <?= gettext('Continue to Download & Apply') ?> <i class="fa fa-arrow-right ms-1"></i>
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
                                        <div class="datagrid-title"><?= gettext('SHA1 Hash') ?></div>
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

<!-- Full Page Spinner Overlay -->
<div id="upgradeSpinner">
    <div class="spinner-content">
        <i class="fa fa-cog fa-spin spinner-icon"></i>
        <h3><?= gettext('Applying System Update...') ?></h3>
        <p><?= gettext('Please do not close this window or refresh the page.') ?></p>
        <p class="text-muted"><?= gettext('This may take several minutes.') ?></p>
    </div>
</div>

<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/upgrade-wizard.min.css') ?>">
<script src="<?= SystemURLs::assetVersioned('/skin/v2/upgrade-wizard.min.js') ?>"></script>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
?>

<!-- System Settings Panel Component -->
<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.css') ?>">
<script src="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
$(document).ready(function() {
    window.CRM.settingsPanel.init({
        container: '#upgradeSettingsPanel',
        title: i18next.t('Upgrade Settings'),
        icon: 'fa-solid fa-sliders',
        settings: [{ name: 'bAllowPrereleaseUpgrade', type: 'boolean', label: i18next.t('Allow Pre-release Upgrades'), tooltip: i18next.t("Allow system upgrades to releases marked as 'pre release' on GitHub") }],
        onSave: function() {
            window.CRM.notify(i18next.t('Settings saved. Refreshing upgrade info...'), { type: 'success', delay: 2000 });
            // Refresh upgrade info and reload to reflect pre-release change
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
