<?php

namespace ChurchCRM\Slim\Middleware\Request\Auth;

class AddEventsRoleAuthMiddleware extends BaseAuthRoleMiddleware
{
    protected function hasRole(): bool
    {
        return $this->user->isAddEvent();
    }

    protected function noRoleMessage(): string
    {
        return gettext('User must have bAddEvent permissions');
    }
}
