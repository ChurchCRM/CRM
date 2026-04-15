<?php

require_once __DIR__ . '/../Include/LoadConfigs.php';

use ChurchCRM\Slim\MvcAppFactory;

$app = MvcAppFactory::create('/people', [
    'dashboardUrl' => '/people/dashboard',
    'dashboardText' => gettext('Back to People Dashboard'),
]);

// Register routes
require __DIR__ . '/routes/dashboard.php';
require __DIR__ . '/routes/view.php';
require __DIR__ . '/routes/people.php';
require __DIR__ . '/routes/family.php';
require __DIR__ . '/routes/person.php';

$app->run();
