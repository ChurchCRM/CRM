<?php

declare(strict_types=1);

use ChurchCRM\data\States;
use ChurchCRM\dto\SystemURLs;
use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

SystemURLs::init('', [], APP_ROOT);

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
    Assert::same($expected, $actual, "States::keepStoredIfSame: {$label}");
}
