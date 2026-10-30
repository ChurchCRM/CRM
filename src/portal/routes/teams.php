<?php

use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\VolunteerMinistryQuery;
use ChurchCRM\model\ChurchCRM\VolunteerOccurrenceQuery;
use ChurchCRM\model\ChurchCRM\VolunteerScheduleQuery;
use ChurchCRM\model\ChurchCRM\VolunteerTeamQuery;
use ChurchCRM\Portal\Auth\PortalContext;
use ChurchCRM\Portal\Auth\PortalMemberUser;
use ChurchCRM\Portal\Permissions\CorePortalCapabilities;
use ChurchCRM\Portal\Permissions\PortalAuthorizationService;
use ChurchCRM\Portal\Permissions\RequirePortalCapability;
use ChurchCRM\Portal\PortalNav;
use ChurchCRM\Portal\PortalTeams;
use ChurchCRM\Portal\PortalTwig;
use ChurchCRM\Volunteer\Service\VolunteerEventService;
use ChurchCRM\Volunteer\Service\VolunteerScheduleService;
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
 *   GET /portal/teams                                    the teams this person runs
 *   GET /portal/teams/{teamId}                           one team: Positions ·
 *                                                        Volunteers · Schedules ·
 *                                                        Occurrences
 *   GET /portal/teams/{teamId}/occurrences/{occurrenceId} staffing for one date
 *
 * Every page needs `volunteer.team.manage` (member sign-in design §8.4). The list
 * is exactly the teams `scopesFor()` returns, and a team page answers 404 for a
 * team that is not the person's, whether or not it exists. A STAFF principal gets
 * the same answer as a member: a coordinator or an administrator runs teams from
 * the ministry pages, and leads in the portal only the teams they lead.
 *
 * The team and occurrence pages are container markup plus a page config; every
 * fact on them comes from `/api/ministries/*`, where the same scope rules are
 * applied again per record. The index is server-rendered (`PortalTeams`).
 */
$group->group('/teams', function (RouteCollectorProxy $teams): void {
    // ── GET /portal/teams ───────────────────────────────────────────────────
    $teams->get('', function (Request $request, Response $response): Response {
        $rows = PortalTeams::listForTeamIds(portalTeamIds());

        if ($rows === []) {
            throw new HttpForbiddenException($request, gettext('You do not lead a volunteer team yet.'));
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
    })->add(RequirePortalCapability::any(
        CorePortalCapabilities::TEAM_MANAGE,
        gettext('You do not lead a volunteer team yet.')
    ));

    // ── GET /portal/teams/{teamId} ──────────────────────────────────────────
    $teams->get('/{teamId:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        $team = VolunteerTeamQuery::create()->findPk((int) $args['teamId']);
        if ($team === null) {
            throw new HttpNotFoundException($request, gettext('Team not found'));
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
                    // D23: read-only here — linking is the ministry coordinator's.
                    'classGroupName' => (string) ($team->getClassGroup()?->getName() ?? ''),
                    // D29: whether the schedule dialog may offer "A class's meetings".
                    'sundaySchool' => $ministry !== null && (bool) $ministry->getSundaySchool(),
                ],
            ],
            PortalNav::TEAMS
        );
    })->add(RequirePortalCapability::onRouteArgument(CorePortalCapabilities::TEAM_MANAGE, 'teamId'));

    // ── GET /portal/teams/{teamId}/occurrences/{occurrenceId} ───────────────
    //
    // The occurrence must belong to a schedule of the team in the path, and that
    // team must be the person's: a leader of team A cannot reach team B's date
    // through a URL that names team A.
    $teams->get(
        '/{teamId:[0-9]+}/occurrences/{occurrenceId:[0-9]+}',
        function (Request $request, Response $response, array $args): Response {
            $teamId = (int) $args['teamId'];
            $occurrenceId = (int) $args['occurrenceId'];

            $team = VolunteerTeamQuery::create()->findPk($teamId);
            $occurrence = VolunteerOccurrenceQuery::create()->findPk($occurrenceId);
            $schedule = $occurrence === null ? null : VolunteerScheduleQuery::create()->findPk((int) $occurrence->getScheduleId());
            if ($team === null || $schedule === null || (int) $schedule->getTeamId() !== $teamId) {
                throw new HttpNotFoundException($request, gettext('Occurrence not found'));
            }

            $ministry = VolunteerMinistryQuery::create()->findPk((int) $schedule->getMinistryId());

            // D20/D21 made visible: the anchored event's time moved by the schedule's
            // offsets, through the one method allowed to decide an occurrence's window.
            $schedules = new VolunteerScheduleService();
            $window = $schedules->resolveOccurrenceWindow($occurrence);

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
                        'offsetNote' => $schedules->offsetSummary($schedule),
                    ],
                    // D26: read-only; the edit link only for a login that can open the admin editor.
                    'headcount' => $linkedEvent === null ? null : (new VolunteerEventService())->headcount($linkedEvent, portalTeamViewer()),
                ],
                PortalNav::TEAMS
            );
        }
    )->add(RequirePortalCapability::onRouteArgument(CorePortalCapabilities::TEAM_MANAGE, 'teamId'));
});

/**
 * @return int[] the teams the portal session's person may manage
 */
function portalTeamIds(): array
{
    $principal = PortalContext::principal();

    return $principal === null
        ? []
        : (new PortalAuthorizationService())->scopeIdsForPerson($principal->personId, CorePortalCapabilities::TEAM_MANAGE);
}

/**
 * Who reads an occurrence's headcount card: the staff account behind a STAFF
 * principal, which may be offered the admin event editor, else the member.
 */
function portalTeamViewer(): User
{
    return PortalContext::staffUser() ?? PortalMemberUser::forPerson((int) PortalContext::principal()?->personId);
}
