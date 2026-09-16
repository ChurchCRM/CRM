<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\model\ChurchCRM\Base\VolunteerSchedule as BaseVolunteerSchedule;

/**
 * Skeleton subclass for representing a row from the 'volunteer_schedule_vsch' table.
 *
 * Volunteer Management v2 (#9705). The recurring series V2 owns: either linked to an event type or standalone.
 *
 * Deliberately holds no business logic and no pre* authorization hooks: the
 * Group and Person2group2roleP2g2r models gate saves through AuthService, which
 * reads $_SESSION flags an API-key caller never sets, so a non-admin coordinator
 * could not save through the ORM at all. V2 authorizes in middleware and
 * services instead (#9706).
 */
class VolunteerSchedule extends BaseVolunteerSchedule
{
    // A linked schedule never carries its own recurrence - the event
    // occurrences are authoritative. Only standalone schedules recur here.
    public const LINK_MODE_EVENT_TYPE = 'event_type';
    public const LINK_MODE_STANDALONE = 'standalone';

    /**
     * Every legal value of vsch_LinkMode.
     *
     * @return string[]
     */
    public static function allLinkModes(): array
    {
        return [
            self::LINK_MODE_EVENT_TYPE,
            self::LINK_MODE_STANDALONE,
        ];
    }

    public const RECUR_NONE = 'none';
    public const RECUR_WEEKLY = 'weekly';
    public const RECUR_MONTHLY = 'monthly';
    public const RECUR_YEARLY = 'yearly';

    /**
     * Every legal value of vsch_RecurType.
     *
     * @return string[]
     */
    public static function allRecurTypes(): array
    {
        return [
            self::RECUR_NONE,
            self::RECUR_WEEKLY,
            self::RECUR_MONTHLY,
            self::RECUR_YEARLY,
        ];
    }

    // Same value domain as event_types.type_defrecurDOW.
    public const DOW_SUNDAY = 'Sunday';
    public const DOW_MONDAY = 'Monday';
    public const DOW_TUESDAY = 'Tuesday';
    public const DOW_WEDNESDAY = 'Wednesday';
    public const DOW_THURSDAY = 'Thursday';
    public const DOW_FRIDAY = 'Friday';
    public const DOW_SATURDAY = 'Saturday';

    /**
     * Every legal value of vsch_RecurDOW.
     *
     * @return string[]
     */
    public static function allRecurDows(): array
    {
        return [
            self::DOW_SUNDAY,
            self::DOW_MONDAY,
            self::DOW_TUESDAY,
            self::DOW_WEDNESDAY,
            self::DOW_THURSDAY,
            self::DOW_FRIDAY,
            self::DOW_SATURDAY,
        ];
    }
}
