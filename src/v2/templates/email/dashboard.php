<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>

<!-- Email Tools Card -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-envelope me-2"></i><?= gettext('Email Tools') ?></h3>
    </div>
    <div class="card-body">
        <div class="d-flex flex-wrap" style="gap:.5rem;">
            <a href="<?= SystemURLs::getRootPath() ?>/v2/email/duplicate" class="btn btn-outline-warning">
                <i class="fa-solid fa-triangle-exclamation me-1"></i><?= gettext('Duplicates') ?>
            </a>
            <a href="<?= SystemURLs::getRootPath() ?>/v2/email/missing" class="btn btn-outline-danger">
                <i class="fa-solid fa-bell-slash me-1"></i><?= gettext('Missing') ?>
            </a>
        </div>
    </div>
</div>

<?php if (AuthenticationManager::getCurrentUser()->isAdmin()): ?>
<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.css') ?>">
<script src="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.js') ?>"></script>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
$(document).ready(function() {
    window.CRM.settingsPanel.init({
        container: '#emailSettings',
        title: i18next.t('Email Settings'),
        icon: 'fa-solid fa-envelope',
        presets: [
            {
                label: 'Gmail (SMTP)',
                icon: 'fa-brands fa-google',
                values: {
                    sSMTPHost: 'smtp.gmail.com:587',
                    iSMTPTimeout: '10',
                    sPHPMailerSMTPSecure: 'tls',
                    bPHPMailerAutoTLS: '1',
                    bSMTPAuth: '1'
                }
            },
            {
                label: 'Outlook / Microsoft 365',
                icon: 'fa-brands fa-microsoft',
                values: {
                    sSMTPHost: 'smtp.office365.com:587',
                    iSMTPTimeout: '10',
                    sPHPMailerSMTPSecure: 'tls',
                    bPHPMailerAutoTLS: '1',
                    bSMTPAuth: '1'
                }
            }
        ],
        settings: [
            {
                name: 'bEnabledEmail',
                label: i18next.t('Enable Email'),
                type: 'boolean',
                tooltip: <?= json_encode($emailSettingTooltips['bEnabledEmail'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
            },
            {
                name: 'sSMTPHost',
                label: i18next.t('SMTP Host'),
                type: 'text',
                tooltip: <?= json_encode($emailSettingTooltips['sSMTPHost'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
            },
            {
                name: 'iSMTPTimeout',
                label: i18next.t('SMTP Timeout'),
                type: 'number',
                tooltip: <?= json_encode($emailSettingTooltips['iSMTPTimeout'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
            },
            {
                name: 'sPHPMailerSMTPSecure',
                label: i18next.t('Encryption'),
                type: 'choice',
                choices: [
                    { value: ' ', label: i18next.t('None') },
                    { value: 'tls', label: 'TLS' },
                    { value: 'ssl', label: 'SSL' }
                ],
                tooltip: <?= json_encode($emailSettingTooltips['sPHPMailerSMTPSecure'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
            },
            {
                name: 'bPHPMailerAutoTLS',
                label: i18next.t('Auto TLS'),
                type: 'boolean',
                tooltip: <?= json_encode($emailSettingTooltips['bPHPMailerAutoTLS'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
            },
            {
                name: 'bSMTPAuth',
                label: i18next.t('SMTP Authentication'),
                type: 'boolean',
                tooltip: <?= json_encode($emailSettingTooltips['bSMTPAuth'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
            },
            {
                name: 'sSMTPUser',
                label: i18next.t('SMTP Username'),
                type: 'text',
                tooltip: <?= json_encode($emailSettingTooltips['sSMTPUser'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
            },
            {
                name: 'sSMTPPass',
                label: i18next.t('SMTP Password'),
                type: 'password',
                tooltip: <?= json_encode($emailSettingTooltips['sSMTPPass'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
            },
            {
                name: 'sToEmailAddress',
                label: i18next.t('BCC All Mail To'),
                type: 'text',
                tooltip: <?= json_encode($emailSettingTooltips['sToEmailAddress'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
            }
        ],
        showAllSettingsLink: true
    });
<?php if (isset($_GET['settings']) && $_GET['settings'] === 'open'): ?>
    var emailSettingsEl = document.getElementById('emailSettings');
    if (emailSettingsEl) {
        new bootstrap.Collapse(emailSettingsEl, { toggle: false }).show();
    }
<?php endif; ?>
});
</script>
<?php endif; ?>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
