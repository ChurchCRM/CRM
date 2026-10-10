<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

// Integrity data — files are plain strings (filenames), not objects
$failingFiles = $integrityCheckData['files'] ?? [];
$orphanedCount = count($integrityCheckData['orphanedFiles'] ?? []);

// max_execution_time warning — long upgrades can be killed on shared hosting
$maxExec = (int) ini_get('max_execution_time');
$execTimeWarning = null;
if ($maxExec > 0 && $maxExec < 60) {
    $execTimeWarning = 'danger';
} elseif ($maxExec > 0 && $maxExec < 120) {
    $execTimeWarning = 'warning';
}
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
                            <?php elseif (isset($isAheadOfStable) && $isAheadOfStable): ?>
                                <span class="badge bg-primary-lt fs-5"><?= InputUtils::escapeHTML($latestGitHubVersion) ?></span>
                                <span class="badge bg-warning-lt text-warning"><?= gettext('Running Pre-release') ?></span>
                            <?php else: ?>
                                <span class="badge bg-primary-lt fs-5"><?= InputUtils::escapeHTML($latestGitHubVersion) ?></span>
                                <span class="badge bg-success-lt text-success"><?= gettext('Up to Date') ?></span>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <span class="badge bg-secondary-lt"><?= gettext('Latest version unknown') ?></span>
                    <?php endif; ?>
                    <div class="ms-auto d-flex align-items-center gap-2">
                        <?php if (!$isUpdateAvailable && $latestGitHubVersion !== null): ?>
                        <button type="button" class="btn btn-outline-warning btn-sm" id="forceReinstallCurrent">
                            <i class="fa fa-arrow-rotate-right me-1"></i><?= gettext('Force Re-install') ?>
                        </button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-ghost-primary btn-sm" id="refreshFromGitHub">
                            <i class="fa fa-arrows-rotate me-1"></i><?= gettext('Refresh') ?>
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
                        <div class="step<?= $hasWarnings ? ' warning-step' : ' ok-step' ?>" data-target="#step-warnings">
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
                        <div class="step" data-target="#step-backup">
                            <button type="button" class="step-trigger" role="tab" aria-controls="step-backup" id="step-backup-trigger">
                                <span class="bs-stepper-circle"><i class="fa fa-database"></i></span>
                                <span class="bs-stepper-label"><?= gettext('Backup') ?></span>
                            </button>
                        </div>
                        <div class="line"></div>
                        <div class="step" data-target="#step-whats-new">
                            <button type="button" class="step-trigger" role="tab" aria-controls="step-whats-new" id="step-whats-new-trigger">
                                <span class="bs-stepper-circle"><i class="fa fa-newspaper"></i></span>
                                <span class="bs-stepper-label"><?= gettext("What's New") ?></span>
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
                                            <i class="fa fa-arrow-rotate-right me-1"></i><?= gettext('Force Re-install') ?>
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
                                            <i class="fa fa-arrow-up-right-from-square me-1"></i><?= gettext('Review & Delete') ?>
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($execTimeWarning !== null): ?>
                                <div class="alert alert-<?= $execTimeWarning ?> mb-3">
                                    <div class="d-flex align-items-start">
                                        <i class="fa fa-clock fa-lg me-2 mt-1"></i>
                                        <div>
                                            <strong><?= gettext('Low PHP max_execution_time') ?></strong>
                                            <span class="badge bg-<?= $execTimeWarning ?>-lt text-<?= $execTimeWarning ?> ms-1"><?= $maxExec ?>s</span>
                                            <div class="mt-1">
                                                <?php if ($execTimeWarning === 'danger'): ?>
                                                    <?= gettext('Your PHP max_execution_time is very low. An in-app upgrade is likely to be killed before it finishes, leaving the database between versions. Raise the limit (120s or higher) in your host control panel, or run the database upgrade via CLI / a longer-running process.') ?>
                                                <?php else: ?>
                                                    <?= gettext('Your PHP max_execution_time may be too low for a long upgrade. Consider raising it to 120s or higher before proceeding, especially if you are jumping several versions.') ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!$hasWarnings && $execTimeWarning === null): ?>
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
