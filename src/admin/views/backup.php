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
<div class="card mb-3">
    <div class="card-header">
        <h3 class="card-title"><i class="ti ti-bulb me-2"></i><?= gettext('Backup Best Practices') ?></h3>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <div class="card card-sm">
                    <div class="card-body">
                        <h3 class="card-title text-info"><i class="ti ti-calendar-check me-2"></i><?= gettext('Regular Backups') ?></h3>
                        <p class="text-secondary"><?= gettext('Create a manual backup at least once a week, or enable the External Backup plugin for automatic WebDAV backups on a schedule.') ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-sm">
                    <div class="card-body">
                        <h3 class="card-title text-warning"><i class="ti ti-copy me-2"></i><?= gettext('Multiple Copies') ?></h3>
                        <p class="text-secondary"><?= gettext('Keep one copy in a fire-proof safe on-site and another off-site.') ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-sm">
                    <div class="card-body">
                        <h3 class="card-title text-danger"><i class="ti ti-lock me-2"></i><?= gettext('Encryption') ?></h3>
                        <p class="text-secondary"><?= gettext('Use external tools (GPG, 7-Zip) to encrypt backups before storing off-site.') ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Backup Form Card -->
<div class="card mb-3">
    <div class="card-status-top bg-primary"></div>
    <div class="card-header d-flex align-items-center">
        <h3 class="card-title"><i class="ti ti-database me-2"></i><?= gettext('Create Backup') ?></h3>
    </div>
    <form method="post" action="<?= SystemURLs::getRootPath() ?>/admin/api/database/backup" id="BackupDatabase">
        <div class="card-body">
            <!-- Backup Type Selection -->
            <div class="mb-3">
                <label class="form-label"><?= gettext('Backup Type') ?></label>
                <div class="form-selectgroup form-selectgroup-boxes d-flex flex-column flex-md-row gap-3">
                    <label class="form-selectgroup-item flex-fill">
                        <input type="radio" id="archiveType2" name="archiveType" value="2" class="form-selectgroup-input" checked>
                        <div class="form-selectgroup-label d-flex align-items-center p-3">
                            <div class="me-3">
                                <i class="ti ti-file-code" style="font-size: 1.5rem;"></i>
                            </div>
                            <div>
                                <strong><?= gettext('Database Only') ?></strong>
                                <span class="badge bg-light text-dark ms-1">.sql</span>
                                <div class="text-secondary small"><?= gettext('Exports database structure and data. Smaller file size.') ?></div>
                            </div>
                        </div>
                    </label>
                    <label class="form-selectgroup-item flex-fill">
                        <input type="radio" id="archiveType3" name="archiveType" value="3" class="form-selectgroup-input">
                        <div class="form-selectgroup-label d-flex align-items-center p-3">
                            <div class="me-3">
                                <i class="ti ti-file-zip" style="font-size: 1.5rem;"></i>
                            </div>
                            <div>
                                <strong><?= gettext('Full Backup') ?></strong>
                                <span class="badge bg-light text-dark ms-1">.tar.gz</span>
                                <div class="text-secondary small"><?= gettext('Includes database and all uploaded photos. Larger file size.') ?></div>
                            </div>
                        </div>
                    </label>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <div class="row">
                <div class="col-md-6 mb-2">
                    <button type="button" class="btn btn-primary w-100" id="doBackup">
                        <i class="ti ti-download me-2"></i><?= gettext('Generate & Download Backup') ?>
                    </button>
                </div>
                <div class="col-md-6 mb-2">
                    <?php if ($externalBackupEnabled && $externalBackupConfigured): ?>
                        <button type="button" class="btn btn-outline-secondary w-100" id="doRemoteBackup">
                            <i class="ti ti-cloud-upload me-2"></i><?= gettext('Backup to External Storage Now') ?>
                        </button>
                        <small class="form-text text-secondary d-block text-center mt-1">
                            <?= gettext('Also runs automatically via WebDAV based on your configured interval.') ?>
                        </small>
                    <?php elseif ($externalBackupEnabled && !$externalBackupConfigured): ?>
                        <a href="<?= SystemURLs::getRootPath() ?>/plugins/external-backup/settings" class="btn btn-outline-warning w-100">
                            <i class="ti ti-settings me-2"></i><?= gettext('Configure External Backup') ?>
                        </a>
                        <small class="form-text text-secondary d-block text-center mt-1">
                            <?= gettext('Plugin enabled but not configured. Once set up, backups run automatically to WebDAV on your configured interval.') ?>
                        </small>
                    <?php else: ?>
                        <a href="<?= SystemURLs::getRootPath() ?>/plugins/management" class="btn btn-outline-info w-100">
                            <i class="ti ti-plug me-2"></i><?= gettext('Enable External Backup Plugin') ?>
                        </a>
                        <small class="form-text text-secondary d-block text-center mt-1">
                            <?= gettext('Backs up to WebDAV cloud storage automatically on a configurable interval.') ?>
                        </small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Backup Status Card -->
<div class="card mb-3" id="statusCard">
    <div class="card-header" id="statusHeader">
        <h3 class="card-title"><i class="ti ti-list-check me-2"></i><?= gettext('Backup Status') ?></h3>
    </div>
    <div class="card-body">
        <div id="statusIdle">
            <div class="empty py-4">
                <div class="empty-icon">
                    <i class="ti ti-cloud-download" style="font-size: 2.5rem;"></i>
                </div>
                <p class="empty-title"><?= gettext('Ready to back up') ?></p>
                <p class="empty-subtitle text-secondary"><?= gettext('Select your options above and click a backup button.') ?></p>
            </div>
        </div>
        <div id="statusRunning" class="d-none">
            <div class="empty py-4">
                <div class="empty-icon">
                    <div class="spinner-border text-primary" role="status" style="width: 2.5rem; height: 2.5rem;">
                        <span class="visually-hidden"><?= gettext('Loading...') ?></span>
                    </div>
                </div>
                <p class="empty-title text-primary"><?= gettext('Backup in progress, please wait...') ?></p>
                <p class="empty-subtitle text-secondary"><?= gettext('This may take a few minutes for large databases.') ?></p>
            </div>
        </div>
        <div id="statusComplete" class="d-none">
            <div class="empty py-4">
                <div class="empty-icon">
                    <i class="ti ti-circle-check text-success" style="font-size: 2.5rem;"></i>
                </div>
                <p class="empty-title text-success" id="statusCompleteMessage"><?= gettext('Backup completed successfully!') ?></p>
                <div id="resultFiles" class="mt-3"></div>
            </div>
        </div>
        <div id="statusError" class="d-none">
            <div class="empty py-4">
                <div class="empty-icon">
                    <i class="ti ti-circle-x text-danger" style="font-size: 2.5rem;"></i>
                </div>
                <p class="empty-title text-danger"><?= gettext('Backup failed. Please try again.') ?></p>
            </div>
        </div>
    </div>
</div>

<script src="<?= SystemURLs::assetVersioned('/skin/v2/backup.min.js') ?>"></script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
