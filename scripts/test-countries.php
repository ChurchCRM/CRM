<?php

/**
 * scripts/test-countries.php — how a stored country is shown and when an editor
 * save keeps a stored country or state (#10418, #10566).
 *
 * Usage:
 *   php scripts/test-countries.php
 *   npm run test:php
 *
 * Records hold the country as a code ("US"), an alias ("USA") or a name
 * ("United States"). Countries::toName() must show a code or alias as the English
 * name without the native name the list labels carry, and show a stored name or
 * an unknown value as stored. Countries::toISO() must resolve an English name
 * without the native suffix ("Germany"). Countries::keepStoredIfSame() and
 * States::keepStoredIfSame() must keep the stored value when the posted code names
 * the same country or state. No database is needed.
 *
 * Exit codes:
 *   0  — every case passed
 *   1  — at least one case failed
 *   2  — vendor autoloader missing
 */

declare(strict_types=1);

use ChurchCRM\data\Countries;
use ChurchCRM\data\States;
use ChurchCRM\dto\SystemURLs;

$autoload = __DIR__ . '/../src/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Missing {$autoload} — run `composer install` in src/ first.\n");
    exit(2);
}
require $autoload;

SystemURLs::init('', [], dirname(__DIR__) . '/src');

$passed = 0;
$failed = 0;

$check = function (bool $ok, string $label, string $detail = '') use (&$passed, &$failed): void {
    $n = $passed + $failed + 1;
    if ($ok) {
        $passed++;
        echo "ok {$n} - {$label}\n";
        return;
    }
    $failed++;
    echo "not ok {$n} - {$label}" . ($detail !== '' ? "  # {$detail}" : '') . "\n";
};

$toName = [
    'ISO code'                                => ['US', 'United States'],
    'legacy alias'                            => ['USA', 'United States'],
    'stored name'                             => ['United States', 'United States'],
    'code whose label has a native name'      => ['DE', 'Germany'],
    'stored English name not in the list'     => ['Germany', 'Germany'],
    'label with a parenthetical English name' => ['CD', 'Congo (DRC)'],
    'native name with nested parentheses'     => ['MF', 'Saint Martin'],
    'native name in another script'           => ['AF', 'Afghanistan'],
    'label without parentheses'               => ['CA', 'Canada'],
    'unknown value'                           => ['Atlantis', 'Atlantis'],
    'empty string'                            => ['', ''],
    'null'                                    => [null, ''],
];

foreach ($toName as $label => [$value, $expected]) {
    $actual = Countries::toName($value);
    $check($actual === $expected, "toName: {$label}", var_export($value, true) . ' gave ' . var_export($actual, true));
}

$toISO = [
    'English name without the native suffix' => ['Germany', 'DE'],
    'English name in another case'           => ['GERMANY', 'DE'],
    'English name of an accented label'      => ['Mexico', 'MX'],
    'English name with its own parentheses'  => ['Congo (DRC)', 'CD'],
    'full list label'                        => ['Germany (Deutschland)', 'DE'],
    'unknown value'                          => ['Atlantis', 'Atlantis'],
];

foreach ($toISO as $label => [$value, $expected]) {
    $actual = Countries::toISO($value);
    $check($actual === $expected, "toISO: {$label}", var_export($value, true) . ' gave ' . var_export($actual, true));
}

$keep = [
    'English name kept when the code matches' => ['Germany', 'DE', 'Germany'],
    'name kept when the code matches'  => ['United States', 'US', 'United States'],
    'alias kept when the code matches' => ['USA', 'US', 'USA'],
    'code kept when the code matches'  => ['US', 'US', 'US'],
    'real change saves the new code'   => ['United States', 'CA', 'CA'],
    'blank stays blank'                => [null, '', null],
    'first country on a new record'    => [null, 'US', 'US'],
];

foreach ($keep as $label => [$stored, $submitted, $expected]) {
    $actual = Countries::keepStoredIfSame($stored, $submitted);
    $check($actual === $expected, "keepStoredIfSame: {$label}", var_export($actual, true));
}

$keepState = [
    'name kept when the code matches'           => ['US', 'Missouri', 'MO', 'Missouri'],
    'code kept when the code matches'           => ['US', 'MO', 'MO', 'MO'],
    'unknown state kept when posted back'       => ['US', 'Mo.', 'Mo.', 'Mo.'],
    'real change saves the new code'            => ['US', 'Missouri', 'KS', 'KS'],
    'country stored by name'                    => ['United States', 'Missouri', 'MO', 'Missouri'],
    'country stored by English name'            => ['Germany', 'Bayern', 'BY', 'Bayern'],
    'country without a state list, unchanged'   => ['Atlantis', 'North', 'North', 'North'],
    'country without a state list, changed'     => ['Atlantis', 'North', 'South', 'South'],
    'blank stays blank'                         => ['US', null, '', null],
    'first state on a new record'               => ['US', null, 'MO', 'MO'],
];

foreach ($keepState as $label => [$country, $stored, $submitted, $expected]) {
    $actual = States::keepStoredIfSame($country, $stored, $submitted);
    $check($actual === $expected, "States::keepStoredIfSame: {$label}", var_export($actual, true));
}

$total = $passed + $failed;
echo "1..{$total}\n";
echo "# {$total} cases, {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
