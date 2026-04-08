<?php

require_once __DIR__ . '/../Include/LoadConfigs.php';

use ChurchCRM\Slim\MvcAppFactory;
use ChurchCRM\Slim\Middleware\Request\Auth\AddEventsRoleAuthMiddleware;

$app = MvcAppFactory::create('/event', [
    'dashboardUrl' => '/event/dashboard',
    'dashboardText' => gettext('Back to Events Dashboard'),
    'roleMiddleware' => AddEventsRoleAuthMiddleware::class,
]);

// Register routes
require __DIR__ . '/routes/event.php';
require __DIR__ . '/routes/checkin.php';
require __DIR__ . '/routes/repeat-editor.php';
require __DIR__ . '/routes/calendar.php';
require __DIR__ . '/routes/list-events.php';

$app->run();
