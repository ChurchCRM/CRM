<?php

declare(strict_types=1);

use ChurchCRM\Service\YearEndGivingService;
use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

$years = [2026, 2025, 2024, 2019];

Assert::same(2024, YearEndGivingService::chooseYear($years, 2024, 2019, 2025), 'a year asked for wins');
Assert::same(2019, YearEndGivingService::chooseYear($years, null, 2019, 2025), 'the last pick is kept when none is asked for');
Assert::same(2025, YearEndGivingService::chooseYear($years, null, null, 2025), 'with no pick it opens on the last completed year');
Assert::same(2025, YearEndGivingService::chooseYear($years, 2031, 2030, 2025), 'a year with no payments is ignored');
Assert::same(2019, YearEndGivingService::chooseYear([2026, 2019], null, null, 2025), 'it falls to the newest completed year that has payments');
Assert::same(2026, YearEndGivingService::chooseYear([2026], null, null, 2025), 'a church with only a current year opens on it');
