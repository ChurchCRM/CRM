<?php

declare(strict_types=1);

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Plugins\Holidays\HolidayCalendarProvider;
use Composer\Autoload\ClassLoader;
use Tester\Assert;

require dirname(__DIR__, 3) . '/bootstrap.php';

SystemURLs::init('', [], APP_ROOT);
SystemConfig::init();

$pluginLoader = new ClassLoader();
$pluginLoader->addPsr4('ChurchCRM\\Plugins\\Holidays\\', APP_ROOT . '/plugins/core/holidays/src');
$pluginLoader->register();

/** @return array<string, string> holiday title => start date, as the calendar receives it */
$holidayDates = static function (string $start, string $end): array {
    $dates = [];
    foreach ((new HolidayCalendarProvider('USA'))->getEvents($start, $end) as $event) {
        $dates[$event->getTitle()] = $event->getStart('Y-m-d');
    }

    return $dates;
};

// Yasumi dates USA holidays at midnight in America/New_York; the church's
// timezone must not move them to the evening before.
foreach (['America/New_York', 'America/Chicago', 'America/Los_Angeles', 'Pacific/Honolulu', 'UTC', 'Asia/Tokyo'] as $timezone) {
    date_default_timezone_set($timezone);
    $november = $holidayDates('2026-11-01', '2026-12-01');
    Assert::same('2026-11-11', $november['Veterans Day'] ?? null, "$timezone: Veterans Day is on November 11");
    Assert::same('2026-11-26', $november['Thanksgiving Day'] ?? null, "$timezone: Thanksgiving is on Thursday, November 26");
}

date_default_timezone_set('America/Chicago');
Assert::same(
    ['Thanksgiving Day' => '2026-11-26'],
    $holidayDates('2026-11-26', '2026-11-27'),
    'a holiday on the first visible day is shown',
);
Assert::same(
    ['Veterans Day' => '2026-11-11'],
    $holidayDates('2026-11-11', '2026-11-26'),
    'a holiday on the exclusive end day is left for the next range',
);
Assert::same(
    ['Thanksgiving Day' => '2026-11-26'],
    $holidayDates('2026-11-26T00:00:00Z', '2026-11-27T00:00:00Z'),
    'FullCalendar ISO bounds select the same days',
);
