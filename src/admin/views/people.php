<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>

<div class="container-fluid">
    <div class="row mb-3">
        <?php foreach ($listLinks as [$label, $path, $icon]) : ?>
            <div class="col-6 col-lg-3 mb-3">
                <a href="<?= InputUtils::escapeAttribute($sRootPath . $path) ?>" class="card card-sm text-decoration-none h-100">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <span class="bg-secondary text-white avatar rounded-circle">
                                    <i class="fa-solid <?= InputUtils::escapeAttribute($icon) ?> icon"></i>
                                </span>
                            </div>
                            <div class="col fw-medium text-body"><?= InputUtils::escapeHTML($label) ?></div>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

    <?php foreach ($sections as $section) : ?>
        <div id="<?= InputUtils::escapeAttribute($section['id']) ?>" class="mb-3"></div>
    <?php endforeach; ?>
</div>

<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.css') ?>">
<script src="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/v2/people-settings.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
$(document).ready(function () {
    <?= InputUtils::jsonEncodeForScript($sections) ?>.forEach(function (section) {
        new window.CRM.SettingsPanel().init({
            container: '#' + section.id,
            title: section.title,
            icon: section.icon,
            settings: section.settings,
            showAllSettingsLink: false,
            autoSave: true
        });
    });
});

</script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
