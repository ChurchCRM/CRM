<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\model\ChurchCRM\Base\VolunteerSchedule as BaseVolunteerSchedule;

/**
 * Skeleton subclass for representing a row from the 'volunteer_schedule_vsch' table.
 *
 * Volunteer Management v2 (#9705). Which calendar events a team staffs: every
 * occurrence is anchored to an event (D20), and the link mode says how the events are
 * found (D22).
 *
 * Deliberately holds no business logic and no pre* authorization hooks: the
 * Group and Person2group2roleP2g2r models gate saves through AuthService, which
 * reads $_SESSION flags an API-key caller never sets, so a non-admin coordinator
 * could not save through the ORM at all. V2 authorizes in middleware and
 * services instead (#9706).
 */
class VolunteerSchedule extends BaseVolunteerSchedule
{
    /** Events of one type, optionally narrowed by title (church-wide services). */
    public const LINK_MODE_EVENT_TYPE = 'event_type';
    /** Events whose Linked Group (`event_audience`) is `vsch_grp_ID` — a class's meetings. */
    public const LINK_MODE_CLASS = 'class';
    /** Events the schedule's ministry owns (`events_event.event_ministry_id`), optionally narrowed by title. */
    public const LINK_MODE_MINISTRY = 'ministry';
    /** Exactly one event, `vsch_event_id` — the hidden schedule behind Staff this event. */
    public const LINK_MODE_EVENT = 'event';

    /** The volunteers' start and end may move at most this far from the event's (D21). */
    public const MAX_OFFSET_MINUTES = 720;

    /**
     * Every legal value of vsch_LinkMode.
     *
     * @return string[]
     */
    public static function allLinkModes(): array
    {
        return [
            self::LINK_MODE_EVENT_TYPE,
            self::LINK_MODE_CLASS,
            self::LINK_MODE_MINISTRY,
            self::LINK_MODE_EVENT,
        ];
    }

    /**
     * The link modes a schedule may be created or edited with directly. The `event`
     * mode is reachable only through Staff this event.
     *
     * @return string[]
     */
    public static function editableLinkModes(): array
    {
        return [
            self::LINK_MODE_EVENT_TYPE,
            self::LINK_MODE_CLASS,
            self::LINK_MODE_MINISTRY,
        ];
    }
}
