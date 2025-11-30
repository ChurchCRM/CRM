<?php

use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<!-- Best Practices Card -->
<div class="card card-info">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-lightbulb mr-2"></i><?= gettext('Backup Best Practices') ?></h3>
        <div class="card-tools">
            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                <i class="fa-solid fa-minus"></i>
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <div class="info-box bg-light">
                    <span class="info-box-icon bg-info"><i class="fa-solid fa-calendar-check"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text"><?= gettext('Regular Backups') ?></span>
                        <span class="info-box-number text-muted" style="font-size: 0.9rem; font-weight: normal;">
                            <?= gettext('Make a backup at least once a week unless you have automated backups.') ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="info-box bg-light">
                    <span class="info-box-icon bg-warning"><i class="fa-solid fa-copy"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text"><?= gettext('Multiple Copies') ?></span>
                        <span class="info-box-number text-muted" style="font-size: 0.9rem; font-weight: normal;">
                            <?= gettext('Keep one copy in a fire-proof safe on-site and another off-site.') ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="info-box bg-light">
                    <span class="info-box-icon bg-danger"><i class="fa-solid fa-lock"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text"><?= gettext('Encryption') ?></span>
                        <span class="info-box-number text-muted" style="font-size: 0.9rem; font-weight: normal;">
                            <?= gettext('Use external tools (GPG, 7-Zip) to encrypt backups before storing off-site.') ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Backup Form Card -->
<div class="card card-primary">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-database mr-2"></i><?= gettext('Create Backup') ?></h3>
    </div>
    <div class="card-body">
        <form method="post" action="<?= SystemURLs::getRootPath() ?>/api/database/backup" id="BackupDatabase">
            <!-- Backup Type Selection -->
            <div class="form-group">
                <label><?= gettext('Backup Type') ?></label>
                <div class="row">
                    <div class="col-md-6">
                        <div class="custom-control custom-radio custom-control-inline">
                            <input type="radio" id="archiveType2" name="archiveType" value="2" class="custom-control-input" checked>
                            <label class="custom-control-label" for="archiveType2">
                                <i class="fa-solid fa-file-code mr-1"></i><?= gettext('Database Only') ?>
                                <span class="badge badge-secondary">.sql</span>
                            </label>
                        </div>
                        <small class="form-text text-muted d-block mt-1">
                            <?= gettext('Exports database structure and data. Smaller file size.') ?>
                        </small>
                    </div>
                    <div class="col-md-6">
                        <div class="custom-control custom-radio custom-control-inline">
                            <input type="radio" id="archiveType3" name="archiveType" value="3" class="custom-control-input">
                            <label class="custom-control-label" for="archiveType3">
                                <i class="fa-solid fa-file-archive mr-1"></i><?= gettext('Full Backup') ?>
                                <span class="badge badge-secondary">.tar.gz</span>
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
                    <button type="button" class="btn btn-primary btn-block" id="doBackup">
                        <i class="fa-solid fa-download mr-2"></i><?= gettext('Generate & Download Backup') ?>
                    </button>
                </div>
                <div class="col-md-6 mb-2">
                    <button type="button" class="btn btn-outline-secondary btn-block" id="doRemoteBackup">
                        <i class="fa-solid fa-cloud-upload-alt mr-2"></i><?= gettext('Backup to External Storage') ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Backup Status Card -->
<div class="card" id="statusCard">
    <div class="card-header" id="statusHeader">
        <h3 class="card-title"><i class="fa-solid fa-tasks mr-2"></i><?= gettext('Backup Status') ?></h3>
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
                    <span class="sr-only"><?= gettext('Loading...') ?></span>
                </div>
                <p class="mb-0 text-primary font-weight-bold"><?= gettext('Backup in progress, please wait...') ?></p>
                <small class="text-muted"><?= gettext('This may take a few minutes for large databases.') ?></small>
            </div>
        </div>
        <div id="statusComplete" class="d-none">
            <div class="text-center py-4">
                <i class="fa-solid fa-check-circle fa-3x text-success mb-3"></i>
                <p class="mb-2 text-success font-weight-bold" id="statusCompleteMessage"><?= gettext('Backup completed successfully!') ?></p>
                <div id="resultFiles" class="mt-3"></div>
            </div>
        </div>
        <div id="statusError" class="d-none">
            <div class="text-center py-4">
                <i class="fa-solid fa-times-circle fa-3x text-danger mb-3"></i>
                <p class="mb-0 text-danger font-weight-bold"><?= gettext('Backup failed. Please try again.') ?></p>
            </div>
        </div>
    </div>
</div>

<script src="<?= SystemURLs::getRootPath() ?>/skin/v2/backup.min.js"></script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
