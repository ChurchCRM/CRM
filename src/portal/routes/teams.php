<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\model\ChurchCRM\VolunteerMinistryQuery;
use ChurchCRM\model\ChurchCRM\VolunteerOccurrenceQuery;
use ChurchCRM\model\ChurchCRM\VolunteerScheduleQuery;
use ChurchCRM\model\ChurchCRM\VolunteerTeamQuery;
use ChurchCRM\Portal\PortalNav;
use ChurchCRM\Portal\PortalTeams;
use ChurchCRM\Portal\PortalTwig;
use ChurchCRM\Portal\VolunteerTeamLeaderMiddleware;
use ChurchCRM\Service\VolunteerAuthorizationService;
use ChurchCRM\Service\VolunteerScheduleService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpNotFoundException;
use Slim\Routing\RouteCollectorProxy;

/**
 * My Teams — team-scoped ministry management inside the portal (MP7, #9868;
 * Member Portal design §5.5 / P17, Volunteer v2 design §4.4 and §4.6).
 *
 * Three pages:
 *
 *   GET /portal/teams                                    the teams this login runs
 *   GET /portal/teams/{teamId}                           one team: Positions ·
 *                                                        Volunteers · Schedules ·
 *                                                        Occurrences
 *   GET /portal/teams/{teamId}/occurrences/{occurrenceId} staffing for one date
 *
 * **Two layers of gate, and they are not the same question** (volunteer design
 * §4.5). `VolunteerTeamLeaderMiddleware` on the group answers the coarse one —
 * "does this login have team authority at all" — and each route below answers the
 * per-record one with `VolunteerAuthorizationService::canManageTeam()`, which is
 * the only thing that knows whether THIS team is theirs. A refusal is an
 * `HttpForbiddenException`, so `PortalTwig::createErrorHandler()` draws the
 * portal's own 403 page in the member's theme; a member must never be redirected
 * into the admin shell's access-denied page (design P10).
 *
 * 404 before 403, as every entity middleware in the codebase does it: a team that
 * does not exist is "not found" whether or not the reader could have run it.
 *
 * The team and occurrence pages are container markup plus a page config; every
 * fact on them comes from `/api/volunteer/*`, where the SAME scope rules are
 * applied again per record. The index is server-rendered because it is a short
 * list of plain reads (`PortalTeams`).
 */
