<?php

use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<!-- Breadcrumb Navigation -->
<div class="row mb-3">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 bg-light">
                <li class="breadcrumb-item">
                    <a href="<?= SystemURLs::getRootPath() ?>/v2/dashboard">
                        <i class="fa-solid fa-home"></i>
                    </a>
                </li>
                <li class="breadcrumb-item">
                    <a href="<?= SystemURLs::getRootPath() ?>/plugins/management"><?= gettext('Plugins') ?></a>
                </li>
                <li class="breadcrumb-item active"><?= gettext('External Backup') ?></li>
            </ol>
        </nav>
    </div>
</div>

<!-- Status Overview -->
<div class="row">
    <div class="col-lg-4 col-md-6">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="<?= $status['configured'] ? 'bg-success' : 'bg-warning' ?> text-white avatar rounded-circle">
                            <i class="fa-solid fa-<?= $status['configured'] ? 'check-circle' : 'exclamation-triangle' ?>"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="h3 m-0"><?= $status['configured'] ? gettext('Configured') : gettext('Not Configured') ?></div>
                        <div class="text-muted"><?= gettext('WebDAV Connection') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4 col-md-6">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="<?= $status['autoBackupEnabled'] ? 'bg-info' : 'bg-secondary' ?> text-white avatar rounded-circle">
                            <i class="fa-solid fa-clock"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="h3 m-0"><?= $status['autoBackupEnabled'] ? $status['autoInterval'] . 'h' : gettext('Disabled') ?></div>
                        <div class="text-muted"><?= gettext('Auto-Backup Interval') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4 col-md-6">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-primary text-white avatar rounded-circle">
                            <i class="fa-solid fa-history"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="h3 m-0"><?= !empty($status['lastBackup']) ? $status['lastBackup'] : gettext('Never') ?></div>
                        <div class="text-muted"><?= gettext('Last Backup') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Configuration Card -->
<div class="card">
    <div class="card-header d-flex align-items-center">
        <h3 class="card-title"><i class="fa-solid fa-cog me-2"></i><?= gettext('WebDAV Configuration') ?></h3>
    </div>
    <div class="card-body">
        <div class="callout callout-info">
            <h5><i class="fa-solid fa-circle-info me-1"></i><?= gettext('About WebDAV Backups') ?></h5>
            <p class="mb-0">
                <?= gettext('Configure this plugin to automatically backup your ChurchCRM database to WebDAV-compatible cloud storage services like Nextcloud, ownCloud, or any WebDAV server. Backups are encrypted during transfer using HTTPS.') ?>
            </p>
        </div>

        <p class="text-muted">
            <i class="fa-solid fa-arrow-right me-1"></i>
            <?= gettext('Configure these settings in the') ?>
            <a href="<?= SystemURLs::getRootPath() ?>/plugins/management/external-backup">
                <?= gettext('Plugin Management') ?> <i class="fa-solid fa-arrow-up-right-from-square fa-xs"></i>
            </a>
        </p>

        <hr>

        <div class="row">
            <div class="col-md-6">
                <dl>
                    <dt><?= gettext('Backup Type') ?></dt>
                    <dd><?= htmlspecialchars($status['backupType'] ?: gettext('Not set'), ENT_QUOTES, 'UTF-8') ?></dd>

                    <dt><?= gettext('Endpoint URL') ?></dt>
                    <dd>
                        <?php if (!empty($status['endpoint'])): ?>
                            <code><?= htmlspecialchars($status['endpoint'], ENT_QUOTES, 'UTF-8') ?></code>
                        <?php else: ?>
                            <span class="text-muted"><?= gettext('Not configured') ?></span>
                        <?php endif; ?>
                    </dd>
                </dl>
            </div>
            <div class="col-md-6">
                <dl>
                    <dt><?= gettext('Auto-Backup Interval') ?></dt>
                    <dd>
                        <?php if ($status['autoInterval'] > 0): ?>
                            <?= sprintf(ngettext('%d hour', '%d hours', $status['autoInterval']), $status['autoInterval']) ?>
                        <?php else: ?>
                            <span class="text-muted"><?= gettext('Disabled') ?></span>
                        <?php endif; ?>
                    </dd>

                    <dt><?= gettext('Last Successful Backup') ?></dt>
                    <dd>
                        <?php if (!empty($status['lastBackup'])): ?>
                            <?= htmlspecialchars($status['lastBackup'], ENT_QUOTES, 'UTF-8') ?>
                        <?php else: ?>
                            <span class="text-muted"><?= gettext('No backups recorded') ?></span>
                        <?php endif; ?>
                    </dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<!-- Test Connection Card -->
