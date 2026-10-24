<?php

require_once __DIR__ . '/../Include/LoadConfigs.php';

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Plugin\PluginManager;
use ChurchCRM\Portal\Auth\PortalSessionMiddleware;
use ChurchCRM\Portal\PortalAccessMiddleware;
use ChurchCRM\Portal\PortalSignInPageMiddleware;
use ChurchCRM\Portal\PortalTwig;
use ChurchCRM\Slim\Middleware\CSRFMiddleware;
use ChurchCRM\Slim\MvcAppFactory;
use Slim\Routing\RouteCollectorProxy;

// The Member Portal: the member-facing area of this installation (epic #8977,
// design .agents/skills/churchcrm/member-portal-design.md).
//
// There is no module-level role middleware. Every login may open the portal:
// a self-service account lands here and can reach nothing else, while staff
// open it from their user menu and get back out through the header's account
// menu ("Admin Console").
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

// Public: the confirm page an email-change link opens (member sign-in design
// §9). The link alone identifies the request, so it needs no session.
require __DIR__ . '/routes/email-confirm.php';

PluginManager::init(SystemURLs::getDocumentRoot() . '/plugins');

// Public: member sign-in by email (member sign-in design §6.1). AuthMiddleware
// lets a signed-out visitor through to /portal/login and nothing else here.
$app->group('/login', function (RouteCollectorProxy $group): void {
    require __DIR__ . '/routes/login.php';
})->add(new CSRFMiddleware())->add(new PortalSignInPageMiddleware());

// Everything else is a portal page: a portal session (member sign-in design
// §5), no API keys, CSRF on every state-changing request. Active plugins add
// their pages under /portal/plugins/{id} (§8.5).
$app->group('', function (RouteCollectorProxy $group): void {
    require __DIR__ . '/routes/home.php';
    require __DIR__ . '/routes/calendar.php';
    require __DIR__ . '/routes/volunteer.php';
    require __DIR__ . '/routes/teams.php';
    require __DIR__ . '/routes/email.php';
    require __DIR__ . '/routes/profile.php';
    require __DIR__ . '/routes/family.php';
    PluginManager::registerPortalRoutes($group, PluginManager::PORTAL_PAGES);
})->add(new CSRFMiddleware())->add(new PortalAccessMiddleware())->add(new PortalSessionMiddleware());

$app->run();
