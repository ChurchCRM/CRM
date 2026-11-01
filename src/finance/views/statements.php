<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>

<div class="container-fluid">
    <?php foreach ($sections as $section) : ?>
        <div id="<?= InputUtils::escapeAttribute($section['id']) ?>" class="mb-3"></div>
    <?php endforeach; ?>

    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <h3 class="card-title mb-1"><?= gettext('Preview') ?></h3>
                <p class="text-secondary mb-0"><?= gettext('Generate a sample statement with the current settings and demo data.') ?></p>
            </div>
            <a href="<?= InputUtils::escapeAttribute($sRootPath) ?>/finance/reports/giving-statements/download?DateStart=2018-01-01&DateEnd=2018-12-31&datetype=Payment"
               class="btn btn-outline-primary" target="_blank">
                <i class="fa-solid fa-file-pdf me-1"></i><?= gettext('Preview PDF') ?>
            </a>
        </div>
    </div>
</div>

<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.css') ?>">
<script src="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
$(document).ready(function () {
    <?= InputUtils::jsonEncodeForScript($sections) ?>.forEach(function (section) {
        new window.CRM.SettingsPanel().init({
            container: '#' + section.id,
            title: section.title,
            icon: section.icon,
            settings: section.settings,
            showAllSettingsLink: false,
            autoSave: true,
            configApiPath: '/finance/api/config'
        });
    });
});
</script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
