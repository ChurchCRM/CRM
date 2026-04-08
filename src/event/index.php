<?php

require_once __DIR__ . '/../Include/LoadConfigs.php';

use ChurchCRM\Slim\MvcAppFactory;
use ChurchCRM\Slim\Middleware\Request\Auth\AddEventsRoleAuthMiddleware;

$app = MvcAppFactory::create('/event', [
    'dashboardUrl' => '/ListEvents.php',
    'dashboardText' => gettext('Back to Events Dashboard'),
    'roleMiddleware' => AddEventsRoleAuthMiddleware::class,
]);

// Register routes
require __DIR__ . '/routes/event.php';

$app->run();
