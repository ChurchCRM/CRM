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
require __DIR__ . '/routes/user.php';
require __DIR__ . '/routes/people.php';
require __DIR__ . '/routes/family.php';
require __DIR__ . '/routes/person.php';
require __DIR__ . '/routes/email.php';
require __DIR__ . '/routes/text.php';
require __DIR__ . '/routes/cart.php';
require __DIR__ . '/routes/user-current.php';
require __DIR__ . '/routes/root.php';
require __DIR__ . '/routes/map.php';

$app->run();
