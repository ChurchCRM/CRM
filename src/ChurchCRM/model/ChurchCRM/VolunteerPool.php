<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\model\ChurchCRM\Base\VolunteerPool as BaseVolunteerPool;

/**
 * Skeleton subclass for representing a row from the 'volunteer_pool_vpol' table.
 *
 * Volunteer Management v2 (#9705). Links a ministry or team to an existing group that acts as its volunteer pool.
 *
 * Deliberately holds no business logic and no pre* authorization hooks: the
 * Group and Person2group2roleP2g2r models gate saves through AuthService, which
 * reads $_SESSION flags an API-key caller never sets, so a non-admin coordinator
 * could not save through the ORM at all. V2 authorizes in middleware and
 * services instead (#9706).
 */
class VolunteerPool extends BaseVolunteerPool
{
    // The owner of a pool is polymorphic, so vpol_OwnerId carries no foreign
    // key; VolunteerSetupService resolves it against one of these two tables.
    public const OWNER_TYPE_MINISTRY = 'ministry';
    public const OWNER_TYPE_TEAM = 'team';

    /**
     * Every legal value of vpol_OwnerType.
     *
     * @return string[]
     */
    public static function allOwnerTypes(): array
    {
        return [
            self::OWNER_TYPE_MINISTRY,
            self::OWNER_TYPE_TEAM,
        ];
    }
}
