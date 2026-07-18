<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\DonatedItemQuery;
use ChurchCRM\model\ChurchCRM\FundRaiserQuery;
use ChurchCRM\model\ChurchCRM\Map\DonatedItemTableMap;
use ChurchCRM\model\ChurchCRM\Map\PaddleNumTableMap;
use ChurchCRM\model\ChurchCRM\PaddleNumQuery;
use ChurchCRM\Utils\CurrencyFormatter;
use ChurchCRM\Utils\LoggerUtils;

/**
 * Service class for Fundraiser module aggregations.
 *
 * All queries use Perpl ORM (Query classes + TableMap constants).
 * Raw SQL / Propel::getConnection() is intentionally absent — every
 * access goes through the ORM layer for consistency and type safety.
 */
class FundRaiserService
{
    private \Psr\Log\LoggerInterface $logger;

    public function __construct()
    {
        $this->logger = LoggerUtils::getAppLogger();
    }

    /**
     * Returns an associative array keyed by fundraiser ID, with per-fundraiser
     * aggregates needed by the landing-page DataTable.
     *
     * Each value is:
     *   [
     *     'items'   => int,    // donated item count
     *     'raised'  => float,  // SUM of sell-price for sold items (buyer > 0 AND sell > 0)
     *     'est'     => float,  // SUM di_estprice
     *     'buyers'  => int,    // paddle number count
     *   ]
     *
     * Two batched ORM GROUP BY queries, zero per-row N+1.
     *
     * @param  int[] $fundraiserIds When provided and non-empty, limits the aggregation
     *                              to only these fundraiser IDs (avoids full-table scans
     *                              on large installs when the listing is filtered).
     * @return array<int, array{items:int, raised:float, est:float, buyers:int}>
     */
    public function getListSummaries(array $fundraiserIds = []): array
    {
        $this->logger->debug('FundRaiserService::getListSummaries — fetching aggregates');

        // -- Items aggregates ------------------------------------------------
        // "Raised" mirrors getViewModel(): only sold items (buyer_ID > 0 AND sellprice > 0).
        $itemQuery = DonatedItemQuery::create();
        if (!empty($fundraiserIds)) {
            $itemQuery->filterByFrId($fundraiserIds);
        }
        $itemRows = $itemQuery
            ->withColumn(DonatedItemTableMap::COL_DI_FR_ID, 'frId')
            ->withColumn('COUNT(*)', 'items')
            ->withColumn(
                'COALESCE(SUM(CASE WHEN ' . DonatedItemTableMap::COL_DI_BUYER_ID . ' > 0 AND '
                . DonatedItemTableMap::COL_DI_SELLPRICE . ' > 0 THEN '
                . DonatedItemTableMap::COL_DI_SELLPRICE . ' ELSE 0 END), 0)',
                'raised'
            )
            ->withColumn('COALESCE(SUM(' . DonatedItemTableMap::COL_DI_ESTPRICE . '), 0)', 'est')
            ->groupBy('FrId')
            ->select(['frId', 'items', 'raised', 'est'])
            ->find();

        $summaries = [];
        foreach ($itemRows as $row) {
            $id             = (int) $row['frId'];
            $summaries[$id] = [
                'items'  => (int)   $row['items'],
                'raised' => (float) $row['raised'],
                'est'    => (float) $row['est'],
                'buyers' => 0,
            ];
        }

        // -- Buyer aggregates ------------------------------------------------
        $buyerQuery = PaddleNumQuery::create();
        if (!empty($fundraiserIds)) {
            $buyerQuery->filterByPnFrId($fundraiserIds);
        }
        $buyerRows = $buyerQuery
            ->withColumn(PaddleNumTableMap::COL_PN_FR_ID, 'frId')
            ->withColumn('COUNT(*)', 'buyers')
            ->groupBy('PnFrId')
            ->select(['frId', 'buyers'])
            ->find();

        foreach ($buyerRows as $row) {
            $id = (int) $row['frId'];
            if (!isset($summaries[$id])) {
                $summaries[$id] = ['items' => 0, 'raised' => 0.0, 'est' => 0.0, 'buyers' => 0];
            }
            $summaries[$id]['buyers'] = (int) $row['buyers'];
        }

        $this->logger->debug('FundRaiserService::getListSummaries — done', ['count' => count($summaries)]);

        return $summaries;
    }

