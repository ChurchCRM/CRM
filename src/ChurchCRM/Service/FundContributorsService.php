<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\DonationFundQuery;
use ChurchCRM\model\ChurchCRM\PledgeQuery;
use ChurchCRM\Utils\LoggerUtils;
use Psr\Log\LoggerInterface;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * Service to aggregate pledge/payment data per family for a specific fund and fiscal year.
 *
 * Used by the Fund Summary contributor drill-down page (GET /finance/fund/{fundId}/contributors).
 */
class FundContributorsService
{
    private LoggerInterface $logger;

    public function __construct()
    {
        $this->logger = LoggerUtils::getAppLogger();
    }

    /**
     * Get per-family pledge summary for a specific donation fund and fiscal year.
     *
     * @param int $fundId Donation fund ID
     * @param int $fyid   Fiscal year ID, or 0/negative for All Time (no FY filter)
     * @return array{
     *   fund: array{id: int, name: string}|null,
     *   contributors: array,
     *   stats: array{
     *     total_pledged: float,
     *     total_paid: float,
     *     total_remaining: float,
     *     contributor_count: int,
     *     percent_paid: float
     *   }
     * }
     */
    public function getFundContributors(int $fundId, int $fyid): array
    {
        $this->logger->debug('FundContributorsService::getFundContributors', [
            'fundId' => $fundId,
            'fyid'   => $fyid,
        ]);

        // Resolve the donation fund record
        $fund = DonationFundQuery::create()->findOneById($fundId);
        if ($fund === null) {
            $this->logger->warning('FundContributorsService: fund not found', ['fundId' => $fundId]);
            return [
                'fund'         => null,
                'contributors' => [],
                'stats'        => $this->emptyStats(),
            ];
        }

        $fundName = $fund->getName();

        // -- Pledges for this fund / fiscal year (fyid <= 0 = All Time) -------------------
        $pledgesQuery = PledgeQuery::create()
            ->filterByFundId($fundId)
            ->filterByPledgeOrPayment('Pledge')
            ->filterByAmount(0, Criteria::GREATER_THAN)
            ->joinWith('Pledge.Family')
            ->orderByFamId();
        if ($fyid > 0) {
            $pledgesQuery->filterByFyId($fyid);
        }
        $pledges = $pledgesQuery->find();

        // -- Payments for this fund / fiscal year (fyid <= 0 = All Time) ------------------
        $paymentsQuery = PledgeQuery::create()
            ->filterByFundId($fundId)
            ->filterByPledgeOrPayment('Payment')
            ->filterByAmount(0, Criteria::GREATER_THAN)
            ->joinWith('Pledge.Family')
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

        // Index payments by family ID and person ID
        $paymentsByFamily  = [];  // famId => ['total' => float, 'by_person' => [personId => float]]
        $paymentGroupKeys  = [];  // "$famId:$personId" => string
        $paymentFamilyInfo = [];  // famId => ['family_id', 'family_name', 'envelope']

        foreach ($payments as $payment) {
            $famId = (int) $payment->getFamId();
            $payPersonId = $personMap[(int) $payment->getId()] ?? 0;
            $payAmt = (float) $payment->getAmount();
            $key = $famId . ':' . $payPersonId;

            if (!isset($paymentsByFamily[$famId])) {
                $paymentsByFamily[$famId] = [
                    'total' => 0.0,
                    'by_person' => [],
                ];
            }
            $paymentsByFamily[$famId]['total'] += $payAmt;
            if (!isset($paymentsByFamily[$famId]['by_person'][$payPersonId])) {
                $paymentsByFamily[$famId]['by_person'][$payPersonId] = 0.0;
            }
            $paymentsByFamily[$famId]['by_person'][$payPersonId] += $payAmt;

            if (!isset($paymentGroupKeys[$key])) {
                $paymentGroupKeys[$key] = (string) $payment->getGroupKey();
            }

            if (!isset($paymentFamilyInfo[$famId])) {
                $payFamily = $payment->getFamily();
                if ($payFamily !== null) {
                    $paymentFamilyInfo[$famId] = [
                        'family_id'   => $famId,
                        'family_name' => (string) $payFamily->getName(),
                        'envelope'    => (string) ($payFamily->getEnvelope() ?? ''),
                    ];
                }
            }
        }

        // Build contributor rows from pledges
        $contributors = [];  // "$famId:$personId" => row

        foreach ($pledges as $pledge) {
            $family = $pledge->getFamily();
            if ($family === null) {
                continue;
            }

            $famId = (int) $family->getId();
            $pledgePersonId = $personMap[(int) $pledge->getId()] ?? 0;
            $pledgePersonName = $pledgePersonId > 0 ? ($personNames[$pledgePersonId] ?? '') : '';
            $pledgeAmount = (float) $pledge->getAmount();
            $key = $famId . ':' . $pledgePersonId;

            if (!isset($contributors[$key])) {
                $contributors[$key] = [
                    'family_id'   => $famId,
                    'family_name' => (string) $family->getName(),
                    'person_id'   => $pledgePersonId,
                    'person_name' => $pledgePersonName,
                    'envelope'    => (string) ($family->getEnvelope() ?? ''),
                    'pledged'     => 0.0,
                    'paid'        => 0.0,
                    'group_key'   => (string) $pledge->getGroupKey(),
                ];
            }

            $contributors[$key]['pledged'] += $pledgeAmount;
        }

        // Reconcile payments with pledges
        $familyPledgeKeys = [];
        foreach ($contributors as $k => $c) {
            $familyPledgeKeys[$c['family_id']][] = $k;
        }

        foreach ($familyPledgeKeys as $famId => $pKeys) {
            $fundPayData = $paymentsByFamily[$famId] ?? null;
            if (!$fundPayData) {
                continue;
            }

            if (count($pKeys) === 1) {
                $k = $pKeys[0];
                $contributors[$k]['paid'] = $fundPayData['total'];
            } else {
                $unallocatedPay = $fundPayData['total'];
                $unassignedKey = null;

                foreach ($pKeys as $k) {
                    $pid = $contributors[$k]['person_id'];
                    if ($pid > 0 && isset($fundPayData['by_person'][$pid])) {
                        $pAmt = $fundPayData['by_person'][$pid];
                        $contributors[$k]['paid'] = $pAmt;
                        $unallocatedPay -= $pAmt;
                    } elseif ($pid === 0) {
                        $unassignedKey = $k;
                    }
                }

                if ($unallocatedPay > 0 && $unassignedKey !== null) {
                    $contributors[$unassignedKey]['paid'] += $unallocatedPay;
                    $unallocatedPay = 0.0;
                } elseif ($unallocatedPay > 0) {
                    $sumPledged = array_sum(array_map(static fn($key) => $contributors[$key]['pledged'], $pKeys));
                    if ($sumPledged > 0) {
                        foreach ($pKeys as $k) {
                            $ratio = $contributors[$k]['pledged'] / $sumPledged;
                            $contributors[$k]['paid'] += $unallocatedPay * $ratio;
                        }
                    }
                }
            }
        }

        // Backfill payment-only families and persons (paid but no pledge recorded)
        foreach ($paymentsByFamily as $famId => $fundPayData) {
            if (isset($familyPledgeKeys[$famId])) {
                continue;
            }
            if (!isset($paymentFamilyInfo[$famId])) {
                continue;
            }

            $info = $paymentFamilyInfo[$famId];
            foreach ($fundPayData['by_person'] as $perId => $paidAmount) {
                if ($paidAmount <= 0.0) {
                    continue;
                }
                $key = $famId . ':' . $perId;
                $contributors[$key] = [
                    'family_id'   => $info['family_id'],
                    'family_name' => $info['family_name'],
                    'person_id'   => $perId,
                    'person_name' => $perId > 0 ? ($personNames[$perId] ?? '') : '',
                    'envelope'    => $info['envelope'],
                    'pledged'     => 0.0,
                    'paid'        => $paidAmount,
                    'group_key'   => $paymentGroupKeys[$key] ?? null,
                ];
            }
        }

        // Compute derived columns and status, then flatten to an indexed array
        $totalPledged         = 0.0;
        $totalPaid            = 0.0;
        $totalRemaining       = 0.0;
        $pledgeDeficitCovered = 0.0;

        foreach ($contributors as &$row) {
            $pledged = $row['pledged'];
            $paid    = $row['paid'];

            if ($pledged <= 0.0) {
                // Payment-only contributor: no pledge to track against
                $remaining = null;
                $percent   = 100.0;
                $status    = 'payment-only';
            } else {
                $remaining = max(0.0, $pledged - $paid);
                $percent   = min(100.0, ($paid / $pledged) * 100.0);
                if ($percent >= 100.0) {
                    $status = 'complete';
                } elseif ($percent >= 75.0) {
                    $status = 'on-track';
                } elseif ($percent >= 50.0) {
                    $status = 'behind';
                } else {
                    $status = 'critical';
                }
                $pledgeDeficitCovered += min($paid, $pledged);
            }

            $row['remaining'] = $remaining;
            $row['percent']   = $percent;
            $row['status']    = $status;

            $totalPledged   += $pledged;
            $totalPaid      += $paid;
            $totalRemaining += ($remaining ?? 0.0);
        }
        unset($row); // break reference

        // Sort by family name, then person name
        usort($contributors, static function (array $a, array $b): int {
            $famCmp = strcasecmp($a['family_name'], $b['family_name']);
            if ($famCmp !== 0) {
                return $famCmp;
            }
            return strcasecmp($a['person_name'] ?? '', $b['person_name'] ?? '');
        });

        $contributorCount = count($contributors);
        // $pledgeDeficitCovered is the sum of min(paid, pledged) across pledging
        // families, so it never exceeds $totalPledged and the ratio stays ≤1.0.
        // Fall back to $totalPaid when there are no pledges at all so the stat
        // card shows 100% for payment-only funds rather than 0%.
        $percentPaid = $totalPledged > 0
            ? ($pledgeDeficitCovered / $totalPledged) * 100.0
            : ($totalPaid > 0 ? 100.0 : 0.0);

        $this->logger->info('FundContributorsService: loaded contributors', [
            'fundId'           => $fundId,
            'fyid'             => $fyid,
            'contributorCount' => $contributorCount,
        ]);

        return [
            'fund'         => ['id' => $fundId, 'name' => $fundName],
            'contributors' => array_values($contributors),
            'stats'        => [
                'total_pledged'     => $totalPledged,
                'total_paid'        => $totalPaid,
                'total_remaining'   => $totalRemaining,
                'contributor_count' => $contributorCount,
                'percent_paid'      => $percentPaid,
            ],
        ];
    }

    /**
     * @return array<string, float|int>
     */
    private function emptyStats(): array
    {
        return [
            'total_pledged'     => 0.0,
            'total_paid'        => 0.0,
            'total_remaining'   => 0.0,
            'contributor_count' => 0,
            'percent_paid'      => 0.0,
        ];
    }
}
