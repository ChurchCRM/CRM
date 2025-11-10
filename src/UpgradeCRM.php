<?php

require_once 'Include/Config.php';
$bSuppressSessionTests = true;
require_once 'Include/Functions.php';
require_once 'Include/Header-function.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\AppIntegrityService;
use ChurchCRM\Service\TaskService;
use ChurchCRM\Utils\RedirectUtils;

$sPageTitle = gettext('Upgrade ChurchCRM');

if (!AuthenticationManager::validateUserSessionIsActive(false) || !AuthenticationManager::getCurrentUser()->isAdmin()) {
    RedirectUtils::redirect('index.php');
}
$expertMode = false;
if (isset($_GET['expertmode'])) {
    $expertMode = true;
}

require_once 'Include/HeaderNotLoggedIn.php';
Header_modals();
Header_body_scripts();

// Prepare pre-upgrade task list and integrity check status before rendering the stepper
$taskService = new TaskService();
$preUpgradeTasks = $taskService->getActivePreUpgradeTasks();
$hasWarnings = count($preUpgradeTasks) > 0 || AppIntegrityService::getIntegrityCheckStatus() === gettext("Failed");

?>
<link rel="stylesheet" href="<?= SystemURLs::getRootPath() ?>/skin/v2/upgrade-wizard.min.css">

