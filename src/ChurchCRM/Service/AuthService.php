<?php

namespace ChurchCRM\Service;

use ChurchCRM\Authentication\AuthenticationManager;
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

        $currentUser = AuthenticationManager::getCurrentUser();

        // Check single permission or if user is admin
        if (is_string($groupName)) {
            if (($_SESSION[$groupName] ?? null) || ($currentUser && $currentUser->isAdmin())) {
                return;
            }
        }

        // Check array of permissions
        if (is_array($groupName)) {
            foreach ($groupName as $role) {
                if (($_SESSION[$role] ?? null) || ($currentUser && $currentUser->isAdmin())) {
                    return;
                }
            }
        }

        // User is not authorized
        throw new Exception('User is not authorized to access ' . debug_backtrace()[1]['function'], 401);
    }
}
