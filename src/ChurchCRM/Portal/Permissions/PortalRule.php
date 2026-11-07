<?php

namespace ChurchCRM\Portal\Permissions;

use ChurchCRM\model\ChurchCRM\Person;

/**
 * Code that works out portal grants from church data, live on every request
 * (member sign-in design §8.3). Nobody assigns these; they end when the data
 * changes. Core registers its rules at boot, plugins return theirs from
 * `getPortalRules()`.
 */
interface PortalRule
{
    /** A stable id, e.g. `core.team-leader` or `plugin.smallgroups.leaders`. */
    public function getKey(): string;

    /**
     * The grants this rule gives the person now. A grant for a capability the
     * registry does not know, or on a scope of the wrong type, is ignored.
     *
     * @return iterable<PortalRuleGrant>
     */
    public function grantsFor(Person $person): iterable;
}
