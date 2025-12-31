<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\PledgeQuery;
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
            
            if (!isset($familyPayments[$famId])) {
                $familyPayments[$famId] = [];
            }
            if (!isset($familyPayments[$famId][$fundId])) {
                $familyPayments[$famId][$fundId] = 0.0;
            }
            
            $familyPayments[$famId][$fundId] += (float) $payment->getAmount();
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
            
            // Add this pledge amount to the fund total
            $pledgeAmount = (float) $pledge->getAmount();
            $familiesPledges[$famId]['pledges'][$fundId]['pledge_amount'] += $pledgeAmount;
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
                    ];
                }
                
                // Add pledge and payment amounts to fund total
                $fundTotals[$fundId]['total_pledged'] += $pledge['pledge_amount'];
                $fundTotals[$fundId]['total_paid'] += $pledge['payment_amount'];
                $totalPledgesAmount += $pledge['pledge_amount'];
                $totalPaymentsAmount += $pledge['payment_amount'];
                
                // Track unique families per fund
                if (!in_array($family['family_id'], $fundTotals[$fundId]['families'])) {
                    $fundTotals[$fundId]['families'][] = $family['family_id'];
                    $fundTotals[$fundId]['family_count']++;
                }
            }
        }
        
        // Clean up fund totals (remove internal tracking array)
        foreach ($fundTotals as &$fundTotal) {
            unset($fundTotal['families']);
        }
        unset($fundTotal); // Break reference

        // Sort by family name
        usort($familiesPledges, function ($a, $b) {
            return strcasecmp($a['family_name'], $b['family_name']);
        });

        return [
            'families' => array_values($familiesPledges),
            'fund_totals' => array_values($fundTotals),
            'total_pledges' => $totalPledgesAmount,
            'total_payments' => $totalPaymentsAmount,
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
