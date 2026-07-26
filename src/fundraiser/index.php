<?php

require_once __DIR__ . '/../Include/LoadConfigs.php';

use ChurchCRM\Slim\MvcAppFactory;
use ChurchCRM\Slim\Middleware\CSRFMiddleware;
use ChurchCRM\Slim\Middleware\Request\Auth\ManageFundraisersRoleAuthMiddleware;
use Slim\Routing\RouteCollectorProxy;

// Global gate: every /fundraiser/* request requires login (AuthMiddleware) AND
// the ManageFundraisers permission (ManageFundraisersRoleAuthMiddleware).
// Individual delete routes add an inline isDeleteRecordsEnabled() check.
$app = MvcAppFactory::create('/fundraiser', [
    'dashboardUrl'  => '/fundraiser/',
    'dashboardText' => gettext('Return to Fundraiser Dashboard'),
    'roleMiddleware' => ManageFundraisersRoleAuthMiddleware::class,
]);

// Register routes inside a group guarded by CSRFMiddleware. Group middleware
// runs inside the routing/body-parsing layer, so the parsed body (and its
// csrf_token) is available for validation — an app-level middleware would run
// before body parsing and never see it. This validates every state-changing
// request (POST/PUT/DELETE/PATCH) automatically, replacing the per-route
// inline CSRFUtils::verifyRequest() checks. The route files reference $app, so
// alias the group proxy to $app for them.
$app->group('', function (RouteCollectorProxy $group): void {
    $app = $group;
    require __DIR__ . '/routes/fundraiser.php';
    require __DIR__ . '/routes/paddle-num.php';
    require __DIR__ . '/routes/donated-item.php';
    require __DIR__ . '/routes/donors.php';
    require __DIR__ . '/routes/batch-winner.php';
    require __DIR__ . '/routes/reports.php';
})->add(new CSRFMiddleware());

$app->run();
