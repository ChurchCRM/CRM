<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\model\ChurchCRM\Base\VolunteerOccurrence as BaseVolunteerOccurrence;

/**
 * Skeleton subclass for representing a row from the 'volunteer_occurrence_vocc' table.
 *
 * Volunteer Management v2 (#9705). One concrete opportunity to serve; the row assignments hang off.
 *
 * Deliberately holds no business logic and no pre* authorization hooks: the
 * Group and Person2group2roleP2g2r models gate saves through AuthService, which
 * reads $_SESSION flags an API-key caller never sets, so a non-admin coordinator
 * could not save through the ORM at all. V2 authorizes in middleware and
 * services instead (#9706).
 */
class VolunteerOccurrence extends BaseVolunteerOccurrence
{
    // A coordinator may cancel a single occurrence without touching the schedule.
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Every legal value of vocc_Status.
     *
     * @return string[]
     */
    public static function allStatuses(): array
    {
        return [
            self::STATUS_SCHEDULED,
            self::STATUS_CANCELLED,
        ];
    }
}
