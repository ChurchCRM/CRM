<?php

require_once __DIR__ . '/../Include/LoadConfigs.php';

use ChurchCRM\Slim\MvcAppFactory;

$app = MvcAppFactory::create('/people', [
    'dashboardUrl' => '/people/dashboard',
    'dashboardText' => gettext('Back to People Dashboard'),
]);

// Register routes
require __DIR__ . '/routes/dashboard.php';

$app->run();
