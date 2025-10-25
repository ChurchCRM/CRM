<?php

namespace ChurchCRM\Service;

use ChurchCRM\Authentication\AuthenticationManager;

class AuthService
{
    /**
     * Verify that the current user belongs to a specific group.
     * Throws an exception if the user doesn't have the required permission.
     *
     * @param string $groupName The name of the group to check membership for
     * @throws \Exception If user is not authenticated or doesn't belong to the group
     */
    public static function requireUserGroupMembership(string $groupName): void
    {
        $currentUser = AuthenticationManager::getCurrentUser();
        
        if (!$currentUser) {
            throw new \Exception(gettext('User not authenticated'));
        }

        $groupProperty = 'get' . $groupName;
        
        if (!method_exists($currentUser, $groupProperty)) {
            throw new \Exception(gettext('Invalid group: ') . $groupName);
        }

        if (!$currentUser->$groupProperty()) {
            throw new \Exception(gettext('Access denied: User does not have permission to perform this action'));
        }
    }
}
