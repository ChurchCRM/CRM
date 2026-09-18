<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\VolunteerAuthorizationService;
use ChurchCRM\Slim\Middleware\Request\Auth\VolunteerCoordinatorRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

// Route paths are module-relative: setBasePath() already carries '/ministries'.
//
// The coordinator area is gated by VolunteerCoordinatorRoleAuthMiddleware (#9706):
// an administrator, a global volunteer manager, a ministry coordinator or a team
// leader. Which ministry or team a coordinator may actually touch is decided per
// record by the entity middlewares and, on the aggregate, by the query scoping in
// `GET /api/ministries/dashboard` — never here (design §4.5).
//
// The Volunteer menu entry mirrors this gate exactly by calling the same predicate,
// User::isVolunteerCoordinatorEnabled(), so nothing is advertised that cannot be
// opened and nothing openable is hidden.
$app->group('', function (RouteCollectorProxy $group): void {
    // GET /ministries/ — send the bare module URL to the dashboard.
    $group->get('/', fn (Request $request, Response $response): Response => SlimUtils::renderRedirect($response, SystemURLs::getRootPath() . '/ministries/dashboard'));

    /**
     * GET /ministries/dashboard — S1, "what needs my attention" (#9711, §5.2).
     *
     * This route renders markup and the page config and runs no query of its own:
     * all five panels come from ONE `GET /api/ministries/dashboard` call made by the
     * bundle (§5.2 forbids fanning out), and that endpoint does the scoping in its
     * query. The only thing decided here is what an ADMINISTRATOR may additionally
     * see — the settings strip (U8) — because that is a server-side decision about
     * markup, not a data read.
     */
    $group->get('/dashboard', function (Request $request, Response $response): Response {
        $currentUser = AuthenticationManager::getCurrentUser();
        $authz = new VolunteerAuthorizationService();

        $renderer = new PhpRenderer(__DIR__ . '/../views/');

        return $renderer->render($response, 'dashboard.php', [
            'sRootPath'     => SystemURLs::getRootPath(),
            'sPageTitle'    => gettext('Ministry Dashboard'),
            'sPageSubtitle' => gettext('What needs your attention this week'),
            'aBreadcrumbs'  => PageHeader::breadcrumbs([[gettext('Ministries')]]),
            'bIsAdmin'      => $currentUser->isAdmin(),
            'bIsManager'    => $authz->isGlobalManager($currentUser),
            // The default window §3.3.2 fixes. The select on the page can widen it.
            'iDays'         => 28,
            // §3.6: reminders are only punctual when a real scheduler drains the
            // outbox, and the supported entry point is the CLI runner — the same
            // command src/cli/timerjobs.php documents, quoted in one place so the
            // hint cannot drift from the runner it names.
            'sTimerJobsHint' => '0,15,30,45 * * * * /usr/bin/php /path/to/churchcrm/cli/timerjobs.php',
        ]);
    });
})->add(VolunteerCoordinatorRoleAuthMiddleware::class);