<div class="upgrade-wizard-container">
    <div class="card">
        <div class="card-header bg-danger text-white">
            <h3 class="card-title mb-0">
                <i class="fa-solid fa-upload mr-2"></i><?= gettext('Upgrade ChurchCRM') ?>
            </h3>
        </div>
        <div class="card-body p-0">
            <div id="upgrade-stepper" class="bs-stepper">
                <div class="bs-stepper-header" role="tablist">
                    <?php if ($hasWarnings) { ?>
                        <div class="step warning-step" data-target="#step-warnings">
                            <button type="button" class="step-trigger" role="tab" aria-controls="step-warnings" id="step-warnings-trigger">
                                <span class="bs-stepper-circle"><i class="fa-solid fa-exclamation-triangle"></i></span>
                                <span class="bs-stepper-label"><?= gettext('Warnings') ?></span>
                            </button>
                        </div>
                        <div class="line"></div>
                    <?php } ?>
                    <div class="step" data-target="#step-backup">
                        <button type="button" class="step-trigger" role="tab" aria-controls="step-backup" id="step-backup-trigger">
                            <span class="bs-stepper-circle">1</span>
                            <span class="bs-stepper-label"><?= gettext('Database Backup') ?></span>
                        </button>
                    </div>
                    <div class="line"></div>
                    <div class="step" data-target="#step-fetch">
                        <button type="button" class="step-trigger" role="tab" aria-controls="step-fetch" id="step-fetch-trigger">
                            <span class="bs-stepper-circle">2</span>
                            <span class="bs-stepper-label"><?= gettext('Fetch Update') ?></span>
                        </button>
                    </div>
                    <div class="line"></div>
                    <div class="step" data-target="#step-apply">
                        <button type="button" class="step-trigger" role="tab" aria-controls="step-apply" id="step-apply-trigger">
                            <span class="bs-stepper-circle">3</span>
                            <span class="bs-stepper-label"><?= gettext('Apply Update') ?></span>
                        </button>
                    </div>
                    <div class="line"></div>
                    <div class="step" data-target="#step-complete">
                        <button type="button" class="step-trigger" role="tab" aria-controls="step-complete" id="step-complete-trigger">
                            <span class="bs-stepper-circle">4</span>
                            <span class="bs-stepper-label"><?= gettext('Complete') ?></span>
                        </button>
                    </div>
                </div>
                <div class="bs-stepper-content">

                    <?php

                    if ($hasWarnings) {
                    ?>
                        <!-- Step: Warnings -->
                        <div id="step-warnings" class="content step-content-area" role="tabpanel" aria-labelledby="step-warnings-trigger">
                            <h4 class="text-danger mb-4">
                                <i class="fa-solid fa-exclamation-triangle mr-2"></i><?= gettext('Pre-Upgrade Warnings') ?>
                            </h4>

                            <?php if (count($preUpgradeTasks) > 0) { ?>
                                <div class="alert alert-danger" role="alert">
                                    <h5 class="alert-heading">
                                        <i class="fa-solid fa-bomb mr-2"></i><?= gettext('Pre-Upgrade Tasks Detected') ?>
                                    </h5>
                                    <hr>
                                    <p><?= gettext("Some conditions have been identified which may prevent a successful upgrade") ?></p>
                                    <p><?= gettext("Please review and mitigate these tasks before continuing with the upgrade:") ?></p>
                                    <ul class="mb-0">
                                        <?php foreach ($preUpgradeTasks as $preUpgradeTask) { ?>
                                            <li><strong><?= $preUpgradeTask->getTitle() ?>:</strong> <?= $preUpgradeTask->getDesc() ?></li>
                                        <?php } ?>
                                    </ul>
                                </div>
                            <?php } ?>

                            <?php if (AppIntegrityService::getIntegrityCheckStatus() === gettext("Failed")) { ?>
                                <div class="alert alert-warning" role="alert">
                                    <h5 class="alert-heading">
                                        <i class="fa-solid fa-exclamation-circle mr-2"></i><?= gettext('Warning: Signature mismatch') ?>
                                    </h5>
                                    <hr>
                                    <p><?= gettext("Some ChurchCRM system files may have been modified since the last installation.") ?> <strong><?= gettext("This upgrade will completely destroy any customizations made to the following files by reverting the files to the official version.") ?></strong></p>
                                    <p><?= gettext("If you wish to maintain your changes to these files, please take a manual backup of these files before proceeding with this upgrade, and then manually restore the files after the upgrade is complete.") ?></p>

                                    <div class="mt-3">
                                        <p><strong><?= gettext('Integrity Check Details:') ?></strong> <?= AppIntegrityService::getIntegrityCheckMessage() ?></p>
                                        <?php if (count(AppIntegrityService::getFilesFailingIntegrityCheck()) > 0) { ?>
                                            <p><strong><?= gettext('Files failing integrity check') ?>:</strong></p>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered" id="fileIntegrityCheckResultsTable">
                                                    <thead class="thead-light">
                                                        <tr>
                                                            <th><?= gettext('File Name') ?></th>
                                                            <th><?= gettext('Expected Hash') ?></th>
                                                            <th><?= gettext('Actual Hash') ?></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach (AppIntegrityService::getFilesFailingIntegrityCheck() as $file) { ?>
                                                            <tr>
                                                                <td><code><?= $file->filename ?></code></td>
                                                                <td><small><?= $file->expectedhash ?></small></td>
                                                                <td>
                                                                    <?php if ($file->status === 'File Missing') {
                                                                        echo '<span class="badge badge-danger">' . gettext('File Missing') . '</span>';
                                                                    } else {
                                                                        echo '<small>' . $file->actualhash . '</small>';
                                                                    } ?>
                                                                </td>
                                                            </tr>
                                                        <?php } ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php } ?>
                                    </div>
                                </div>
                            <?php } ?>

                            <div class="form-group mt-4 mb-0">
                                <button type="button" class="btn btn-primary btn-lg" id="acceptWarnings">
                                    <?= gettext('I Understand - Continue') ?> <i class="fa-solid fa-arrow-right ml-2"></i>
                                </button>
                            </div>
                        </div>
                    <?php } ?>

                    <!-- Step 1: Database Backup -->
                    <div id="step-backup" class="content step-content-area" role="tabpanel" aria-labelledby="step-backup-trigger">
                        <h4 class="mb-4">
                            <i class="fa-solid fa-database text-primary mr-2"></i><?= gettext('Step 1: Backup Database') ?>
                            <span id="status-backup" class="ml-2"></span>
                        </h4>

                        <div class="alert alert-info" role="alert">
                            <i class="fa-solid fa-info-circle mr-2"></i>
                            <?= gettext('Please create a database backup before beginning the upgrade process.') ?>
                        </div>

                        <div class="form-group">
                            <button type="button" class="btn btn-primary btn-lg" id="doBackup">
                                <i class="fa-solid fa-download mr-2"></i><?= gettext('Generate Database Backup') ?>
                            </button>
                        </div>

                        <div id="backupStatus" class="mt-3"></div>
                        <div id="resultFiles" class="mt-3"></div>

                        <div class="form-group mt-4 mb-0" id="backupNavButtons" style="display:none;">
                            <button type="button" class="btn btn-success btn-lg" id="backup-next">
                                <?= gettext('Continue to Fetch Update') ?> <i class="fa-solid fa-arrow-right ml-2"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Step 2: Fetch Update -->
                    <div id="step-fetch" class="content step-content-area" role="tabpanel" aria-labelledby="step-fetch-trigger">
                        <h4 class="mb-4">
                            <i class="fa-solid fa-cloud-download text-primary mr-2"></i><?= gettext('Step 2: Fetch Update Package') ?>
                            <span id="status-fetch" class="ml-2"></span>
                        </h4>

                        <div class="alert alert-info" role="alert">
                            <i class="fa-solid fa-info-circle mr-2"></i>
                            <?= gettext('Fetch the latest files from the ChurchCRM GitHub release page') ?>
                        </div>

                        <div class="form-group">
                            <button type="button" class="btn btn-primary btn-lg" id="fetchUpdate">
                                <i class="fa-solid fa-download mr-2"></i><?= gettext('Fetch Update Files') ?>
                            </button>
                        </div>

                        <div id="fetchStatus" class="mt-3"></div>

                        <div class="form-group mt-4 mb-0">
                            <button type="button" class="btn btn-secondary btn-lg mr-2" id="fetch-previous">
                                <i class="fa-solid fa-arrow-left mr-2"></i><?= gettext('Previous') ?>
                            </button>
                            <button type="button" class="btn btn-success btn-lg" id="fetch-next" style="display:none;">
                                <?= gettext('Continue to Apply Update') ?> <i class="fa-solid fa-arrow-right ml-2"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Step 3: Apply Update -->
                    <div id="step-apply" class="content step-content-area" role="tabpanel" aria-labelledby="step-apply-trigger">
                        <h4 class="mb-4">
                            <i class="fa-solid fa-cogs text-primary mr-2"></i><?= gettext('Step 3: Apply Update Package') ?>
                            <span id="status-apply" class="ml-2"></span>
                        </h4>

                        <div class="alert alert-warning" role="alert">
                            <i class="fa-solid fa-exclamation-triangle mr-2"></i>
                            <?= gettext('Extract the upgrade archive, and apply the new files') ?>
                        </div>

                        <div id="updateDetails" style="display:none;">
                            <h5><?= gettext('Release Notes') ?></h5>
                            <div class="card mb-3">
                                <div class="card-body">
                                    <pre id="releaseNotes" class="mb-0" style="max-height: 200px; overflow-y: auto;"></pre>
                                </div>
                            </div>

                            <div class="card mb-3">
                                <div class="card-body">
                                    <ul class="list-unstyled mb-0">
                                        <li><strong><?= gettext('File Name:') ?></strong> <span id="updateFileName"></span></li>
                                        <li><strong><?= gettext('Full Path:') ?></strong> <span id="updateFullPath"></span></li>
                                        <li><strong><?= gettext('SHA1:') ?></strong> <code id="updateSHA1"></code></li>
                                    </ul>
                                </div>
                            </div>

                            <div class="form-group">
                                <button type="button" class="btn btn-warning btn-lg" id="applyUpdate">
                                    <i class="fa-solid fa-rocket mr-2"></i><?= gettext('Upgrade System') ?>
                                </button>
                            </div>
                        </div>

                        <div id="applyStatus" class="mt-3"></div>

                        <div class="form-group mt-4 mb-0">
                            <button type="button" class="btn btn-secondary btn-lg mr-2" id="apply-previous">
                                <i class="fa-solid fa-arrow-left mr-2"></i><?= gettext('Previous') ?>
                            </button>
                            <button type="button" class="btn btn-success btn-lg" id="apply-next" style="display:none;">
                                <?= gettext('Continue to Login') ?> <i class="fa-solid fa-arrow-right ml-2"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Step 4: Complete -->
                    <div id="step-complete" class="content step-content-area" role="tabpanel" aria-labelledby="step-complete-trigger">
                        <h4 class="mb-4">
                            <i class="fa-solid fa-check-circle text-success mr-2"></i><?= gettext('Upgrade Complete') ?>
                        </h4>

                        <div class="alert alert-success" role="alert">
                            <h5 class="alert-heading">
                                <i class="fa-solid fa-thumbs-up mr-2"></i><?= gettext('Success!') ?>
                            </h5>
                            <hr>
                            <p class="mb-0"><?= gettext('Your ChurchCRM system has been successfully upgraded. Please login to access the upgraded system.') ?></p>
                        </div>

                        <div class="form-group mt-4 mb-0">
                            <a href="/session/end" class="btn btn-success btn-lg">
                                <i class="fa-solid fa-right-to-bracket mr-2"></i><?= gettext('Login to Upgraded System') ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= SystemURLs::getRootPath() ?>/skin/v2/upgrade-wizard.min.js"></script>

<?php
require_once 'Include/FooterNotLoggedIn.php';

// Turn OFF output buffering
ob_end_flush();
?>