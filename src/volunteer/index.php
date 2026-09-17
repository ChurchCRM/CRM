<?php

require_once __DIR__ . '/../Include/LoadConfigs.php';

use ChurchCRM\Slim\Middleware\CSRFMiddleware;
use ChurchCRM\Slim\Middleware\Request\Setting\VolunteerV2EnabledMiddleware;
use ChurchCRM\Slim\MvcAppFactory;
use Slim\Routing\RouteCollectorProxy;

// Redirects only. The member pages moved into the Member Portal (MP6, #9867) and
// the coordinator area moved to /ministries (product-owner review, 2026-09-17);
// every URL this module ever served is in bookmarks, old emails and browser
// histories, so each one still lands where its reader wanted to go.
$app = MvcAppFactory::create('/volunteer', [
    'dashboardUrl'  => '/ministries/dashboard',
    'dashboardText' => gettext('Back to Ministry Dashboard'),
]);

$app->group('', function (RouteCollectorProxy $group): void {
    $app = $group;
    require __DIR__ . '/routes/member-redirects.php';
    require __DIR__ . '/routes/coordinator-redirects.php';
})->add(new CSRFMiddleware())->add(new VolunteerV2EnabledMiddleware());

$app->run();
