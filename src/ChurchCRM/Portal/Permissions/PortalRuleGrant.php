<?php

namespace ChurchCRM\Portal\Permissions;

use ChurchCRM\Authorization\Scope;

/**
 * One grant a rule gives: a capability on a scope, and the label the admin
 * pages show as its source (for example "Leader of Sunday Greeters").
 */
final class PortalRuleGrant
{
    public readonly Scope $scope;

    public function __construct(
        public readonly string $capability,
        ?Scope $scope,
        public readonly string $label,
    ) {
        $this->scope = $scope ?? Scope::none();
    }
}
