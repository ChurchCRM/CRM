<?php

declare(strict_types=1);

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Service\PledgeCommitmentService;
use Tester\Assert;

require dirname(__DIR__, 3) . '/bootstrap.php';

$configs = new ReflectionProperty(SystemConfig::class, 'configs');
$setFiscalMonth = static function (int $month) use ($configs): void {
    $configs->setValue(null, ['iFYMonth' => new class($month) {
        public function __construct(private int $month) {}
        public function getValue(): string { return (string) $this->month; }
    }]);
};
$setFiscalMonth(1);
$payment = (object) [
    'type' => 'Pledge', 'amountBasis' => 'installment', 'schedule' => 'Monthly',
    'FYID' => 30, 'firstInstallment' => '2026-01-01', 'lastInstallment' => '2026-12-31',
];
$funds = [(object) ['FundID' => 1, 'Amount' => '100.00', 'NonDeductible' => '10.00']];
$result = PledgeCommitmentService::allocations($payment, $funds);
Assert::same(1200, $result[0]->Amount);
Assert::same(120, $result[0]->NonDeductible);
Assert::same(100, $result[0]->InstallmentAmount);
Assert::same('100.00', $funds[0]->Amount, 'Preview and retries do not multiply the input twice');
Assert::same(1000, $result[0]->Amount - 200);
$split = PledgeCommitmentService::allocations($payment, [
    (object) ['FundID' => 1, 'Amount' => '10.01', 'NonDeductible' => '0.01'],
    (object) ['FundID' => 2, 'Amount' => '25.50', 'NonDeductible' => '2.50'],
]);
Assert::same(120.12, $split[0]->Amount);
Assert::same(0.12, $split[0]->NonDeductible);
Assert::same(306, $split[1]->Amount);
Assert::same(30, $split[1]->NonDeductible);
Assert::same(12, PledgeCommitmentService::installmentCount('Monthly', '2026-01-31', '2026-12-31', 30));
Assert::same(2, PledgeCommitmentService::installmentCount('Monthly', '2026-01-31', '2026-03-30', 30));
Assert::same(3, PledgeCommitmentService::installmentCount('Monthly', '2024-01-31', '2024-03-31', 28));
Assert::same(4, PledgeCommitmentService::installmentCount('Quarterly', '2026-01-31', '2026-12-31', 30));
Assert::same(53, PledgeCommitmentService::installmentCount('Weekly', '2026-01-01', '2026-12-31', 30));
Assert::same(1, PledgeCommitmentService::installmentCount('Monthly', '2026-05-15', '2026-05-15', 30));
Assert::same($funds, PledgeCommitmentService::allocations((object) ['type' => 'Pledge', 'schedule' => 'Monthly'], $funds), 'Legacy monthly amounts remain full totals');

foreach ([
    ['Monthly', '2026-02-30', '2026-12-31', 30],
    ['Monthly', '2026-12-31', '2026-01-01', 30],
    ['Monthly', '2025-12-31', '2026-12-31', 30],
    ['Monthly', '2026-01-01', '2027-01-01', 30],
    ['Other', '2026-01-01', '2026-12-31', 30],
    ['Once', '2026-01-01', '2026-12-31', 30],
    ['Monthly', [], '2026-12-31', 30],
] as $invalid) {
    Assert::exception(fn () => PledgeCommitmentService::installmentCount(...$invalid), UnexpectedValueException::class);
}
foreach (['100000.00', '0.001', '-1', 'invalid', INF] as $amount) {
    Assert::exception(fn () => PledgeCommitmentService::allocations($payment, [(object) ['Amount' => $amount]]), UnexpectedValueException::class);
}
$payment->type = 'Payment';
Assert::exception(fn () => PledgeCommitmentService::allocations($payment, $funds), UnexpectedValueException::class);
$setFiscalMonth(7);
Assert::same(12, PledgeCommitmentService::installmentCount('Monthly', '2025-07-01', '2026-06-30', 30));
Assert::exception(fn () => PledgeCommitmentService::installmentCount('Monthly', '2026-01-01', '2026-12-31', 30), UnexpectedValueException::class);
