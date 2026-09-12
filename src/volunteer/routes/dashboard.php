<?php

use ChurchCRM\dto\SystemURLs;
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

    // GET /volunteer/dashboard — placeholder coordinator dashboard.
    $group->get('/dashboard', function (Request $request, Response $response): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');

        return $renderer->render($response, 'dashboard.php', [
            'sRootPath'     => SystemURLs::getRootPath(),
            'sPageTitle'    => gettext('Volunteer Management'),
            'sPageSubtitle' => gettext('Schedule, assign and track volunteers'),
            'aBreadcrumbs'  => PageHeader::breadcrumbs([[gettext('Volunteer')]]),
        ]);
    });
})->add(VolunteerCoordinatorRoleAuthMiddleware::class);
