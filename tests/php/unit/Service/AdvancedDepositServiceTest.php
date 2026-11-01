<?php

declare(strict_types=1);

use ChurchCRM\Service\AdvancedDepositService;
use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

foreach ([['DateStart' => '2026-02-30'], ['DateStart' => '2026-01-02', 'DateEnd' => '2026-01-01'], ['deposit' => []], ['funds' => [['1']]]] as $invalid) {
    Assert::notSame('', AdvancedDepositService::validationError($invalid), 'Invalid report input is rejected');
}
Assert::same('', AdvancedDepositService::validationError(['deposit' => '12', 'DateStart' => 'stale-date']), 'Selected deposit overrides date range');
