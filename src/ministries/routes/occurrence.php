<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\model\ChurchCRM\VolunteerMinistryQuery;
use ChurchCRM\model\ChurchCRM\VolunteerOccurrenceQuery;
use ChurchCRM\model\ChurchCRM\VolunteerScheduleQuery;
use ChurchCRM\model\ChurchCRM\VolunteerTeamQuery;
use ChurchCRM\Volunteer\Service\VolunteerAuthorizationService;
use ChurchCRM\Volunteer\Service\VolunteerEventService;
use ChurchCRM\Volunteer\Service\VolunteerScheduleService;
use ChurchCRM\Volunteer\Middleware\VolunteerCoordinatorRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

/**
 * S4 — the occurrence / staffing view (#9709, design §5.5).
 *
 * The single most important coordinator screen: who is needed, who is on, who has
 * answered, and what is still short. Everything it shows comes from
 * `GET /api/ministries/occurrences/{id}/staffing`; this route renders markup and the page
 * config, and runs no query of its own beyond the header (groups-mvc-guidelines.md).
 *
 * The header is resolved server-side rather than waiting on the API because it is what
 * makes D4 visible: the date and time of a LINKED occurrence come from the
 * `events_event` row through `VolunteerScheduleService::resolveOccurrenceWindow()` — the
 * one method allowed to decide them — and the page says so, with a link to the event.
 *
 * The role middleware answers "does this person coordinate anything". **Which**
 * occurrence they may open is a per-record question it cannot answer, so the handler
 * asks `VolunteerAuthorizationService` directly (design §4.5, layer three). An occurrence
 * that does not exist is a 404; one outside the caller's scope goes to the access-denied
 * page, the same place `BaseAuthRoleMiddleware` sends a browser it turns away.
 *
 * Route paths are module-relative: `setBasePath()` already carries '/ministries'.
 */
$app->group('', function (RouteCollectorProxy $group): void {
    // GET /ministries/occurrences/{occurrenceId}
    $group->get('/occurrences/{occurrenceId:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        $occurrenceId = (int) $args['occurrenceId'];
        $occurrence = VolunteerOccurrenceQuery::create()->findPk($occurrenceId);

        if ($occurrence === null) {
            throw new HttpNotFoundException($request, gettext('Occurrence not found'));
        }

        $currentUser = AuthenticationManager::getCurrentUser();
        $authz = new VolunteerAuthorizationService();

        if (!$authz->canManageOccurrence($currentUser, $occurrence)) {
            return SlimUtils::renderRedirect(
                $response,
                SystemURLs::getRootPath() . '/v2/access-denied?role=VolunteerCoordinator'
            );
        }

        $schedule = VolunteerScheduleQuery::create()->findPk((int) $occurrence->getScheduleId());
        $ministry = $schedule === null
            ? null
            : VolunteerMinistryQuery::create()->findPk((int) $schedule->getMinistryId());
        $team = $schedule !== null && $schedule->getTeamId() !== null
            ? VolunteerTeamQuery::create()->findPk((int) $schedule->getTeamId())
            : null;

        // D20/D21 made visible: the time is the anchored event's, moved by the schedule's
        // offsets, and the page says where it came from.
        $schedules = new VolunteerScheduleService();
        $window = $schedules->resolveOccurrenceWindow($occurrence);

        // #9713: name the event rather than just linking to it, and show where it happens —
        // "Times come from this event" on its own does not tell a coordinator WHICH event,
        // and several occurrences of different ministries may share one (UC3). Read-only;
        // the event row is the source of truth for both (D4).
        $linkedEvent = $occurrence->getEventId() === null
            ? null
            : EventQuery::create()->findPk((int) $occurrence->getEventId());
        $eventLocation = $linkedEvent === null ? null : $linkedEvent->getLocation();

        $ministryName = $ministry === null ? gettext('Ministry') : $ministry->getName();
        $scheduleName = $schedule === null ? gettext('Schedule') : $schedule->getName();

        $query = $request->getQueryParams();
        $back = ['tab' => 'occurrences'];
        $backTeamId = InputUtils::filterInt($query['team'] ?? 0);
        if ($backTeamId > 0) {
            $back['team'] = $backTeamId;
        }
        $text = trim(mb_substr((string) ($query['q'] ?? ''), 0, 100));
        if ($text !== '') {
            $back['q'] = $text;
        }
        foreach (['from', 'to'] as $key) {
            $date = (string) ($query[$key] ?? '');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
                $back[$key] = $date;
            }
        }
        $backUrl = $ministry === null
            ? '/ministries/dashboard'
            : '/ministries/' . (int) $ministry->getId() . '?' . http_build_query($back);

        $renderer = new PhpRenderer(__DIR__ . '/../views/');

        return $renderer->render($response, 'occurrence-view.php', [
            'sBackUrl' => SystemURLs::getRootPath() . $backUrl,
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => $scheduleName,
            'sPageSubtitle' => gettext('Who is needed, who is on, and what is still short'),
            'aBreadcrumbs' => PageHeader::breadcrumbs([
                [gettext('Ministries'), '/ministries/dashboard'],
                [$ministryName, $ministry === null ? null : '/ministries/' . (int) $ministry->getId()],
                [$scheduleName],
            ]),
            'iOccurrenceId' => $occurrenceId,
            'iMinistryId' => $ministry === null ? 0 : (int) $ministry->getId(),
            'sMinistryName' => $ministryName,
            'sTeamName' => $team === null ? '' : $team->getName(),
            'sScheduleName' => $scheduleName,
            'sOccurrenceStatus' => (string) $occurrence->getStatus(),
            'sOccurrenceDate' => (string) $occurrence->getOccurrenceDate('Y-m-d'),
            'sStart' => $window['start'] === null ? '' : $window['start']->format('Y-m-d H:i:s'),
            'sEnd' => $window['end'] === null ? '' : $window['end']->format('Y-m-d H:i:s'),
            'iEventId' => $occurrence->getEventId() === null ? 0 : (int) $occurrence->getEventId(),
            'sEventTitle' => $linkedEvent === null ? '' : (string) $linkedEvent->getTitle(),
            'sEventLocation' => $eventLocation === null ? '' : (string) $eventLocation->getLocationName(),
            'sOffsetNote' => $schedule === null ? '' : $schedules->offsetSummary($schedule),
            // D26: the anchored event's headcount, read-only; null once the event was deleted.
            'aHeadcount' => $linkedEvent === null ? null : (new VolunteerEventService($authz))->headcount($linkedEvent, $currentUser),
        ]);
    });
})->add(VolunteerCoordinatorRoleAuthMiddleware::class);
