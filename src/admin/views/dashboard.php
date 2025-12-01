<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\AppIntegrityService;
use ChurchCRM\Utils\VersionUtils;

include SystemURLs::getDocumentRoot() . '/Include/Header.php';

// Get system status info
$integrityStatus = AppIntegrityService::getIntegrityCheckStatus();
$integrityPassed = $integrityStatus === gettext('Passed');
$orphanedFiles = AppIntegrityService::getOrphanedFiles();
$hasOrphanedFiles = count($orphanedFiles) > 0;
?>

<!-- Load admin welcome CSS -->
<link rel="stylesheet" href="<?= SystemURLs::getRootPath() ?>/skin/v2/admin-dashboard.min.css">

<div class="container-fluid mt-2">
    <!-- Page Header -->
    <div class="row mb-3">
        <div class="col-lg-12">
            <h1 class="display-4 mb-2">
                <i class="fa-solid fa-hand-fist text-primary"></i> <?= gettext('Welcome to ChurchCRM') ?>
            </h1>
            <p class="lead text-muted"><?= gettext('Let\'s get your system set up and ready to use') ?></p>
            <hr class="my-2">
        </div>
    </div>

    <div class="row">
        <!-- Quick Start Card -->
        <div class="col-lg-8 mb-4">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="fa-solid fa-rocket"></i> <?= gettext('Quick Start') ?>
                    </h4>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4"><?= gettext('Complete these essential setup tasks to get ChurchCRM running smoothly:') ?></p>
                    <ol class="list-group list-group-flush">
                        <li class="list-group-item d-flex align-items-start">
                            <span class="badge bg-primary rounded-circle">1</span>
                            <div class="flex-grow-1">
                                <strong><?= gettext('Configure System Settings') ?></strong>
                                <p class="mb-0 text-muted small"><?= gettext('Organization name, timezone, currency, and email settings') ?></p>
                                <a href="<?= SystemURLs::getRootPath() ?>/OptionManager.php" class="btn btn-sm btn-outline-primary mt-2">
                                    <i class="fa-solid fa-cog"></i> <?= gettext('System Settings') ?>
                                </a>
                            </div>
                        </li>
                        <li class="list-group-item d-flex align-items-start">
                            <span class="badge bg-primary rounded-circle">2</span>
                            <div class="flex-grow-1">
                                <strong><?= gettext('Set Up Administrative Users') ?></strong>
                                <p class="mb-0 text-muted small"><?= gettext('Create user accounts and assign roles and permissions') ?></p>
                                <a href="<?= SystemURLs::getRootPath() ?>/UserList.php" class="btn btn-sm btn-outline-primary mt-2">
                                    <i class="fa-solid fa-users"></i> <?= gettext('Manage Users') ?>
                                </a>
                            </div>
                        </li>
                        <li class="list-group-item d-flex align-items-start">
                            <span class="badge bg-primary rounded-circle">3</span>
                            <div class="flex-grow-1">
                                <strong><?= gettext('Configure Financial Settings') ?></strong>
                                <p class="mb-0 text-muted small"><?= gettext('Create giving funds, payment methods, and donation categories (if needed)') ?></p>
                                <a href="<?= SystemURLs::getRootPath() ?>/DonationFundEditor.php" class="btn btn-sm btn-outline-primary mt-2">
                                    <i class="fa-solid fa-dollar-sign"></i> <?= gettext('Manage Funds') ?>
                                </a>
                            </div>
                        </li>
                        <li class="list-group-item d-flex align-items-start">
                            <span class="badge bg-primary rounded-circle">4</span>
                            <div class="flex-grow-1">
                                <strong><?= gettext('Add Organizational Structure') ?></strong>
                                <p class="mb-0 text-muted small"><?= gettext('Create locations, groups, families, and other organizational units') ?></p>
                                <a href="<?= SystemURLs::getRootPath() ?>/GroupList.php" class="btn btn-sm btn-outline-primary mt-2">
                                    <i class="fa-solid fa-sitemap"></i> <?= gettext('Manage Groups') ?>
                                </a>
                            </div>
                        </li>
                        <li class="list-group-item d-flex align-items-start">
                            <span class="badge bg-primary rounded-circle">5</span>
                            <div class="flex-grow-1">
                                <strong><?= gettext('Set Up Sunday School') ?></strong>
                                <p class="mb-0 text-muted small"><?= gettext('Create Sunday school classes and configure class settings') ?></p>
                                <a href="<?= SystemURLs::getRootPath() ?>/sundayschool/SundaySchoolDashboard.php" class="btn btn-sm btn-outline-primary mt-2">
                                    <i class="fa-solid fa-child"></i> <?= gettext('Sunday School') ?>
                                </a>
                            </div>
                        </li>
                        <li class="list-group-item d-flex align-items-start">
                            <span class="badge bg-primary rounded-circle">6</span>
                            <div class="flex-grow-1">
                                <strong><?= gettext('Import Data from CSV') ?></strong>
                                <p class="mb-0 text-muted small"><?= gettext('Import families, people, and financial data from CSV files') ?></p>
                                <a href="<?= SystemURLs::getRootPath() ?>/CSVImport.php" class="btn btn-sm btn-outline-primary mt-2">
                                    <i class="fa-solid fa-file-csv"></i> <?= gettext('CSV Import') ?>
                                </a>
                            </div>
                        </li>
                        <li class="list-group-item d-flex align-items-start">
                            <span class="badge bg-primary rounded-circle">7</span>
                            <div class="flex-grow-1">
                                <strong><?= gettext('Enable Security Features') ?></strong>
                                <p class="mb-0 text-muted small"><?= gettext('Review security settings and consider enabling two-factor authentication') ?></p>
                                <a href="<?= SystemURLs::getRootPath() ?>/OptionManager.php" class="btn btn-sm btn-outline-primary mt-2">
                                    <i class="fa-solid fa-lock"></i> <?= gettext('Security Settings') ?>
                                </a>
                            </div>
                        </li>
                    </ol>
                    <p class="text-muted small mt-4 mb-0 p-3 bg-light rounded">
                        <i class="fa-solid fa-circle-info"></i>
                        <?= gettext('You can complete these tasks in any order. Most settings are available in the Admin and System Settings sections.') ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- System Info Card -->
            <div class="card mb-4 shadow-sm border-0">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <i class="fa-solid fa-circle-info"></i> <?= gettext('System Info') ?>
                    </h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled">
                        <li class="mb-3">
                            <strong><?= gettext('Software:') ?></strong><br>
                            <code class="text-muted small"><?= VersionUtils::getInstalledVersion() ?></code>
                        </li>
                        <li class="mb-3">
                            <strong><?= gettext('Database:') ?></strong><br>
                            <code class="text-muted small"><?= VersionUtils::getDBVersion() ?></code>
                        </li>
                        <li class="mb-3">
                            <strong><?= gettext('Admin Tools:') ?></strong><br>
                            <div class="btn-group w-100 mt-2" role="group">
                                <a href="<?= SystemURLs::getRootPath() ?>/admin/system/backup" class="btn btn-sm btn-outline-info" title="<?= gettext('Backup Database') ?>">
                                    <i class="fa-solid fa-download"></i> <?= gettext('Backup') ?>
                                </a>
                                <a href="<?= SystemURLs::getRootPath() ?>/admin/system/upgrade" class="btn btn-sm btn-outline-info" title="<?= gettext('System Upgrade') ?>">
                                    <i class="fa-solid fa-arrow-up"></i> <?= gettext('Upgrade') ?>
                                </a>
                            </div>
                        </li>
                        <li class="mt-3">
                            <a href="https://github.com/ChurchCRM/CRM/wiki" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-info w-100">
                                <i class="fa-solid fa-book"></i> <?= gettext('Documentation') ?>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Try Demo Data Card -->
            <div class="card mb-4 shadow-sm border-0">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fa-solid fa-database"></i> <?= gettext('Try ChurchCRM with demo data') ?>
                    </h5>
                </div>
                <div class="card-body">
                    <p><?= gettext('Import sample families, people, groups and optional events/financial records so you can explore ChurchCRM populated with realistic data.') ?></p>
                    <button type="button" id="importDemoDataV2" class="btn btn-success btn-lg w-100">
                        <i class="fa-solid fa-arrow-right"></i> <?= gettext('Import Demo Data') ?>
                    </button>
                </div>
            </div>

            <!-- System Health Card -->
            <div class="card mb-4 shadow-sm border-0">
                <div class="card-header <?= $integrityPassed ? 'bg-success' : 'bg-warning' ?> text-white">
                    <h5 class="mb-0">
                        <i class="fa-solid fa-heartbeat"></i> <?= gettext('System Health') ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <p class="mb-2"><strong><?= gettext('File Integrity:') ?></strong></p>
                        <?php if ($integrityPassed): ?>
                            <span class="badge bg-success"><i class="fa-solid fa-check-circle"></i> <?= gettext('Passed') ?></span>
                        <?php else: ?>
                            <span class="badge bg-danger"><i class="fa-solid fa-exclamation-circle"></i> <?= gettext('Failed') ?></span>
                            <p class="text-danger small mt-2"><?= gettext('Some files may be modified or missing.') ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($hasOrphanedFiles): ?>
                    <div class="mb-3">
                        <p class="mb-2"><strong><?= gettext('Orphaned Files:') ?></strong></p>
                        <span class="badge bg-danger"><?= count($orphanedFiles) ?> <?= gettext('files') ?></span>
                        <p class="text-muted small mt-2"><?= gettext('Orphaned files may pose security risks.') ?></p>
                        <a href="<?= SystemURLs::getRootPath() ?>/admin/system/orphaned-files" class="btn btn-sm btn-outline-danger w-100 mt-2">
                            <i class="fa-solid fa-trash"></i> <?= gettext('Clean Up') ?>
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="mb-3">
                        <p class="mb-2"><strong><?= gettext('Orphaned Files:') ?></strong></p>
                        <span class="badge bg-success"><i class="fa-solid fa-check-circle"></i> <?= gettext('None detected') ?></span>
                    </div>
                    <?php endif; ?>

                    <hr class="my-3">
                    <a href="<?= SystemURLs::getRootPath() ?>/admin/system/debug" class="btn btn-sm btn-outline-secondary w-100">
                        <i class="fa-solid fa-bug"></i> <?= gettext('Full Debug Info') ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

</div>

<!-- Load admin welcome JavaScript -->
<script src="<?= SystemURLs::getRootPath() ?>/skin/v2/admin-dashboard.min.js"></script>

<!-- Load import demo data script -->
<script src="<?= SystemURLs::getRootPath() ?>/skin/js/importDemoData.js"></script>

<?php
include SystemURLs::getDocumentRoot() . '/Include/Footer.php';
