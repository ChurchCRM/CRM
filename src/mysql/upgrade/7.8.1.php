<?php

use ChurchCRM\data\Countries;
use ChurchCRM\data\GradePresets;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\ConfigQuery;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\Service\GradeService;
use ChurchCRM\Utils\LoggerUtils;

// 7.8.1.sql seeded the US grade names. A church in a country with
// its own names gets those instead (#10189).
SystemConfig::init(ConfigQuery::create()->find());
$gradePresetKey = GradePresets::keyForCountry(
    Countries::toISO(SystemConfig::getValue('sChurchCountry')),
    SystemConfig::getValue('sChurchState')
);
if ($gradePresetKey !== null && $gradePresetKey !== GradePresets::DEFAULT_KEY) {
    GradeService::applyPreset($gradePresetKey, Person::SCHEDULED_TASK);
    LoggerUtils::getAppLogger()->info('Grade names set from the church country', ['preset' => $gradePresetKey]);
}
