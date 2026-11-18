<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Service\GivingStatementService;
use ChurchCRM\Service\TaxSignatureService;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>

<div class="container-fluid">
    <?php if (GivingStatementService::isCanadianChurch()) : ?>
        <div class="alert alert-info" role="status" id="canadian-receipts-info">
            <?= gettext('Your church country is Canada, so Canadian official receipts are available on Giving Statements and ordinary statements are marked as not an official donation receipt.') ?> <?= gettext('Official receipts need a registration number in the form 123456789RR0001 and a signature image.') ?>
            <strong class="ms-1"><?= TaxSignatureService::hasUploaded() ? gettext('Signature on file') : gettext('No signature on file') ?></strong>
            <?php if (AuthenticationManager::getCurrentUser()->isAdmin()) : ?>
                <a href="<?= SystemURLs::getRootPath() ?>/admin/system/report-settings" id="signature-upload-link"><?= gettext('Upload or replace the signature') ?></a>
            <?php else : ?>
                <?= gettext('An administrator uploads it under Report Settings.') ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <div class="mb-3">
        <a class="btn btn-outline-primary" href="<?= SystemURLs::getRootPath() ?>/finance/reports/giving-statements/sample" target="_blank" rel="noopener">
            <i class="fa-solid fa-file-pdf me-1" aria-hidden="true"></i><?= gettext('Preview PDF') ?>
        </a>
        <span class="text-body-secondary ms-2"><?= gettext('Uses saved settings and fictional sample gifts only.') ?></span>
    </div>
    <?php foreach ($sections as $section) : ?>
        <div id="<?= InputUtils::escapeAttribute($section['id']) ?>" class="mb-3"></div>
    <?php endforeach; ?>
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
            columns: 1,
            configApiPath: '/finance/api/config',
            csrfToken: <?= InputUtils::jsonEncodeForScript(CSRFUtils::generateToken()) ?>
        });
    });
});
</script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
