<?php

namespace ChurchCRM\Service;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\User;
use Exception;

/**
 * AuthService
 *
 * Centralized authentication and authorization service for ChurchCRM.
 * Provides static methods for common authorization checks.
 */
class AuthService
{
    /**
     * Require user group membership - checks if current user belongs to specified group/role.
     *
     * @param string|array $groupName Single permission name or array of allowed permissions
     * @return void
     * @throws Exception if user is not authorized
     */
    public static function requireUserGroupMembership($groupName): void
    {
        if (!$groupName) {
            throw new Exception('Role(s) must be defined for the function which you are trying to access.  End users should never see this error unless something went horribly wrong.');
        }

        if (self::hasUserGroupMembership($groupName)) {
            return;
        }

        // User is not authorized
        throw new Exception('User is not authorized to access ' . debug_backtrace()[1]['function'], 401);
    }

    /**
     * The same decision as requireUserGroupMembership(), as a boolean.
     *
     * Added for the Volunteer v2 pool-group exception (D19): the Propel lifecycle
     * hooks on Group / Person2group2roleP2g2r must be able to ASK whether the V1
     * answer is already yes before they spend a query resolving a ministry, so the
     * V1 path stays exactly what it was — same flags, same admin bypass, same order
     * — and only a caller V1 would have refused pays for the second question.
     *
     * @param string|array $groupName Single permission name or array of allowed permissions
     */
    public static function hasUserGroupMembership($groupName): bool
    {
        if (!$groupName) {
            return false;
        }

        $currentUser = AuthenticationManager::getCurrentUser();
        $roles = is_array($groupName) ? $groupName : [$groupName];

        foreach ($roles as $role) {
            if (self::currentUserHasRole($currentUser, $role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * $_SESSION['bManageGroups'] / ['bFinance'] are only ever populated by
     * LocalAuthentication at browser login (see LocalAuthentication::authenticate()).
     * API-key callers (APITokenAuthentication) never populate them, so checking
     * $_SESSION alone denies every non-admin API-key user regardless of their
     * actual permissions (issue #9830). Resolve known role names against the
     * live permission state on the authenticated user instead; fall back to the
     * legacy $_SESSION flag for any role name not in the map below.
     */
    private static function currentUserHasRole(User $currentUser, string $role): bool
    {
        $liveCheck = match ($role) {
            'bManageGroups' => $currentUser->isManageGroupsEnabled(),
            'bFinance' => $currentUser->isFinanceEnabled(),
            'bAdmin' => $currentUser->isAdmin(),
            default => false,
        };

        return $liveCheck || ($_SESSION[$role] ?? false) || $currentUser->isAdmin();
    }
}
