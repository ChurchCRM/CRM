<?php

namespace ChurchCRM\Portal\Permissions;

/**
 * One grant a rule gives: a capability on a scope, and the label the admin
 * pages show as its source (for example "Leader of Sunday Greeters").
 */
final class PortalRuleGrant
{
    public readonly PortalScope $scope;

    public function __construct(
        public readonly string $capability,
        ?PortalScope $scope,
        public readonly string $label,
    ) {
        $this->scope = $scope ?? PortalScope::none();
    }
}
