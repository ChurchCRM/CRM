<?php

require_once __DIR__ . '/../Include/LoadConfigs.php';

use ChurchCRM\Slim\Middleware\CSRFMiddleware;
use ChurchCRM\Slim\Middleware\Request\Setting\VolunteerV2EnabledMiddleware;
use ChurchCRM\Slim\MvcAppFactory;
use Slim\Routing\RouteCollectorProxy;

// NO module-level roleMiddleware: the coordinator area and the volunteer's own
// self-service area will live in the same module behind different gates, so
// every gate is applied per route group instead (see the volunteer-v2 design,
// §3.2). Today only the coordinator half exists.
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
})->add(new CSRFMiddleware())->add(new VolunteerV2EnabledMiddleware());

$app->run();
