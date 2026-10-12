<?php

use ChurchCRM\model\ChurchCRM\Config;
use ChurchCRM\model\ChurchCRM\ConfigQuery;
use ChurchCRM\Utils\LoggerUtils;

// #10526: unfilled-position alerts used the reminder lead time until they got a setting of
// their own. A church that changed the reminder lead keeps the alerts it had, including
// none at 0; one that never changed it gets the new default, the same 48 hours.
$reminderLead = ConfigQuery::create()->findOneByName('iVolunteerReminderLeadHours');
if ($reminderLead !== null && ConfigQuery::create()->findOneByName('iVolunteerGapAlertLeadHours') === null) {
    (new Config())
        ->setName('iVolunteerGapAlertLeadHours')
        ->setValue($reminderLead->getValue())
        ->save();

    LoggerUtils::getAppLogger()->info('Unfilled-position alert lead time copied from the reminder lead time', [
        'hours' => $reminderLead->getValue(),
    ]);
}
