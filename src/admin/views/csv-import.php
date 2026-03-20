<?php

use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<!-- Info Card -->
<div class="card card-primary">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-file-csv mr-2"></i><?= gettext('Import from Spreadsheet') ?></h3>
    </div>
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-1 text-center d-none d-md-block">
                <i class="fa-solid fa-table fa-3x text-primary"></i>
            </div>
            <div class="col-md-11">
                <p class="mb-2 lead"><?= gettext('Upload a CSV file to import families and people into ChurchCRM.') ?></p>
                <ul class="mb-2">
                    <li><?= gettext('Download the template to see the expected column format.') ?></li>
                    <li><?= gettext('Each row is one person. Members of the same family share a FamilyID.') ?></li>
                    <li><?= gettext('Compatible with Excel, Google Sheets, or any CSV export.') ?></li>
                </ul>
                <a href="<?= SystemURLs::getRootPath() ?>/admin/api/import/csv/families" class="btn btn-outline-primary">
                    <i class="fa-solid fa-download mr-2"></i><?= gettext('Download CSV Template') ?>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Upload Card -->
<div class="card card-warning">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-upload mr-2"></i><?= gettext('Upload CSV File') ?></h3>
    </div>
    <div class="card-body">
        <form id="csv-import-form" enctype="multipart/form-data">
            <div class="form-group mb-3">
                <div id="dropzone" class="dropzone-area text-center p-5 rounded" style="border: 2px dashed #dee2e6; cursor: pointer;">
                    <input type="file" name="csvFile" id="csvFile" class="d-none" accept=".csv">
                    <i class="fa-solid fa-file-arrow-up fa-4x text-muted mb-3 d-block"></i>
                    <p class="mb-1 font-weight-bold"><?= gettext('Drag and drop your CSV file here') ?></p>
                    <p class="text-muted mb-0"><?= gettext('or click to browse') ?></p>
                    <small class="text-muted"><?= gettext('Accepted format: .csv') ?></small>
                </div>
                <div id="fileInfo" class="alert alert-success mt-3 d-none">
                    <i class="fa-solid fa-file-csv mr-2"></i>
                    <strong id="fileName"></strong>
                    <span class="text-muted ml-2">(<span id="fileSize"></span>)</span>
                </div>
            </div>
            <button type="submit" class="btn btn-warning btn-lg btn-block">
                <i class="fa-solid fa-upload mr-2"></i><?= gettext('Import CSV') ?>
            </button>
        </form>
    </div>
</div>

<!-- Status Card -->
<div class="card d-none" id="statusCard">
    <div class="card-body">
        <div id="statusRunning" class="text-center py-4 d-none">
            <div class="spinner-border text-warning mb-3" role="status" style="width: 3rem; height: 3rem;">
                <span class="sr-only"><?= gettext('Loading...') ?></span>
            </div>
            <p class="mb-0 font-weight-bold text-warning"><?= gettext('Uploading, please wait...') ?></p>
        </div>
        <div id="statusError" class="text-center py-4 d-none">
            <i class="fa-solid fa-circle-xmark fa-3x text-danger mb-3"></i>
            <p class="mb-2 font-weight-bold text-danger"><?= gettext('Upload failed.') ?></p>
            <p class="text-muted" id="errorMessage"></p>
        </div>
    </div>
</div>

<style>
.dropzone-area {
    background-color: #f8f9fa;
    transition: background-color 0.2s ease, border-color 0.2s ease;
}
.dropzone-area:hover,
.dropzone-area.dragover {
    background-color: #e9ecef;
    border-color: #007bff !important;
}
.dropzone-area.has-file {
    background-color: #d4edda;
    border-color: #28a745 !important;
}
</style>

<script src="<?= SystemURLs::assetVersioned('/skin/v2/csv-import.min.js') ?>"></script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
