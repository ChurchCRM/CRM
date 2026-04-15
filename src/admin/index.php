<?php

require_once __DIR__ . '/../Include/LoadConfigs.php';

use ChurchCRM\Slim\MvcAppFactory;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;

$app = MvcAppFactory::create('/admin', [
    'dashboardUrl' => '/admin/',
    'dashboardText' => gettext('Back to Admin Dashboard'),
    'roleMiddleware' => AdminRoleAuthMiddleware::class,
]);

// Register routes
require __DIR__ . '/routes/dashboard.php';
require __DIR__ . '/routes/get-started.php';
require __DIR__ . '/routes/api/demo.php';
require __DIR__ . '/routes/api/database.php';
require __DIR__ . '/routes/api/orphaned-files.php';
require __DIR__ . '/routes/api/options.php';
require __DIR__ . '/routes/api/system/system-config.php';
require __DIR__ . '/routes/api/system/system-logs.php';
require __DIR__ . '/routes/api/upgrade.php';
require __DIR__ . '/routes/api/user-admin.php';
require __DIR__ . '/routes/api/import.php';
require __DIR__ . '/routes/import.php';
require __DIR__ . '/routes/export.php';
require __DIR__ . '/routes/system.php';

$app->run();
