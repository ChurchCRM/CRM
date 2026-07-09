<?php

require_once __DIR__ . '/../Include/LoadConfigs.php';

use ChurchCRM\Slim\MvcAppFactory;
use ChurchCRM\Slim\Middleware\Request\Auth\ManageGroupRoleAuthMiddleware;

$app = MvcAppFactory::create('/groups', [
    'dashboardUrl' => '/groups/dashboard',
    'dashboardText' => gettext('Back to Groups Dashboard'),
    'roleMiddleware' => ManageGroupRoleAuthMiddleware::class,
]);

// Register routes
require __DIR__ . '/routes/dashboard.php';
require __DIR__ . '/routes/reports.php';
require __DIR__ . '/routes/sundayschool.php';
require __DIR__ . '/routes/view.php';

$app->run();
