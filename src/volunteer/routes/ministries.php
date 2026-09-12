<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\VolunteerMinistry;
use ChurchCRM\model\ChurchCRM\VolunteerMinistryQuery;
use ChurchCRM\model\ChurchCRM\VolunteerTeam;
use ChurchCRM\model\ChurchCRM\VolunteerTeamQuery;
use ChurchCRM\Service\VolunteerAuthorizationService;
use ChurchCRM\Service\VolunteerSetupService;
use ChurchCRM\Slim\Middleware\Request\Auth\VolunteerCoordinatorRoleAuthMiddleware;
use ChurchCRM\view\PageHeader;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

/**
 * "My ministries and teams" — the module's index (#9711).
 *
 * §5.1 names a Ministry *detail* screen but no list, and the Volunteer menu needs a
 * third child that lands somewhere. More importantly it closes a hole in the design
 * that #9715's notes recorded: a **pure team leader** — someone holding only a
 * `team` scope — passes `VolunteerCoordinatorRoleAuthMiddleware` and then has nowhere
 * to go, because `GET /api/volunteer/ministries` and `/volunteer/ministries/{id}` are
 * both scoped to `getManagedMinistryIds()`, which is empty for them.
 *
 * So this page lists two things, exactly within §4.6:
 *
 *   - the ministries the caller **coordinates**, each linking to S3;
 *   - the teams they **lead**, with their parent ministry named but NOT linked — a
 *     team leader may manage that team's pools, positions, qualifications, schedules
 *     and assignments, but not the ministry, and offering a link to a page the server
 *     would refuse is worse than offering none.
 *
 * Rendered server-side from two scoped queries. There is nothing to poll and nothing
 * that changes while you look at it, so it carries no loading state — only the empty
 * state §5.8 requires, which is the state that actually happens here.
 *
 * Route paths are module-relative: setBasePath() already carries '/volunteer'.
 */
$app->group('', function (RouteCollectorProxy $group): void {
    // GET /volunteer/ministries
    //
    // Declared BEFORE the parameterised `/ministries/{ministryId}` route in
    // ministry.php would be a concern in a single file; across files Slim matches the
    // literal path first because `{ministryId:[0-9]+}` cannot match an empty segment.
    $group->get('/ministries', function (Request $request, Response $response): Response {
        $currentUser = AuthenticationManager::getCurrentUser();
        $authz = new VolunteerAuthorizationService();
        $setup = new VolunteerSetupService($authz);

        // Ministries the caller coordinates. Scoped in the query (§4.4) by the service
        // that already owns that rule — this route re-derives nothing.
        $managed = $setup->listMinistriesFor($currentUser);
        $managedIds = array_map(static fn (VolunteerMinistry $m): int => (int) $m->getId(), $managed);

        // Teams the caller may act on: own team scopes ∪ every team under a managed
        // ministry (§4.4). For a ministry coordinator this is their own teams, already
        // reachable from S3; for a team leader it is the whole of their authority.
        //
        // A global manager and an administrator hold no explicit grants at all, so
        // `getManagedTeamIds()` is empty for them — it lists grants, not authority.
        // Telling the one tier that can see every team that it has none would be a
        // plain lie, so for them the list is every team of every ministry.
        if ($authz->isGlobalManager($currentUser)) {
            $teams = iterator_to_array(VolunteerTeamQuery::create()->orderByName()->find(), false);
        } else {
            $teamIds = $authz->getManagedTeamIds($currentUser);
            $teams = $teamIds === []
                ? []
                : iterator_to_array(
                    VolunteerTeamQuery::create()->filterById($teamIds, Criteria::IN)->orderByName()->find(),
                    false
                );
        }

        // Parent ministries of those teams that the caller does NOT coordinate — named
        // so a team leader can see where their team sits, never linked.
        $parentIds = array_values(array_diff(
            array_unique(array_map(static fn (VolunteerTeam $t): int => (int) $t->getMinistryId(), $teams)),
            $managedIds
        ));
        $parentNames = [];
        if ($parentIds !== []) {
            foreach (VolunteerMinistryQuery::create()->filterById($parentIds, Criteria::IN)->find() as $ministry) {
                $parentNames[(int) $ministry->getId()] = $ministry->getName();
            }
        }
        foreach ($managed as $ministry) {
            $parentNames[(int) $ministry->getId()] = $ministry->getName();
        }

        $teamCounts = $setup->countTeamsByMinistry($managedIds);
        $positionCounts = $setup->countPositionsByMinistry($managedIds);

        $aMinistries = [];
        foreach ($managed as $ministry) {
            $id = (int) $ministry->getId();
            $aMinistries[] = [
                'id' => $id,
                'name' => $ministry->getName(),
                'description' => $ministry->getDescription(),
                'active' => (bool) $ministry->getActive(),
                'teamCount' => $teamCounts[$id] ?? 0,
                'positionCount' => $positionCounts[$id] ?? 0,
            ];
        }

        $aTeams = [];
        foreach ($teams as $team) {
            $ministryId = (int) $team->getMinistryId();
            $aTeams[] = [
                'id' => (int) $team->getId(),
                'name' => $team->getName(),
                'description' => $team->getDescription(),
                'active' => (bool) $team->getActive(),
                'ministryId' => $ministryId,
                'ministryName' => $parentNames[$ministryId] ?? null,
                // Only a link the server would honour is offered (§4.6).
                'ministryManageable' => in_array($ministryId, $managedIds, true),
            ];
        }

        $renderer = new PhpRenderer(__DIR__ . '/../views/');

        return $renderer->render($response, 'ministries.php', [
            'sRootPath'     => SystemURLs::getRootPath(),
            'sPageTitle'    => gettext('My ministries and teams'),
            'sPageSubtitle' => gettext('Everything you help run'),
            'aBreadcrumbs'  => PageHeader::breadcrumbs([
                [gettext('Volunteer'), 'volunteer/dashboard'],
                [gettext('Ministries')],
            ]),
            'aMinistries'   => $aMinistries,
            'aTeams'        => $aTeams,
            'bIsManager'    => $authz->isGlobalManager($currentUser),
        ]);
    });
})->add(VolunteerCoordinatorRoleAuthMiddleware::class);
