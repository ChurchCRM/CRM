<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

// Route paths are module-relative: setBasePath() already carries '/volunteer'.
//
// The coordinator area is gated by AdminRoleAuthMiddleware for now. The scoped
// VolunteerCoordinatorRoleAuthMiddleware arrives with #9706 and replaces it;
// until then the Volunteer menu entry mirrors this gate exactly, so nothing is
// advertised that cannot be opened.
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
})->add(AdminRoleAuthMiddleware::class);