$group->group('/teams', function (RouteCollectorProxy $teams): void {
    // ── GET /portal/teams ───────────────────────────────────────────────────
    //
    // A ministry coordinator or an administrator passes the group gate and sees
    // every team under them, which is correct — they may run all of them. Somebody
    // who reaches this with no team at all is shown the portal's 403 page rather
    // than an empty list: the page has nothing to offer them and saying so plainly
    // is better than a blank card.
    $teams->get('', function (Request $request, Response $response): Response {
        $rows = PortalTeams::listForUser(AuthenticationManager::getCurrentUser());

        if ($rows === []) {
            throw new HttpForbiddenException(
                $request,
                gettext('You do not lead a volunteer team yet.')
            );
        }

        return PortalTwig::render(
            $response,
            'teams/index.html.twig',
            [
                'pageTitle' => gettext('My Teams'),
                'teams' => $rows,
            ],
            PortalNav::TEAMS
        );
    });

    // ── GET /portal/teams/{teamId} ──────────────────────────────────────────
    $teams->get('/{teamId:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        $teamId = (int) $args['teamId'];
        $team = PortalTeams::findManagedTeam(AuthenticationManager::getCurrentUser(), $teamId);

        if ($team === null) {
            return portalTeamRefusal($request, $teamId);
        }

        $ministry = VolunteerMinistryQuery::create()->findPk((int) $team->getMinistryId());

        return PortalTwig::render(
            $response,
            'teams/team.html.twig',
            [
                'pageTitle' => (string) $team->getName(),
                'team' => [
                    'id' => (int) $team->getId(),
                    'name' => (string) $team->getName(),
                    'description' => (string) ($team->getDescription() ?? ''),
                    'active' => (bool) $team->getActive(),
                    'ministryId' => (int) $team->getMinistryId(),
                    'ministryName' => $ministry === null ? '' : (string) $ministry->getName(),
                ],
            ],
            PortalNav::TEAMS
        );
    });

    // ── GET /portal/teams/{teamId}/occurrences/{occurrenceId} ───────────────
    //
    // The portal's twin of `/volunteer/occurrences/{id}`, with the same
    // per-occurrence authorization: the occurrence must belong to a schedule of
    // the team in the path, and that team must be one this login runs. Checking
    // the path team as well as the occurrence's own is what stops a leader of team
    // A reaching team B's occurrence through a URL that names team A.
    $teams->get(
        '/{teamId:[0-9]+}/occurrences/{occurrenceId:[0-9]+}',
        function (Request $request, Response $response, array $args): Response {
            $teamId = (int) $args['teamId'];
            $occurrenceId = (int) $args['occurrenceId'];
            $user = AuthenticationManager::getCurrentUser();

            $team = PortalTeams::findManagedTeam($user, $teamId);
            if ($team === null) {
                return portalTeamRefusal($request, $teamId);
            }

            $occurrence = VolunteerOccurrenceQuery::create()->findPk($occurrenceId);
            if ($occurrence === null) {
                throw new HttpNotFoundException($request, gettext('Occurrence not found'));
            }

            $schedule = VolunteerScheduleQuery::create()->findPk((int) $occurrence->getScheduleId());
            if ($schedule === null || (int) $schedule->getTeamId() !== $teamId) {
                // Not this team's date. "Not found" rather than "forbidden": under
                // this URL it genuinely is not there, and the reader learns nothing
                // about another team's schedule either way.
                throw new HttpNotFoundException($request, gettext('Occurrence not found'));
            }

            // Belt and braces, and the line that matters if the two checks above
            // ever drift: the occurrence itself has to be in scope.
            if (!(new VolunteerAuthorizationService())->canManageOccurrence($user, $occurrence)) {
                throw new HttpForbiddenException($request, gettext('This is not one of your team\'s dates.'));
            }

            $ministry = VolunteerMinistryQuery::create()->findPk((int) $schedule->getMinistryId());

            // D4 made visible: for a linked occurrence this reads the event row, so
            // the page shows the event's time and says where it came from. The one
            // method allowed to decide an occurrence's window.
            $window = (new VolunteerScheduleService())->resolveOccurrenceWindow($occurrence);

            $linkedEvent = $occurrence->getEventId() === null
                ? null
                : EventQuery::create()->findPk((int) $occurrence->getEventId());
            $eventLocation = $linkedEvent === null ? null : $linkedEvent->getLocation();

            return PortalTwig::render(
                $response,
                'teams/occurrence.html.twig',
                [
                    'pageTitle' => (string) $schedule->getName(),
                    'team' => [
                        'id' => $teamId,
                        'name' => (string) $team->getName(),
                        'ministryId' => (int) $schedule->getMinistryId(),
                        'ministryName' => $ministry === null ? '' : (string) $ministry->getName(),
                    ],
                    'occurrence' => [
                        'id' => $occurrenceId,
                        'scheduleName' => (string) $schedule->getName(),
                        'status' => (string) $occurrence->getStatus(),
                        'date' => (string) $occurrence->getOccurrenceDate('Y-m-d'),
                        'start' => $window['start'] === null ? '' : $window['start']->format('Y-m-d H:i'),
                        'end' => $window['end'] === null ? '' : $window['end']->format('H:i'),
                        'eventId' => $occurrence->getEventId() === null ? 0 : (int) $occurrence->getEventId(),
                        'eventTitle' => $linkedEvent === null ? '' : (string) $linkedEvent->getTitle(),
                        'eventLocation' => $eventLocation === null ? '' : (string) $eventLocation->getLocationName(),
                    ],
                ],
                PortalNav::TEAMS
            );
        }
    );
})->add(new VolunteerTeamLeaderMiddleware());

/**
 * 404 for a team that is not there, 403 for one that is but is not theirs.
 *
 * Both answers are drawn by the portal's own error templates. It never returns —
 * the return type only exists so the callers above read as ordinary handlers.
 *
 * @throws HttpNotFoundException|HttpForbiddenException
 */
function portalTeamRefusal(Request $request, int $teamId): Response
{
    if (VolunteerTeamQuery::create()->findPk($teamId) === null) {
        throw new HttpNotFoundException($request, gettext('Team not found'));
    }

    throw new HttpForbiddenException($request, gettext('This is not one of your teams.'));
}
