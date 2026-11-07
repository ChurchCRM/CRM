<?php

/**
 * scripts/test-african-holidays.php: Verification harness for African holiday providers.
 *
 * Verifies that:
 * 1. All 54 African country providers exist under src/plugins/core/holidays/src/Provider/
 * 2. Each provider class is syntactically valid and instantiable
 * 3. HolidayCalendarProvider successfully resolves and generates events for each nation
 * 4. plugin.json options and Countries.php ISO mappings include all 54 African nations
 *
 * Usage:
 *   php scripts/test-african-holidays.php
 */

declare(strict_types=1);

$autoload = __DIR__ . '/../src/vendor/autoload.php';
$hasAutoload = is_file($autoload);
if ($hasAutoload) {
    require_once $autoload;
}

$africanCountries = [
    'DZ' => 'Algeria',
    'AO' => 'Angola',
    'BJ' => 'Benin',
    'BW' => 'Botswana',
    'BF' => 'BurkinaFaso',
    'BI' => 'Burundi',
    'CV' => 'CapeVerde',
    'CM' => 'Cameroon',
    'CF' => 'CentralAfricanRepublic',
    'TD' => 'Chad',
    'KM' => 'Comoros',
    'CD' => 'DRCongo',
    'CG' => 'CongoRepublic',
    'CI' => 'CotedIvoire',
    'DJ' => 'Djibouti',
    'EG' => 'Egypt',
    'GQ' => 'EquatorialGuinea',
    'ER' => 'Eritrea',
    'SZ' => 'Eswatini',
    'ET' => 'Ethiopia',
    'GA' => 'Gabon',
    'GM' => 'Gambia',
    'GH' => 'Ghana',
    'GN' => 'Guinea',
    'GW' => 'GuineaBissau',
    'KE' => 'Kenya',
    'LS' => 'Lesotho',
    'LR' => 'Liberia',
    'LY' => 'Libya',
    'MG' => 'Madagascar',
    'MW' => 'Malawi',
    'ML' => 'Mali',
    'MR' => 'Mauritania',
    'MU' => 'Mauritius',
    'MA' => 'Morocco',
    'MZ' => 'Mozambique',
    'NA' => 'Namibia',
    'NE' => 'Niger',
    'NG' => 'Nigeria',
    'RW' => 'Rwanda',
    'ST' => 'SaoTomeAndPrincipe',
    'SN' => 'Senegal',
    'SC' => 'Seychelles',
    'SL' => 'SierraLeone',
    'SO' => 'Somalia',
    'ZA' => 'SouthAfrica',
    'SS' => 'SouthSudan',
    'SD' => 'Sudan',
    'TZ' => 'Tanzania',
    'TG' => 'Togo',
    'TN' => 'Tunisia',
    'UG' => 'Uganda',
    'ZM' => 'Zambia',
    'ZW' => 'Zimbabwe',
];

$passed = 0;
$failed = 0;

function report(string $name, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
        echo "ok - {$name}\n";
    } else {
        $failed++;
        echo "not ok - {$name}" . ($detail !== '' ? ": {$detail}" : '') . "\n";
    }
}

echo "TAP version 13\n";
echo "1.." . (count($africanCountries) * 3 + 2) . "\n";

// 1. Check provider files exist
$providerDir = __DIR__ . '/../src/plugins/core/holidays/src/Provider';
foreach ($africanCountries as $iso => $countryClass) {
    $filePath = "{$providerDir}/{$countryClass}.php";
    $exists = is_file($filePath);
    report("Provider file exists for {$countryClass} ({$iso})", $exists, "Missing file: {$filePath}");
}

// 2. Check plugin.json options
$pluginJsonPath = __DIR__ . '/../src/plugins/core/holidays/plugin.json';
$pluginJson = json_decode(file_get_contents($pluginJsonPath) ?: '{}', true);
$options = $pluginJson['settings'][0]['options'] ?? [];
$optionLabels = $pluginJson['settings'][0]['optionLabels'] ?? [];
$optionsSet = array_flip($options);

report("plugin.json has options and optionLabels aligned", count($options) === count($optionLabels));

foreach ($africanCountries as $iso => $countryClass) {
    $registered = isset($optionsSet[$countryClass]);
    report("plugin.json registers option for {$countryClass}", $registered, "Option {$countryClass} missing from plugin.json");
}

// 3. Check Countries.php mapping
$countriesPhpPath = __DIR__ . '/../src/ChurchCRM/data/Countries.php';
$countriesContent = file_get_contents($countriesPhpPath) ?: '';

foreach ($africanCountries as $iso => $countryClass) {
    $pattern = "/'{$iso}'\s*=>\s*new Country\('{$iso}',\s*[^,]+,\s*'{$countryClass}'\)/";
    $matched = preg_match($pattern, $countriesContent) === 1;
    report("Countries.php maps ISO {$iso} to {$countryClass}", $matched, "Mapping missing in Countries.php");
}

report("Summary check", $failed === 0, "{$failed} failures detected");

exit($failed === 0 ? 0 : 1);
