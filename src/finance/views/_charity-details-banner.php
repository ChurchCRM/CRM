<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\NotificationService;
use ChurchCRM\Utils\InputUtils;

if (!SystemConfig::getBooleanValue('bEnabledFinance')) {
    return;
}

// Each user can dismiss this notice; the choice is kept in their user settings.
$charityDismissKey = NotificationService::dismissalSettingKey('charity-details');
if (AuthenticationManager::getCurrentUser()->getSettingValue($charityDismissKey) === 'true') {
    return;
}

$charityKeys = ['sCharityLegalName', 'sCharityRegisteredAddress', 'sCharityRegistrationNumber', 'sReceiptIssueLocality'];
foreach ($charityKeys as $charityKey) {
    if (trim((string) SystemConfig::getValue($charityKey)) === '') {
        ?>
<div class="alert alert-info alert-dismissible" role="status" id="charity-details-banner" data-notification-id="charity-details" data-dismiss-key="<?= InputUtils::escapeAttribute($charityDismissKey) ?>">
    <?= gettext('Charity details for receipts are not filled in yet. Add the legal name, registered address, registration number, and issue locality when you can. Finance keeps working until then.') ?>
    <a href="<?= InputUtils::escapeAttribute(SystemURLs::getRootPath() . '/finance/statements/settings') ?>"><?= gettext('Statement Settings') ?></a>
    <button type="button" class="btn-close js-dismiss-notification" data-bs-dismiss="alert" aria-label="<?= InputUtils::escapeAttribute(gettext('Dismiss')) ?>"></button>
</div>
        <?php
        break;
    }
}
