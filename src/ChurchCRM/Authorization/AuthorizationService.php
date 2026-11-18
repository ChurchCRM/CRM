<?php

declare(strict_types=1);

namespace ChurchCRM\Authorization;

/**
 * What a signed-in person may do. The union of their role grants, then the
 * module flags. A portal member is denied every staff permission. A module
 * that is off is off for the Owner too. Nothing here is called on sign-in yet.
 */
final class AuthorizationService
{
    public function decide(AccessPrincipal $principal, string $permission): Decision
    {
        if (PermissionRegistry::get($permission) === null) {
            return Decision::deny(Decision::UNKNOWN_CAPABILITY);
        }
        if ($principal->portal) {
            return Decision::deny(Decision::STAFF_BOUNDARY);
        }
        if (!$this->moduleAllows($principal, $permission)) {
            return Decision::deny(Decision::FEATURE_OFF);
        }
        if ($principal->owner || $this->holds($principal, $permission, Scope::ALL)) {
            return Decision::allow($this->grant($permission, new Scope(Scope::ALL, 0)));
        }
        if ($this->holds($principal, $permission, Scope::MY_MINISTRIES)) {
            if ($principal->ministryIds === []) {
                return Decision::deny(Decision::SCOPE_REQUIRED);
            }

            return Decision::allow($this->grant($permission, new Scope(Scope::MY_MINISTRIES, $principal->ministryIds[0])));
        }

        return Decision::deny(Decision::NO_GRANT);
    }

    public function canReadDirectory(AccessPrincipal $principal): bool
    {
        return !$principal->portal;
    }

    private function moduleAllows(AccessPrincipal $principal, string $permission): bool
    {
        $module = PermissionRegistry::get($permission)?->module;
        if ($module === null) {
            return true;
        }
        $value = $principal->modules[$module] ?? false;
        if ($module === 'sVolunteerVersion') {
            return $value === 'v2' || $value === 'both';
        }

        return $value === true;
    }

    private function holds(AccessPrincipal $principal, string $permission, string $scope): bool
    {
        $roles = BuiltinRoles::all();
        foreach ($principal->roleKeys as $role) {
            foreach ($roles[$role] ?? [] as $grant) {
                if ($grant['permission'] === $permission && $grant['scope'] === $scope) {
                    return true;
                }
            }
        }

        return false;
    }

    private function grant(string $permission, Scope $scope): EffectiveGrant
    {
        return new EffectiveGrant($permission, $scope, EffectiveGrant::SOURCE_GIVEN, $permission);
    }
}
