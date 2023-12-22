<?php

namespace ChurchCRM\Slim\Middleware\Request\Auth;

class ManageGroupRoleAuthMiddleware extends BaseAuthRoleMiddleware
{
    protected function hasRole(): bool
    {
        return $this->user->isManageGroupsEnabled();
    }

    protected function noRoleMessage(): string
    {
        return gettext('User must have Manage Groups permission');
    }
}
