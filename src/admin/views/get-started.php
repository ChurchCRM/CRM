<?php

use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-1">
                <i class="fa-solid fa-play-circle text-primary"></i> <?= gettext('Get Started') ?>
            </h2>
            <p class="text-muted mb-0"><?= gettext('Choose how you would like to add your church data.') ?></p>
        </div>
    </div>

    <div class="row justify-content-center">
        <!-- Start Fresh -->
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center p-4">
                    <div class="mb-3">
                        <span class="bg-success rounded-circle d-inline-flex align-items-center justify-content-center" style="width:4rem;height:4rem;">
                            <i class="fa-solid fa-pen-to-square fa-2x text-white"></i>
                        </span>
                    </div>
                    <h4 class="card-title"><?= gettext('Start Fresh') ?></h4>
                    <p class="card-text text-muted">
                        <?= gettext('Add your families and people one at a time. Perfect for small churches or getting started quickly.') ?>
                    </p>
                    <a href="<?= SystemURLs::getRootPath() ?>/admin/get-started/manual" class="btn btn-success btn-block mt-3">
                        <i class="fa-solid fa-arrow-right mr-1"></i> <?= gettext('Start Fresh') ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- Import CSV -->
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center p-4">
                    <div class="mb-3">
                        <span class="bg-info rounded-circle d-inline-flex align-items-center justify-content-center" style="width:4rem;height:4rem;">
                            <i class="fa-solid fa-file-import fa-2x text-white"></i>
                        </span>
                    </div>
                    <h4 class="card-title"><?= gettext('Import from CSV') ?></h4>
                    <p class="card-text text-muted">
                        <?= gettext('Already have data in a spreadsheet? Import it all at once using a CSV file.') ?>
                    </p>
                    <a href="<?= SystemURLs::getRootPath() ?>/CSVImport.php" class="btn btn-info btn-block mt-3">
                        <i class="fa-solid fa-upload mr-1"></i> <?= gettext('Import CSV') ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- Use Demo Data -->
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center p-4">
                    <div class="mb-3">
                        <span class="bg-warning rounded-circle d-inline-flex align-items-center justify-content-center" style="width:4rem;height:4rem;">
                            <i class="fa-solid fa-flask fa-2x text-white"></i>
                        </span>
                    </div>
                    <h4 class="card-title"><?= gettext('Use Demo Data') ?></h4>
                    <p class="card-text text-muted">
                        <?= gettext('Explore ChurchCRM with sample families, people, and groups. Import demo data from the Admin Dashboard.') ?>
                    </p>
                    <a href="<?= SystemURLs::getRootPath() ?>/admin/" class="btn btn-warning btn-block mt-3">
                        <i class="fa-solid fa-database mr-1"></i> <?= gettext('Go to Admin Dashboard') ?>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Back link -->
    <div class="row">
        <div class="col-12">
            <a href="<?= SystemURLs::getRootPath() ?>/admin/" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-left mr-1"></i> <?= gettext('Back to Admin Dashboard') ?>
            </a>
        </div>
    </div>
</div>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
