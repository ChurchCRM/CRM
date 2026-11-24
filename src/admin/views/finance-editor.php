<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="container-fluid">
    <div id="<?= InputUtils::escapeAttribute($panelId) ?>"
         data-title="<?= InputUtils::escapeAttribute($panelTitle) ?>"
         data-icon="<?= InputUtils::escapeAttribute($panelIcon) ?>"
         data-settings="<?= InputUtils::escapeAttribute(json_encode($settings, JSON_THROW_ON_ERROR)) ?>"></div>
</div>

<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.css') ?>">
<script src="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/v2/finance-settings.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
