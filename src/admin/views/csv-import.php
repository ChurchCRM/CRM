<?php

use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<!-- How It Works -->
<div class="card mb-3">
    <div class="card-header">
        <h3 class="card-title"><i class="ti ti-info-circle me-2"></i><?= gettext('How the CSV Import Works') ?></h3>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="card card-sm h-100">
                    <div class="card-body">
                        <h4 class="card-title text-primary"><i class="ti ti-download me-2"></i><?= gettext('1. Download the template') ?></h4>
                        <p class="text-secondary mb-0"><?= gettext('The template already contains every column you can map — the core Person/Family fields plus each custom field and property your site defines.') ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-sm h-100">
                    <div class="card-body">
                        <h4 class="card-title text-info"><i class="ti ti-table-filled me-2"></i><?= gettext('2. Fill in your data') ?></h4>
                        <p class="text-secondary mb-0"><?= gettext('Each row is one person. People in the same family share a FamilyID. Leave columns blank when you have no value for them.') ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-sm h-100">
                    <div class="card-body">
                        <h4 class="card-title text-success"><i class="ti ti-upload me-2"></i><?= gettext('3. Upload and map') ?></h4>
                        <p class="text-secondary mb-0"><?= gettext('ChurchCRM auto-matches each column by name. Anything it cannot match is marked as Unmapped — pick a field or leave it as Ignore.') ?></p>
                    </div>
                </div>
            </div>
        </div>
        <div class="alert alert-info mt-3 mb-0">
            <div class="d-flex">
                <div><i class="ti ti-bulb fs-3 me-2"></i></div>
                <div>
                    <h4 class="alert-title mb-1"><?= gettext('Custom fields and properties are supported') ?></h4>
                    <div class="text-secondary">
                        <?= gettext('The template already includes a column for every Person/Family custom field and for every property you have defined. If you add columns manually, just use the exact name of the custom field or property as the column header and the importer will match it automatically.') ?>
                        <ul class="mb-0 mt-2">
                            <li><?= gettext('Boolean properties/fields accept yes / no / true / false / 1 / 0.') ?></li>
                            <li><?= gettext('Date fields accept YYYY-MM-DD or M/D/YYYY. Use 0000-MM-DD or M/D for a month and day with no year.') ?></li>
                            <li><?= gettext('Custom field columns you have not configured are ignored — nothing is created on the fly.') ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Step 1: Upload Card -->
<div class="card border-top border-warning border-3" id="upload-card">
    <div class="card-header d-flex align-items-center">
        <h3 class="card-title"><i class="fa-solid fa-upload me-2"></i><?= gettext('Step 1 — Upload CSV File') ?></h3>
    </div>
    <div class="card-body">
        <form id="csv-import-form" enctype="multipart/form-data">
            <div class="mb-3 mb-3">
                <div id="dropzone" class="dropzone-area text-center p-5 rounded" style="border: 2px dashed #dee2e6; cursor: pointer;">
                    <input type="file" name="csvFile" id="csvFile" class="d-none" accept=".csv">
                    <i class="fa-solid fa-file-arrow-up fa-4x text-muted mb-3 d-block"></i>
                    <p class="mb-1 fw-bold"><?= gettext('Drag and drop your CSV file here') ?></p>
                    <p class="text-muted mb-0"><?= gettext('or click to browse') ?></p>
                    <small class="text-muted"><?= gettext('Accepted format: .csv') ?></small>
                </div>
                <div id="fileInfo" class="alert alert-success mt-3 d-none">
                    <i class="fa-solid fa-file-csv me-2"></i>
                    <strong id="fileName"></strong>
                    <span class="text-muted ms-2">(<span id="fileSize"></span>)</span>
                </div>
            </div>
            <button type="submit" class="btn btn-warning w-100">
                <i class="fa-solid fa-upload me-2"></i><?= gettext('Upload CSV') ?>
            </button>
        </form>
    </div>
</div>

