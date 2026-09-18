<?php

namespace ChurchCRM\Volunteer\Middleware;

use ChurchCRM\Slim\Middleware\Request\Auth\BaseAuthRoleMiddleware;

/**
 * Volunteer Management v2 (#9706): the coordinator-area gate.
 *
 * Guards the `/volunteer` coordinator MVC routes and the coordinator half of
 * `/api/ministries` (design §3.2). It answers only "does this user have volunteer
 * coordination authority at all" — an administrator, a global volunteer manager, a
 * ministry coordinator or a team leader. Which ministry or team is decided per record
 * by the entity middlewares (§4.5); this class must never be asked.
 *
 * The predicate lives on the User model so that Menu::buildMenuItems() can mirror this
 * gate exactly with the same call, and so the scope query is memoised for the request
 * (§3.5, A11: menu visibility must mirror the route middleware).
 *
 * **The team-leader clause (#9868).** `isVolunteerCoordinatorEnabled()` short-circuits
 * to false for an EditSelf-exclusive login, on purpose: the admin dashboard and the
 * sidebar's Ministries heading must stay shut to a self-service account, and
 * `Menu::buildMenuItems()` mirrors that call. But the Member Portal's revision of D14
 * (P17, #9867) says a team leader MAY hold an ordinary member login and must be able
 * to run their team — from the portal (MP7), through this very API. So the second
 * clause restores what this class's own first paragraph always claimed: a team leader
 * has volunteer coordination authority, whatever kind of login they hold.
 *
 * Nothing about a STAFF login changes: a staff team leader holds a scope, so
 * `hasAnyScope()` already made `isVolunteerCoordinatorEnabled()` true for them.
 * And a self-service team leader still cannot open the `/volunteer` MVC area:
 * `AuthMiddleware` confines an EditSelf-exclusive browser session to `/portal`
 * and the paths `isLimitedAccessAllowedPath()` names, and `/volunteer` is not one
 * of them. This widens the API, not the admin shell.
 */
class VolunteerCoordinatorRoleAuthMiddleware extends BaseAuthRoleMiddleware
{
    protected function hasRole(): bool
    {
        return $this->user->isVolunteerCoordinatorEnabled() || $this->user->isVolunteerTeamLeaderEnabled();
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
