<?php

namespace ChurchCRM\Slim\Middleware\Request\Auth;

class AddEventsRoleAuthMiddleware extends BaseAuthRoleMiddleware
{
    protected function hasRole(): bool
    {
        // Events module must be enabled AND user must have AddEvent permission.
        // Defense in depth — even if a user has the permission, disabling
        // the events module system-wide should block all write operations.
        return $this->user->canManageEvents();
    }

    protected function noRoleMessage(): string
    {
        return gettext('User must have Add Event permission and the Events module must be enabled');
    }

    protected function getRoleName(): string
    {
        return 'AddEvent';
    }
}
