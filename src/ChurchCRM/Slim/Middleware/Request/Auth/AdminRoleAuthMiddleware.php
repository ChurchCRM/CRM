<?php

namespace ChurchCRM\Slim\Middleware\Request\Auth;

class AdminRoleAuthMiddleware extends BaseAuthRoleMiddleware
{
    protected function hasRole(): bool
    {
        return $this->user->isAdmin();
    }

    protected function noRoleMessage(): string
    {
        return gettext('User must be an Admin');
    }

    protected function getRoleName(): string
    {
        return 'Admin';
    }
}
