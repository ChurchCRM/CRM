<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\AdminService;
use ChurchCRM\Service\AppIntegrityService;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\VersionUtils;

include SystemURLs::getDocumentRoot() . '/Include/Header.php';

// Initialize admin service for dashboard checks
$adminService = new AdminService();
$setupTasks = $adminService->getSetupTasks();
$systemWarnings = $adminService->getSystemWarnings();
$hasSetupTasks = count($setupTasks) > 0;
$hasWarnings = count($systemWarnings) > 0;

// Check for configuration URL errors
$urlError = $adminService->getConfigurationURLError();
$hasURLError = $urlError !== null;

// Get system status info
$integrityStatus = AppIntegrityService::getIntegrityCheckStatus();
$integrityPassed = $integrityStatus === 'Passed';
$orphanedFiles = AppIntegrityService::getOrphanedFiles();
$hasOrphanedFiles = count($orphanedFiles) > 0;

// Calculate overall health status
$healthStatus = $integrityPassed && !$hasOrphanedFiles && !$adminService->hasCriticalWarnings() && !$hasURLError;
?>

<!-- Load admin dashboard CSS -->
<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/admin-dashboard.min.css') ?>">

<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-3">
        <div class="col-12">
            <h2 class="mb-1">
                <i class="fa-solid fa-hand-fist text-primary"></i> <?= gettext('Welcome to ChurchCRM') ?>
            </h2>
            <p class="text-muted mb-0"><?= gettext("Let's get your system set up and ready to use") ?></p>
        </div>
    </div>

    <?php if ($hasURLError): ?>
    <!-- Configuration URL Error Alert -->
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
        <div class="d-flex align-items-start">
            <div class="mr-3 mt-1">
                <i class="fa-solid fa-exclamation-circle fa-3x"></i>
            </div>
            <div class="flex-grow-1">
                <h4 class="alert-heading mb-3">
                    <i class="fa-solid fa-triangle-exclamation mr-2"></i><?= gettext('Critical Configuration Error') ?>
                </h4>
                
                <div class="card border-0 mb-3">
                    <div class="card-body bg-white">
                        <h6 class="text-danger mb-2"><strong><?= gettext('Error') ?>:</strong></h6>
                        <p class="mb-0 text-dark"><strong><?= $urlError['message'] ?></strong></p>
                    </div>
                </div>

                <div class="mb-3">
                    <h6 class="mb-2"><?= gettext('Current $URL[0] value:') ?></h6>
                    <div class="p-3 bg-dark rounded" style="font-family: 'Courier New', monospace; word-break: break-all;">
                        <code class="text-warning" style="font-size: 1.1em;"><?= InputUtils::escapeHTML($urlError['url']) ?></code>
                    </div>
                </div>

                <div class="card border-0 mb-3">
                    <div class="card-body bg-white">
                        <h6 class="text-dark mb-3"><strong><?= gettext('How to Fix:') ?></strong></h6>
                        <ol class="mb-2 pl-3 text-dark">
                            <li class="mb-2"><?= gettext('Connect to your server via SSH, FTP, or your hosting control panel') ?></li>
                            <li class="mb-2"><?= gettext('Navigate to your ChurchCRM installation directory') ?></li>
                            <li class="mb-2"><?= gettext('Open this file in a text editor:') ?> <code class="text-primary">src/Include/Config.php</code></li>
                            <li class="mb-2"><?= gettext('Find the line:') ?> <code>$URL[0] = '...';</code></li>
                            <li class="mb-2"><?= gettext('Update it to a valid URL that:') ?>
                                <ul class="mt-1">
                                    <li><?= gettext('Starts with <strong>http://</strong> or <strong>https://</strong>') ?></li>
                                    <li><?= gettext('Ends with a <strong>trailing slash</strong> (/)') ?></li>
                                </ul>
                            </li>
                            <li><?= gettext('Save the file and refresh this page') ?></li>
                        </ol>
                    </div>
                </div>

                <div class="card border-success mb-0">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0"><i class="fa-solid fa-check-circle mr-2"></i><?= gettext('Valid Examples:') ?></h6>
                    </div>
                    <div class="card-body bg-white">
                        <div class="mb-2">
                            <small class="text-muted"><?= gettext('Local development:') ?></small><br>
                            <code class="text-success" style="font-size: 1em;">$URL[0] = 'http://localhost/';</code>
                        </div>
                        <div class="mb-2">
                            <small class="text-muted"><?= gettext('Subdirectory installation:') ?></small><br>
                            <code class="text-success" style="font-size: 1em;">$URL[0] = 'https://www.yourdomain.com/churchcrm/';</code>
                        </div>
                        <div>
                            <small class="text-muted"><?= gettext('Custom port:') ?></small><br>
                            <code class="text-success" style="font-size: 1em;">$URL[0] = 'https://www.yourdomain.com:8080/app/';</code>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($hasWarnings): ?>
    <!-- System Warnings Alert -->
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <div class="d-flex align-items-start">
            <div class="mr-3">
                <i class="fa-solid fa-exclamation-triangle fa-2x"></i>
            </div>
            <div class="flex-grow-1">
                <strong><?= gettext('System Configuration:') ?></strong>
                <?php 
                $warningLinks = [];
                foreach ($systemWarnings as $warning) {
                    $warningLinks[] = '<a href="' . $warning['link'] . '" class="alert-link">' . $warning['title'] . '</a>';
                }
                echo implode(' · ', $warningLinks);
                ?>
            </div>
        </div>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    <?php endif; ?>

    <?php if ($hasSetupTasks): ?>
    <!-- Setup Tasks Alert -->
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        <div class="d-flex align-items-start">
            <div class="mr-3">
                <i class="fa-solid fa-clipboard-list fa-2x"></i>
            </div>
            <div class="flex-grow-1">
                <strong><?= gettext('Setup Tasks:') ?></strong>
                <?php 
                $taskLinks = [];
                foreach ($setupTasks as $task) {
                    $taskLinks[] = '<a href="' . $task['link'] . '" class="alert-link">' . $task['title'] . '</a>';
                }
                echo implode(' · ', $taskLinks);
                ?>
            </div>
        </div>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    <?php endif; ?>

    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- Quick Start Card -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-primary text-white py-2">
                    <h5 class="mb-0">
                        <i class="fa-solid fa-rocket"></i> <?= gettext('Quick Start') ?>
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3"><?= gettext('Complete these essential setup tasks to get ChurchCRM running smoothly:') ?></p>
                    
                    <div class="row">
                        <!-- System Settings -->
                        <div class="col-md-6 col-lg-4 mb-3">
                            <a href="<?= SystemURLs::getRootPath() ?>/SystemSettings.php" class="quick-start-card">
                                <div class="quick-start-icon bg-primary">
                                    <i class="fa-solid fa-cog"></i>
                                </div>
                                <div class="quick-start-content">
                                    <h6><?= gettext('System Settings') ?></h6>
                                    <small><?= gettext('Organization, timezone, email') ?></small>
                                </div>
                            </a>
                        </div>
                        
                        <!-- Manage Users -->
                        <div class="col-md-6 col-lg-4 mb-3">
                            <a href="<?= SystemURLs::getRootPath() ?>/admin/system/users" class="quick-start-card">
                                <div class="quick-start-icon bg-info">
                                    <i class="fa-solid fa-users"></i>
                                </div>
                                <div class="quick-start-content">
                                    <h6><?= gettext('Manage Users') ?></h6>
                                    <small><?= gettext('User accounts and roles') ?></small>
                                </div>
                            </a>
                        </div>
                        
                        <!-- Groups -->
                        <div class="col-md-6 col-lg-4 mb-3">
                            <a href="<?= SystemURLs::getRootPath() ?>/GroupList.php" class="quick-start-card">
                                <div class="quick-start-icon bg-warning">
                                    <i class="fa-solid fa-sitemap"></i>
                                </div>
                                <div class="quick-start-content">
                                    <h6><?= gettext('Groups') ?></h6>
                                    <small><?= gettext('Organizational structure') ?></small>
                                </div>
                            </a>
                        </div>
                        
                        <!-- Sunday School -->
                        <div class="col-md-6 col-lg-4 mb-3">
                            <a href="<?= SystemURLs::getRootPath() ?>/sundayschool/SundaySchoolDashboard.php" class="quick-start-card">
                                <div class="quick-start-icon bg-orange">
                                    <i class="fa-solid fa-children"></i>
                                </div>
                                <div class="quick-start-content">
                                    <h6><?= gettext('Sunday School') ?></h6>
                                    <small><?= gettext('Classes and enrollments') ?></small>
                                </div>
                            </a>
                        </div>
                        
                        <!-- CSV Import -->
                        <div class="col-md-6 col-lg-4 mb-3">
                            <a href="<?= SystemURLs::getRootPath() ?>/CSVImport.php" class="quick-start-card">
                                <div class="quick-start-icon bg-secondary">
                                    <i class="fa-solid fa-file-import"></i>
                                </div>
                                <div class="quick-start-content">
                                    <h6><?= gettext('Import Data') ?></h6>
                                    <small><?= gettext('Import from CSV files') ?></small>
                                </div>
                            </a>
                        </div>
                        
                        <!-- Financial Settings -->
                        <div class="col-md-6 col-lg-4 mb-3">
                            <a href="<?= SystemURLs::getRootPath() ?>/DonationFundEditor.php" class="quick-start-card">
                                <div class="quick-start-icon bg-success">
                                    <i class="fa-solid fa-dollar-sign"></i>
                                </div>
                                <div class="quick-start-content">
                                    <h6><?= gettext('Donation Funds') ?></h6>
                                    <small><?= gettext('Giving funds and categories') ?></small>
                                </div>
                            </a>
                        </div>
                    </div>
                    
                    <div class="alert alert-light border mb-0 py-2">
                        <small><i class="fa-solid fa-lightbulb text-warning"></i> <strong><?= gettext('Tip:') ?></strong> <?= gettext('Complete these in any order. Use Demo Data to explore with sample records.') ?></small>
                    </div>
                </div>
            </div>

            <!-- Advanced Operations -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-secondary text-white py-2">
                    <h5 class="mb-0">
                        <i class="fa-solid fa-tools"></i> <?= gettext('Advanced Operations') ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Restore Database -->
                        <div class="col-md-6 mb-3 mb-md-0">
                            <div class="border rounded p-3 h-100">
                                <h6 class="text-warning"><i class="fa-solid fa-upload"></i> <?= gettext('Restore Database') ?></h6>
                                <p class="small text-muted mb-2"><?= gettext('Restore from a backup file. Erases existing data.') ?></p>
                                <a href="<?= SystemURLs::getRootPath() ?>/admin/system/restore" class="btn btn-sm btn-outline-warning">
                                    <?= gettext('Restore') ?>
                                </a>
                            </div>
                        </div>
                        <!-- Reset Database -->
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100">
                                <h6 class="text-danger"><i class="fa-solid fa-exclamation-triangle"></i> <?= gettext('Reset Database') ?></h6>
                                <p class="small text-muted mb-2"><?= gettext('Clear all data and start fresh. Cannot be undone.') ?></p>
                                <a href="<?= SystemURLs::getRootPath() ?>/admin/system/reset" class="btn btn-sm btn-outline-danger">
                                    <?= gettext('Reset') ?>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <!-- System Logs -->
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100">
                                <h6 class="text-info"><i class="fa-solid fa-file-alt"></i> <?= gettext('System Logs') ?></h6>
                                <p class="small text-muted mb-2"><?= gettext('View and manage system log files for debugging.') ?></p>
                                <a href="<?= SystemURLs::getRootPath() ?>/admin/system/logs" class="btn btn-sm btn-outline-info">
                                    <?= gettext('View Logs') ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Try Demo Data Card -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-success text-white py-2">
                    <h5 class="mb-0">
                        <i class="fa-solid fa-database"></i> <?= gettext('Demo Data') ?>
                    </h5>
                </div>
                <div class="card-body">
                    <p class="small mb-3"><?= gettext('Import sample families, people, and groups to explore ChurchCRM with realistic data.') ?></p>
                    <button type="button" id="importDemoDataV2" class="btn btn-success btn-lg btn-block">
                        <i class="fa-solid fa-download"></i> <?= gettext('Import Demo Data') ?>
                    </button>
                </div>
            </div>

            <!-- System Info Card -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-info text-white py-2">
                    <h5 class="mb-0">
                        <i class="fa-solid fa-circle-info"></i> <?= gettext('System Info') ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted"><?= gettext('Version:') ?></span>
                        <code><?= VersionUtils::getInstalledVersion() ?></code>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-muted"><?= gettext('Database:') ?></span>
                        <code><?= VersionUtils::getDBVersion() ?></code>
                    </div>
                    <div class="btn-group d-flex mb-2" role="group">
                        <a href="<?= SystemURLs::getRootPath() ?>/admin/system/backup" class="btn btn-sm btn-outline-info flex-fill">
                            <i class="fa-solid fa-download"></i> <?= gettext('Backup') ?>
                        </a>
                        <a href="<?= SystemURLs::getRootPath() ?>/admin/system/upgrade" class="btn btn-sm btn-outline-info flex-fill">
                            <i class="fa-solid fa-arrow-up"></i> <?= gettext('Upgrade') ?>
                        </a>
                    </div>
                    <a href="https://github.com/ChurchCRM/CRM/wiki" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-secondary btn-block">
                        <i class="fa-solid fa-book"></i> <?= gettext('Documentation') ?>
                    </a>
                </div>
            </div>

            <!-- Register Your Church Card -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-primary text-white py-2">
                    <h5 class="mb-0">
                        <i class="fa-solid fa-handshake"></i> <?= gettext('Register Your Church') ?>
                    </h5>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-3"><?= gettext('Join the ChurchCRM community and help us improve by sharing your information. It takes less than a minute!') ?></p>
                    <a href="https://forms.gle/F1xgoBaWUD1Fy7Bn9" target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-block">
                        <i class="fa-solid fa-external-link-alt"></i> <?= gettext('Register Now') ?>
                    </a>
                    <p class="small text-muted mt-3 mb-0"><i class="fa-solid fa-shield-alt"></i> <?= gettext('Your privacy is important. We never share your information with third parties.') ?></p>
                </div>
            </div>

            <!-- System Health Card -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header <?= $healthStatus ? 'bg-success' : 'bg-warning' ?> text-white py-2">
                    <h5 class="mb-0">
                        <i class="fa-solid fa-heartbeat"></i> <?= gettext('System Health') ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span><?= gettext('File Integrity:') ?></span>
                        <?php if ($integrityPassed): ?>
                            <span class="badge bg-success"><i class="fa-solid fa-check"></i> <?= gettext('OK') ?></span>
                        <?php else: ?>
                            <span class="badge bg-danger"><i class="fa-solid fa-times"></i> <?= gettext('Failed') ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span><?= gettext('Orphaned Files:') ?></span>
                        <?php if ($hasOrphanedFiles): ?>
                            <span class="badge bg-danger"><?= count($orphanedFiles) ?> <?= gettext('found') ?></span>
                        <?php else: ?>
                            <span class="badge bg-success"><i class="fa-solid fa-check"></i> <?= gettext('None') ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span><?= gettext('Configuration:') ?></span>
                        <?php if ($hasWarnings): ?>
                            <span class="badge bg-warning"><?= count($systemWarnings) ?> <?= gettext('issues') ?></span>
                        <?php else: ?>
                            <span class="badge bg-success"><i class="fa-solid fa-check"></i> <?= gettext('OK') ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($hasOrphanedFiles): ?>
                    <a href="<?= SystemURLs::getRootPath() ?>/admin/system/orphaned-files" class="btn btn-sm btn-outline-danger w-100 mb-2">
                        <i class="fa-solid fa-trash"></i> <?= gettext('Clean Up Files') ?>
                    </a>
                    <?php endif; ?>
                    <a href="<?= SystemURLs::getRootPath() ?>/admin/system/debug" class="btn btn-sm btn-outline-secondary w-100">
                        <i class="fa-solid fa-bug"></i> <?= gettext('Debug Info') ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Load admin dashboard JavaScript -->
<script src="<?= SystemURLs::assetVersioned('/skin/v2/admin-dashboard.min.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/js/importDemoData.js') ?>"></script>

<?php include SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
