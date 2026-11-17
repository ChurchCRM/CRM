<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\LetterheadService;
use ChurchCRM\Service\MpdfRenderer;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

$letterheadUrl = LetterheadService::getUrlPath();
foreach ($letterSettings as &$setting) {
    if ($setting['name'] === 'sConfirmSigner') {
        $setting['label'] = gettext('Confirmation Letter Printed Signer');
    }
}
unset($setting);
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
                    <i class="fa-solid fa-circle-info me-1"></i><?= MpdfRenderer::graphicLetterhead() !== null ? gettext('Using the letterhead path set in System Settings') : gettext('Using the church name and address') ?>
                </span>
                <?php endif; ?>

                <small class="form-text text-body-secondary d-block mt-2">
                    <?= gettext('PNG, JPG, GIF or WebP. A wide banner works best, for example 2000x300.') ?>
                </small>
            </div>
        </div>
    </div>
</div>

<div class="row mt-3">
    <div class="col-12">
        <div id="letter-settings-panel" data-title="<?= InputUtils::escapeAttribute(gettext('Letter greeting and signature')) ?>" data-settings="<?= InputUtils::escapeAttribute(json_encode($letterSettings, JSON_THROW_ON_ERROR)) ?>"></div>
    </div>
</div>

<div class="card mt-3" id="canadian-settings-card">
    <div class="card-header"><h3 class="card-title"><?= gettext('Charity details') ?></h3></div>
    <div class="card-body">
        <p class="form-text"><?= gettext('Legal name, registered address, registration number, and issue locality are stored for any church. Canadian official receipts require a CRA number in the form 123456789RR0001, a currency symbol that identifies CAD, and a signature. Saving the number does not check that format or CRA registration status.') ?></p>
        <?php
        $canadianSettings = \ChurchCRM\dto\SystemConfig::getSettingsConfig([
            'bCanadianGivingStatement', 'sCharityLegalName', 'sCharityRegisteredAddress',
            'sCharityRegistrationNumber', 'sReceiptIssueLocality', 'sReceiptClosing', 'sTaxSigner',
        ]);
        foreach ($canadianSettings as &$setting) {
            if ($setting['name'] === 'sCharityRegisteredAddress') {
                $setting['type'] = 'textarea';
            }
            if ($setting['name'] === 'sTaxSigner') {
                $setting['label'] = gettext('Giving Statement Printed Signer');
                $setting['tooltip'] = gettext('Independent of the confirmation-letter signer. Confirm this person is authorized before generating a Canadian receipt.');
            }
        }
        unset($setting);
        ?>
        <div id="canadian-settings-panel" data-title="<?= InputUtils::escapeAttribute(gettext('Charity details')) ?>" data-settings="<?= InputUtils::escapeAttribute(json_encode($canadianSettings, JSON_THROW_ON_ERROR)) ?>"></div>
        <hr>
        <h4><?= gettext('Tax Signature') ?></h4>
        <p id="tax-signature-status" role="status" aria-live="polite"></p>
        <label class="form-label" for="tax-signature-file"><?= gettext('Upload a PNG or JPEG signature image.') ?></label>
        <input class="form-control mb-2" id="tax-signature-file" type="file" accept="image/png,image/jpeg">
        <div class="form-text mb-2"><?= gettext('Maximum 2 MiB and 2000 × 1000 pixels. Choose a new image to replace the stored signature. A signature image is not a secure digital signature.') ?></div>
        <button class="btn btn-outline-danger" id="tax-signature-remove" type="button" disabled><?= gettext('Remove') ?></button>
        <p class="text-danger mt-2" id="tax-signature-error" role="alert"></p>
    </div>
</div>

<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.css') ?>">
<script src="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.js') ?>"></script>
<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/photo-uploader.min.css') ?>">
<script src="<?= SystemURLs::assetVersioned('/skin/v2/photo-uploader.min.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/v2/report-settings.min.js') ?>"></script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
