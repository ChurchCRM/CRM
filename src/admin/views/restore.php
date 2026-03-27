<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\SystemService;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

$maxUploadSize = SystemService::getMaxUploadFileSize();
// $isOnboarding is injected by the route handler when ?context=onboarding is present
$isOnboarding = $isOnboarding ?? false;
?>

<?php if ($isOnboarding): ?>
<!-- Onboarding Welcome Card -->
<div class="card mb-3">
    <div class="card-status-top bg-primary"></div>
    <div class="card-header d-flex align-items-center">
        <h3 class="card-title"><i class="ti ti-circle-check me-2"></i><?= gettext('Welcome Back!') ?></h3>
    </div>
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-1 text-center d-none d-md-block">
                <i class="ti ti-database fa-3x text-primary" style="font-size: 2.5rem;"></i>
            </div>
            <div class="col-md-11">
                <p class="mb-2 lead"><?= gettext("Let's restore your previous ChurchCRM data.") ?></p>
                <ul class="mb-2">
                    <li><?= gettext('Since this is a fresh installation, restoring will simply load your backup data.') ?></li>
                    <li><?= gettext('Supports backups from ChurchCRM and ChurchInfo.') ?></li>
                    <li><?= gettext('Your backup will be automatically upgraded to the latest database schema if needed.') ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<!-- Warning Card -->
<div class="card mb-3">
    <div class="card-status-top bg-danger"></div>
    <div class="card-header d-flex align-items-center">
        <h3 class="card-title"><i class="ti ti-alert-triangle me-2"></i><?= gettext('Important Warning') ?></h3>
    </div>
    <div class="card-body">
        <ul class="mb-0">
            <li class="text-danger"><strong><?= gettext('CAUTION: Restoring a backup will completely erase the existing database and replace it with the backup data.') ?></strong></li>
            <li><?= gettext('This action cannot be undone. Make sure you have a backup of the current data if needed.') ?></li>
            <li><?= gettext('If you upload a backup from ChurchInfo or a previous version of ChurchCRM, it will be automatically upgraded to the current database schema.') ?></li>
        </ul>
    </div>
</div>
<?php endif; ?>

<!-- Restore Form Card -->
<div class="card mb-3">
    <div class="card-status-top bg-warning"></div>
    <div class="card-header d-flex align-items-center">
        <h3 class="card-title"><i class="ti ti-upload me-2"></i><?= gettext('Restore Database') ?></h3>
    </div>
    <form id="restoredatabase" action="<?= SystemURLs::getRootPath() ?>/api/database/restore" method="POST" enctype="multipart/form-data">
        <div class="card-body">
            <!-- File Upload Area -->
            <div class="mb-3">
                <label class="form-label required"><?= gettext('Select Backup File') ?></label>
                <div id="dropzone" class="dropzone-area text-center p-4 rounded" style="cursor: pointer;">
                    <input type="file" name="restoreFile" id="restoreFile" class="d-none" accept=".sql,.gz">
                    <i class="ti ti-cloud-upload mb-3" style="font-size: 2.5rem;"></i>
                    <p class="mb-1"><?= gettext('Drag and drop your backup file here') ?></p>
                    <p class="text-secondary mb-0"><?= gettext('or click to browse') ?></p>
                    <small class="text-secondary"><?= gettext('Supported formats:') ?> .sql, .sql.gz, .tar.gz</small>
                </div>
                <div id="fileInfo" class="card card-sm mt-2 d-none">
                    <div class="card-body py-2 px-3">
                        <i class="ti ti-file me-2"></i>
                        <strong id="fileName"></strong>
                        <span class="text-secondary ms-2">(<span id="fileSize"></span>)</span>
                    </div>
                </div>
                <small class="form-text text-secondary">
                    <?= gettext('Maximum upload size') ?>: <strong><?= $maxUploadSize ?></strong>
                </small>
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-warning w-100" id="submitRestore">
                <i class="ti ti-upload me-2"></i><?= gettext('Restore Database') ?>
            </button>
        </div>
    </form>
</div>

<!-- Restore Status Card -->
<div class="card mb-3" id="statusCard">
    <div class="card-header d-flex align-items-center">
        <h3 class="card-title"><i class="ti ti-list-check me-2"></i><?= gettext('Restore Status') ?></h3>
    </div>
    <div class="card-body">
        <div id="statusIdle">
            <div class="empty py-4">
                <div class="empty-icon">
                    <i class="ti ti-database" style="font-size: 2.5rem;"></i>
                </div>
                <p class="empty-title"><?= gettext('Ready to restore') ?></p>
                <p class="empty-subtitle text-secondary"><?= gettext('Select a backup file and click Restore Database.') ?></p>
            </div>
        </div>
        <div id="statusRunning" class="d-none">
            <div class="empty py-4">
                <div class="empty-icon">
                    <div class="spinner-border text-warning" role="status" style="width: 2.5rem; height: 2.5rem;">
                        <span class="visually-hidden"><?= gettext('Loading...') ?></span>
                    </div>
                </div>
                <p class="empty-title text-warning"><?= gettext('Restore in progress, please wait...') ?></p>
                <p class="empty-subtitle text-secondary"><?= gettext('This may take several minutes for large databases. Do not close this page.') ?></p>
            </div>
        </div>
        <div id="statusError" class="d-none">
            <div class="empty py-4">
                <div class="empty-icon">
                    <i class="ti ti-circle-x text-danger" style="font-size: 2.5rem;"></i>
                </div>
                <p class="empty-title text-danger"><?= gettext('Restore failed.') ?></p>
                <p class="empty-subtitle text-secondary" id="errorMessage"><?= gettext('Please check the backup file and try again.') ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Success Modal Overlay -->
<div class="modal fade" id="restoreSuccessModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="restoreSuccessModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-5">
                <i class="ti ti-circle-check text-success mb-4" style="font-size: 4rem;"></i>
                <h3 class="text-success mb-3"><?= gettext('Database Restored Successfully!') ?></h3>
                <div id="restoreModalMessages" class="text-start mb-3"></div>
                <?php if ($isOnboarding): ?>
                <p class="text-secondary mb-2"><?= gettext('Your backup data has been loaded. You will be redirected to log in with your previous credentials.') ?></p>
                <?php else: ?>
                <p class="text-secondary mb-2"><?= gettext('You will be logged out and redirected to the login page.') ?></p>
                <?php endif; ?>
                <div class="mt-4">
                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status">
                        <span class="visually-hidden"><?= gettext('Loading...') ?></span>
                    </div>
                    <span id="redirectCountdown"><?= gettext('Redirecting in') ?> <strong>5</strong> <?= gettext('seconds...') ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.dropzone-area {
    background-color: var(--tblr-bg-surface-secondary);
    border: 2px dashed var(--tblr-border-color);
    transition: all 0.3s ease;
}
.dropzone-area:hover, .dropzone-area.dragover {
    background-color: var(--tblr-bg-surface);
    border-color: var(--tblr-primary);
}
.dropzone-area.has-file {
    background-color: var(--tblr-success-lt);
    border-color: var(--tblr-success);
}
</style>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    window.CRM.restoreContext = <?= json_encode($isOnboarding ? 'onboarding' : 'standard') ?>;
</script>
<script src="<?= SystemURLs::assetVersioned('/skin/v2/restore.min.js') ?>"></script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
