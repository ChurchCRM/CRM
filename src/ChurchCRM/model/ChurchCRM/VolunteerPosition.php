<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\model\ChurchCRM\Base\VolunteerPosition as BaseVolunteerPosition;

/**
 * Skeleton subclass for representing a row from the 'volunteer_position_vpos' table.
 *
 * Volunteer Management v2 (#9705). A role a volunteer can serve in, owned by a ministry and optionally narrowed to a team.
 *
 * Deliberately holds no business logic and no pre* authorization hooks: the
 * Group and Person2group2roleP2g2r models gate saves through AuthService, which
 * reads $_SESSION flags an API-key caller never sets, so a non-admin coordinator
 * could not save through the ORM at all. V2 authorizes in middleware and
 * services instead (#9706).
 */
class VolunteerPosition extends BaseVolunteerPosition
{
}
