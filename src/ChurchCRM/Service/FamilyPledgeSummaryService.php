<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\PledgeQuery;
use ChurchCRM\model\ChurchCRM\DonationFundQuery;
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
        // Use join to ensure fund data is loaded
        $pledges = PledgeQuery::create()
            ->filterByFyId($fyid)
            ->filterByPledgeOrPayment('Pledge')
            ->filterByAmount(0, Criteria::GREATER_THAN)
            ->joinWith('Pledge.Family')
            ->joinWith('Pledge.DonationFund', Criteria::LEFT_JOIN)
            ->orderByFamId()
            ->find();

        // Organize data by family and aggregate by fund
        $familiesPledges = [];
        $fundTotals = []; // Track fund totals: family count and total amount
        
        foreach ($pledges as $pledge) {
            $family = $pledge->getFamily();
            $famId = $family->getId();
            $fund = $pledge->getDonationFund();
            
            // Determine fund ID and name
            if ($fund) {
                $fundId = (int) $fund->getId();
                $fundName = $fund->getName();
            } else {
                // No valid fund - use "Other" bucket
                $fundId = -1;
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
            
            // Initialize fund totals if not exists
            if (!isset($fundTotals[$fundId])) {
                $fundTotals[$fundId] = [
                    'fund_id' => $fundId,
                    'fund_name' => $fundName,
                    'total_amount' => 0.0,
                    'family_count' => 0,
                    'families' => [], // Track unique families
                ];
            }
            
            // Aggregate pledges by fund (sum amounts for same family-fund combination)
            if (!isset($familiesPledges[$famId]['pledges'][$fundId])) {
                $familiesPledges[$famId]['pledges'][$fundId] = [
                    'fund_id' => $fundId,
                    'fund_name' => $fundName,
                    'pledge_amount' => 0.0,
                    'group_key' => $pledge->getGroupKey(), // Keep first group key for edit link
                    'pledge_type' => $pledge->getPledgeOrPayment(),
                ];
                
                // Track unique families per fund
                if (!in_array($famId, $fundTotals[$fundId]['families'])) {
                    $fundTotals[$fundId]['families'][] = $famId;
                    $fundTotals[$fundId]['family_count']++;
                }
            }
            
            // Add this pledge amount to the fund total
            $pledgeAmount = (float) $pledge->getAmount();
            $familiesPledges[$famId]['pledges'][$fundId]['pledge_amount'] += $pledgeAmount;
            $fundTotals[$fundId]['total_amount'] += $pledgeAmount;
        }

        // Convert pledges associative array to indexed array and sort by fund name
        foreach ($familiesPledges as &$family) {
            $family['pledges'] = array_values($family['pledges']);
            usort($family['pledges'], function ($a, $b) {
                return strcasecmp($a['fund_name'], $b['fund_name']);
            });
        }

        // Sort by family name
        usort($familiesPledges, function ($a, $b) {
            return strcasecmp($a['family_name'], $b['family_name']);
        });
        
        // Clean up fund totals (remove internal tracking array)
        foreach ($fundTotals as &$fundTotal) {
            unset($fundTotal['families']);
        }
        
        // Sort fund totals by fund name
        usort($fundTotals, function ($a, $b) {
            return strcasecmp($a['fund_name'], $b['fund_name']);
        });

        // Calculate total pledges for the year
        $totalPledgesAmount = 0.0;
        foreach ($fundTotals as $fundTotal) {
            $totalPledgesAmount += $fundTotal['total_amount'];
        }

        return [
            'families' => array_values($familiesPledges),
            'fund_totals' => array_values($fundTotals),
            'total_pledges' => $totalPledgesAmount,
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
