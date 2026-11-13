<?php

declare(strict_types=1);

namespace ChurchCRM\Tests\Unit\Plugins\Holidays;

use Composer\Autoload\ClassLoader;
use Tester\Assert;
use Yasumi\Holiday;
use Yasumi\Yasumi;

require dirname(__DIR__, 3) . '/bootstrap.php';

$pluginLoader = new ClassLoader();
$pluginLoader->addPsr4('ChurchCRM\\Plugins\\Holidays\\', APP_ROOT . '/plugins/core/holidays/src');
$pluginLoader->register();

// All 54 African countries: 53 custom providers + 1 Yasumi native (SouthAfrica)
$africanCustomProviders = [
    'Algeria', 'Angola', 'Benin', 'Botswana', 'BurkinaFaso', 'Burundi',
    'Cameroon', 'CapeVerde', 'CentralAfricanRepublic', 'Chad', 'Comoros',
    'CongoRepublic', 'CotedIvoire', 'Djibouti', 'DRCongo', 'Egypt',
    'EquatorialGuinea', 'Eritrea', 'Eswatini', 'Ethiopia', 'Gabon',
    'Gambia', 'Ghana', 'Guinea', 'GuineaBissau', 'Kenya', 'Lesotho',
    'Liberia', 'Libya', 'Madagascar', 'Malawi', 'Mali', 'Mauritania',
    'Mauritius', 'Morocco', 'Mozambique', 'Namibia', 'Niger', 'Nigeria',
    'Rwanda', 'SaoTomeAndPrincipe', 'Senegal', 'Seychelles', 'SierraLeone',
    'Somalia', 'SouthSudan', 'Sudan', 'Tanzania', 'Togo', 'Tunisia',
    'Uganda', 'Zambia', 'Zimbabwe',
];

$testYear = 2025;

foreach ($africanCustomProviders as $country) {
    $className = "ChurchCRM\\Plugins\\Holidays\\Provider\\{$country}";
    Assert::true(class_exists($className), "Provider class exists for {$country}");

    $provider = new $className($testYear);
    $holidays = $provider->getHolidays();

    Assert::true(count($holidays) > 0, "Provider {$country} returns holidays for year {$testYear}");

    // Verify each holiday has an identifier and name
    foreach ($holidays as $holiday) {
        Assert::type(Holiday::class, $holiday);
        Assert::truthy($holiday->getName(), "Holiday in {$country} has a valid name");
    }

    // Verify official sources are defined
    Assert::true(method_exists($provider, 'getSources'), "Provider {$country} defines getSources()");
    $sources = $provider->getSources();
    Assert::true(is_array($sources) && count($sources) > 0, "Provider {$country} returns at least one source URL");
}

// Verify South Africa resolves via native Yasumi provider without regression
$saProvider = Yasumi::create('SouthAfrica', $testYear);
$saHolidays = $saProvider->getHolidays();
Assert::true(count($saHolidays) > 0, 'Native Yasumi SouthAfrica provider generates holidays');

// Specific regression tests:
// 1. Zimbabwe: Defence Forces Day is day after Heroes' Day (second Monday of August)
$zim2023 = new \ChurchCRM\Plugins\Holidays\Provider\Zimbabwe(2023);
$zimHolidays2023 = $zim2023->getHolidays();
Assert::same('2023-08-14', $zimHolidays2023['heroesDay']->format('Y-m-d'));
Assert::same('2023-08-15', $zimHolidays2023['defenceForcesDay']->format('Y-m-d'));

// 2. Zambia: Unity Day is day after Heroes' Day (first Monday of July)
$zam2025 = new \ChurchCRM\Plugins\Holidays\Provider\Zambia(2025);
$zamHolidays2025 = $zam2025->getHolidays();
Assert::same('2025-07-07', $zamHolidays2025['heroesDay']->format('Y-m-d'));
Assert::same('2025-07-08', $zamHolidays2025['unityDay']->format('Y-m-d'));

// 3. Namibia: Genocide Remembrance Day from 2025
$nam2024 = new \ChurchCRM\Plugins\Holidays\Provider\Namibia(2024);
Assert::false(isset($nam2024->getHolidays()['genocideRemembranceDay']));
$nam2025 = new \ChurchCRM\Plugins\Holidays\Provider\Namibia(2025);
Assert::same('2025-05-28', $nam2025->getHolidays()['genocideRemembranceDay']->format('Y-m-d'));

// 4. Ghana: Republic Day status and Founders' Day post-2025
$gha2024 = new \ChurchCRM\Plugins\Holidays\Provider\Ghana(2024);
Assert::same(Holiday::TYPE_OTHER, $gha2024->getHolidays()['republicDay']->getType());
Assert::same('2024-08-04', $gha2024->getHolidays()['foundersDay']->format('Y-m-d'));

$gha2025 = new \ChurchCRM\Plugins\Holidays\Provider\Ghana(2025);
Assert::same(Holiday::TYPE_OFFICIAL, $gha2025->getHolidays()['republicDay']->getType());
Assert::same('2025-09-21', $gha2025->getHolidays()['foundersDay']->format('Y-m-d'));

// Orthodox/Coptic Easter moveables (2026: Orthodox Easter 12 Apr, Western 5 Apr)
foreach (['Ethiopia', 'Eritrea', 'Egypt'] as $country) {
    $class = "ChurchCRM\\Plugins\\Holidays\\Provider\\{$country}";
    $h = (new $class(2026))->getHolidays();
    Assert::same('2026-04-13', $h['easterMonday']->format('Y-m-d'), "{$country} Easter Monday follows Orthodox Easter");
}
Assert::same('2026-04-10', (new \ChurchCRM\Plugins\Holidays\Provider\Ethiopia(2026))->getHolidays()['goodFriday']->format('Y-m-d'));
Assert::same('2026-04-10', (new \ChurchCRM\Plugins\Holidays\Provider\Eritrea(2026))->getHolidays()['goodFriday']->format('Y-m-d'));
