<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\DonationFundQuery;
use ChurchCRM\model\ChurchCRM\Pledge;
use ChurchCRM\model\ChurchCRM\PledgeQuery;
use ChurchCRM\Service\FinancialService;
use ChurchCRM\Utils\FiscalYearUtils;
use Propel\Runtime\ActiveQuery\Criteria;

class FamilyPledgeSummaryService
{
    /**
     * Get family pledge summary for a given fiscal year
     *
     * Returns an array of families with their pledges grouped by donation fund
     *
     * @param int $fyid Fiscal Year ID, or 0/negative for All Time (no FY filter)
     * @return array Array of families with pledge data
     */
    public function getFamilyPledgesByFiscalYear(int $fyid): array
    {
        // Get all pledges for the fiscal year (only actual pledges, not payments)
        $pledgesQuery = PledgeQuery::create()
            ->filterByPledgeOrPayment('Pledge')
            ->filterByAmount(0, Criteria::GREATER_THAN)
            ->leftJoinWithFamily()
            ->joinWith('Pledge.DonationFund', Criteria::LEFT_JOIN)
            ->orderByFamId();
        if ($fyid > 0) {
            $pledgesQuery->filterByFyId($fyid);
        }
        $pledges = $pledgesQuery->find();

        // Get all payments for the fiscal year to compare with pledges
        $paymentsQuery = PledgeQuery::create()
            ->filterByPledgeOrPayment('Payment')
            ->filterByAmount(0, Criteria::GREATER_THAN)
            ->leftJoinWithFamily()
            ->joinWith('Pledge.DonationFund', Criteria::LEFT_JOIN)
            ->orderByFamId();
        if ($fyid > 0) {
            $paymentsQuery->filterByFyId($fyid);
        }
        $payments = $paymentsQuery->find();

        $donors = PledgeDonor::describe($pledges, $payments);

        // Per-fund record counters (to match legacy PledgeSummary report)
        $fundPledgeCounts = [];
        $fundPaymentCounts = [];
        $fundInfo = [];   // fundId => fund_name

        // Organize payments by donor and fund for lookup
        $donorPayments = [];
        foreach ($payments as $payment) {
            $donorKey = PledgeDonor::key($payment);
            if ($donorKey === null) {
                continue;
            }
            [$fundId, $fundName] = $this->resolveFund($payment);

            $donorPayments[$donorKey][$fundId] = ($donorPayments[$donorKey][$fundId] ?? 0.0) + (float) $payment->getAmount();
            $fundInfo[$fundId] ??= $fundName;
            $fundPaymentCounts[$fundId] = ($fundPaymentCounts[$fundId] ?? 0) + 1;
        }

        // Organize data by donor and aggregate by fund
        $familiesPledges = [];
        foreach ($pledges as $pledge) {
            $donorKey = PledgeDonor::key($pledge);
            if ($donorKey === null) {
                continue;
            }
            [$fundId, $fundName] = $this->resolveFund($pledge);

            $familiesPledges[$donorKey] ??= $donors[$donorKey] + ['pledges' => []];
            $familiesPledges[$donorKey]['pledges'][$fundId] ??= [
                'fund_id' => $fundId,
                'fund_name' => $fundName,
                'pledge_amount' => 0.0,
                'payment_amount' => $donorPayments[$donorKey][$fundId] ?? 0.0,
                'group_key' => $pledge->getGroupKey(),
                'pledge_type' => $pledge->getPledgeOrPayment(),
            ];
            $familiesPledges[$donorKey]['pledges'][$fundId]['pledge_amount'] += (float) $pledge->getAmount();
            $fundPledgeCounts[$fundId] = ($fundPledgeCounts[$fundId] ?? 0) + 1;
        }

        // Backfill payment-only entries: payments for a donor-fund pair that has
        // no matching pledge (e.g. ad-hoc / one-time donations). Without this step
        // those amounts would be excluded from fund totals entirely.
        foreach ($donorPayments as $donorKey => $fundPayments) {
            foreach ($fundPayments as $fundId => $paymentAmount) {
                $familiesPledges[$donorKey] ??= $donors[$donorKey] + ['pledges' => []];
                $familiesPledges[$donorKey]['pledges'][$fundId] ??= [
                    'fund_id' => $fundId,
                    'fund_name' => $fundInfo[$fundId] ?? gettext('Other'),
                    'pledge_amount' => 0.0,
                    'payment_amount' => $paymentAmount,
                    'group_key' => null,
                    'pledge_type' => 'Payment',
                ];
            }
        }

        // Convert pledges associative array to indexed array and sort by fund name
        foreach ($familiesPledges as &$family) {
            $family['pledges'] = array_values($family['pledges']);
            usort($family['pledges'], function ($a, $b) {
                return strcasecmp($a['fund_name'], $b['fund_name']);
            });
        }
        unset($family); // Break reference

        // Calculate fund totals from the families collection BEFORE sorting families
        $fundTotals = [];
        $totalPledgesAmount = 0.0;
        $totalPaymentsAmount = 0.0;
        
        foreach ($familiesPledges as $family) {
            foreach ($family['pledges'] as $pledge) {
                $fundId = $pledge['fund_id'];
                
                // Initialize fund total if not exists
                if (!isset($fundTotals[$fundId])) {
                    $fundTotals[$fundId] = [
                        'fund_id' => $fundId,
                        'fund_name' => $pledge['fund_name'],
                        'total_pledged' => 0.0,
                        'total_paid' => 0.0,
                        'family_count' => 0,
                        'families' => [],
                        'pledge_count' => 0,
                        'payment_count' => 0,
                        'overpaid' => 0.0,
                        'underpaid' => 0.0,
                    ];
                }
                
                // Add pledge and payment amounts to fund total
                $fundTotals[$fundId]['total_pledged'] += $pledge['pledge_amount'];
                $fundTotals[$fundId]['total_paid'] += $pledge['payment_amount'];
                $totalPledgesAmount += $pledge['pledge_amount'];
                $totalPaymentsAmount += $pledge['payment_amount'];

                // Compute per-family overpaid / underpaid for this fund
                $diff = $pledge['payment_amount'] - $pledge['pledge_amount'];
                if ($diff > 0) {
                    $fundTotals[$fundId]['overpaid'] += $diff;
                } elseif ($diff < 0) {
                    $fundTotals[$fundId]['underpaid'] -= $diff;
                }
                
                // Track unique donors per fund
                if (!in_array($family['donor_key'], $fundTotals[$fundId]['families'], true)) {
                    $fundTotals[$fundId]['families'][] = $family['donor_key'];
                    $fundTotals[$fundId]['family_count']++;
                }
            }
        }
        
        $categoryByFund = [];
        foreach (DonationFundQuery::create()->find() as $fund) {
            $categoryByFund[(int) $fund->getId()] = (string) $fund->getCategory();
        }

        // Attach per-fund record counts from the pledge/payment loops and clean up
        foreach ($fundTotals as $fId => &$fundTotal) {
            $fundTotal['category'] = $categoryByFund[$fId] ?? '';
            $fundTotal['pledge_count'] = $fundPledgeCounts[$fId] ?? 0;
            $fundTotal['payment_count'] = $fundPaymentCounts[$fId] ?? 0;
            unset($fundTotal['families']);
        }
        unset($fundTotal); // Break reference

        // Compute overall totals for the tfoot row
        $overallTotals = [
            'total_pledged' => 0.0,
            'total_paid' => 0.0,
            'pledge_count' => 0,
            'payment_count' => 0,
            'overpaid' => 0.0,
            'underpaid' => 0.0,
        ];
        foreach ($fundTotals as $fundTotal) {
            $overallTotals['total_pledged'] += $fundTotal['total_pledged'];
            $overallTotals['total_paid'] += $fundTotal['total_paid'];
            $overallTotals['pledge_count'] += $fundTotal['pledge_count'];
            $overallTotals['payment_count'] += $fundTotal['payment_count'];
            $overallTotals['overpaid'] += $fundTotal['overpaid'];
            $overallTotals['underpaid'] += $fundTotal['underpaid'];
        }

        usort($familiesPledges, function ($a, $b) {
            return strcasecmp($a['display_name'], $b['display_name']);
        });

        return [
            'families' => array_values($familiesPledges),
            'fund_totals' => array_values($fundTotals),
            'category_totals' => $this->rollupByCategory($fundTotals),
            'total_pledges' => $totalPledgesAmount,
            'total_payments' => $totalPaymentsAmount,
            'overall_totals' => $overallTotals,
        ];
    }

