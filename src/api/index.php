<?php

require '../Include/Config.php';

require_once __DIR__ . '/../vendor/autoload.php';

use ChurchCRM\Slim\Middleware\AuthMiddleware;
use ChurchCRM\Slim\Middleware\VersionMiddleware;
use Slim\Factory\AppFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;

$container = new ContainerBuilder();
require __DIR__ . '/dependencies.php';
$container->compile();
AppFactory::setContainer($container);
$app = AppFactory::create();
$app->setBasePath('/api');

$app->add(VersionMiddleware::class);
$app->add(AuthMiddleware::class);
$app->addBodyParsingMiddleware();

// Set up
require __DIR__ . '/../Include/slim/error-handler.php';

// calendar
require __DIR__ . '/routes/calendar/events.php';
require __DIR__ . '/routes/calendar/calendar.php';

// finance routes
require __DIR__ . '/routes/finance/finance-deposits.php';
require __DIR__ . '/routes/finance/finance-payments.php';

// People (families / persons)
require __DIR__ . '/routes/people/people-family.php';
require __DIR__ . '/routes/people/people-families.php';
require __DIR__ . '/routes/people/people-groups.php';
require __DIR__ . '/routes/people/people-person.php';
require __DIR__ . '/routes/people/people-persons.php';
require __DIR__ . '/routes/people/people-properties.php';

// Public
require __DIR__ . '/routes/public/public.php';
require __DIR__ . '/routes/public/public-data.php';
require __DIR__ . '/routes/public/public-calendar.php';
require __DIR__ . '/routes/public/public-user.php';
require __DIR__ . '/routes/public/public-register.php';

// system routes
require __DIR__ . '/routes/system/system.php';
require __DIR__ . '/routes/system/system-config.php';
require __DIR__ . '/routes/system/system-custom-fields.php';
require __DIR__ . '/routes/system/system-database.php';
require __DIR__ . '/routes/system/system-debug.php';
require __DIR__ . '/routes/system/system-issues.php';
require __DIR__ . '/routes/system/system-register.php';
require __DIR__ . '/routes/system/system-upgrade.php';
require __DIR__ . '/routes/system/system-custom-menu.php';
require __DIR__ . '/routes/system/system-locale.php';

// other
require __DIR__ . '/routes/cart.php';
require __DIR__ . '/routes/background.php';
require __DIR__ . '/routes/geocoder.php';
require __DIR__ . '/routes/kiosks.php';
require __DIR__ . '/routes/email/mailchimp.php';
require __DIR__ . '/routes/search.php';

//user
require __DIR__ . '/routes/users/user.php';
require __DIR__ . '/routes/users/user-admin.php';
require __DIR__ . '/routes/users/user-current.php';
require __DIR__ . '/routes/users/user-settings.php';

$app->run();
