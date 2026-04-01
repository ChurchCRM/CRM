<?php

require_once __DIR__ . '/../Include/LoadConfigs.php';

use ChurchCRM\Slim\Middleware\AuthMiddleware;
use ChurchCRM\Slim\Middleware\ChurchInfoRequiredMiddleware;
use ChurchCRM\Slim\Middleware\CorsMiddleware;
use ChurchCRM\Slim\Middleware\VersionMiddleware;
use ChurchCRM\Slim\SlimUtils;
use Slim\Factory\AppFactory;

// Get base path by combining $sRootPath from Config.php with /v2 endpoint
// Examples: '' + '/v2' = '/v2' (root install)
//           '/churchcrm' + '/v2' = '/churchcrm/v2' (subdirectory install)
$basePath = SlimUtils::getBasePath('/v2');

$app = AppFactory::create();
$app->setBasePath($basePath);

// Add Slim error middleware for proper error handling
$errorMiddleware = $app->addErrorMiddleware(true, true, true);
SlimUtils::setupErrorLogger($errorMiddleware);
SlimUtils::registerDefaultJsonErrorHandler($errorMiddleware);

// CRITICAL: Middleware order matters in Slim 4 (LIFO - Last In, First Out)
// Middleware are added in reverse execution order: added last runs first.
// Execution order: VersionMiddleware → AuthMiddleware → ChurchInfoRequiredMiddleware → CorsMiddleware
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->add(new CorsMiddleware());
$app->add(new ChurchInfoRequiredMiddleware());
$app->add(AuthMiddleware::class);
$app->add(VersionMiddleware::class);

require __DIR__ . '/routes/common/mvc-helper.php';
require __DIR__ . '/routes/search.php';
require __DIR__ . '/routes/user.php';
require __DIR__ . '/routes/people.php';
require __DIR__ . '/routes/family.php';
require __DIR__ . '/routes/person.php';
require __DIR__ . '/routes/email.php';
require __DIR__ . '/routes/text.php';
require __DIR__ . '/routes/calendar.php';
require __DIR__ . '/routes/cart.php';
require __DIR__ . '/routes/user-current.php';
require __DIR__ . '/routes/root.php';
require __DIR__ . '/routes/map.php';

// Run app
$app->run();