    /**
     * @return array{0: int, 1: string} fund id (-1 when unknown) and display name
     */
    private function resolveFund(Pledge $pledge): array
    {
        $rawFundId = $pledge->getFundId();
        $fund = $pledge->getDonationFund();
        if (!$fund && $rawFundId) {
            $fund = DonationFundQuery::create()->findOneById($rawFundId);
        }
        if ($fund) {
            return [(int) $fund->getId(), $fund->getName()];
        }

        return [$rawFundId ?: -1, gettext('Other')];
    }

    /**
     * Get all available fiscal years for the dropdown.
     *
     * Returns fiscal years from the oldest pledge in the database to the next fiscal year,
     * sorted newest first. Delegates label-building to FiscalYearUtils::buildFiscalYearList().
     *
     * @return array<int, array{id: int, label: string}>
     */
    public function getAvailableFiscalYears(): array
    {
        // Get the oldest fiscal year with pledges
        $oldestPledge = PledgeQuery::create()
            ->orderByFyId()
            ->select(['FyId'])
            ->findOne();

        $oldestFyId = $oldestPledge !== null ? (int) $oldestPledge : FiscalYearUtils::getCurrentFiscalYearId();

        return FiscalYearUtils::buildFiscalYearList($oldestFyId);
    }

    /**
     * Get current fiscal year ID
     *
     * @return int Current fiscal year ID
     */
    public function getCurrentFiscalYearId(): int
    {
        return FiscalYearUtils::getCurrentFiscalYearId();
    }

    /**
     * Sum fund totals per category (named categories sorted, uncategorized last).
     * Empty when no fund has a category, so callers can hide the rollup.
     *
     * @param array<int, array<string, mixed>> $fundTotals
     * @return array<int, array<string, mixed>>
     */
    private function rollupByCategory(array $fundTotals): array
    {
        $rollup = [];
        foreach ($fundTotals as $fundTotal) {
            $category = (string) $fundTotal['category'];
            $rollup[$category] ??= [
                'category' => $category,
                'fund_count' => 0,
                'total_pledged' => 0.0,
                'total_paid' => 0.0,
                'overpaid' => 0.0,
                'underpaid' => 0.0,
            ];
            $rollup[$category]['fund_count']++;
            foreach (['total_pledged', 'total_paid', 'overpaid', 'underpaid'] as $key) {
                $rollup[$category][$key] += $fundTotal[$key];
            }
        }

        if (array_keys($rollup) === [''] || $rollup === []) {
            return [];
        }

        uksort($rollup, static fn (string $a, string $b): int => $a === '' ? 1 : ($b === '' ? -1 : strcasecmp($a, $b)));

        return array_values($rollup);
    }
}
