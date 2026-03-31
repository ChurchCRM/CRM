<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>

<!-- Vonage Integration Status -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-plug me-2"></i><?= gettext('SMS Integration') ?></h3>
    </div>
    <div class="card-body">
        <?php if ($vonageConfigured): ?>
        <div class="alert alert-success mb-0">
            <div class="d-flex align-items-center">
                <i class="fa-solid fa-circle-check me-2 fs-3"></i>
                <div>
                    <h4 class="alert-title mb-0"><?= gettext('Vonage SMS Connected') ?></h4>
                    <div class="text-secondary"><?= gettext('SMS messages can be sent directly from ChurchCRM via the Vonage API.') ?></div>
                </div>
                <?php if (AuthenticationManager::getCurrentUser()->isAdmin()): ?>
                <a href="<?= SystemURLs::getRootPath() ?>/admin/system/plugins" class="btn btn-outline-success ms-auto">
                    <i class="fa-solid fa-gear me-1"></i><?= gettext('Plugin Settings') ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-warning mb-0">
            <div class="d-flex align-items-center">
                <i class="fa-solid fa-triangle-exclamation me-2 fs-3"></i>
                <div>
                    <h4 class="alert-title mb-0"><?= gettext('Vonage SMS Not Configured') ?></h4>
                    <div class="text-secondary"><?= gettext('Without Vonage, text actions will open your device\'s native SMS app or copy phone numbers to clipboard.') ?></div>
                </div>
                <?php if (AuthenticationManager::getCurrentUser()->isAdmin()): ?>
                <a href="<?= SystemURLs::getRootPath() ?>/admin/system/plugins" class="btn btn-outline-warning ms-auto">
                    <i class="fa-solid fa-gear me-1"></i><?= gettext('Configure Vonage') ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Text Tools Card -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-comment-sms me-2"></i><?= gettext('Text Tools') ?></h3>
    </div>
    <div class="card-body">
        <div class="text-secondary">
            <?= gettext('Text messaging actions are available from Group and Sunday School class views. Use the "Text" dropdown to copy phone numbers or send SMS messages to group members.') ?>
        </div>
    </div>
</div>

<?php if (AuthenticationManager::getCurrentUser()->isAdmin()): ?>
<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.css') ?>">
<script src="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.js') ?>"></script>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
$(document).ready(function() {
    window.CRM.settingsPanel.init({
        container: '#textSettings',
        title: i18next.t('Text Settings'),
        icon: 'fa-solid fa-comment-sms',
        settings: [
            {
                name: 'iDoNotSmsPropertyId',
                label: i18next.t('Do Not SMS Property'),
                type: 'ajax',
                ajaxUrl: '/api/system/properties/person',
                tooltip: <?= json_encode($textSettingTooltips['iDoNotSmsPropertyId'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
            }
        ],
        showAllSettingsLink: true
    });
<?php if (isset($_GET['settings']) && $_GET['settings'] === 'open'): ?>
    var textSettingsEl = document.getElementById('textSettings');
    if (textSettingsEl) {
        new bootstrap.Collapse(textSettingsEl, { toggle: false }).show();
    }
<?php endif; ?>
});
</script>
<?php endif; ?>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
