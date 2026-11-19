<?php

require_once __DIR__ . '/../Include/LoadConfigs.php';

use ChurchCRM\Slim\MvcAppFactory;

// The signed-in staff user's own account.
$app = MvcAppFactory::create('/account', [
    'dashboardUrl' => '/people/dashboard',
    'dashboardText' => gettext('Return to Dashboard'),
]);

require __DIR__ . '/routes/account.php';

$app->run();
