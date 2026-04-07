<?php

require_once __DIR__ . '/../Include/LoadConfigs.php';

use ChurchCRM\Slim\Middleware\AuthMiddleware;
use ChurchCRM\Slim\Middleware\CorsMiddleware;
use ChurchCRM\Slim\Middleware\VersionMiddleware;
use ChurchCRM\Slim\SlimUtils;
use Slim\Factory\AppFactory;

// Get base path by combining $sRootPath from Config.php with /api endpoint
// Examples: '' + '/api' = '/api' (root install)
//           '/churchcrm' + '/api' = '/churchcrm/api' (subdirectory install)
$basePath = SlimUtils::getBasePath('/api');

$app = AppFactory::create();
$app->setBasePath($basePath);

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

// Error middleware must be added AFTER routing (Slim 4 LIFO: last added = first executed)
$errorMiddleware = $app->addErrorMiddleware(true, true, true);
SlimUtils::registerDefaultJsonErrorHandler($errorMiddleware);

$app->add(new CorsMiddleware());
$app->add(AuthMiddleware::class);
$app->add(VersionMiddleware::class);

// Group routes for better organization
require __DIR__ . '/routes/calendar/events.php';
require __DIR__ . '/routes/calendar/calendar.php';
require __DIR__ . '/routes/finance/finance-deposits.php';
require __DIR__ . '/routes/finance/finance-payments.php';
require __DIR__ . '/routes/people/people-family.php';
require __DIR__ . '/routes/people/people-families.php';
require __DIR__ . '/routes/people/people-groups.php';
require __DIR__ . '/routes/people/groups-properties.php';
require __DIR__ . '/routes/people/people-person.php';
require __DIR__ . '/routes/people/people-persons.php';
require __DIR__ . '/routes/people/people-properties.php';
require __DIR__ . '/routes/people/notes.php';
require __DIR__ . '/routes/public/public.php';
require __DIR__ . '/routes/public/public-data.php';
require __DIR__ . '/routes/public/public-calendar.php';
require __DIR__ . '/routes/public/public-user.php';
require __DIR__ . '/routes/public/public-register.php';
require __DIR__ . '/routes/system/system-custom-fields.php';
require __DIR__ . '/routes/system/system-database.php';
require __DIR__ . '/routes/system/system-debug.php';
require __DIR__ . '/routes/system/system-issues.php';
require __DIR__ . '/routes/system/system-locale.php';
require __DIR__ . '/routes/cart.php';
require __DIR__ . '/routes/background.php';
require __DIR__ . '/routes/geocoder.php';
require __DIR__ . '/routes/search.php';
require __DIR__ . '/routes/users/user.php';
require __DIR__ . '/routes/users/user-current.php';
require __DIR__ . '/routes/users/user-settings.php';
require __DIR__ . '/routes/map.php';

$app->run();
