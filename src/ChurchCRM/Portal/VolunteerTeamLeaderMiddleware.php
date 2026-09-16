<?php

namespace ChurchCRM\Portal;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\User;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpNotFoundException;

/**
 * The coarse gate on the Member Portal's My Teams pages (MP7, #9868).
 *
 * Deliberately thin, and deliberately NOT a `BaseAuthRoleMiddleware`: that base
 * class answers a browser it turns away by redirecting to `/v2/access-denied`,
 * which is a page in the admin shell — the one thing a member must never be shown
 * (Member Portal design P10). Throwing `HttpForbiddenException` instead hands the
 * refusal to `PortalTwig::createErrorHandler()`, which draws the portal's own 403
 * page in the member's theme.
 *
 * It answers one question — "does this login have team authority at all" — and it
 * is the union of two readings, because both kinds of person open these pages:
 *
 *  - `isVolunteerTeamLeaderEnabled()` — an explicit `team` grant, which is what a
 *    member-login team leader has (Member Portal P17, volunteer design §4.4). The
 *    same predicate the nav entry is drawn from, so the menu and the route can
 *    never disagree.
 *  - `isVolunteerCoordinatorEnabled()` — a ministry coordinator, a global manager
 *    or an administrator, all of whom may manage every team under them and any of
 *    whom may open the portal as themselves.
 *
 * WHICH team is a per-record question this class must never be asked; each route
 * asks `VolunteerAuthorizationService::canManageTeam()` itself (§4.5, layer three).
 *
 * The rollout flag comes first: with V2 off there are no teams, so the pages do
 * not exist rather than being forbidden — the same 404 the portal's volunteering
 * pages give in that state (#9867).
 */
class VolunteerTeamLeaderMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!PortalNav::isVolunteeringVisible()) {
            throw new HttpNotFoundException($request);
        }

        $user = AuthenticationManager::getCurrentUser();
        if (!$user instanceof User) {
            throw new HttpForbiddenException($request, gettext('No logged in user'));
        }

        if (!$user->isVolunteerTeamLeaderEnabled() && !$user->isVolunteerCoordinatorEnabled()) {
            throw new HttpForbiddenException(
                $request,
                gettext('This part of the Member Portal is for volunteer team leaders.')
            );
        }

        return $handler->handle($request);
    }
}
