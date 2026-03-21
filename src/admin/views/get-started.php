<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Plugin\PluginManager;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

$isMailChimpActive = PluginManager::isPluginActive('mailchimp');
?>

<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/get-started.min.css') ?>">

<div class="container-fluid">
<div class="gs-wrap">

    <!-- ── Hero ──────────────────────────────────────────────── -->
    <div class="gs-hero mb-4">
        <h2><i class="fa-solid fa-rocket mr-2"></i><?= gettext('Get Your Data Into ChurchCRM') ?></h2>
        <p><?= gettext('Choose how you\'d like to populate your database. You can always use a different method later.') ?></p>
    </div>

    <!-- ── 2 × 2 option grid ──────────────────────────────────── -->
    <p class="gs-section-label"><?= gettext('Pick a path to get started') ?></p>

    <div class="row">

        <!-- Explore with Demo Data -->
        <div class="col-sm-6 mb-4">
            <!-- <a> wrapping the whole card; JS intercepts the click via id -->
            <a href="#" id="importDemoDataV2" role="button" class="gs-card gs-card--green">
                <div class="gs-card-icon">
                    <i class="fa-solid fa-flask"></i>
                </div>
                <h5><?= gettext('Explore with Demo Data') ?></h5>
                <p><?= gettext('Load sample families, people, groups, and giving records. The safest way to learn ChurchCRM before committing your real data.') ?></p>
                <span class="gs-card-cta">
                    <?= gettext('Load demo data') ?>
                    <i class="fa-solid fa-arrow-right fa-sm"></i>
                </span>
            </a>
        </div>

        <!-- Import from CSV -->
        <div class="col-sm-6 mb-4">
            <a href="<?= SystemURLs::getRootPath() ?>/admin/import/csv" class="gs-card gs-card--blue">
                <div class="gs-card-icon">
                    <i class="fa-solid fa-file-csv"></i>
                </div>
                <h5><?= gettext('Import from a Spreadsheet') ?></h5>
                <p><?= gettext('Upload a CSV from Excel, Google Sheets, or your previous church management system and map columns to ChurchCRM fields.') ?></p>
                <span class="gs-card-cta">
                    <?= gettext('Import CSV') ?>
                    <i class="fa-solid fa-arrow-right fa-sm"></i>
                </span>
            </a>
        </div>

        <!-- Enter Manually -->
        <div class="col-sm-6 mb-4">
            <a href="<?= SystemURLs::getRootPath() ?>/admin/get-started/manual" class="gs-card gs-card--teal">
                <div class="gs-card-icon">
                    <i class="fa-solid fa-user-plus"></i>
                </div>
                <h5><?= gettext('Enter Data Manually') ?></h5>
                <p><?= gettext('Add families and individuals one by one directly in ChurchCRM. Great for smaller congregations or topping up imported data.') ?></p>
                <span class="gs-card-cta">
                    <?= gettext('Add first family') ?>
                    <i class="fa-solid fa-arrow-right fa-sm"></i>
                </span>
            </a>
        </div>

        <!-- Restore Backup -->
        <div class="col-sm-6 mb-4">
            <a href="<?= SystemURLs::getRootPath() ?>/admin/system/restore?context=onboarding" class="gs-card gs-card--orange">
                <div class="gs-card-icon">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                </div>
                <h5><?= gettext('Restore a Backup') ?></h5>
                <p><?= gettext('Moving from an existing ChurchCRM or ChurchInfo installation? Upload your backup file and we\'ll restore your data automatically.') ?></p>
                <span class="gs-card-cta">
                    <?= gettext('Restore backup') ?>
                    <i class="fa-solid fa-arrow-right fa-sm"></i>
                </span>
            </a>
        </div>

    </div>

    <!-- ── External integrations ─────────────────────────────── -->
    <div class="gs-divider">
        <span><?= gettext('or connect an external service') ?></span>
    </div>

    <div class="gs-plugin-strip mb-4">
        <span class="gs-plugin-strip-label">
            <i class="fa-solid fa-plug mr-1"></i><?= gettext('Plugins') ?>
        </span>

        <?php if ($isMailChimpActive): ?>
            <a href="<?= SystemURLs::getRootPath() ?>/plugins/mailchimp/dashboard"
               class="gs-plugin-badge"
               data-toggle="tooltip"
               title="<?= gettext('Import contacts from your MailChimp audiences') ?>">
                <i class="fa-brands fa-mailchimp text-warning"></i>
                MailChimp
                <span class="badge badge-success"><?= gettext('Active') ?></span>
            </a>
        <?php else: ?>
            <a href="<?= SystemURLs::getRootPath() ?>/plugins/management"
               class="gs-plugin-badge"
               data-toggle="tooltip"
               title="<?= gettext('Enable the MailChimp plugin to sync contacts') ?>">
                <i class="fa-brands fa-mailchimp" style="color: #ffe01b;"></i>
                MailChimp
                <span class="badge badge-light border"><?= gettext('Enable') ?></span>
            </a>
        <?php endif; ?>

        <a href="<?= SystemURLs::getRootPath() ?>/plugins/management"
           class="gs-plugin-badge"
           data-toggle="tooltip"
           title="<?= gettext('Browse all available plugins') ?>">
            <i class="fa-solid fa-grid-2 text-muted"></i>
            <?= gettext('Browse all plugins') ?>
        </a>
    </div>

    <!-- ── Skip ───────────────────────────────────────────────── -->
    <div class="gs-skip">
        <?= gettext('Not ready yet?') ?>
        <a href="<?= SystemURLs::getRootPath() ?>/admin/">
            <?= gettext('Skip — go to Admin Dashboard') ?>
        </a>
    </div>

</div><!-- /.gs-wrap -->
</div><!-- /.container-fluid -->

<script src="<?= SystemURLs::assetVersioned('/skin/v2/get-started.min.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/js/importDemoData.js') ?>"></script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
