<?php

require_once __DIR__ . '/../Include/LoadConfigs.php';

use ChurchCRM\Portal\PortalAccessMiddleware;
use ChurchCRM\Portal\PortalTwig;
use ChurchCRM\Slim\Middleware\CSRFMiddleware;
use ChurchCRM\Slim\MvcAppFactory;
use Slim\Routing\RouteCollectorProxy;

// The Member Portal: the member-facing area of this installation (epic #8977,
// design .agents/skills/churchcrm/member-portal-design.md).
//
// There is no module-level role middleware. Every login may open the portal:
// a self-service account lands here and can reach nothing else, while staff
// open it from their user menu and see the "viewing as yourself" bar.
$app = MvcAppFactory::create('/portal', [
    'dashboardUrl' => '/portal/',
    'dashboardText' => gettext('Back to the Member Portal'),
    // The shared MVC error page is the admin shell; the portal draws its own
    // error pages from the active theme instead.
    'errorHandler' => PortalTwig::createErrorHandler(),
]);

// Public: theme assets. Include/ is deny-all at the web-server level, so a
// theme's stylesheet, script, images and fonts are streamed by the application
// (design P5). The route carries no session so a cached page never breaks on a
// logged-out asset request.
require __DIR__ . '/routes/theme-asset.php';

// Everything else is a portal page: signed-in session, no API keys, CSRF on
// every state-changing request.
$app->group('', function (RouteCollectorProxy $group): void {
    require __DIR__ . '/routes/home.php';
    require __DIR__ . '/routes/volunteer.php';
    require __DIR__ . '/routes/teams.php';
    require __DIR__ . '/routes/profile.php';
    require __DIR__ . '/routes/family.php';
})->add(new CSRFMiddleware())->add(new PortalAccessMiddleware());

$app->run();
