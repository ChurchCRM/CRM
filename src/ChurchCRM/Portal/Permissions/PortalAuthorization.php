<?php

namespace ChurchCRM\Portal\Permissions;

use ChurchCRM\Portal\Auth\PortalPrincipal;

/**
 * The one portal permission check (member sign-in design §8.2), shaped like
 * #10511's `decide()` so the RFC's service can take it over.
 */
interface PortalAuthorization
{
    /** May this person do this, on this thing? Unknown capability: no. */
    public function decide(PortalPrincipal $who, string $capability, ?PortalScope $on = null): Decision;

    /**
     * Every scope this person holds the capability on, e.g. the teams they lead.
     * An unscoped capability that is held answers `[PortalScope::none()]`.
     *
     * @return PortalScope[]
     */
    public function scopesFor(PortalPrincipal $who, string $capability): array;
}
