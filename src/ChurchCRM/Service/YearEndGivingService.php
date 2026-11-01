<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\DepositQuery;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\PledgeQuery;
use ChurchCRM\Utils\FiscalYearUtils;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * What the finance team needs to see before they print or email a year's giving statements.
 * A chosen year is the fiscal year that ends in that calendar year.
 */
class YearEndGivingService
{
    /**
     * Calendar years that have payments, newest first. The current year is always offered.
     *
     * @return int[]
     */
    public static function years(): array
    {
        $years = [(int) date('Y')];
        $rows = PledgeQuery::create()
            ->filterByPledgeOrPayment('Payment')
            ->filterByDate(null, Criteria::ISNOTNULL)
            ->withColumn('YEAR(pledge_plg.plg_date)', 'PaymentYear')
            ->groupBy('PaymentYear')
            ->select(['PaymentYear'])
            ->find();
        foreach ($rows as $year) {
            $years[] = (int) $year;
        }
        $years = array_values(array_unique($years));
        rsort($years);

        return $years;
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
            'openDeposits' => DepositQuery::create()
                ->filterByClosed(false)
                ->filterByDate(['min' => $dateStart, 'max' => $dateEnd])
                ->count(),
            'undeposited' => PledgeQuery::create()
                ->filterByPledgeOrPayment('Payment')
                ->filterByDate(['min' => $dateStart, 'max' => $dateEnd])
                ->filterByDepId(0)
                ->count(),
            'unassigned' => $unassigned,
        ];
    }
}
