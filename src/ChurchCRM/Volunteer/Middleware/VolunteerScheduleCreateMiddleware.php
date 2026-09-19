<?php

namespace ChurchCRM\Volunteer\Middleware;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\VolunteerTeamQuery;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Volunteer\Service\VolunteerAuthorizationService;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The gate on `POST /api/ministries/ministries/{ministryId}/schedules` (#9868).
 *
 * `VolunteerMinistryMiddleware` — which this used to carry — asks "may you
 * administer this MINISTRY". That was too coarse the moment the volunteer design's
 * §4.6 row said a **team leader** may create, edit and generate schedules for
 * their own team: the route is keyed on the ministry because that is where a
 * schedule is created under, but the thing being created belongs to a TEAM, which
 * the payload names. The Member Portal's My Teams page (MP7, Member Portal design
 * P17) is what needs it.
 *
 * So the rule is the union of the two readings, in this order:
 *
 *   1. `canManageMinistry()` — unchanged. An administrator, a global manager and a
 *      ministry coordinator pass here exactly as before, whatever the payload says.
 *   2. Otherwise the payload's `teamId` is resolved and must be a team the caller
 *      leads **and** a team of the ministry in the path. Both halves matter: the
 *      first is the scope question, the second stops a team leader creating a
 *      schedule that names their own team under somebody else's ministry.
 *
 * This is advisory in exactly the sense §4.5 layer three describes — a middleware
 * cannot be the only answer, because the body it reads is the body the handler
 * will read again. `VolunteerScheduleService::createSchedule()` re-checks the
 * resolved team against the same service before it writes anything, so a payload
 * that changed shape between here and there cannot get through.
 *
 * Sibling routes are unchanged: editing, deleting and generating a schedule go
 * through `VolunteerScheduleMiddleware`, which has always been team-scoped because
 * a schedule row names its team (§4.4).
 */
class VolunteerScheduleCreateMiddleware extends VolunteerMinistryMiddleware
{
    protected function postEntityLoad(ServerRequestInterface $request, mixed $entity): ?ResponseInterface
    {
        $currentUser = AuthenticationManager::getCurrentUser();
        $authz = new VolunteerAuthorizationService();
        $ministryId = (int) $entity->getId();

        if ($authz->canManageMinistry($currentUser, $ministryId)) {
            return null;
        }

        $body = $request->getParsedBody();
        $teamId = is_array($body) && isset($body['teamId']) ? (int) $body['teamId'] : 0;

        if ($teamId > 0 && $authz->canManageTeam($currentUser, $teamId)) {
            $team = VolunteerTeamQuery::create()->findPk($teamId);
            if ($team !== null && (int) $team->getMinistryId() === $ministryId) {
                return null;
            }
        }

        return SlimUtils::renderErrorJSON(new Response(), gettext('Not authorized for this ministry'), [], 403);
    }
}
