<?php

namespace ChurchCRM\Slim\Middleware\Request\Auth;

class EmailRoleAuthMiddleware extends BaseAuthRoleMiddleware
{
    protected function hasRole(): bool
    {
        return $this->user->isEmailEnabled();
    }

    protected function noRoleMessage(): string
    {
        return gettext('User must have Email permission');
    }

    protected function getRoleName(): string
    {
        return 'Email';
    }
}
