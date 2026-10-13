<?php

use ChurchCRM\model\ChurchCRM\Config;
use ChurchCRM\model\ChurchCRM\ConfigQuery;
use ChurchCRM\Utils\LoggerUtils;

// #10561: a new installation starts on Volunteer Management V2. An installation that is
// upgraded and never chose a version was on V1, the old default, and stays there.
if (ConfigQuery::create()->findOneByName('sVolunteerVersion') === null) {
    (new Config())
        ->setName('sVolunteerVersion')
        ->setValue('v1')
        ->save();

    LoggerUtils::getAppLogger()->info('Volunteer Management kept on V1, the version this installation was using');
}