    /**
     * Returns aggregate statistics for a single fundraiser view page.
     *
     * Loads item and paddle-number data via ORM and computes aggregates in PHP —
     * avoids complex sub-queries while keeping zero per-row N+1.
     *
     * @return array{
     *   items: int,
     *   itemsSold: int,
     *   sellThroughPct: float,
     *   totalRaised: float,
     *   avgSalePrice: float,
     *   highestSale: float,
     *   totalEstValue: float,
     *   totalMaterialValue: float,
     *   buyers: int,
     *   totalRaised_formatted: string,
     *   avgSalePrice_formatted: string,
     *   highestSale_formatted: string,
     *   totalEstValue_formatted: string,
     *   totalMaterialValue_formatted: string,
     * }
     */
    public function getViewModel(int $id): array
    {
        $this->logger->debug('FundRaiserService::getViewModel', ['id' => $id]);

        // -- Item stats via ORM object iteration (no raw SQL) -----------------
        $items = DonatedItemQuery::create()
            ->filterByFrId($id)
            ->find();

        $itemsTotal    = count($items);
        $itemsSold     = 0;
        $totalRaised   = 0.0;
        $highestSale   = 0.0;
        $totalEstValue = 0.0;
        $totalMatValue = 0.0;

        foreach ($items as $item) {
            $sell = (float) $item->getSellprice();
            $est  = (float) $item->getEstprice();
            $mat  = (float) $item->getMaterialValue();
            $totalEstValue += $est;
            $totalMatValue += $mat;
            if ($sell > 0 && (int) $item->getBuyerId() > 0) {
                $itemsSold++;
                $totalRaised += $sell;
                if ($sell > $highestSale) {
                    $highestSale = $sell;
                }
            }
        }

        $sellThroughPct = $itemsTotal > 0
            ? round(($itemsSold / $itemsTotal) * 100, 1)
            : 0.0;
        $avgSalePrice = $itemsSold > 0
            ? round($totalRaised / $itemsSold, 2)
            : 0.0;

        // -- Buyer count via ORM ----------------------------------------------
        $buyers = PaddleNumQuery::create()
            ->filterByPnFrId($id)
            ->count();

        return [
            'items'                        => $itemsTotal,
            'itemsSold'                    => $itemsSold,
            'sellThroughPct'               => $sellThroughPct,
            'totalRaised'                  => $totalRaised,
            'avgSalePrice'                 => $avgSalePrice,
            'highestSale'                  => $highestSale,
            'totalEstValue'                => $totalEstValue,
            'totalMaterialValue'           => $totalMatValue,
            'buyers'                       => $buyers,
            'totalRaised_formatted'        => CurrencyFormatter::format($totalRaised),
            'avgSalePrice_formatted'       => CurrencyFormatter::format($avgSalePrice),
            'highestSale_formatted'        => CurrencyFormatter::format($highestSale),
            'totalEstValue_formatted'      => CurrencyFormatter::format($totalEstValue),
            'totalMaterialValue_formatted' => CurrencyFormatter::format($totalMatValue),
        ];
    }

    /**
     * Returns this-year aggregates for the landing-page stat widgets.
     *
     * "This year" means fundraisers whose fr_date falls in the current calendar year.
     * Two-step approach: first fetch the IDs of this-year's fundraisers via FundRaiserQuery,
     * then aggregate donated items and paddle numbers for those IDs — no raw SQL join needed.
     *
     * @return array{activeCount:int, raisedThisYear:float, itemsThisYear:int, buyersThisYear:int}
     */
    public function getWidgetStats(): array
    {
        $this->logger->debug('FundRaiserService::getWidgetStats');

        $year = (int) date('Y');

        // Active fundraisers count (Active + Planning)
        $activeCount = FundRaiserQuery::create()
            ->filterByStatus(['Active', 'Planning'])
            ->count();

        // This-year fundraiser IDs (step 1 of 2-step approach — no FK relations defined)
        $yearStart = $year . '-01-01';
        $yearEnd   = $year . '-12-31';
        $yearIds   = FundRaiserQuery::create()
            ->filterByDate(['min' => $yearStart, 'max' => $yearEnd])
            ->select(['Id'])
            ->find()
            ->toArray();

        $raisedThisYear = 0.0;
        $itemsThisYear  = 0;
        $buyersThisYear = 0;

        if (!empty($yearIds)) {
            // Item stats for this year's fundraisers
            // "Raised" mirrors getViewModel(): sold items only (buyer > 0 AND sell > 0).
            $itemRow = DonatedItemQuery::create()
                ->filterByFrId($yearIds)
                ->withColumn('COUNT(*)', 'items')
                ->withColumn(
                    'COALESCE(SUM(CASE WHEN ' . DonatedItemTableMap::COL_DI_BUYER_ID . ' > 0 AND '
                    . DonatedItemTableMap::COL_DI_SELLPRICE . ' > 0 THEN '
                    . DonatedItemTableMap::COL_DI_SELLPRICE . ' ELSE 0 END), 0)',
                    'raised'
                )
                ->select(['items', 'raised'])
                ->findOne();

            $raisedThisYear = (float) ($itemRow['raised'] ?? 0);
            $itemsThisYear  = (int)   ($itemRow['items']  ?? 0);

            // Buyer count for this year's fundraisers
            $buyersThisYear = PaddleNumQuery::create()
                ->filterByPnFrId($yearIds)
                ->count();
        }

        return [
            'activeCount'    => $activeCount,
            'raisedThisYear' => $raisedThisYear,
            'itemsThisYear'  => $itemsThisYear,
            'buyersThisYear' => $buyersThisYear,
        ];
    }

    /**
     * Returns the count of fundraisers in Active or Planning status.
     *
     * Used by the navigation menu counter. Callers are responsible for any
     * session-level caching (e.g. storing in $_SESSION['iFundraiserActiveCount']
     * and invalidating on state changes).
     */
    public function getActiveFundraiserCount(): int
    {
        $this->logger->debug('FundRaiserService::getActiveFundraiserCount');
        return FundRaiserQuery::create()
            ->filterByStatus(['Active', 'Planning'])
            ->count();
    }
}
