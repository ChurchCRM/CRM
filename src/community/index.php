<?php

require_once __DIR__ . '/../Include/LoadConfigs.php';

use ChurchCRM\Slim\MvcAppFactory;

$app = MvcAppFactory::create('/community', [
    'dashboardUrl' => '/v2/dashboard',
    'dashboardText' => gettext('Return to Dashboard'),
]);

// Register routes
require __DIR__ . '/routes/feedback.php';

$app->run();
