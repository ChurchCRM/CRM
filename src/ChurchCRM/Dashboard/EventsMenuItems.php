<?php

namespace ChurchCRM\Dashboard;

use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\Map\FamilyTableMap;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use DateTime;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * Provides counts of today's events, birthdays, and anniversaries
 * for menu badge display
 */
class EventsMenuItems
{
    public static function getDashboardItemValue(?DateTime $clientDate = null): array
    {
        // If the client passed its local date (browser "today"), use that so the
        // counter matches the calendar's highlighted day. Fall back to the
        // church-configured timezone when no date is provided.
        $today = $clientDate ?? DateTimeUtils::getStartOfToday();

        return [
            'Events'        => self::getNumberEventsOfToday($today),
            'Birthdays'     => self::getNumberBirthDates($today),
            'Anniversaries' => self::getNumberAnniversaries($today),
        ];
    }

    private static function getNumberEventsOfToday(DateTime $today): int
    {
        // today_start is midnight on the given date, tomorrow_start is the day after.
        $today_start = clone $today;
        $today_start->setTime(0, 0, 0);

        // Tomorrow at midnight for boundary checking
        $tomorrow_start = clone $today_start;
        $tomorrow_start->modify('+1 day');

        // Use ORM with parameterized queries instead of raw SQL
        // An event occurs today if: event_start <= tomorrow_start AND event_end >= today_start
        // This correctly includes all-day events that end at midnight (event_end = tomorrow_start)
        return EventQuery::create()
            ->filterByStart($tomorrow_start, Criteria::LESS_EQUAL)
            ->filterByEnd($today_start, Criteria::GREATER_EQUAL)
            ->count();
    }

    private static function getNumberBirthDates(DateTime $today): int
    {
        return PersonQuery::create()
            ->filterByBirthMonth($today->format('m'))
            ->filterByBirthDay($today->format('d'))
            ->count();
    }

    private static function getNumberAnniversaries(DateTime $today): int
    {
        return FamilyQuery::create()
            ->filterByDateDeactivated(null)
            ->filterByWeddingdate(null, Criteria::ISNOTNULL)
            ->addUsingAlias(FamilyTableMap::COL_FAM_WEDDINGDATE, 'MONTH(' . FamilyTableMap::COL_FAM_WEDDINGDATE . ') =' . $today->format('m'), Criteria::CUSTOM)
            ->addUsingAlias(FamilyTableMap::COL_FAM_WEDDINGDATE, 'DAY(' . FamilyTableMap::COL_FAM_WEDDINGDATE . ') =' . $today->format('d'), Criteria::CUSTOM)
            ->orderByWeddingdate('DESC')
            ->count();
    }
}
