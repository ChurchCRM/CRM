<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\DepositQuery;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\PledgeQuery;
use ChurchCRM\Utils\FiscalYearUtils;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * What the finance team needs to see before they download a year's giving statements.
 * A chosen year is the fiscal year that ends in that calendar year.
 */
class YearEndGivingService
{
    /**
     * Fiscal years with payments, each named by the calendar year it ends in, newest first.
     * The current fiscal year is always offered.
     *
     * @return int[]
     */
    public static function years(): array
    {
        $years = [FiscalYearUtils::endYearForId(FiscalYearUtils::getCurrentFiscalYearId())];
        $rows = PledgeQuery::create()
            ->filterByPledgeOrPayment('Payment')
            ->filterByDate(null, Criteria::ISNOTNULL)
            ->withColumn('YEAR(pledge_plg.plg_date)', 'PaymentYear')
            ->withColumn('MONTH(pledge_plg.plg_date)', 'PaymentMonth')
            ->groupBy('PaymentYear')
            ->groupBy('PaymentMonth')
            ->select(['PaymentYear', 'PaymentMonth'])
            ->find();
        foreach ($rows as $row) {
            $years[] = FiscalYearUtils::getEndYearForDate(sprintf('%04d-%02d-01', $row['PaymentYear'], $row['PaymentMonth']));
        }
        $years = array_values(array_unique($years));
        rsort($years);

        return $years;
    }

    /**
     * The year the statement pages open on: the one asked for, else the user's last pick,
     * else the newest completed year. $years is newest first.
     *
     * @param int[] $years
     */
    public static function chooseYear(array $years, ?int $requested, ?int $remembered, int $lastCompleted): int
    {
        foreach ([$requested, $remembered] as $candidate) {
            if ($candidate !== null && in_array($candidate, $years, true)) {
                return $candidate;
            }
        }
        foreach ($years as $year) {
            if ($year <= $lastCompleted) {
                return $year;
            }
        }

        return $years[0];
    }

    public static function selectedYear(?int $requested = null): int
    {
        return self::chooseYear(
            self::years(),
            $requested,
            FiscalYearUtils::rememberedEndYear(),
            FiscalYearUtils::endYearForId(FiscalYearUtils::getCurrentFiscalYearId()) - 1
        );
    }

    public static function openDeposits(int $year): int
    {
        $period = FiscalYearUtils::getFiscalYearDatesForCalendarYear($year);

        return DepositQuery::create()
            ->filterByClosed(false)
            ->filterByDate(['min' => $period['startDate'], 'max' => $period['endDate']])
            ->count();
    }

    /**
     * @return array{
     *   year: int,
     *   startDate: string,
     *   endDate: string,
     *   label: string,
     *   statements: array{families: int, gifts: int, total: string},
     *   emailable: int,
     *   mailOnly: int,
     *   openDeposits: int,
     *   undeposited: int,
     *   unassigned: int
     * }
     */
    public static function overview(int $year): array
    {
        $period = FiscalYearUtils::getFiscalYearDatesForCalendarYear($year);
        $dateStart = $period['startDate'];
        $dateEnd = $period['endDate'];

        $groups = GivingStatementService::paymentsByFamily(
            GivingStatementService::criteriaFromQuery(['DateStart' => $dateStart, 'DateEnd' => $dateEnd])
        );

        $unassigned = 0;
        if (isset($groups[0])) {
            $unassigned = count($groups[0]);
            unset($groups[0]);
        }

        $emailable = 0;
        foreach (array_keys($groups) as $familyId) {
            $family = FamilyQuery::create()->findPk($familyId);
            if ($family !== null && $family->getEmails() !== []) {
                $emailable++;
            }
        }

        return [
            'year' => $year,
            'startDate' => $dateStart,
            'endDate' => $dateEnd,
            'label' => $period['label'],
            'statements' => GivingStatementService::summary($groups),
            'emailable' => $emailable,
            'mailOnly' => count($groups) - $emailable,
            'openDeposits' => self::openDeposits($year),
            'undeposited' => PledgeQuery::create()
                ->filterByPledgeOrPayment('Payment')
                ->filterByDate(['min' => $dateStart, 'max' => $dateEnd])
                ->filterByDepId(0)
                ->count(),
            'unassigned' => $unassigned,
        ];
    }
}
