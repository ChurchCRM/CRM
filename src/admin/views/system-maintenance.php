<?php

use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<!-- Quick Actions -->
<div class="row mb-3">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-body">
                <div class="text-center">
                    <a href="<?= SystemURLs::getRootPath() ?>/admin/system/logs" class="btn btn-app bg-info">
                        <i class="fa fa-file-alt fa-3x"></i><br><?= gettext('System Logs') ?>
                    </a>
                    <a href="<?= SystemURLs::getRootPath() ?>/admin/system/debug" class="btn btn-app bg-secondary">
                        <i class="fa fa-bug fa-3x"></i><br><?= gettext('Debug Info') ?>
                    </a>
                    <a href="<?= SystemURLs::getRootPath() ?>/admin/system/orphaned-files" class="btn btn-app bg-danger">
                        <i class="fa fa-trash fa-3x"></i><br><?= gettext('Orphaned Files') ?>
                    </a>
                    <button type="button" class="btn btn-app bg-success" id="importDemoDataQuickBtn">
                        <i class="fa fa-users fa-3x"></i><br><?= gettext('Import Demo Data') ?>
                    </button>
                    <a href="<?= SystemURLs::getRootPath() ?>/admin/system/upgrade" class="btn btn-app bg-primary">
                        <i class="fa fa-cloud-upload-alt fa-3x"></i><br><?= gettext('System Upgrade') ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>


<div class="row">
    <!-- Backup Database -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-primary">
                <h4 class="card-title mb-0 text-white">
                    <i class="fa fa-download mr-2"></i><?= gettext("Backup Database") ?>
                </h4>
            </div>
            <div class="card-body">
                <p><?= gettext("Create a backup of your ChurchCRM database and optionally include photos.") ?></p>
                <ul class="mb-3">
                    <li><?= gettext("Make backups regularly (at least weekly)") ?></li>
                </ul>
                <div class="text-center">
                    <a href="<?= SystemURLs::getRootPath() ?>/BackupDatabase.php" class="btn btn-primary btn-block">
                        <i class="fa fa-download mr-2"></i><?= gettext("Backup Database") ?>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Restore Database -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-warning">
                <h4 class="card-title mb-0">
                    <i class="fa fa-upload mr-2"></i><?= gettext("Restore Database") ?>
                </h4>
            </div>
            <div class="card-body">
                <p><?= gettext("Restore ChurchCRM database from a backup file.") ?></p>
                <ul class="mb-3">
                    <li class="text-danger"><strong><?= gettext("WARNING: This completely erases the existing database") ?></strong></li>
                    <li><?= gettext("Supports ChurchInfo and older ChurchCRM backups") ?></li>
                </ul>
                <div class="text-center">
                    <a href="<?= SystemURLs::getRootPath() ?>/RestoreDatabase.php" class="btn btn-warning btn-block">
                        <i class="fa fa-upload mr-2"></i><?= gettext("Restore Database") ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Reset Database -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-danger">
                <h4 class="card-title mb-0 text-white">
                    <i class="fa fa-exclamation-triangle mr-2"></i><?= gettext("Reset Database") ?>
                </h4>
            </div>
            <div class="card-body">
                <p><?= gettext("Clear all data and reset the database to factory defaults. Application configuration (database credentials, install location, etc.) is preserved.") ?></p>
                <ul class="mb-3">
                    <li class="text-danger"><strong><?= gettext("WARNING: This completely erases all database records including families, groups, events, and financial data") ?></strong></li>                </ul>
                <div class="text-center">
                    <a href="<?= SystemURLs::getRootPath() ?>/admin/system/reset" class="btn btn-danger btn-block">
                        <i class="fa fa-exclamation-triangle mr-2"></i><?= gettext("Reset Database") ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>



<script src="<?= SystemURLs::getRootPath() ?>/skin/js/importDemoData.js"></script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>

