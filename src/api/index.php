<?php

require_once '../Include/LoadConfig.php';
require_once __DIR__ . '/../vendor/autoload.php';

use ChurchCRM\Service\DepositService;
use ChurchCRM\Service\FinancialService;
use ChurchCRM\Service\GroupService;
use ChurchCRM\Service\PersonService;
use ChurchCRM\Service\SystemService;
use ChurchCRM\Slim\Middleware\AuthMiddleware;
use ChurchCRM\Slim\Middleware\CorsMiddleware;
use ChurchCRM\Slim\Middleware\VersionMiddleware;
use ChurchCRM\Slim\SlimUtils;
use Slim\Factory\AppFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;

// Get base path by combining $sRootPath from Config.php with /api endpoint
// Examples: '' + '/api' = '/api' (root install)
//           '/churchcrm' + '/api' = '/churchcrm/api' (subdirectory install)
$basePath = SlimUtils::getBasePath('/api');


$container = new ContainerBuilder();
$container->set('PersonService', new PersonService());
$container->set('GroupService', new GroupService());
$container->set('FinancialService', new FinancialService());
$container->set('SystemService', new SystemService());
$container->set('DepositService', new DepositService());
$container->compile();

AppFactory::setContainer($container);
$app = AppFactory::create();
$app->setBasePath($basePath);

// Add Slim error middleware for proper error handling
$errorMiddleware = $app->addErrorMiddleware(true, true, true);
SlimUtils::setupErrorLogger($errorMiddleware);
SlimUtils::registerDefaultJsonErrorHandler($errorMiddleware);

// Add CORS middleware for browser API access
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

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
require __DIR__ . '/routes/people/people-person.php';
require __DIR__ . '/routes/people/people-persons.php';
require __DIR__ . '/routes/people/people-properties.php';
require __DIR__ . '/routes/public/public.php';
require __DIR__ . '/routes/public/public-data.php';
require __DIR__ . '/routes/public/public-calendar.php';
require __DIR__ . '/routes/public/public-user.php';
require __DIR__ . '/routes/public/public-register.php';
require __DIR__ . '/routes/system/system.php';
require __DIR__ . '/routes/system/system-config.php';
require __DIR__ . '/routes/system/system-custom-fields.php';
require __DIR__ . '/routes/system/system-database.php';
require __DIR__ . '/routes/system/system-debug.php';
require __DIR__ . '/routes/system/system-issues.php';
require __DIR__ . '/routes/system/system-logs.php';
require __DIR__ . '/routes/system/system-register.php';
require __DIR__ . '/routes/system/system-upgrade.php';
require __DIR__ . '/routes/system/system-custom-menu.php';
require __DIR__ . '/routes/system/system-locale.php';
require __DIR__ . '/routes/cart.php';
require __DIR__ . '/routes/background.php';
require __DIR__ . '/routes/geocoder.php';
require __DIR__ . '/routes/kiosks.php';
require __DIR__ . '/routes/email/mailchimp.php';
require __DIR__ . '/routes/search.php';
require __DIR__ . '/routes/users/user.php';
require __DIR__ . '/routes/users/user-admin.php';
require __DIR__ . '/routes/users/user-current.php';
require __DIR__ . '/routes/users/user-settings.php';

$app->run();
