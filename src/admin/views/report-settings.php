<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\LetterheadService;
use ChurchCRM\Service\MpdfRenderer;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

$letterheadUrl = LetterheadService::getUrlPath();
?>

<div class="row">
    <div class="col-12">
        <div class="card" id="church-letterhead-card">
            <div class="card-header">
                <h3 class="card-title"><i class="fa-solid fa-file-image me-2"></i><?= gettext('Letterhead') ?></h3>
            </div>
            <div class="card-body">
                <p class="text-body-secondary">
                    <?= gettext('Printed at the top of PDF letters that use a graphic letterhead, such as giving statements. Without one, letters print the church name and address.') ?>
                </p>

                <?php if ($letterheadUrl !== null): ?>
                <div class="mb-2">
                    <img id="church-letterhead-preview" class="church-letterhead-preview border rounded bg-light p-2"
                         src="<?= InputUtils::escapeAttribute(SystemURLs::getRootPath() . $letterheadUrl) ?>" alt="<?= InputUtils::escapeAttribute(gettext('Letterhead')) ?>">
                </div>
                <?php endif; ?>

                <button type="button" class="btn btn-outline-primary" id="church-letterhead-upload-btn">
                    <i class="fa-solid fa-upload me-1"></i><?= gettext('Upload') ?>
                </button>
                <?php if ($letterheadUrl !== null): ?>
                <button type="button" class="btn btn-outline-danger ms-2" id="church-letterhead-remove-btn">
                    <i class="fa-solid fa-trash me-1"></i><?= gettext('Remove') ?>
                </button>
                <?php else: ?>
                <span class="text-body-secondary small ms-2" id="church-letterhead-default-note">
                    <i class="fa-solid fa-circle-info me-1"></i><?= MpdfRenderer::graphicLetterhead() !== null ? gettext('Using the letterhead image set in System Settings') : gettext('Using the church name and address') ?>
                </span>
                <?php endif; ?>

                <small class="form-text text-body-secondary d-block mt-2">
                    <?= gettext('PNG, JPG, GIF or WebP. A wide banner works best, for example 2000x300.') ?>
                </small>
            </div>
        </div>
    </div>
</div>

<script src="<?= SystemURLs::assetVersioned('/skin/v2/photo-uploader.min.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/v2/report-settings.min.js') ?>"></script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
