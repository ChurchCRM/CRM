<?php

namespace ChurchCRM\Slim\Middleware\Request\Auth;

/**
 * Gates view-only event routes (e.g. /event/dashboard, /event/calendars,
 * /event/checkin) to ensure the Events module is enabled system-wide.
 *
 * Does NOT require the AddEvent permission — anyone with login can view
 * events when the module is enabled. Use AddEventsRoleAuthMiddleware
 * for routes that create/edit/delete events.
 */
class ViewEventsRoleAuthMiddleware extends BaseAuthRoleMiddleware
{
    protected function hasRole(): bool
    {
        return $this->user->canViewEvents();
    }

    protected function noRoleMessage(): string
    {
        return gettext('The Events module is disabled');
    }

    protected function getRoleName(): string
    {
        return 'ViewEvents';
    }
}
