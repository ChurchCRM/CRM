<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Plugin\PluginManager;

// Check if External Backup plugin is active and configured
$externalBackupEnabled = PluginManager::isPluginActive('external-backup');
$externalBackupConfigured = false;
if ($externalBackupEnabled) {
    $plugin = PluginManager::getPlugin('external-backup');
    $externalBackupConfigured = $plugin !== null && $plugin->isConfigured();
}

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<!-- Best Practices Card -->
<div class="card">
    <div class="card-header d-flex align-items-center">
        <h3 class="card-title"><i class="fa-solid fa-lightbulb me-2"></i><?= gettext('Backup Best Practices') ?></h3>
        <div class="card-tools ms-auto">
            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                <i class="fa-solid fa-minus"></i>
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <div class="card-sm">
                    <div class="card-body">
                        <h3 class="card-title text-info"><i class="fa-solid fa-calendar-check me-2"></i><?= gettext('Regular Backups') ?></h3>
                        <p class="text-muted"><?= gettext('Make a backup at least once a week unless you have automated backups.') ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card-sm">
                    <div class="card-body">
                        <h3 class="card-title text-warning"><i class="fa-solid fa-copy me-2"></i><?= gettext('Multiple Copies') ?></h3>
                        <p class="text-muted"><?= gettext('Keep one copy in a fire-proof safe on-site and another off-site.') ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card-sm">
                    <div class="card-body">
                        <h3 class="card-title text-danger"><i class="fa-solid fa-lock me-2"></i><?= gettext('Encryption') ?></h3>
                        <p class="text-muted"><?= gettext('Use external tools (GPG, 7-Zip) to encrypt backups before storing off-site.') ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Backup Form Card -->
<div class="card">
    <div class="card-header d-flex align-items-center">
        <h3 class="card-title"><i class="fa-solid fa-database me-2"></i><?= gettext('Create Backup') ?></h3>
    </div>
    <div class="card-body">
        <form method="post" action="<?= SystemURLs::getRootPath() ?>/api/database/backup" id="BackupDatabase">
            <!-- Backup Type Selection -->
            <div class="mb-3">
                <label><?= gettext('Backup Type') ?></label>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-check form-check-inline">
                            <input type="radio" id="archiveType2" name="archiveType" value="2" class="form-check-input" checked>
                            <label class="form-check-label" for="archiveType2">
                                <i class="fa-solid fa-file-code me-1"></i><?= gettext('Database Only') ?>
                                <span class="badge bg-light text-dark">.sql</span>
                            </label>
                        </div>
                        <small class="form-text text-muted d-block mt-1">
                            <?= gettext('Exports database structure and data. Smaller file size.') ?>
                        </small>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check form-check-inline">
                            <input type="radio" id="archiveType3" name="archiveType" value="3" class="form-check-input">
                            <label class="form-check-label" for="archiveType3">
                                <i class="fa-solid fa-file-archive me-1"></i><?= gettext('Full Backup') ?>
                                <span class="badge bg-light text-dark">.tar.gz</span>
                            </label>
                        </div>
                        <small class="form-text text-muted d-block mt-1">
                            <?= gettext('Includes database and all uploaded photos. Larger file size.') ?>
                        </small>
                    </div>
                </div>
            </div>

            <hr>

            <!-- Action Buttons -->
            <div class="row">
                <div class="col-md-6 mb-2">
                    <button type="button" class="btn btn-primary w-100" id="doBackup">
                        <i class="fa-solid fa-download me-2"></i><?= gettext('Generate & Download Backup') ?>
                    </button>
                </div>
                <div class="col-md-6 mb-2">
                    <?php if ($externalBackupEnabled && $externalBackupConfigured): ?>
                        <button type="button" class="btn btn-outline-secondary w-100" id="doRemoteBackup">
                            <i class="fa-solid fa-cloud-upload-alt me-2"></i><?= gettext('Backup to External Storage') ?>
                        </button>
                    <?php elseif ($externalBackupEnabled && !$externalBackupConfigured): ?>
                        <a href="<?= SystemURLs::getRootPath() ?>/plugins/external-backup/settings" class="btn btn-outline-warning w-100">
                            <i class="fa-solid fa-cog me-2"></i><?= gettext('Configure External Backup') ?>
                        </a>
                        <small class="form-text text-muted text-center">
                            <?= gettext('External Backup plugin is enabled but not configured.') ?>
                        </small>
                    <?php else: ?>
                        <a href="<?= SystemURLs::getRootPath() ?>/plugins/management" class="btn btn-outline-info w-100">
                            <i class="fa-solid fa-plug me-2"></i><?= gettext('Enable External Backup Plugin') ?>
                        </a>
                        <small class="form-text text-muted text-center">
                            <?= gettext('Enable the External Backup plugin for WebDAV cloud storage.') ?>
                        </small>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Backup Status Card -->
<div class="card" id="statusCard">
    <div class="card-header" id="statusHeader">
        <h3 class="card-title"><i class="fa-solid fa-tasks me-2"></i><?= gettext('Backup Status') ?></h3>
    </div>
    <div class="card-body">
        <div id="statusIdle">
            <div class="text-center text-muted py-4">
                <i class="fa-solid fa-cloud-download-alt fa-3x mb-3"></i>
                <p class="mb-0"><?= gettext('Ready to create a backup. Select your options above and click a backup button.') ?></p>
            </div>
        </div>
        <div id="statusRunning" class="d-none">
            <div class="text-center py-4">
                <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                    <span class="visually-hidden"><?= gettext('Loading...') ?></span>
                </div>
                <p class="mb-0 text-primary fw-bold"><?= gettext('Backup in progress, please wait...') ?></p>
                <small class="text-muted"><?= gettext('This may take a few minutes for large databases.') ?></small>
            </div>
        </div>
        <div id="statusComplete" class="d-none">
            <div class="text-center py-4">
                <i class="fa-solid fa-circle-check fa-3x text-success mb-3"></i>
                <p class="mb-2 text-success fw-bold" id="statusCompleteMessage"><?= gettext('Backup completed successfully!') ?></p>
                <div id="resultFiles" class="mt-3"></div>
            </div>
        </div>
        <div id="statusError" class="d-none">
            <div class="text-center py-4">
                <i class="fa-solid fa-circle-xmark fa-3x text-danger mb-3"></i>
                <p class="mb-0 text-danger fw-bold"><?= gettext('Backup failed. Please try again.') ?></p>
            </div>
        </div>
    </div>
</div>

<script src="<?= SystemURLs::assetVersioned('/skin/v2/backup.min.js') ?>"></script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
