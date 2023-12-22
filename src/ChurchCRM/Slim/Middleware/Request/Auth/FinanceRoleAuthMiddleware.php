<?php

namespace ChurchCRM\Slim\Middleware\Request\Auth;

class FinanceRoleAuthMiddleware extends BaseAuthRoleMiddleware
{
    protected function hasRole(): bool
    {
        return $this->user->isFinanceEnabled();
    }

    protected function noRoleMessage(): string
    {
        return gettext('User must have Finance permission');
    }
}
