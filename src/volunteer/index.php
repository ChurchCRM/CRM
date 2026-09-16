<?php

require_once __DIR__ . '/../Include/LoadConfigs.php';

use ChurchCRM\Slim\Middleware\CSRFMiddleware;
use ChurchCRM\Slim\Middleware\Request\Setting\VolunteerV2EnabledMiddleware;
use ChurchCRM\Slim\MvcAppFactory;
use Slim\Routing\RouteCollectorProxy;

// NO module-level roleMiddleware: every gate is applied per route group instead
// (see the volunteer-v2 design, §3.2). Since MP6 (#9867) this module is the
// coordinator area and nothing else — the volunteer's own two pages moved into
// the Member Portal, and all that is left of them here is a pair of redirects.
$app = MvcAppFactory::create('/volunteer', [
    'dashboardUrl'  => '/volunteer/dashboard',
    'dashboardText' => gettext('Back to Volunteer Dashboard'),
]);

// Rollout gate for the whole module, using the wrapper-group idiom from
// src/fundraiser/index.php (MvcAppFactory exposes no hook for this).
// LIFO: VolunteerV2Enabled runs first, then CSRF. The route files reference
// $app, so alias the group proxy to $app for them.
$app->group('', function (RouteCollectorProxy $group): void {
    $app = $group;
    require __DIR__ . '/routes/dashboard.php';
    require __DIR__ . '/routes/ministry.php';
    require __DIR__ . '/routes/occurrence.php';
    require __DIR__ . '/routes/member-redirects.php';
})->add(new CSRFMiddleware())->add(new VolunteerV2EnabledMiddleware());

$app->run();
