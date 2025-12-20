<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="row">
    <div class="col-12">
        <!-- Version Information Card -->
        <div class="card mb-3">
            <div class="card-header bg-primary text-white">
                <h3 class="card-title mb-0">
                    <i class="fa fa-info-circle mr-2"></i><?= gettext('Version Information') ?>
                </h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <strong><?= gettext('Current Version:') ?></strong>
                            <span class="badge badge-info ml-2" style="font-size: 1em;"><?= InputUtils::escapeHTML($currentVersion) ?></span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <strong><?= gettext('Latest GitHub Version:') ?></strong>
                            <?php if ($latestGitHubVersion !== null): ?>
                                <?php if ($isUpdateAvailable): ?>
                                    <span class="badge badge-success ml-2" style="font-size: 1em;"><?= InputUtils::escapeHTML($latestGitHubVersion) ?></span>
                                    <small class="text-success ml-1"><?= gettext('Update Available!') ?></small>
                                <?php else: ?>
                                    <span class="badge badge-info ml-2" style="font-size: 1em;"><?= InputUtils::escapeHTML($latestGitHubVersion) ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge badge-secondary ml-2" style="font-size: 1em;"><?= gettext('Unknown') ?></span>
                                <small class="text-muted ml-1"><?= gettext('(refresh from GitHub)') ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="mb-3">
                            <strong><?= gettext('Allow Pre-release Upgrades') ?>:</strong>
                            <input type="checkbox" id="bAllowPrereleaseUpgrade" data-toggle="toggle" data-size="sm" data-onstyle="success" data-offstyle="secondary"<?= $allowPrereleaseUpgrade ? ' checked' : '' ?>>
                            <small class="form-text text-muted d-inline-block ml-2">
                                <?= InputUtils::escapeHTML($prereleaseConfig->getTooltip()) ?>
                            </small>
                        </div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-12">
                        <button type="button" class="btn btn-info btn-sm" id="refreshFromGitHub">
                            <i class="fa fa-sync mr-2"></i><?= gettext('Refresh from GitHub') ?>
                        </button>
                        <small class="form-text text-muted d-inline-block ml-2">
                            <?= gettext('Check GitHub for the latest release information') ?>
                        </small>
                    </div>
                </div>
                
                <?php if ($isUpdateAvailable): ?>
                    <div class="alert alert-info mb-0" style="background-color: #d1ecf1; border-color: #bee5eb; color: #0c5460;">
                        <i class="fa fa-info-circle mr-2"></i>
                        <strong><?= gettext('Update Available!') ?></strong>
                        <?= gettext('A new version of ChurchCRM is available for installation.') ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-success mb-0" style="background-color: #d4edda; border-color: #c3e6cb; color: #155724;">
                        <i class="fa fa-check-circle mr-2"></i>
                        <strong><?= gettext('System Up to Date') ?></strong>
                        <?= gettext('Your ChurchCRM installation is running the latest version.') ?>
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
            <div class="card-header <?= $integrityPassed ? 'bg-success' : 'bg-warning' ?> text-white">
                <h3 class="card-title mb-0">
                    <i class="fa fa-shield-alt mr-2"></i><?= gettext('File Integrity Check') ?>
                    <?php if ($hasIntegrityIssues): ?>
                        <span class="badge badge-light ml-2"><?= count($failingFiles) ?></span>
                    <?php endif; ?>
                </h3>
            </div>
            <div class="card-body">
                <?php if ($integrityPassed): ?>
                    <div class="text-center py-3">
                        <i class="fa fa-check-circle text-success" style="font-size: 3rem;"></i>
                        <h5 class="mt-3"><?= gettext('All Files Verified') ?></h5>
                        <p class="text-muted mb-0"><?= gettext('All system files match their expected signatures.') ?></p>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning mb-3">
                        <i class="fa fa-exclamation-triangle mr-2"></i>
                        <strong><?= gettext('File Integrity Issues Detected') ?></strong>
                        <p class="mb-0 mt-2"><?= sprintf(gettext('%d files have been modified or are missing. Use Force Re-install to restore official versions.'), count($failingFiles)) ?></p>
                    </div>
                    
                    <?php
                    // Group files by status
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
                        <h6 class="mb-2">
                            <a href="#collapseModifiedFiles" data-toggle="collapse" class="text-warning text-decoration-none">
                                <i class="fa fa-edit mr-2"></i><?= gettext('Modified Files') ?> (<?= count($modifiedFiles) ?>)
                                <i class="fa fa-chevron-down ml-2"></i>
                            </a>
                        </h6>
                        <div id="collapseModifiedFiles" class="collapse mb-3">
                            <div class="table-responsive">
                                <table class="table table-sm table-striped mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th><?= gettext('File Name') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($modifiedFiles as $file): ?>
                                            <tr>
                                                <td><code><?= InputUtils::escapeHTML(is_object($file) ? $file->filename : $file) ?></code></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (count($missingFiles) > 0): ?>
                        <h6 class="mb-2">
                            <a href="#collapseMissingFilesCard" data-toggle="collapse" class="text-danger text-decoration-none">
                                <i class="fa fa-times-circle mr-2"></i><?= gettext('Missing Files') ?> (<?= count($missingFiles) ?>)
                                <i class="fa fa-chevron-down ml-2"></i>
                            </a>
                        </h6>
                        <div id="collapseMissingFilesCard" class="collapse mb-3">
                            <div class="table-responsive">
                                <table class="table table-sm table-striped mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th><?= gettext('File Name') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($missingFiles as $file): ?>
                                            <tr>
                                                <td><code class="text-danger"><?= InputUtils::escapeHTML(is_object($file) ? $file->filename : $file) ?></code></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <hr>
                    <button type="button" class="btn btn-warning" id="forceReinstall">
                        <i class="fa fa-redo mr-2"></i><?= gettext('Force Re-install') ?>
                    </button>
                    <small class="form-text text-muted d-inline-block ml-2">
                        <?= gettext('Re-download and re-apply the current version to restore all files to their official state') ?>
                    </small>
                <?php endif; ?>
            </div>
        </div>

        <!-- Upgrade Wizard Stepper -->
        <div class="card<?= $isUpdateAvailable ? ' show' : '' ?>" id="upgrade-wizard-card">
            <div class="card-header">
                <h3 class="card-title mb-0"><?= gettext('System Upgrade Wizard') ?></h3>
            </div>
            <div class="card-body p-0">
                <div id="upgrade-stepper" class="bs-stepper">
                    <div class="bs-stepper-header" role="tablist">
                        <!-- Step 1: Pre-Upgrade Checks -->
                        <div class="step<?= $hasWarnings ? ' warning-step' : '' ?>" data-target="#step-warnings">
                            <button type="button" class="step-trigger" role="tab" aria-controls="step-warnings" id="step-warnings-trigger">
                                <span class="bs-stepper-circle">
                                    <i class="fa fa-exclamation-triangle"></i>
                                </span>
                                <span class="bs-stepper-label"><?= gettext('Warnings') ?></span>
                            </button>
                        </div>
                        <div class="line"></div>
                        
                        <!-- Step 2: Backup Database -->
                        <div class="step" data-target="#step-backup">
                            <button type="button" class="step-trigger" role="tab" aria-controls="step-backup" id="step-backup-trigger">
                                <span class="bs-stepper-circle">
                                    <i class="fa fa-database"></i>
                                </span>
                                <span class="bs-stepper-label"><?= gettext('Database Backup') ?></span>
                            </button>
                        </div>
                        <div class="line"></div>
                        
                        <!-- Step 3: Download and Apply Update -->
                        <div class="step" data-target="#step-apply">
                            <button type="button" class="step-trigger" role="tab" aria-controls="step-apply" id="step-apply-trigger">
                                <span class="bs-stepper-circle">
                                    <i class="fa fa-cloud-download"></i>
                                </span>
                                <span class="bs-stepper-label"><?= gettext('Download & Apply') ?></span>
                            </button>
                        </div>
                        <div class="line"></div>
                        
                        <!-- Step 4: Complete -->
                        <div class="step" data-target="#step-complete">
                            <button type="button" class="step-trigger" role="tab" aria-controls="step-complete" id="step-complete-trigger">
                                <span class="bs-stepper-circle">
                                    <i class="fa fa-check"></i>
                                </span>
                                <span class="bs-stepper-label"><?= gettext('Complete') ?></span>
                            </button>
                        </div>
                    </div>

                    <div class="bs-stepper-content">
                        <!-- Step 1: Pre-Upgrade Checks -->
                        <div id="step-warnings" class="content p-4" role="tabpanel" aria-labelledby="step-warnings-trigger">
                            <h4 class="mb-3 text-danger">
                                <i class="fa fa-exclamation-triangle mr-2"></i><?= gettext('Pre-Upgrade Warnings') ?>
                            </h4>

                            <?php if ($integrityCheckFailed): ?>
                                <div class="alert alert-warning">
                                    <h5 class="alert-heading">
                                        <i class="fa fa-exclamation-circle mr-2"></i><?= gettext('Warning: Signature mismatch') ?>
                                    </h5>
                                    <hr>
                                    <p><?= gettext("Some ChurchCRM system files may have been modified since the last installation.") ?> 
                                       <strong><?= gettext("This upgrade will completely destroy any customizations made to the following files by reverting the files to the official version.") ?></strong>
                                    </p>
                                    <p><?= gettext("If you wish to maintain your changes to these files, please take a manual backup of these files before proceeding with this upgrade, and then manually restore the files after the upgrade is complete.") ?></p>
                                    
                                    <p class="mb-0"><strong><?= gettext('Integrity Check Details:') ?></strong> <?= InputUtils::escapeHTML($integrityCheckData['message']) ?></p>
                                </div>
                                
                                <?php if (count($integrityCheckData['files']) > 0): ?>
                                    <?php
                                    // Group files by status
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
                                        <div class="card mt-3">
                                            <div class="card-header" id="headingMissingFiles">
                                                <h6 class="mb-0">
                                                    <button class="btn btn-link text-danger collapsed" type="button" data-toggle="collapse" 
                                                            data-target="#collapseMissingFiles" aria-expanded="false" 
                                                            aria-controls="collapseMissingFiles">
                                                        <i class="fa fa-times-circle mr-2"></i><?= gettext('Files Missing') ?> (<?= count($missingFiles) ?>)
                                                        <i class="fa fa-chevron-down float-right"></i>
                                                    </button>
                                                </h6>
                                            </div>
                                            <div id="collapseMissingFiles" class="collapse" aria-labelledby="headingMissingFiles">
                                                <div class="card-body">
                                                    <div class="table-responsive">
                                                        <table class="table table-sm table-bordered mb-0">
                                                            <thead class="thead-light">
                                                                <tr>
                                                                    <th><?= gettext('File Name') ?></th>
                                                                    <th><?= gettext('Expected Hash') ?></th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($missingFiles as $file): ?>
                                                                    <tr>
                                                                        <td><code><?= InputUtils::escapeHTML($file->filename) ?></code></td>
                                                                        <td><small><?= InputUtils::escapeHTML($file->expectedhash) ?></small></td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (count($modifiedFiles) > 0): ?>
                                        <div class="card mt-3">
                                            <div class="card-header" id="headingModifiedFiles">
                                                <h6 class="mb-0">
                                                    <button class="btn btn-link text-warning collapsed" type="button" data-toggle="collapse" 
                                                            data-target="#collapseModifiedFiles" aria-expanded="false" 
                                                            aria-controls="collapseModifiedFiles">
                                                        <i class="fa fa-edit mr-2"></i><?= gettext('Files Modified') ?> (<?= count($modifiedFiles) ?>)
                                                        <i class="fa fa-chevron-down float-right"></i>
                                                    </button>
                                                </h6>
                                            </div>
                                            <div id="collapseModifiedFiles" class="collapse" aria-labelledby="headingModifiedFiles">
                                                <div class="card-body">
                                                    <div class="table-responsive">
                                                        <table class="table table-sm table-bordered mb-0">
                                                            <thead class="thead-light">
                                                                <tr>
                                                                    <th><?= gettext('File Name') ?></th>
                                                                    <th><?= gettext('Expected Hash') ?></th>
                                                                    <th><?= gettext('Actual Hash') ?></th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($modifiedFiles as $file): ?>
                                                                    <tr>
                                                                        <td><code><?= InputUtils::escapeHTML($file->filename) ?></code></td>
                                                                        <td><small><?= InputUtils::escapeHTML($file->expectedhash) ?></small></td>
                                                                        <td><small><?= InputUtils::escapeHTML($file->actualhash) ?></small></td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php if ($hasOrphanedFiles && isset($integrityCheckData['orphanedFiles']) && count($integrityCheckData['orphanedFiles']) > 0): ?>
                                <div class="alert alert-danger mt-3">
                                    <h5 class="alert-heading">
                                        <i class="fa fa-exclamation-triangle mr-2"></i><?= gettext('Security Warning: Orphaned Files Detected') ?>
                                    </h5>
                                    <hr>
                                    <p><?= gettext("The following files exist on your server but are NOT part of the official ChurchCRM release. These may be leftover from previous versions and could pose security risks.") ?></p>
                                    <p><strong><?= gettext("Recommendation: Review and delete these files before or after the upgrade.") ?></strong></p>
                                </div>
                                
                                <div class="card mt-3">
                                    <div class="card-header bg-danger text-white" id="headingOrphanedFiles">
                                        <h6 class="mb-0">
                                            <button class="btn btn-link text-white" type="button" data-toggle="collapse" 
                                                    data-target="#collapseOrphanedFiles" aria-expanded="false" 
                                                    aria-controls="collapseOrphanedFiles">
                                                <i class="fa fa-trash mr-2"></i><?= gettext('Orphaned Files') ?> (<?= count($integrityCheckData['orphanedFiles']) ?>)
                                                <i class="fa fa-chevron-down float-right ml-2"></i>
                                            </button>
                                        </h6>
                                    </div>
                                    <div id="collapseOrphanedFiles" class="collapse" aria-labelledby="headingOrphanedFiles">
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered mb-0">
                                                    <thead class="thead-light">
                                                        <tr>
                                                            <th><?= gettext('File Path') ?></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($integrityCheckData['orphanedFiles'] as $orphanedFile): ?>
                                                            <tr>
                                                                <td><code><?= InputUtils::escapeHTML($orphanedFile) ?></code></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div class="alert alert-warning mt-3 mb-0">
                                                <small>
                                                    <i class="fa fa-info-circle mr-1"></i>
                                                    <?= gettext('These files were likely part of an older ChurchCRM version and were not cleaned up during a previous upgrade. They may contain outdated code with security vulnerabilities.') ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!$hasWarnings): ?>
                                <div class="alert alert-success">
                                    <i class="fa fa-check-circle mr-2"></i>
                                    <strong><?= gettext('Pre-Upgrade Tasks Complete') ?></strong>
                                    <?= gettext('All pre-upgrade checks have passed. You may proceed with the upgrade.') ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mt-3">
                                <button class="btn btn-primary btn-lg" id="acceptWarnings">
                                    <?php if ($hasWarnings): ?>
                                        <?= gettext('I Understand - Continue') ?> <i class="fa fa-arrow-right ml-2"></i>
                                    <?php else: ?>
                                        <?= gettext('Continue to Backup') ?> <i class="fa fa-arrow-right ml-2"></i>
                                    <?php endif; ?>
                                </button>
                            </div>
                        </div>

                        <!-- Step 2: Backup Database -->
                        <div id="step-backup" class="content p-4" role="tabpanel" aria-labelledby="step-backup-trigger">
                            <h4 class="mb-3">
                                <span id="status-backup"><i class="fa fa-circle text-muted"></i></span>
                                <?= gettext('Backup Database') ?>
                            </h4>
                            <p class="text-muted"><?= gettext('Create a backup of your database before applying the update.') ?></p>
                            
                            <div id="backupStatus"></div>
                            <div id="resultFiles"></div>
                            
                            <div class="mt-3">
                                <button class="btn btn-success" id="doBackup">
                                    <i class="fa fa-database mr-2"></i><?= gettext('Create Backup') ?>
                                </button>
                                <button class="btn btn-outline-secondary ml-2" id="skipBackup">
                                    <i class="fa fa-forward mr-2"></i><?= gettext('Skip Backup') ?>
                                </button>
                            </div>
                            
                            <div id="backupNavButtons" class="mt-3" style="display: none;">
                                <button class="btn btn-primary" id="backup-next">
                                    <?= gettext('Continue to Download & Apply') ?> <i class="fa fa-arrow-right ml-2"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Step 3: Download and Apply Update -->
                        <div id="step-apply" class="content p-4" role="tabpanel" aria-labelledby="step-apply-trigger">
                            <h4 class="mb-3">
                                <span id="status-apply"><i class="fa fa-circle text-muted"></i></span>
                                <?= gettext('Download and Apply System Update') ?>
                            </h4>
                            <p class="text-muted"><?= gettext('Download the latest release and apply it to your ChurchCRM installation.') ?></p>
                            
                            <!-- Download Status -->
                            <div id="downloadStatus"></div>
                            
                            <!-- Update Package Details (shown after download) -->
                            <div id="updateDetails" style="display: none;" class="card mb-3">
                                <div class="card-header">
                                    <h5><?= gettext('Update Package Details') ?></h5>
                                </div>
                                <div class="card-body">
                                    <p><strong><?= gettext('File Name:') ?></strong> <span id="updateFileName"></span></p>
                                    <p><strong><?= gettext('Full Path:') ?></strong> <code id="updateFullPath"></code></p>
                                    <p><strong><?= gettext('SHA1 Hash:') ?></strong> <code id="updateSHA1"></code></p>
                                    <div class="mt-3">
                                        <strong><?= gettext('Release Notes:') ?></strong>
                                        <pre id="releaseNotes" class="mt-2 p-3" style="background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; max-height: 300px; overflow-y: auto; white-space: pre-wrap; word-wrap: break-word;"></pre>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Apply Status -->
                            <div id="applyStatus"></div>
                            
                            <div class="mt-3" id="applyButtonContainer" style="display: none;">
                                <button class="btn btn-danger btn-lg" id="applyUpdate">
                                    <i class="fa fa-cog mr-2"></i><?= gettext('Apply Update Now') ?>
                                </button>
                            </div>
                        </div>

                        <!-- Step 4: Complete -->
                        <div id="step-complete" class="content p-4" role="tabpanel" aria-labelledby="step-complete-trigger">
                            <div class="text-center py-5">
                                <i class="fa fa-check-circle text-success" style="font-size: 5rem;"></i>
                                <h2 class="mt-4"><?= gettext('Upgrade Complete!') ?></h2>
                                <p class="lead text-muted"><?= gettext('Your ChurchCRM installation has been successfully upgraded.') ?></p>
                                <div class="alert alert-info mt-3 text-left" style="max-width: 600px; margin: 0 auto;">
                                    <h5><i class="fa fa-info-circle mr-2"></i><?= gettext('Upgrade Summary') ?></h5>
                                    <ul class="mb-0">
                                        <li><?= gettext('Application files updated to latest version') ?></li>
                                        <li><?= gettext('Database schema upgraded automatically') ?></li>
                                        <li><?= gettext('Orphaned files from previous versions cleaned up') ?></li>
                                    </ul>
                                </div>
                                <p class="text-muted mb-2 mt-3"><?= gettext('You will be logged out and redirected to the login page.') ?></p>
                                <div class="mt-4">
                                    <div class="spinner-border spinner-border-sm text-primary mr-2" role="status">
                                        <span class="sr-only"><?= gettext('Loading...') ?></span>
                                    </div>
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

<!-- Full Page Spinner Overlay for Upgrade Process -->
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
