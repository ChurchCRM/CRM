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
     * @param int $fyid   Fiscal year ID
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

        // -- Pledges for this fund / fiscal year ------------------------------------------
        $pledges = PledgeQuery::create()
            ->filterByFyId($fyid)
            ->filterByFundId($fundId)
            ->filterByPledgeOrPayment('Pledge')
            ->filterByAmount(0, Criteria::GREATER_THAN)
            ->joinWith('Pledge.Family')
            ->orderByFamId()
            ->find();

        // -- Payments for this fund / fiscal year -----------------------------------------
        $payments = PledgeQuery::create()
            ->filterByFyId($fyid)
            ->filterByFundId($fundId)
            ->filterByPledgeOrPayment('Payment')
            ->filterByAmount(0, Criteria::GREATER_THAN)
            ->joinWith('Pledge.Family')
            ->orderByFamId()
            ->find();

        // Index payments by family ID
        $paymentsByFamily  = [];  // famId => float
        $paymentGroupKeys  = [];  // famId => string (first payment group key)
        $paymentFamilyInfo = [];  // famId => ['family_id', 'family_name', 'envelope']

        foreach ($payments as $payment) {
            $famId = (int) $payment->getFamId();

            if (!isset($paymentsByFamily[$famId])) {
                $paymentsByFamily[$famId] = 0.0;
            }
            $paymentsByFamily[$famId] += (float) $payment->getAmount();

            if (!isset($paymentGroupKeys[$famId])) {
                $paymentGroupKeys[$famId] = (string) $payment->getGroupKey();
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

        // Build per-family contributor rows from pledges
        $contributors = [];  // famId => row

        foreach ($pledges as $pledge) {
            $family = $pledge->getFamily();
            if ($family === null) {
                continue;
            }

            $famId       = (int) $family->getId();
            $pledgeAmount = (float) $pledge->getAmount();

            if (!isset($contributors[$famId])) {
                $contributors[$famId] = [
                    'family_id'   => $famId,
                    'family_name' => (string) $family->getName(),
                    'envelope'    => (string) ($family->getEnvelope() ?? ''),
                    'pledged'     => 0.0,
                    'paid'        => $paymentsByFamily[$famId] ?? 0.0,
                    // Known limitation: only the first pledge record's group_key is
                    // stored. Families with multiple pledge records for the same
                    // fund/FY will have their pledged amounts aggregated correctly,
                    // but the 'View Pledge' action links to the first record only.
                    'group_key'   => (string) $pledge->getGroupKey(),
                ];
            }

            $contributors[$famId]['pledged'] += $pledgeAmount;
        }

        // Backfill payment-only families (paid but no pledge recorded)
        foreach ($paymentsByFamily as $famId => $paidAmount) {
            if (isset($contributors[$famId])) {
                continue; // already created from pledges above
            }

            if (!isset($paymentFamilyInfo[$famId])) {
                continue; // no family metadata available, skip
            }

            $info = $paymentFamilyInfo[$famId];
            $contributors[$famId] = [
                'family_id'   => $info['family_id'],
                'family_name' => $info['family_name'],
                'envelope'    => $info['envelope'],
                'pledged'     => 0.0,
                'paid'        => $paidAmount,
                'group_key'   => $paymentGroupKeys[$famId] ?? null,
            ];
        }

        // Compute derived columns and status, then flatten to an indexed array
        $totalPledged         = 0.0;
        $totalPaid            = 0.0;
        $totalRemaining       = 0.0;
        // Accumulate each family's contribution capped at their pledge so that
        // overpayment in one family never masks underpayment in another and
        // the aggregate % stays internally consistent with $totalRemaining.
        $pledgeDeficitCovered = 0.0;

        foreach ($contributors as &$row) {
            $pledged = $row['pledged'];
            $paid    = $row['paid'];

            if ($pledged <= 0.0) {
                // Payment-only contributor — no pledge to track against
                $remaining = null;
                $percent   = 100.0;
                $status    = 'payment-only';
            } else {
                // Cap remaining at 0; overpayment is communicated by 'complete' status
                $remaining = max(0.0, $pledged - $paid);
                // Cap per-row % at 100% so it is consistent with remaining = 0
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
                // Each family contributes at most their pledged amount to the
                // aggregate coverage so overpayers don't hide underpayers.
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

        // Sort by family name
        usort($contributors, static function (array $a, array $b): int {
            return strcasecmp($a['family_name'], $b['family_name']);
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
