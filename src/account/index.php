<?php

require_once __DIR__ . '/../Include/LoadConfigs.php';

use ChurchCRM\Slim\MvcAppFactory;

// The signed-in staff user's own account. AuthMiddleware sends a self-service
// (Edit Self only) login to the Member Portal, whose account pages live at
// /portal/profile/*.
$app = MvcAppFactory::create('/account', [
    'dashboardUrl' => '/v2/dashboard',
    'dashboardText' => gettext('Return to Dashboard'),
]);

require __DIR__ . '/routes/account.php';

$app->run();
