<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>

<div class="container-fluid" id="settingsIndex">
    <?php foreach ($linkGroups as [$groupTitle, $links]) : ?>
        <h3 class="mb-2"><?= InputUtils::escapeHTML($groupTitle) ?></h3>
        <div class="row mb-3">
            <?php foreach ($links as [$label, $path, $icon]) : ?>
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
    <?php endforeach; ?>

    <h3 class="mb-2"><?= gettext('System-wide') ?></h3>
    <?php foreach ($sections as $section) : ?>
        <div id="<?= InputUtils::escapeAttribute($section['id']) ?>" class="mb-3"
             data-title="<?= InputUtils::escapeAttribute($section['title']) ?>"
             data-icon="<?= InputUtils::escapeAttribute($section['icon']) ?>"

             data-settings="<?= InputUtils::jsonEncodeForAttribute($section['settings']) ?>"></div>
    <?php endforeach; ?>
</div>

<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.css') ?>">
<script src="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/v2/admin-settings.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
