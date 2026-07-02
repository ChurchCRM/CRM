<?php

namespace ChurchCRM\Slim\Middleware\Request\Auth;

class ManageFundraisersRoleAuthMiddleware extends BaseAuthRoleMiddleware
{
    protected function hasRole(): bool
    {
        // Admins have access to all fundraiser features, plus users with ManageFundraisers permission
        return $this->user->isAdmin() || $this->user->isManageFundraisersEnabled();
    }

    protected function noRoleMessage(): string
    {
        return gettext('User must be an Admin or have Manage Fundraisers permission');
    }

    protected function getRoleName(): string
    {
        return 'ManageFundraisers';
    }
}
