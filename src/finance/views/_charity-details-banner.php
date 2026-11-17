<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

if (!SystemConfig::getBooleanValue('bEnabledFinance')) {
    return;
}

$charityKeys = ['sCharityLegalName', 'sCharityRegisteredAddress', 'sCharityRegistrationNumber', 'sReceiptIssueLocality'];
foreach ($charityKeys as $charityKey) {
    if (trim((string) SystemConfig::getValue($charityKey)) === '') {
        ?>
<div class="alert alert-info" role="status" id="charity-details-banner">
    <?= gettext('Charity details for receipts are not filled in yet. Add the legal name, registered address, registration number, and issue locality when you can. Finance keeps working until then.') ?>
    <a href="<?= InputUtils::escapeAttribute(SystemURLs::getRootPath() . '/finance/statements/settings') ?>"><?= gettext('Statement Settings') ?></a>
</div>
        <?php
        break;
    }
}
