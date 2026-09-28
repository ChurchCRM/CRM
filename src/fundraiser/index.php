<?php

require_once __DIR__ . '/../Include/LoadConfigs.php';

use ChurchCRM\Slim\MvcAppFactory;
use ChurchCRM\Slim\Middleware\CSRFMiddleware;
use ChurchCRM\Slim\Middleware\Request\Auth\ManageFundraisersRoleAuthMiddleware;
use ChurchCRM\Slim\Middleware\Request\Setting\FundraiserEnabledMiddleware;
use Slim\Routing\RouteCollectorProxy;

// Global gate: every /fundraiser/* request requires login (AuthMiddleware) AND
// the ManageFundraisers permission (ManageFundraisersRoleAuthMiddleware).
// Individual delete routes add an inline isDeleteRecordsEnabled() check.
$app = MvcAppFactory::create('/fundraiser', [
    'dashboardUrl'  => '/fundraiser/',
    'dashboardText' => gettext('Return to Fundraiser Dashboard'),
    'roleMiddleware' => ManageFundraisersRoleAuthMiddleware::class,
]);

// Register routes inside a group guarded by CSRFMiddleware and
// FundraiserEnabledMiddleware (LIFO: FundraiserEnabled runs first, then CSRF).
// FundraiserEnabledMiddleware ensures ALL access — including by admins who
// would otherwise pass the app-level ManageFundraisersRoleAuthMiddleware —
// is blocked when bEnabledFundraiser is false (redirects browser to home;
// returns 403 JSON for API clients). The route files reference $app, so
// alias the group proxy to $app for them.
$app->group('', function (RouteCollectorProxy $group): void {
    $app = $group;
    require __DIR__ . '/routes/fundraiser.php';
    require __DIR__ . '/routes/paddle-num.php';
    require __DIR__ . '/routes/donated-item.php';
    require __DIR__ . '/routes/donors.php';
    require __DIR__ . '/routes/batch-winner.php';
    require __DIR__ . '/routes/reports.php';
})->add(new CSRFMiddleware())->add(new FundraiserEnabledMiddleware());

$app->run();
