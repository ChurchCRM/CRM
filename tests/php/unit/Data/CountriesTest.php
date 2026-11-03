<?php

declare(strict_types=1);

use ChurchCRM\data\Countries;
use ChurchCRM\dto\SystemURLs;
use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

SystemURLs::init('', [], APP_ROOT);

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
    Assert::same($expected, $actual, "toName: {$label}");
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
    Assert::same($expected, $actual, "toISO: {$label}");
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
    Assert::same($expected, $actual, "keepStoredIfSame: {$label}");
}
