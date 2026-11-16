<?php

declare(strict_types=1);

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Plugins\Holidays\HolidayCalendarProvider;
use ChurchCRM\Plugins\Holidays\HolidayLocale;
use ChurchCRM\Plugins\Holidays\HolidayTitleTranslations;
use Composer\Autoload\ClassLoader;
use Tester\Assert;
use Yasumi\Yasumi;

require dirname(__DIR__, 3) . '/bootstrap.php';
require __DIR__ . '/fixtures/Testland.php';

SystemURLs::init('', [], APP_ROOT);
SystemConfig::init();

$pluginLoader = new ClassLoader();
$pluginLoader->addPsr4('ChurchCRM\\Plugins\\Holidays\\', APP_ROOT . '/plugins/core/holidays/src');
$pluginLoader->register();

/** @return array<string, array{id: int, start: string}> holiday title => ID and date */
$calendar = static function (string $country, ?string $locale, string $start, string $end): array {
    $provider = new HolidayCalendarProvider(
        $country,
        [],
        $locale,
        new HolidayTitleTranslations(__DIR__ . '/fixtures/titles', HolidayLocale::resolve($locale)),
    );
    $events = [];
    foreach ($provider->getEvents($start, $end) as $event) {
        $events[$event->getTitle()] = ['id' => $event->getId(), 'start' => $event->getStart('Y-m-d')];
    }

    return $events;
};

// Every ChurchCRM language maps to a locale Yasumi accepts, in that language.
$yasumiLocales = Yasumi::getAvailableLocales();
$crmLocales = json_decode((string) file_get_contents(APP_ROOT . '/locale/locales.json'), true);
foreach ($crmLocales as $name => $info) {
    $resolved = HolidayLocale::resolve($info['locale']);
    Assert::true(in_array($resolved, $yasumiLocales, true), "$name resolves to a Yasumi locale");
    if (!str_starts_with($info['locale'], 'en_')) {
        Assert::notSame(HolidayLocale::FALLBACK, $resolved, "$name is not shown in English");
    }
}
Assert::same('zh_Hant_TW', HolidayLocale::resolve('zh_TW'));
Assert::same('fil_PH', HolidayLocale::resolve('tl_PH'));
Assert::same('de_DE', HolidayLocale::resolve('de_DE.UTF-8'));
Assert::same('pt', HolidayLocale::resolve('pt_XX'), 'an unknown region uses its language');
Assert::same('en_US', HolidayLocale::resolve('xx_YY'), 'an unknown language falls back to English');
Assert::same('en_US', HolidayLocale::resolve(''));

// A Yasumi provider in German keeps its IDs and dates.
$usaEnglish = $calendar('USA', 'en_US', '2026-12-01', '2027-01-01');
$usaGerman = $calendar('USA', 'de_DE', '2026-12-01', '2027-01-01');
Assert::same(['start' => '2026-12-25'], array_diff_key($usaEnglish['Christmas'] ?? [], ['id' => 0]));
Assert::true(isset($usaGerman['1. Weihnachtsfeiertag']), 'Christmas is titled in German: ' . implode(', ', array_keys($usaGerman)));
Assert::same($usaEnglish['Christmas'], $usaGerman['1. Weihnachtsfeiertag'], 'same ID and date in both languages');

// A custom provider: Yasumi's shared translations and the plugin's own titles both apply.
$testlandEnglish = $calendar('Testland', 'en_US', '2026-01-01', '2027-01-01');
$testlandGerman = $calendar('Testland', 'de_DE', '2026-01-01', '2027-01-01');
Assert::same(['Good Friday', "Founders' Day"], array_keys($testlandEnglish));
Assert::same(['Karfreitag', 'Gründertag'], array_keys($testlandGerman));
Assert::same(array_values($testlandEnglish), array_values($testlandGerman), 'same IDs and dates in both languages');

// An unsupported locale shows English titles, not keys such as "goodFriday".
Assert::same($testlandEnglish, $calendar('Testland', 'xx_YY', '2026-01-01', '2027-01-01'));
