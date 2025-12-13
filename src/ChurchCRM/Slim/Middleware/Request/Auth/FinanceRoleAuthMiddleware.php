<?php

namespace ChurchCRM\Slim\Middleware\Request\Auth;

class FinanceRoleAuthMiddleware extends BaseAuthRoleMiddleware
{
    protected function hasRole(): bool
    {
        // Admins have access to all finance features, plus users with finance permission
        return $this->user->isAdmin() || $this->user->isFinanceEnabled();
    }

    protected function noRoleMessage(): string
    {
        return gettext('User must be an Admin or have Finance permission');
    }

    protected function getRoleName(): string
    {
        return 'Finance';
    }
}
