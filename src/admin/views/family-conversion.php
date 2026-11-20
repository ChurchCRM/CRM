<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<?php if ($familyConversion['convertedFamily'] !== null) : ?>
<div class="alert alert-success" id="familyConversionDone">
    <?= gettext('Converted') ?>:
    <a href="<?= InputUtils::escapeAttribute($familyConversion['convertedFamily']['url']) ?>"><?= InputUtils::escapeHTML($familyConversion['convertedFamily']['name']) ?></a>
</div>
<?php elseif ($familyConversion['convertedCount'] !== null) : ?>
<div class="alert alert-success" id="familyConversionDone">
    <?= InputUtils::escapeHTML(sprintf(gettext('People converted to families: %d'), $familyConversion['convertedCount'])) ?>
</div>
<?php endif; ?>

<div class="card" id="familyConversionPage">
    <?php if ($familyConversion['unassignedCount'] === 0) : ?>
    <div class="card-body text-center text-secondary py-5" id="familyConversionNothing">
        <i class="fa-solid fa-people-roof fa-2x mb-2 d-block"></i>
        <?= gettext('Everyone belongs to a family.') ?>
    </div>
    <?php else : ?>
    <div class="card-body">
        <p class="mb-2" id="familyConversionCount">
            <?= InputUtils::escapeHTML(sprintf(gettext('People without a family: %d'), $familyConversion['unassignedCount'])) ?>
        </p>
        <p class="text-secondary mb-0">
            <?= gettext('Next') ?>:
            <a href="<?= InputUtils::escapeAttribute($familyConversion['next']['url']) ?>" id="familyConversionNext"><?= InputUtils::escapeHTML($familyConversion['next']['name']) ?></a>
        </p>
    </div>
    <div class="card-footer d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-primary" id="familyConversionConvertNext">
            <i class="fa-solid fa-user-plus me-2"></i><?= gettext('Convert Next') ?>
        </button>
        <button type="button" class="btn btn-warning" id="familyConversionConvertAll">
            <i class="fa-solid fa-people-arrows me-2"></i><?= gettext('Convert All') ?>
        </button>
    </div>
    <?php endif; ?>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    window.CRM = window.CRM || {};
    window.CRM.familyConversion = <?= InputUtils::jsonEncodeForScript($familyConversion) ?>;
</script>
<script src="<?= SystemURLs::assetVersioned('/skin/v2/admin-family-conversion.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
