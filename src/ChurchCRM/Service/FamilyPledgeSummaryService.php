<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\DonationFundQuery;
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
     * @param int $fyid Fiscal Year ID
     * @return array Array of families with pledge data
     */
    public function getFamilyPledgesByFiscalYear(int $fyid): array
    {
        // Get all pledges for the fiscal year (only actual pledges, not payments)
        $pledges = PledgeQuery::create()
            ->filterByFyId($fyid)
            ->filterByPledgeOrPayment('Pledge')
            ->filterByAmount(0, Criteria::GREATER_THAN)
            ->joinWith('Pledge.Family')
            ->joinWith('Pledge.DonationFund', Criteria::LEFT_JOIN)
            ->orderByFamId()
            ->find();

        // Get all payments for the fiscal year to compare with pledges
        $payments = PledgeQuery::create()
            ->filterByFyId($fyid)
            ->filterByPledgeOrPayment('Payment')
            ->filterByAmount(0, Criteria::GREATER_THAN)
            ->joinWith('Pledge.Family')
            ->joinWith('Pledge.DonationFund', Criteria::LEFT_JOIN)
            ->orderByFamId()
            ->find();

        // Per-fund record counters (to match legacy PledgeSummary report)
        $fundPledgeCounts = [];
        $fundPaymentCounts = [];

        // Store family and fund info for payment-only backfill step below
        $familyInfo = []; // famId => ['family_id', 'family_name', 'envelope']
        $fundInfo = [];   // fundId => fund_name

        // Organize payments by family and fund for lookup
        $familyPayments = [];
        foreach ($payments as $payment) {
            $famId = $payment->getFamId();
            $rawFundId = $payment->getFundId();
            
            $fund = $payment->getDonationFund();
            if (!$fund && $rawFundId) {
                $fund = DonationFundQuery::create()->findOneById($rawFundId);
            }
            
            $fundId = $fund ? (int) $fund->getId() : ($rawFundId ?: -1);
            $fundName = $fund ? $fund->getName() : gettext('Other');
            
            if (!isset($familyPayments[$famId])) {
                $familyPayments[$famId] = [];
            }
            if (!isset($familyPayments[$famId][$fundId])) {
                $familyPayments[$famId][$fundId] = 0.0;
            }
            
            $familyPayments[$famId][$fundId] += (float) $payment->getAmount();

            // Save family/fund metadata so we can backfill payment-only rows later
            if (!isset($familyInfo[$famId])) {
                $payFamily = $payment->getFamily();
                if ($payFamily) {
                    $familyInfo[$famId] = [
                        'family_id' => $famId,
                        'family_name' => $payFamily->getName(),
                        'envelope' => $payFamily->getEnvelope(),
                    ];
                }
            }
            if (!isset($fundInfo[$fundId])) {
                $fundInfo[$fundId] = $fundName;
            }

            // Count individual payment records per fund
            if (!isset($fundPaymentCounts[$fundId])) {
                $fundPaymentCounts[$fundId] = 0;
            }
            $fundPaymentCounts[$fundId]++;
        }

        // Organize data by family and aggregate by fund
        $familiesPledges = [];
        
        foreach ($pledges as $pledge) {
            $family = $pledge->getFamily();
            $famId = $family->getId();
            $rawFundId = $pledge->getFundId();
            
            // Try to get the fund - use direct query if join didn't work
            $fund = $pledge->getDonationFund();
            if (!$fund && $rawFundId) {
                $fund = DonationFundQuery::create()->findOneById($rawFundId);
            }
            
            // Determine fund ID and name
            if ($fund) {
                $fundId = (int) $fund->getId();
                $fundName = $fund->getName();
            } else {
                // No valid fund - use fund ID as key or "Other" bucket
                $fundId = $rawFundId ?: -1;
                $fundName = gettext('Other');
            }
            
            // Initialize family array if not exists
            if (!isset($familiesPledges[$famId])) {
                $familiesPledges[$famId] = [
                    'family_id' => $famId,
                    'family_name' => $family->getName(),
                    'envelope' => $family->getEnvelope(),
                    'pledges' => [],
                ];
            }
            
            // Aggregate pledges by fund (sum amounts for same family-fund combination)
            if (!isset($familiesPledges[$famId]['pledges'][$fundId])) {
                // Get payment amount for this family/fund combination
                $paymentAmount = isset($familyPayments[$famId][$fundId]) ? $familyPayments[$famId][$fundId] : 0.0;
                
                $familiesPledges[$famId]['pledges'][$fundId] = [
                    'fund_id' => $fundId,
                    'fund_name' => $fundName,
                    'pledge_amount' => 0.0,
                    'payment_amount' => $paymentAmount,
                    'group_key' => $pledge->getGroupKey(),
                    'pledge_type' => $pledge->getPledgeOrPayment(),
                ];
            }
            
            // Count individual pledge records per fund
            if (!isset($fundPledgeCounts[$fundId])) {
                $fundPledgeCounts[$fundId] = 0;
            }
            $fundPledgeCounts[$fundId]++;

            // Add this pledge amount to the fund total
            $pledgeAmount = (float) $pledge->getAmount();
            $familiesPledges[$famId]['pledges'][$fundId]['pledge_amount'] += $pledgeAmount;
        }

        // Backfill payment-only entries: payments for a family-fund pair that has
        // no matching pledge (e.g. ad-hoc / one-time donations). Without this step
        // those amounts would be excluded from fund totals entirely.
        foreach ($familyPayments as $famId => $fundPayments) {
            foreach ($fundPayments as $fundId => $paymentAmount) {
                // Skip if the pledge loop already created an entry for this pair
                if (isset($familiesPledges[$famId]['pledges'][$fundId])) {
                    continue;
                }

                // Ensure the family container exists (payment-only family has no pledge row)
                if (!isset($familiesPledges[$famId])) {
                    if (!isset($familyInfo[$famId])) {
                        continue; // No metadata available — cannot reconstruct; skip
                    }
                    $info = $familyInfo[$famId];
                    $familiesPledges[$famId] = [
                        'family_id' => $info['family_id'],
                        'family_name' => $info['family_name'],
                        'envelope' => $info['envelope'],
                        'pledges' => [],
                    ];
                }

                $familiesPledges[$famId]['pledges'][$fundId] = [
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
                
                // Track unique families per fund
                if (!in_array($family['family_id'], $fundTotals[$fundId]['families'])) {
                    $fundTotals[$fundId]['families'][] = $family['family_id'];
                    $fundTotals[$fundId]['family_count']++;
                }
            }
        }
        
        // Attach per-fund record counts from the pledge/payment loops and clean up
        foreach ($fundTotals as $fId => &$fundTotal) {
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

        // Sort by family name
        usort($familiesPledges, function ($a, $b) {
            return strcasecmp($a['family_name'], $b['family_name']);
        });

        return [
            'families' => array_values($familiesPledges),
            'fund_totals' => array_values($fundTotals),
            'total_pledges' => $totalPledgesAmount,
            'total_payments' => $totalPaymentsAmount,
            'overall_totals' => $overallTotals,
        ];
    }

    /**
     * Get all available fiscal years for the dropdown
     *
     * Returns fiscal years from the oldest pledge in the database to the next fiscal year
     * 
     * @return array Array of fiscal years with id and label, sorted newest to oldest
     */
    public function getAvailableFiscalYears(): array
    {
        // Get the oldest fiscal year with pledges
        $oldestPledge = PledgeQuery::create()
            ->orderByFyId()
            ->select(['FyId'])
            ->findOne();
        
        $oldestFyId = $oldestPledge ? (int) $oldestPledge : FiscalYearUtils::getCurrentFiscalYearId();
        $currentFyId = FiscalYearUtils::getCurrentFiscalYearId();
        $nextFyId = $currentFyId + 1; // Include next fiscal year for planning
        
        $years = [];
        // Build array from oldest to next year, then reverse to show newest first
        for ($fyid = $oldestFyId; $fyid <= $nextFyId; $fyid++) {
            $fyLabel = FinancialService::formatFiscalYear($fyid);
            $years[] = [
                'id' => $fyid,
                'label' => $fyLabel,
            ];
        }
        
        // Reverse to show newest first
        return array_reverse($years);
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
}
