<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\model\ChurchCRM\Base\VolunteerSwap as BaseVolunteerSwap;

/**
 * Skeleton subclass for representing a row from the 'volunteer_swap_vswp' table.
 *
 * Volunteer Management v2 (#9705). A proposed substitute who has already agreed, plus the coordinator decision.
 *
 * Deliberately holds no business logic and no pre* authorization hooks: the
 * Group and Person2group2roleP2g2r models gate saves through AuthService, which
 * reads $_SESSION flags an API-key caller never sets, so a non-admin coordinator
 * could not save through the ORM at all. V2 authorizes in middleware and
 * services instead (#9706).
 */
class VolunteerSwap extends BaseVolunteerSwap
{
    // proposed -> approved | rejected | withdrawn, all terminal. Withdrawn is
    // reachable only by the proposer and leaves the original assignment accepted.
    public const STATUS_PROPOSED = 'proposed';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_WITHDRAWN = 'withdrawn';

    /**
     * Every legal value of vswp_Status.
     *
     * @return string[]
     */
    public static function allStatuses(): array
    {
        return [
            self::STATUS_PROPOSED,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_WITHDRAWN,
        ];
    }
}
