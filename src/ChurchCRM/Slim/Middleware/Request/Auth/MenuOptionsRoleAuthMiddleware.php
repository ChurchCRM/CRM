<?php

namespace ChurchCRM\Slim\Middleware\Request\Auth;

class MenuOptionsRoleAuthMiddleware extends BaseAuthRoleMiddleware
{
    protected function hasRole(): bool
    {
        return $this->user->isMenuOptionsEnabled();
    }

    protected function noRoleMessage(): string
    {
        return gettext('User must have Menu Options permission');
    }

    protected function getRoleName(): string
    {
        return 'MenuOptions';
    }
}
