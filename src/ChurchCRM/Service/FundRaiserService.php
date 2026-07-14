<?php

namespace ChurchCRM\Service;

use ChurchCRM\Utils\CurrencyFormatter;
use ChurchCRM\Utils\LoggerUtils;
use Propel\Runtime\Propel;

/**
 * Service class for Fundraiser module aggregations.
 *
 * Provides grouped, N+1-free queries for list summaries and single-event view data.
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
     *     'raised'  => float,  // SUM di_sellprice
     *     'est'     => float,  // SUM di_estprice
     *     'buyers'  => int,    // paddle number count
     *   ]
     *
     * Two batched GROUP BY queries, zero per-row N+1.
     *
     * @return array<int, array{items:int, raised:float, est:float, buyers:int}>
     */
    public function getListSummaries(): array
    {
        $this->logger->debug('FundRaiserService::getListSummaries — fetching aggregates');

        $conn = Propel::getConnection();

        // -- Items aggregates ------------------------------------------------
        $itemSql = '
            SELECT
                di_FR_ID          AS fr_id,
                COUNT(*)          AS items,
                COALESCE(SUM(di_sellprice), 0)    AS raised,
                COALESCE(SUM(di_estprice), 0)     AS est
            FROM donateditem_di
            GROUP BY di_FR_ID
        ';
        $itemStmt = $conn->prepare($itemSql);
        $itemStmt->execute();
        $itemRows = $itemStmt->fetchAll(\PDO::FETCH_ASSOC);

        $summaries = [];
        foreach ($itemRows as $row) {
            $id               = (int) $row['fr_id'];
            $summaries[$id]   = [
                'items'  => (int)   $row['items'],
                'raised' => (float) $row['raised'],
                'est'    => (float) $row['est'],
                'buyers' => 0,
            ];
        }

        // -- Buyer aggregates ------------------------------------------------
        $buyerSql = '
            SELECT pn_fr_ID AS fr_id, COUNT(*) AS buyers
            FROM paddlenum_pn
            GROUP BY pn_fr_ID
        ';
        $buyerStmt = $conn->prepare($buyerSql);
        $buyerStmt->execute();
        $buyerRows = $buyerStmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($buyerRows as $row) {
            $id = (int) $row['fr_id'];
            if (!isset($summaries[$id])) {
                $summaries[$id] = ['items' => 0, 'raised' => 0.0, 'est' => 0.0];
            }
            $summaries[$id]['buyers'] = (int) $row['buyers'];
        }

        $this->logger->debug('FundRaiserService::getListSummaries — done', ['count' => count($summaries)]);

        return $summaries;
    }

    /**
     * Returns aggregate statistics for a single fundraiser view page.
     *
     * Loads item and paddle-number data for the given fundraiser ID and
     * computes in-PHP memory — no per-item sub-queries.
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

        $conn = Propel::getConnection();

        // -- Item stats -------------------------------------------------------
        $itemSql = '
            SELECT
                di_sellprice,
                di_estprice,
                di_materialvalue,
                di_buyer_ID
            FROM donateditem_di
            WHERE di_FR_ID = :id
        ';
        $itemStmt = $conn->prepare($itemSql);
        $itemStmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $itemStmt->execute();
        $itemRows = $itemStmt->fetchAll(\PDO::FETCH_ASSOC);

        $itemsTotal       = count($itemRows);
        $itemsSold        = 0;
        $totalRaised      = 0.0;
        $highestSale      = 0.0;
        $totalEstValue    = 0.0;
        $totalMatValue    = 0.0;

        foreach ($itemRows as $row) {
            $sell = (float) $row['di_sellprice'];
            $est  = (float) $row['di_estprice'];
            $mat  = (float) $row['di_materialvalue'];
            $totalEstValue += $est;
            $totalMatValue += $mat;
            if ($sell > 0 && (int) $row['di_buyer_ID'] > 0) {
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

        // -- Buyer count ------------------------------------------------------
        $buyerSql = 'SELECT COUNT(*) AS buyers FROM paddlenum_pn WHERE pn_fr_ID = :id';
        $buyerStmt = $conn->prepare($buyerSql);
        $buyerStmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $buyerStmt->execute();
        $buyers = (int) $buyerStmt->fetchColumn();

        return [
            'items'                     => $itemsTotal,
            'itemsSold'                 => $itemsSold,
            'sellThroughPct'            => $sellThroughPct,
            'totalRaised'               => $totalRaised,
            'avgSalePrice'              => $avgSalePrice,
            'highestSale'               => $highestSale,
            'totalEstValue'             => $totalEstValue,
            'totalMaterialValue'        => $totalMatValue,
            'buyers'                    => $buyers,
            'totalRaised_formatted'     => CurrencyFormatter::format($totalRaised),
            'avgSalePrice_formatted'    => CurrencyFormatter::format($avgSalePrice),
            'highestSale_formatted'     => CurrencyFormatter::format($highestSale),
            'totalEstValue_formatted'   => CurrencyFormatter::format($totalEstValue),
            'totalMaterialValue_formatted' => CurrencyFormatter::format($totalMatValue),
        ];
    }

    /**
     * Returns this-year aggregates for the landing-page stat widgets.
     *
     * "This year" means fundraisers whose fr_date falls in the current calendar year.
     *
     * @return array{activeCount:int, raisedThisYear:float, itemsThisYear:int, buyersThisYear:int}
     */
    public function getWidgetStats(): array
    {
        $this->logger->debug('FundRaiserService::getWidgetStats');

        $conn = Propel::getConnection();
        $year = date('Y');

        // Active fundraisers count
        $activeStmt = $conn->prepare(
            "SELECT COUNT(*) FROM fundraiser_fr WHERE fr_Status IN ('Active','Planning')"
        );
        $activeStmt->execute();
        $activeCount = (int) $activeStmt->fetchColumn();

        // This-year item/money stats
        $itemStmt = $conn->prepare("
            SELECT
                COUNT(d.di_ID)                        AS items,
                COALESCE(SUM(d.di_sellprice), 0)      AS raised
            FROM donateditem_di d
            INNER JOIN fundraiser_fr f ON f.fr_ID = d.di_FR_ID
            WHERE YEAR(f.fr_date) = :year
        ");
        $itemStmt->bindValue(':year', $year, \PDO::PARAM_INT);
        $itemStmt->execute();
        $itemRow = $itemStmt->fetch(\PDO::FETCH_ASSOC);

        // This-year buyer count
        $buyerStmt = $conn->prepare("
            SELECT COUNT(p.pn_ID) AS buyers
            FROM paddlenum_pn p
            INNER JOIN fundraiser_fr f ON f.fr_ID = p.pn_fr_ID
            WHERE YEAR(f.fr_date) = :year
        ");
        $buyerStmt->bindValue(':year', $year, \PDO::PARAM_INT);
        $buyerStmt->execute();
        $buyers = (int) $buyerStmt->fetchColumn();

        return [
            'activeCount'    => $activeCount,
            'raisedThisYear' => (float) ($itemRow['raised'] ?? 0),
            'itemsThisYear'  => (int)   ($itemRow['items']  ?? 0),
            'buyersThisYear' => $buyers,
        ];
    }
}
