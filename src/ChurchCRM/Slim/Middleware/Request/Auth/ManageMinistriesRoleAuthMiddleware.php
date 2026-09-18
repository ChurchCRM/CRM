<?php

namespace ChurchCRM\Slim\Middleware\Request\Auth;

/**
 * Volunteer Management v2 (#9706): the global-manager gate.
 *
 * Guards the routes that create or destroy volunteer structure across the whole
 * installation — today that is all of `/api/ministries/scopes`, i.e. deciding who
 * coordinates what (design §3.2, §4.6). Later issues add ministry create/delete.
 *
 * Coarse only. It runs before route arguments become domain objects, so it can never
 * answer "may this user touch ministry 7" — that is the entity middlewares' job
 * (AbstractEntityMiddleware::postEntityLoad(), §4.5).
 *
 * User::isManageMinistriesEnabled() already carries the administrator bypass, the
 * EditSelf-exclusive short-circuit and the `sVolunteerVersion` rollout gate, so this
 * class deliberately adds nothing to it.
 */
class ManageMinistriesRoleAuthMiddleware extends BaseAuthRoleMiddleware
{
    protected function hasRole(): bool
    {
        return $this->user->isManageMinistriesEnabled();
    }

    protected function noRoleMessage(): string
    {
        return gettext('Ministry management access is required');
    }

    protected function getRoleName(): string
    {
        // Must stay in the allow-list at src/v2/routes/root.php or the access-denied
        // page renders no reason at all (design A10).
        return 'ManageMinistries';
    }
}
