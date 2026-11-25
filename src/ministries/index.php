<?php

require_once __DIR__ . '/../Include/LoadConfigs.php';

use ChurchCRM\Slim\Middleware\CSRFMiddleware;
use ChurchCRM\Slim\MvcAppFactory;
use Slim\Routing\RouteCollectorProxy;

// The coordinator area of Volunteer Management v2 (epic #9701). NO module-level
// roleMiddleware: every gate is applied per route group instead (design §3.2).
$app = MvcAppFactory::create('/ministries', [
    'dashboardUrl'  => '/ministries/dashboard',
    'dashboardText' => gettext('Back to Ministry Dashboard'),
]);

// CSRF for the whole module, using the wrapper-group idiom from
// src/fundraiser/index.php (MvcAppFactory exposes no hook for this). The route
// files reference $app, so alias the group proxy to $app for them.
$app->group('', function (RouteCollectorProxy $group): void {
    $app = $group;
    require __DIR__ . '/routes/dashboard.php';
    require __DIR__ . '/routes/ministry.php';
    require __DIR__ . '/routes/occurrence.php';
})->add(new CSRFMiddleware());

$app->run();
