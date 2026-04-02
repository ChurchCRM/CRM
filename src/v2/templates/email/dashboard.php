<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>

<?php if (!$bEmailEnabled): ?>
<!-- Email Disabled Warning -->
<div class="card border-warning">
    <div class="card-header bg-warning-lt">
        <h3 class="card-title text-warning"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= gettext('Email is Disabled') ?></h3>
    </div>
    <div class="card-body">
        <p class="mb-2"><?= gettext('Email functionality is currently disabled. The following features will not work until email is enabled:') ?></p>
        <ul class="mb-2">
            <li><?= gettext('Password reset emails — users cannot recover their accounts') ?></li>
            <li><?= gettext('New member notification emails') ?></li>
            <li><?= gettext('Email links in group and people views') ?></li>
            <li><?= gettext('Kiosk check-in email notifications') ?></li>
        </ul>
        <?php if (AuthenticationManager::getCurrentUser()->isAdmin()): ?>
        <p class="text-secondary mb-0"><?= gettext('Enable email in the settings panel below, then configure your SMTP server.') ?></p>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Integrations Status -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-plug me-2"></i><?= gettext('Email Integrations') ?></h3>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <!-- SMTP Status -->
            <div class="col-md-6">
                <div class="alert <?= $bSmtpConfigured ? 'alert-success' : 'alert-warning' ?> mb-0">
                    <div class="d-flex align-items-center">
                        <i class="fa-solid <?= $bSmtpConfigured ? 'fa-circle-check' : 'fa-triangle-exclamation' ?> me-2 fs-3"></i>
                        <div>
                            <h4 class="alert-title mb-0"><?= $bSmtpConfigured ? gettext('SMTP Configured') : gettext('SMTP Not Configured') ?></h4>
                            <div class="text-secondary"><?= $bSmtpConfigured ? gettext('ChurchCRM can send emails directly.') : gettext('Configure SMTP in the settings below to send emails from the system.') ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Mailchimp Status -->
            <div class="col-md-6">
                <div class="alert <?= $bMailchimpConfigured ? 'alert-success' : 'alert-secondary' ?> mb-0">
                    <div class="d-flex align-items-center">
                        <i class="fa-solid <?= $bMailchimpConfigured ? 'fa-circle-check' : 'fa-circle-info' ?> me-2 fs-3"></i>
                        <div>
                            <h4 class="alert-title mb-0"><?= $bMailchimpConfigured ? gettext('Mailchimp Connected') : gettext('Mailchimp Not Configured') ?></h4>
                            <div class="text-secondary"><?= $bMailchimpConfigured ? gettext('Email lists are synced with Mailchimp.') : gettext('Connect Mailchimp to sync email lists for newsletters and campaigns.') ?></div>
                        </div>
                        <?php if (AuthenticationManager::getCurrentUser()->isAdmin()): ?>
                        <a href="<?= SystemURLs::getRootPath() ?>/admin/system/plugins#plugin-mailchimp" class="btn btn-outline-<?= $bMailchimpConfigured ? 'success' : 'secondary' ?> ms-auto btn-sm">
                            <i class="fa-solid fa-gear me-1"></i><?= gettext('Plugin Settings') ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

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
                <i class="fa-solid fa-bell-slash me-1"></i><?= gettext('People Without Emails') ?>
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
        title: <?= json_encode(gettext('Email Settings')) ?>,
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
            { name: 'bEnabledEmail',         type: 'boolean', label: <?= json_encode(gettext('Enable Email')) ?>,            tooltip: <?= json_encode(SystemConfig::getTooltip('bEnabledEmail')) ?> },
            { name: 'sSMTPHost',             type: 'text',    label: <?= json_encode(gettext('SMTP Host')) ?>,               tooltip: <?= json_encode(SystemConfig::getTooltip('sSMTPHost')) ?> },
            { name: 'iSMTPTimeout',          type: 'number',  label: <?= json_encode(gettext('SMTP Timeout')) ?>,            tooltip: <?= json_encode(SystemConfig::getTooltip('iSMTPTimeout')) ?> },
            { name: 'sPHPMailerSMTPSecure',  type: 'choice',  label: <?= json_encode(gettext('Encryption')) ?>,             tooltip: <?= json_encode(SystemConfig::getTooltip('sPHPMailerSMTPSecure')) ?>,
              choices: [
                  { value: ' ', label: <?= json_encode(gettext('None')) ?> },
                  { value: 'tls', label: 'TLS' },
                  { value: 'ssl', label: 'SSL' }
              ]},
            { name: 'bPHPMailerAutoTLS',     type: 'boolean', label: <?= json_encode(gettext('Auto TLS')) ?>,               tooltip: <?= json_encode(SystemConfig::getTooltip('bPHPMailerAutoTLS')) ?> },
            { name: 'bSMTPAuth',             type: 'boolean', label: <?= json_encode(gettext('SMTP Authentication')) ?>,    tooltip: <?= json_encode(SystemConfig::getTooltip('bSMTPAuth')) ?> },
            { name: 'sSMTPUser',             type: 'text',    label: <?= json_encode(gettext('SMTP Username')) ?>,          tooltip: <?= json_encode(SystemConfig::getTooltip('sSMTPUser')) ?> },
            { name: 'sSMTPPass',             type: 'password',label: <?= json_encode(gettext('SMTP Password')) ?>,          tooltip: <?= json_encode(SystemConfig::getTooltip('sSMTPPass')) ?> },
            { name: 'sToEmailAddress',       type: 'text',    label: <?= json_encode(gettext('BCC All Mail To')) ?>,        tooltip: <?= json_encode(SystemConfig::getTooltip('sToEmailAddress')) ?> },
            { name: 'iDoNotEmailPropertyId', type: 'ajax',    label: <?= json_encode(gettext('Do Not Email Property')) ?>,  tooltip: <?= json_encode(SystemConfig::getTooltip('iDoNotEmailPropertyId')) ?>, ajaxUrl: '/api/system/properties/person' }
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
