<?php

require_once __DIR__ . '/../Include/LoadConfigs.php';

use ChurchCRM\Slim\MvcAppFactory;

$app = MvcAppFactory::create('/v2', [
    'dashboardUrl' => '/v2/dashboard',
    'dashboardText' => gettext('Return to Dashboard'),
]);

// Register routes
require __DIR__ . '/routes/common/mvc-helper.php';
require __DIR__ . '/routes/search.php';
require __DIR__ . '/routes/root.php';

$app->run();
