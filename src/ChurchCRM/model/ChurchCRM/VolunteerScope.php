<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\model\ChurchCRM\Base\VolunteerScope as BaseVolunteerScope;

/**
 * Skeleton subclass for representing a row from the 'volunteer_scope_vscp' table.
 *
 * Volunteer Management v2 (#9705). Persists that a person coordinates a ministry or leads a team.
 *
 * Deliberately holds no business logic and no pre* authorization hooks: the
 * Group and Person2group2roleP2g2r models gate saves through AuthService, which
 * reads $_SESSION flags an API-key caller never sets, so a non-admin coordinator
 * could not save through the ORM at all. V2 authorizes in middleware and
 * services instead (#9706).
 */
class VolunteerScope extends BaseVolunteerScope
{
    // vscp_ScopeId is polymorphic and therefore carries no foreign key;
    // VolunteerAuthorizationService resolves it against one of these two tables.
    public const TYPE_MINISTRY = 'ministry';
    public const TYPE_TEAM = 'team';

    /**
     * Every legal value of vscp_ScopeType.
     *
     * @return string[]
     */
    public static function allScopeTypes(): array
    {
        return [
            self::TYPE_MINISTRY,
            self::TYPE_TEAM,
        ];
    }
}
