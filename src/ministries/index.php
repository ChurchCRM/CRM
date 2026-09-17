<?php

require_once __DIR__ . '/../Include/LoadConfigs.php';

use ChurchCRM\Slim\Middleware\CSRFMiddleware;
use ChurchCRM\Slim\Middleware\Request\Setting\VolunteerV2EnabledMiddleware;
use ChurchCRM\Slim\MvcAppFactory;
use Slim\Routing\RouteCollectorProxy;

// The coordinator area of Volunteer Management v2 (epic #9701), mounted at
// /ministries since the product-owner review of 2026-09-17: the sidebar heading,
// the dashboard and the ministry pages all say "Ministries", so the URLs do too.
// /volunteer/* keeps only redirects (src/volunteer/index.php). NO module-level
// roleMiddleware: every gate is applied per route group instead (design §3.2).
$app = MvcAppFactory::create('/ministries', [
    'dashboardUrl'  => '/ministries/dashboard',
    'dashboardText' => gettext('Back to Ministry Dashboard'),
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
})->add(new CSRFMiddleware())->add(new VolunteerV2EnabledMiddleware());

$app->run();
