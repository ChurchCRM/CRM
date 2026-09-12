<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\VolunteerMinistry;
use ChurchCRM\Service\VolunteerSetupService;
use ChurchCRM\Slim\Middleware\Request\Auth\VolunteerCoordinatorRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

// Route paths are module-relative: setBasePath() already carries '/volunteer'.
//
// The coordinator area is gated by VolunteerCoordinatorRoleAuthMiddleware (#9706):
// an administrator, a global volunteer manager, a ministry coordinator or a team
// leader. Which ministry or team a coordinator may actually touch is decided per
// record by the entity middlewares, never here (design §4.5).
//
// The Volunteer menu entry mirrors this gate exactly by calling the same predicate,
// User::isVolunteerCoordinatorEnabled(), so nothing is advertised that cannot be
// opened and nothing openable is hidden.
$app->group('', function (RouteCollectorProxy $group): void {
    // GET /volunteer/ — send the bare module URL to the dashboard.
    $group->get('/', fn (Request $request, Response $response): Response => SlimUtils::renderRedirect($response, SystemURLs::getRootPath() . '/volunteer/dashboard'));

    // GET /volunteer/dashboard — the coordinator's landing page.
    //
    // Still a placeholder in the sense #9711 means it: the real S1 dashboard
    // answers "what needs my attention" from gaps, pending responses and swaps,
    // none of which exist yet. What it does now is the one thing it can do
    // usefully — name the ministries this person coordinates and point at the
    // setup flow — so the module is navigable the moment #9715 lands rather than
    // being a dead end until #9711.
    //
    // The ministry list is prepared here, not in the view: no queries in views
    // (groups-mvc-guidelines.md).
    $group->get('/dashboard', function (Request $request, Response $response): Response {
        $currentUser = AuthenticationManager::getCurrentUser();
        $service = new VolunteerSetupService();

        $ministries = $service->listMinistriesFor($currentUser);
        $ministryIds = array_map(static fn (VolunteerMinistry $m): int => (int) $m->getId(), $ministries);
        $teamCounts = $service->countTeamsByMinistry($ministryIds);
        $positionCounts = $service->countPositionsByMinistry($ministryIds);

        $rows = [];
        foreach ($ministries as $ministry) {
            $id = (int) $ministry->getId();
            $rows[] = [
                'id' => $id,
                'name' => $ministry->getName(),
                'description' => $ministry->getDescription(),
                'active' => (bool) $ministry->getActive(),
                'teamCount' => $teamCounts[$id] ?? 0,
                'positionCount' => $positionCounts[$id] ?? 0,
            ];
        }

        $renderer = new PhpRenderer(__DIR__ . '/../views/');

        return $renderer->render($response, 'dashboard.php', [
            'sRootPath'     => SystemURLs::getRootPath(),
            'sPageTitle'    => gettext('Volunteer Management'),
            'sPageSubtitle' => gettext('Schedule, assign and track volunteers'),
            'aBreadcrumbs'  => PageHeader::breadcrumbs([[gettext('Volunteer')]]),
            'aMinistries'   => $rows,
            'bIsManager'    => $service->getAuthorizationService()->isGlobalManager($currentUser),
        ]);
    });
})->add(VolunteerCoordinatorRoleAuthMiddleware::class);
