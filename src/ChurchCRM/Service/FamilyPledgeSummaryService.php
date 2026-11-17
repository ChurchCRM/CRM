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
     * @param int $fyid Fiscal Year ID, or 0/negative for All Time (no FY filter)
     * @return array Array of families with pledge data
     */
    public function getFamilyPledgesByFiscalYear(int $fyid): array
    {
        // Get all pledges for the fiscal year (only actual pledges, not payments)
        $pledgesQuery = PledgeQuery::create()
            ->filterByPledgeOrPayment('Pledge')
            ->filterByAmount(0, Criteria::GREATER_THAN)
            ->joinWith('Pledge.Family')
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
            ->joinWith('Pledge.Family')
            ->joinWith('Pledge.DonationFund', Criteria::LEFT_JOIN)
            ->orderByFamId();
        if ($fyid > 0) {
            $paymentsQuery->filterByFyId($fyid);
        }
        $payments = $paymentsQuery->find();

        // Batch lookup individual person attribution for all pledges and payments
        $allRecordIds = [];
        foreach ($pledges as $p) {
            $allRecordIds[] = (int) $p->getId();
        }
        foreach ($payments as $p) {
            $allRecordIds[] = (int) $p->getId();
        }
        $personMap = PersonPledgeService::getPersonsForPledges($allRecordIds);
        $personNames = PersonPledgeService::getPersonNames(array_values($personMap));

        // Per-fund record counters (to match legacy PledgeSummary report)
        $fundPledgeCounts = [];
        $fundPaymentCounts = [];

        // Store family and fund info for payment-only backfill step below
        $familyInfo = []; // famId => ['family_id', 'family_name', 'envelope']
        $fundInfo = [];   // fundId => fund_name

        // Organize payments by family, fund, and person for lookup
        $familyPayments = [];
        foreach ($payments as $payment) {
            $famId = (int) $payment->getFamId();
            $rawFundId = $payment->getFundId();
            
            $fund = $payment->getDonationFund();
            if (!$fund && $rawFundId) {
                $fund = DonationFundQuery::create()->findOneById($rawFundId);
            }
            
            $fundId = $fund ? (int) $fund->getId() : ($rawFundId ?: -1);
            $fundName = $fund ? $fund->getName() : gettext('Other');
            $payPersonId = $personMap[(int) $payment->getId()] ?? 0;
            $payAmount = (float) $payment->getAmount();
            
            if (!isset($familyPayments[$famId])) {
                $familyPayments[$famId] = [];
            }
            if (!isset($familyPayments[$famId][$fundId])) {
                $familyPayments[$famId][$fundId] = [
                    'total' => 0.0,
                    'by_person' => [],
                ];
            }
            
            $familyPayments[$famId][$fundId]['total'] += $payAmount;
            if (!isset($familyPayments[$famId][$fundId]['by_person'][$payPersonId])) {
                $familyPayments[$famId][$fundId]['by_person'][$payPersonId] = 0.0;
            }
            $familyPayments[$famId][$fundId]['by_person'][$payPersonId] += $payAmount;

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

        // Organize data by family and aggregate by fund and individual person
        $familiesPledges = [];
        
        foreach ($pledges as $pledge) {
            $family = $pledge->getFamily();
            $famId = (int) $family->getId();
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

            $personId = $personMap[(int) $pledge->getId()] ?? 0;
            $personName = $personId > 0 ? ($personNames[$personId] ?? '') : '';
            $itemKey = $fundId . ':' . $personId;
            
            // Initialize family array if not exists
            if (!isset($familiesPledges[$famId])) {
                $familiesPledges[$famId] = [
                    'family_id' => $famId,
                    'family_name' => $family->getName(),
                    'envelope' => $family->getEnvelope(),
                    'pledges' => [],
                ];
            }
            
            if (!isset($familiesPledges[$famId]['pledges'][$itemKey])) {
                $familiesPledges[$famId]['pledges'][$itemKey] = [
                    'fund_id' => $fundId,
                    'fund_name' => $fundName,
                    'person_id' => $personId,
                    'person_name' => $personName,
                    'pledge_amount' => 0.0,
                    'payment_amount' => 0.0,
                    'group_key' => $pledge->getGroupKey(),
                    'pledge_type' => $pledge->getPledgeOrPayment(),
                ];
            }
            
            // Count individual pledge records per fund
            if (!isset($fundPledgeCounts[$fundId])) {
                $fundPledgeCounts[$fundId] = 0;
            }
            $fundPledgeCounts[$fundId]++;

            // Add this pledge amount to the item total
            $pledgeAmount = (float) $pledge->getAmount();
            $familiesPledges[$famId]['pledges'][$itemKey]['pledge_amount'] += $pledgeAmount;
        }

        // Reconcile payments with pledges for each family and fund
        foreach ($familiesPledges as $famId => &$famData) {
            $fundToPledgeKeys = [];
            foreach ($famData['pledges'] as $k => $item) {
                $fundToPledgeKeys[$item['fund_id']][] = $k;
            }

            foreach ($fundToPledgeKeys as $fundId => $pKeys) {
                $fundPayData = $familyPayments[$famId][$fundId] ?? null;
                if (!$fundPayData) {
                    continue;
                }

                if (count($pKeys) === 1) {
                    // Single pledge for this fund: all payments apply to it
                    $k = $pKeys[0];
                    $famData['pledges'][$k]['payment_amount'] = $fundPayData['total'];
                } else {
                    // Multiple pledges for the same fund in this family:
                    // Allocate specifically attributed payments first
                    $unallocatedPay = $fundPayData['total'];
                    $unassignedPledgeKey = null;

                    foreach ($pKeys as $k) {
                        $pid = $famData['pledges'][$k]['person_id'];
                        if ($pid > 0 && isset($fundPayData['by_person'][$pid])) {
                            $attributedPay = $fundPayData['by_person'][$pid];
                            $famData['pledges'][$k]['payment_amount'] = $attributedPay;
                            $unallocatedPay -= $attributedPay;
                        } elseif ($pid === 0) {
                            $unassignedPledgeKey = $k;
                        }
                    }

                    if ($unallocatedPay > 0 && $unassignedPledgeKey !== null) {
                        $famData['pledges'][$unassignedPledgeKey]['payment_amount'] += $unallocatedPay;
                        $unallocatedPay = 0.0;
                    } elseif ($unallocatedPay > 0) {
                        $sumPledged = array_sum(array_map(static fn($key) => $famData['pledges'][$key]['pledge_amount'], $pKeys));
                        if ($sumPledged > 0) {
                            foreach ($pKeys as $k) {
                                $ratio = $famData['pledges'][$k]['pledge_amount'] / $sumPledged;
                                $famData['pledges'][$k]['payment_amount'] += $unallocatedPay * $ratio;
                            }
                        }
                    }
                }
            }
        }
        unset($famData);

        // Backfill payment-only entries: payments for a family-fund pair that has
        // no matching pledge (e.g. ad-hoc or one-time donations).
        foreach ($familyPayments as $famId => $fundPayments) {
            foreach ($fundPayments as $fundId => $fundPayData) {
                $hasPledgeForFund = false;
                if (isset($familiesPledges[$famId])) {
                    foreach ($familiesPledges[$famId]['pledges'] as $plg) {
                        if ($plg['fund_id'] === $fundId) {
                            $hasPledgeForFund = true;
                            break;
                        }
                    }
                }
                if ($hasPledgeForFund) {
                    continue;
                }

                // Ensure the family container exists
                if (!isset($familiesPledges[$famId])) {
                    if (!isset($familyInfo[$famId])) {
                        continue; // No metadata available, cannot reconstruct; skip
                    }
                    $info = $familyInfo[$famId];
                    $familiesPledges[$famId] = [
                        'family_id' => $info['family_id'],
                        'family_name' => $info['family_name'],
                        'envelope' => $info['envelope'],
                        'pledges' => [],
                    ];
                }

                foreach ($fundPayData['by_person'] as $perId => $payAmount) {
                    if ($payAmount <= 0.0) {
                        continue;
                    }
                    $itemKey = $fundId . ':' . $perId;
                    $familiesPledges[$famId]['pledges'][$itemKey] = [
                        'fund_id' => $fundId,
                        'fund_name' => $fundInfo[$fundId] ?? gettext('Other'),
                        'person_id' => $perId,
                        'person_name' => $perId > 0 ? ($personNames[$perId] ?? '') : '',
                        'pledge_amount' => 0.0,
                        'payment_amount' => $payAmount,
                        'group_key' => null,
                        'pledge_type' => 'Payment',
                    ];
                }
            }
        }

        // Convert pledges associative array to indexed array and sort by fund name, then person name
        foreach ($familiesPledges as &$family) {
            $family['pledges'] = array_values($family['pledges']);
            usort($family['pledges'], function ($a, $b) {
                $fundCmp = strcasecmp($a['fund_name'], $b['fund_name']);
                if ($fundCmp !== 0) {
                    return $fundCmp;
                }
                return strcasecmp($a['person_name'] ?? '', $b['person_name'] ?? '');
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

        // Sort by family name
        usort($familiesPledges, function ($a, $b) {
            return strcasecmp($a['family_name'], $b['family_name']);
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
