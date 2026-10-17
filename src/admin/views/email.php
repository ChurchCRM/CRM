<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>

<?php if (!$bEmailEnabled): ?>
<div class="card border-warning">
    <div class="card-header bg-warning-lt">
        <h3 class="card-title text-warning"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= gettext('Email is Disabled') ?></h3>
    </div>
    <div class="card-body">
        <p class="mb-0"><?= gettext('Enable email below, then configure your SMTP server.') ?></p>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-plug me-2"></i><?= gettext('Email Integrations') ?></h3>
    </div>
    <div class="card-body">
        <div class="row g-3">
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
            <div class="col-md-6">
                <div class="alert <?= $bMailchimpConfigured ? 'alert-success' : 'alert-secondary' ?> mb-0">
                    <div class="d-flex align-items-center">
                        <i class="fa-solid <?= $bMailchimpConfigured ? 'fa-circle-check' : 'fa-circle-info' ?> me-2 fs-3"></i>
                        <div>
                            <h4 class="alert-title mb-0"><?= $bMailchimpConfigured ? gettext('Mailchimp Connected') : gettext('Mailchimp Not Configured') ?></h4>
                            <div class="text-secondary"><?= $bMailchimpConfigured ? gettext('Email lists are synced with Mailchimp.') : gettext('Connect Mailchimp to sync email lists for newsletters and campaigns.') ?></div>
                        </div>
                        <a href="<?= SystemURLs::getRootPath() ?>/plugins/management#plugin-mailchimp" class="btn btn-outline-<?= $bMailchimpConfigured ? 'success' : 'secondary' ?> ms-auto btn-sm">
                            <i class="fa-solid fa-gear me-1"></i><?= gettext('Plugin Settings') ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<form method="POST"
      action="<?= $sRootPath ?>/admin/email"
      id="email-settings-form"
      novalidate>

    <!-- General -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa-solid fa-envelope me-2"></i><?= gettext('General') ?></h3>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="bEnabledEmail" name="bEnabledEmail" value="1"<?= $settings['bEnabledEmail'] ? ' checked' : '' ?>>
                            <span class="form-check-label"><?= gettext('Enable Email') ?></span>
                        </label>
                        <small class="form-text text-body-secondary"><?= InputUtils::escapeHTML(SystemConfig::getTooltip('bEnabledEmail')) ?></small>
                    </div>

                    <div class="mb-3">
                        <label for="sToEmailAddress"><?= gettext('Copy Church Email') ?></label>
                        <input type="email" class="form-control" id="sToEmailAddress" name="sToEmailAddress" maxlength="200"
                               value="<?= InputUtils::escapeAttribute($settings['sToEmailAddress']) ?>">
                        <small class="form-text text-body-secondary"><?= InputUtils::escapeHTML(SystemConfig::getTooltip('sToEmailAddress')) ?></small>
                    </div>

                    <div class="mb-3">
                        <label for="sEmailPreheader"><?= gettext('Default Inbox Preview Text') ?></label>
                        <input type="text" class="form-control" id="sEmailPreheader" name="sEmailPreheader" maxlength="200"
                               value="<?= InputUtils::escapeAttribute($settings['sEmailPreheader']) ?>">
                        <small class="form-text text-body-secondary"><?= InputUtils::escapeHTML(SystemConfig::getTooltip('sEmailPreheader')) ?></small>
                    </div>

                    <div class="mb-0">
                        <label for="iDoNotEmailPropertyId"><?= gettext('Do Not Email Property') ?></label>
                        <select class="form-select" id="iDoNotEmailPropertyId" name="iDoNotEmailPropertyId">
                            <option value=""<?= $settings['iDoNotEmailPropertyId'] === '' ? ' selected' : '' ?>><?= gettext('None') ?></option>
                            <?php foreach ($personProperties as $property) : ?>
                            <option value="<?= (int) $property->getProId() ?>"<?= (string) $property->getProId() === $settings['iDoNotEmailPropertyId'] ? ' selected' : '' ?>><?= InputUtils::escapeHTML($property->getProName()) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-body-secondary"><?= InputUtils::escapeHTML(SystemConfig::getTooltip('iDoNotEmailPropertyId')) ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SMTP Server -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa-solid fa-server me-2"></i><?= gettext('SMTP Server') ?></h3>
                </div>
                <div class="card-body">
                    <div class="mb-3 d-flex flex-wrap gap-2" id="email-presets">
                        <button type="button" class="btn btn-outline-secondary btn-sm"
                                data-email-preset data-smtp-host="smtp.gmail.com:587" data-smtp-timeout="10" data-smtp-secure="tls" data-smtp-auto-tls="1" data-smtp-auth="1">
                            <i class="fa-brands fa-google me-1"></i>Gmail (SMTP)
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm"
                                data-email-preset data-smtp-host="smtp.office365.com:587" data-smtp-timeout="10" data-smtp-secure="tls" data-smtp-auto-tls="1" data-smtp-auth="1">
                            <i class="fa-brands fa-microsoft me-1"></i>Outlook / Microsoft 365
                        </button>
                    </div>

                    <div class="mb-3">
                        <label for="sSMTPHost"><?= gettext('SMTP Host') ?></label>
                        <input type="text" class="form-control" id="sSMTPHost" name="sSMTPHost" maxlength="200" placeholder="mail.example.com:587"
                               value="<?= InputUtils::escapeAttribute($settings['sSMTPHost']) ?>">
                        <small class="form-text text-body-secondary"><?= InputUtils::escapeHTML(SystemConfig::getTooltip('sSMTPHost')) ?></small>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="iSMTPTimeout"><?= gettext('SMTP Timeout') ?></label>
                            <input type="number" class="form-control" id="iSMTPTimeout" name="iSMTPTimeout" min="1" max="600"
                                   value="<?= InputUtils::escapeAttribute($settings['iSMTPTimeout']) ?>">
                            <small class="form-text text-body-secondary"><?= InputUtils::escapeHTML(SystemConfig::getTooltip('iSMTPTimeout')) ?></small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="sPHPMailerSMTPSecure"><?= gettext('Encryption') ?></label>
                            <select class="form-select" id="sPHPMailerSMTPSecure" name="sPHPMailerSMTPSecure">
                                <?php foreach ($encryptionChoices as $value => $label) : ?>
                                <option value="<?= InputUtils::escapeAttribute((string) $value) ?>"<?= (string) $value === $settings['sPHPMailerSMTPSecure'] ? ' selected' : '' ?>><?= InputUtils::escapeHTML($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-body-secondary"><?= InputUtils::escapeHTML(SystemConfig::getTooltip('sPHPMailerSMTPSecure')) ?></small>
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="bPHPMailerAutoTLS" name="bPHPMailerAutoTLS" value="1"<?= $settings['bPHPMailerAutoTLS'] ? ' checked' : '' ?>>
                            <span class="form-check-label"><?= gettext('Auto TLS') ?></span>
                        </label>
                        <small class="form-text text-body-secondary"><?= InputUtils::escapeHTML(SystemConfig::getTooltip('bPHPMailerAutoTLS')) ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Authentication -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa-solid fa-key me-2"></i><?= gettext('SMTP Authentication') ?></h3>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="bSMTPAuth" name="bSMTPAuth" value="1"<?= $settings['bSMTPAuth'] ? ' checked' : '' ?>>
                            <span class="form-check-label"><?= gettext('SMTP Authentication') ?></span>
                        </label>
                        <small class="form-text text-body-secondary">
                            <?= InputUtils::escapeHTML(SystemConfig::getTooltip('bSMTPAuth')) ?>
                            <?= gettext('Turning this off clears the saved username and password.') ?>
                        </small>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-0">
                            <label for="sSMTPUser"><?= gettext('SMTP Username') ?></label>
                            <input type="text" class="form-control" id="sSMTPUser" name="sSMTPUser" maxlength="200" autocomplete="off"
                                   value="<?= InputUtils::escapeAttribute($settings['sSMTPUser']) ?>"<?= $settings['bSMTPAuth'] ? '' : ' disabled' ?>>
                        </div>
                        <div class="col-md-6 mb-0">
                            <label for="sSMTPPass"><?= gettext('SMTP Password') ?></label>
                            <input type="password" class="form-control" id="sSMTPPass" name="sSMTPPass" maxlength="255" autocomplete="new-password"
                                   placeholder="<?= $hasSmtpPassword ? InputUtils::escapeAttribute(gettext('Leave blank to keep existing password')) : '' ?>"<?= $settings['bSMTPAuth'] ? '' : ' disabled' ?>>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Form Actions -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-floppy-disk me-1"></i>
                        <?= gettext('Save Email Settings') ?>
                    </button>
                    <a href="<?= $sRootPath ?>/admin/" class="btn btn-secondary ms-2">
                        <?= gettext('Cancel') ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</form>

<script src="<?= SystemURLs::assetVersioned('/skin/v2/email-settings.min.js') ?>"></script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
