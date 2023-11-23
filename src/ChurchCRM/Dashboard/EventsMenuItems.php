<?php

namespace ChurchCRM\Dashboard;

use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\Map\FamilyTableMap;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use Propel\Runtime\ActiveQuery\Criteria;

class EventsMenuItems implements DashboardItemInterface
{
    public static function getDashboardItemName(): string
    {
        return 'EventsCounters';
    }

    public static function getDashboardItemValue(): array
    {
        $activeEvents = [
            'Events'        => self::getNumberEventsOfToday(),
            'Birthdays'     => self::getNumberBirthDates(),
            'Anniversaries' => self::getNumberAnniversaries(),
        ];

        return $activeEvents;
    }

    public static function shouldInclude(string $PageName): bool
    {
        return true; // this ID would be found on all pages.
    }

    private static function getNumberEventsOfToday()
    {
        $start_date = date('Y-m-d ') . ' 00:00:00';
        $end_date = date('Y-m-d H:i:s', strtotime($start_date . ' +1 day'));

        return EventQuery::create()
            ->where("event_start <= '" . $start_date . "' AND event_end >= '" . $end_date . "'") /* the large events */
            ->_or()->where("event_start>='" . $start_date . "' AND event_end <= '" . $end_date . "'") /* the events of the day */
            ->count();
    }

    private static function getNumberBirthDates()
    {
        return PersonQuery::create()
            ->filterByBirthMonth(date('m'))
            ->filterByBirthDay(date('d'))
            ->count();

        return $peopleWithBirthDays;
    }

    private static function getNumberAnniversaries()
    {
        return $families = FamilyQuery::create()
            ->filterByDateDeactivated(null)
            ->filterByWeddingdate(null, Criteria::NOT_EQUAL)
            ->addUsingAlias(FamilyTableMap::COL_FAM_WEDDINGDATE, 'MONTH(' . FamilyTableMap::COL_FAM_WEDDINGDATE . ') =' . date('m'), Criteria::CUSTOM)
            ->addUsingAlias(FamilyTableMap::COL_FAM_WEDDINGDATE, 'DAY(' . FamilyTableMap::COL_FAM_WEDDINGDATE . ') =' . date('d'), Criteria::CUSTOM)
            ->orderByWeddingdate('DESC')
            ->count();
    }
}
