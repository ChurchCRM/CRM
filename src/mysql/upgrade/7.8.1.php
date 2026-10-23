<?php

use ChurchCRM\data\Countries;
use ChurchCRM\data\GradePresets;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\ConfigQuery;
use ChurchCRM\Service\GradeService;
use ChurchCRM\Utils\LoggerUtils;

// 7.8.1.sql seeded the US grade names. A church in a country with
// its own names gets those instead (#10189).
//
// Do not reference Person::SCHEDULED_TASK here. The in-app upgrader runs
// this script in the same process that just moved the new code into place,
// so the old Person class (without the constant) can still be loaded and
// the constant is undefined. Use the literal value (-3) instead.
// See issue #10826.
SystemConfig::init(ConfigQuery::create()->find());
$gradePresetKey = GradePresets::keyForCountry(
    Countries::toISO(SystemConfig::getValue('sChurchCountry')),
    SystemConfig::getValue('sChurchState')
);
if ($gradePresetKey !== null && $gradePresetKey !== GradePresets::DEFAULT_KEY) {
    GradeService::applyPreset($gradePresetKey, -3);
    LoggerUtils::getAppLogger()->info('Grade names set from the church country', ['preset' => $gradePresetKey]);
}
