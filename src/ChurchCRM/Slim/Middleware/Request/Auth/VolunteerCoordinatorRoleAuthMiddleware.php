<?php

namespace ChurchCRM\Slim\Middleware\Request\Auth;

/**
 * Volunteer Management v2 (#9706): the coordinator-area gate.
 *
 * Guards the `/volunteer` coordinator MVC routes and the coordinator half of
 * `/api/volunteer` (design §3.2). It answers only "does this user have volunteer
 * coordination authority at all" — an administrator, a global volunteer manager, a
 * ministry coordinator or a team leader. Which ministry or team is decided per record
 * by the entity middlewares (§4.5); this class must never be asked.
 *
 * The predicate lives on the User model so that Menu::buildMenuItems() can mirror this
 * gate exactly with the same call, and so the scope query is memoised for the request
 * (§3.5, A11: menu visibility must mirror the route middleware).
 */
class VolunteerCoordinatorRoleAuthMiddleware extends BaseAuthRoleMiddleware
{
    protected function hasRole(): bool
    {
        return $this->user->isVolunteerCoordinatorEnabled();
    }

    protected function noRoleMessage(): string
    {
        return gettext('Volunteer coordinator access is required');
    }

    protected function getRoleName(): string
    {
        // Must stay in the allow-list at src/v2/routes/root.php or the access-denied
        // page renders no reason at all (design A10).
        return 'VolunteerCoordinator';
    }
}