<!-- Upload Status -->
<div class="card d-none" id="status-card">
    <div class="card-body">
        <div id="statusRunning" class="text-center py-4 d-none">
            <div class="spinner-border text-warning mb-3" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden"><?= gettext('Loading...') ?></span>
            </div>
            <p class="mb-0 fw-bold text-warning"><?= gettext('Uploading, please wait...') ?></p>
        </div>
        <div id="statusError" class="text-center py-4 d-none">
            <i class="fa-solid fa-circle-xmark fa-3x text-danger mb-3"></i>
            <p class="mb-2 fw-bold text-danger"><?= gettext('Upload failed.') ?></p>
            <p class="text-muted" id="errorMessage"></p>
        </div>
    </div>
</div>

<!-- Step 3: Import Summary -->
<div class="card border-top border-success border-3 d-none" id="summary-card">
    <div class="card-header d-flex align-items-center">
        <h3 class="card-title"><i class="fa-solid fa-circle-check me-2"></i><?= gettext('Import Complete') ?></h3>
    </div>
    <div class="card-body">
        <div class="row text-center">
            <div class="col-4">
                <div class="card-sm">
                    <div class="card-body">
                        <h3 class="card-title"><i class="fa-solid fa-person me-2 text-success"></i><?= gettext('People') ?></h3>
                        <div class="h2 m-0 text-success" id="summary-imported">0</div>
                    </div>
                </div>
            </div>
            <div class="col-4">
                <div class="card-sm">
                    <div class="card-body">
                        <h3 class="card-title"><i class="fa-solid fa-house-user me-2 text-primary"></i><?= gettext('Families') ?></h3>
                        <div class="h2 m-0 text-primary" id="summary-families">0</div>
                    </div>
                </div>
            </div>
            <div class="col-4">
                <div class="card-sm">
                    <div class="card-body">
                        <h3 class="card-title"><i class="fa-solid fa-triangle-exclamation me-2 text-warning"></i><?= gettext('Skipped') ?></h3>
                        <div class="h2 m-0 text-warning" id="summary-skipped">0</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="card-footer">
        <a href="<?= SystemURLs::getRootPath() ?>/people/list" class="btn btn-success">
            <i class="fa-solid fa-people-group me-2"></i><?= gettext('View People') ?>
        </a>
        <button class="btn btn-outline-secondary ms-2" id="restart-import-summary">
            <i class="fa-solid fa-rotate-left me-2"></i><?= gettext('Import Another File') ?>
        </button>
    </div>
</div>

<!-- Step 2: Column Mapping -->
<div class="card d-none" id="mapping-card">
    <div class="card-header d-flex align-items-center">
        <h3 class="card-title"><i class="fa-solid fa-table-columns me-2"></i><?= gettext('Step 2 — Map Columns') ?></h3>
    </div>
    <div class="card-body">
        <p class="mb-3">
            <span class="badge bg-green-lt text-green me-2"><i class="fa-solid fa-check me-1"></i><?= gettext('Auto-mapped') ?></span><?= gettext('Column was automatically matched to a ChurchCRM field.') ?>
            <span class="badge bg-warning text-dark ms-3 me-2"><i class="fa-solid fa-triangle-exclamation me-1"></i><?= gettext('Unmapped') ?></span><?= gettext('No match found — select a field or leave as Ignore.') ?>
        </p>
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0">
                <thead>
                    <tr>
                        <th><?= gettext('CSV Column') ?></th>
                        <th><?= gettext('Sample Data') ?></th>
                        <th><?= gettext('Status') ?></th>
                        <th><?= gettext('Map to Field') ?></th>
                    </tr>
                </thead>
                <tbody id="mapping-tbody"></tbody>
            </table>
        </div>
    </div>
    <div class="card-footer">
        <input type="hidden" id="mapping-token">
        <button class="btn btn-primary" id="execute-import">
            <i class="fa-solid fa-file-import me-2"></i><?= gettext('Import Data') ?>
        </button>
        <button class="btn btn-outline-secondary ms-2" id="restart-import">
            <i class="fa-solid fa-rotate-left me-2"></i><?= gettext('Start Over') ?>
        </button>
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
