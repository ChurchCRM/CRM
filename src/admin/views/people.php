<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\CSRFUtils;
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
        <?php if ($section['id'] === 'peopleGrades') : ?>
        <div class="card mb-3" id="gradePromotionCard">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between gap-2">
                    <div>
                        <div>
                            <span class="text-secondary"><?= gettext('Last promotion') ?>:</span>
                            <span id="gradeLastPromotion">
                                <?php if ($gradePromotion['lastRun'] === null) : ?>
                                    <?= gettext('Never') ?>
                                <?php else : ?>
                                    <?= InputUtils::escapeHTML($gradePromotion['lastRun']) ?> (<?= InputUtils::escapeHTML($gradePromotion['lastBy'] ?? '') ?>)
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php if ($gradePromotion['lastResult'] !== null) : ?>
                        <div class="text-secondary small" id="gradeLastResult">
                            <?= InputUtils::escapeHTML(sprintf(gettext('Moved up: %d'), (int) $gradePromotion['lastResult']['moved'])) ?>,
                            <?= InputUtils::escapeHTML(sprintf(gettext('Finished the last grade: %d'), (int) $gradePromotion['lastResult']['finished'])) ?>
                        </div>
                        <?php endif; ?>
                        <div>
                            <span class="text-secondary"><?= gettext('Next automatic promotion') ?>:</span>
                            <span id="gradeNextPromotion"><?= InputUtils::escapeHTML($gradePromotion['nextDateText']) ?></span>
                        </div>
                    </div>
                    <div class="align-self-center">
                        <button type="button" class="btn btn-outline-primary" id="gradePromoteNow"
                                data-expected-last-at="<?= InputUtils::escapeAttribute($gradePromotion['lastAt']) ?>"
                                data-moved="<?= (int) $gradePromotion['preview']['moved'] ?>"
                                data-finished="<?= (int) $gradePromotion['preview']['finished'] ?>"
                                data-promoted-this-year="<?= $gradePromotion['promotedThisYear'] ? '1' : '0' ?>"
                                data-last-run="<?= InputUtils::escapeAttribute($gradePromotion['lastRun'] ?? '') ?>"
                                data-csrf-token="<?= InputUtils::escapeAttribute(CSRFUtils::generateToken()) ?>">
                            <i class="fa-solid fa-arrow-up-right-dots me-1"></i><?= gettext('Promote Now') ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
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
            autoSave: true,
            onSave: section.id === 'peopleGrades' ? function () { window.CRM.refreshGradePromotion(); } : null
        });
    });
});

</script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
