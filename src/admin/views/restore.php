<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\SystemService;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

$maxUploadSize = SystemService::getMaxUploadFileSize();
?>

<!-- Warning Card -->
<div class="card card-danger">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-exclamation-triangle mr-2"></i><?= gettext('Important Warning') ?></h3>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-12">
                <ul class="mb-0">
                    <li class="text-danger"><strong><?= gettext('CAUTION: Restoring a backup will completely erase the existing database and replace it with the backup data.') ?></strong></li>
                    <li><?= gettext('This action cannot be undone. Make sure you have a backup of the current data if needed.') ?></li>
                    <li><?= gettext('If you upload a backup from ChurchInfo or a previous version of ChurchCRM, it will be automatically upgraded to the current database schema.') ?></li>
                </ul>
                <div class="mt-3">
                    <a href="<?= SystemURLs::getRootPath() ?>/admin/system/backup" class="btn btn-outline-primary">
                        <i class="fa-solid fa-download mr-2"></i><?= gettext('Create a backup first') ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Restore Form Card -->
<div class="card card-warning">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-upload mr-2"></i><?= gettext('Restore Database') ?></h3>
    </div>
    <div class="card-body">
        <form id="restoredatabase" action="<?= SystemURLs::getRootPath() ?>/api/database/restore" method="POST" enctype="multipart/form-data">
            
            <!-- File Upload Area -->
            <div class="form-group">
                <label><?= gettext('Select Backup File') ?></label>
                <div id="dropzone" class="dropzone-area text-center p-4 border border-dashed rounded" style="border-style: dashed !important; cursor: pointer;">
                    <input type="file" name="restoreFile" id="restoreFile" class="d-none" accept=".sql,.gz">
                    <i class="fa-solid fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                    <p class="mb-1"><?= gettext('Drag and drop your backup file here') ?></p>
                    <p class="text-muted mb-0"><?= gettext('or click to browse') ?></p>
                    <small class="text-muted"><?= gettext('Supported formats:') ?> .sql, .sql.gz, .tar.gz</small>
                </div>
                <div id="fileInfo" class="alert alert-info mt-2 d-none">
                    <i class="fa-solid fa-file mr-2"></i>
                    <strong id="fileName"></strong>
                    <span class="text-muted ml-2">(<span id="fileSize"></span>)</span>
                </div>
                <small class="form-text text-muted">
                    <?= gettext('Maximum upload size') ?>: <strong><?= $maxUploadSize ?></strong>
                </small>
            </div>

            <hr>

            <!-- Submit Button -->
            <button type="submit" class="btn btn-warning btn-block btn-lg" id="submitRestore">
                <i class="fa-solid fa-upload mr-2"></i><?= gettext('Restore Database') ?>
            </button>
        </form>
    </div>
</div>

<!-- Restore Status Card -->
<div class="card" id="statusCard">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-tasks mr-2"></i><?= gettext('Restore Status') ?></h3>
    </div>
    <div class="card-body">
        <div id="statusIdle">
            <div class="text-center text-muted py-4">
                <i class="fa-solid fa-database fa-3x mb-3"></i>
                <p class="mb-0"><?= gettext('Ready to restore. Select a backup file and click Restore Database.') ?></p>
            </div>
        </div>
        <div id="statusRunning" class="d-none">
            <div class="text-center py-4">
                <div class="spinner-border text-warning mb-3" role="status" style="width: 3rem; height: 3rem;">
                    <span class="sr-only"><?= gettext('Loading...') ?></span>
                </div>
                <p class="mb-0 text-warning font-weight-bold"><?= gettext('Restore in progress, please wait...') ?></p>
                <small class="text-muted"><?= gettext('This may take several minutes for large databases. Do not close this page.') ?></small>
            </div>
        </div>
        <div id="statusError" class="d-none">
            <div class="text-center py-4">
                <i class="fa-solid fa-times-circle fa-3x text-danger mb-3"></i>
                <p class="mb-2 text-danger font-weight-bold"><?= gettext('Restore failed.') ?></p>
                <p class="text-muted" id="errorMessage"><?= gettext('Please check the backup file and try again.') ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Success Modal Overlay -->
<div class="modal fade" id="restoreSuccessModal" data-backdrop="static" data-keyboard="false" tabindex="-1" aria-labelledby="restoreSuccessModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-5">
                <i class="fa-solid fa-check-circle fa-5x text-success mb-4"></i>
                <h3 class="text-success mb-3"><?= gettext('Database Restored Successfully!') ?></h3>
                <div id="restoreModalMessages" class="text-left mb-3"></div>
                <p class="text-muted mb-2"><?= gettext('You will be logged out and redirected to the login page.') ?></p>
                <div class="mt-4">
                    <div class="spinner-border spinner-border-sm text-primary mr-2" role="status">
                        <span class="sr-only"><?= gettext('Loading...') ?></span>
                    </div>
                    <span id="redirectCountdown"><?= gettext('Redirecting in') ?> <strong>5</strong> <?= gettext('seconds...') ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.dropzone-area {
    background-color: #f8f9fa;
    transition: all 0.3s ease;
}
.dropzone-area:hover, .dropzone-area.dragover {
    background-color: #e9ecef;
    border-color: #007bff !important;
}
.dropzone-area.has-file {
    background-color: #d4edda;
    border-color: #28a745 !important;
}
</style>

<script src="<?= SystemURLs::getRootPath() ?>/skin/v2/restore.min.js"></script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