<div class="card-secondary">
    <div class="card-header d-flex align-items-center">
        <h3 class="card-title"><i class="fa-solid fa-plug me-2"></i><?= gettext('Test Connection') ?></h3>
    </div>
    <div class="card-body">
        <?php if ($isConfigured): ?>
            <p><?= gettext('Click the button below to test your WebDAV connection settings.') ?></p>
            <button type="button" class="btn btn-info" id="testConnection">
                <i class="fa-solid fa-circle-check me-1"></i><?= gettext('Test WebDAV Connection') ?>
            </button>
            <div id="testResult" class="mt-3"></div>
        <?php else: ?>
            <div class="callout callout-warning mb-0">
                <p class="mb-0">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i>
                    <?= gettext('Please configure the WebDAV settings before testing the connection.') ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Manual Backup Card -->
<div class="card border-top border-success border-3">
    <div class="card-header d-flex align-items-center">
        <h3 class="card-title"><i class="fa-solid fa-cloud-upload-alt me-2"></i><?= gettext('Manual Backup') ?></h3>
    </div>
    <div class="card-body">
        <?php if ($isConfigured): ?>
            <p><?= gettext('Create a backup now and upload it to your WebDAV server.') ?></p>
            <div class="mb-3">
                <label><?= gettext('Backup Type') ?></label>
                <div class="form-check">
                    <input type="radio" id="backupType2" name="backupType" value="2" class="form-check-input" checked>
                    <label class="form-check-label" for="backupType2">
                        <i class="fa-solid fa-file-code me-1"></i><?= gettext('Database Only') ?>
                        <span class="badge bg-light text-dark">.sql</span>
                    </label>
                </div>
                <div class="form-check">
                    <input type="radio" id="backupType3" name="backupType" value="3" class="form-check-input">
                    <label class="form-check-label" for="backupType3">
                        <i class="fa-solid fa-file-archive me-1"></i><?= gettext('Full Backup') ?>
                        <span class="badge bg-light text-dark">.tar.gz</span>
                    </label>
                </div>
            </div>
            <button type="button" class="btn btn-success" id="doRemoteBackup">
                <i class="fa-solid fa-cloud-upload-alt me-1"></i><?= gettext('Backup to WebDAV Now') ?>
            </button>
            <div id="backupResult" class="mt-3"></div>
        <?php else: ?>
            <div class="callout callout-warning mb-0">
                <p class="mb-0">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i>
                    <?= gettext('Please configure the WebDAV settings before creating a remote backup.') ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
$(document).ready(function() {
    // Test Connection
    $('#testConnection').on('click', function() {
        var $btn = $(this);
        var originalText = $btn.html();
        
        $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-1"></i>' + i18next.t('Testing...'));
        $('#testResult').html('');
        
        fetch(window.CRM.root + '/plugins/external-backup/api/test', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            var alertClass = data.success ? 'alert-success' : 'alert-danger';
            var icon = data.success ? 'check-circle' : 'times-circle';
            $('#testResult').html(
                '<div class="alert ' + alertClass + '">' +
                '<i class="fa-solid fa-' + icon + ' me-1"></i>' + data.message +
                '</div>'
            );
        })
        .catch(function() {
            $('#testResult').html(
                '<div class="alert alert-danger">' +
                '<i class="fa-solid fa-circle-xmark me-1"></i>' + i18next.t('Connection test failed') +
                '</div>'
            );
        })
        .finally(function() {
            $btn.prop('disabled', false).html(originalText);
        });
    });
    
    // Manual Remote Backup
    $('#doRemoteBackup').on('click', function() {
        var $btn = $(this);
        var originalText = $btn.html();
        var backupType = $('input[name=backupType]:checked').val();
        
        $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-1"></i>' + i18next.t('Backing up...'));
        $('#backupResult').html(
            '<div class="text-info"><i class="fa-solid fa-spinner fa-spin me-1"></i>' + 
            i18next.t('Creating and uploading backup. This may take several minutes...') + 
            '</div>'
        );
        
        fetch(window.CRM.root + '/plugins/external-backup/api/backup', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ BackupType: backupType })
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            var alertClass = data.success ? 'alert-success' : 'alert-danger';
            var icon = data.success ? 'check-circle' : 'times-circle';
            $('#backupResult').html(
                '<div class="alert ' + alertClass + '">' +
                '<i class="fa-solid fa-' + icon + ' me-1"></i>' + data.message +
                '</div>'
            );
            if (data.success) {
                window.CRM.notify(i18next.t('Backup uploaded successfully'), { type: 'success' });
            }
        })
        .catch(function() {
            $('#backupResult').html(
                '<div class="alert alert-danger">' +
                '<i class="fa-solid fa-circle-xmark me-1"></i>' + i18next.t('Backup failed') +
                '</div>'
            );
        })
        .finally(function() {
            $btn.prop('disabled', false).html(originalText);
        });
    });
});
</script>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
