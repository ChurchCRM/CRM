<?php
require '../Include/Config.php';

// This file is generated by Composer
require_once dirname(__FILE__) . '/../vendor/autoload.php';

use ChurchCRM\Slim\Middleware\AuthMiddleware;
use ChurchCRM\Slim\Middleware\VersionMiddleware;
use Slim\App;
use Slim\Container;
use Slim\HttpCache\CacheProvider;

// Instantiate the app
$settings = require __DIR__ . '/../Include/slim/settings.php';

$container = new Container;
$container['cache'] = function () {
    return new CacheProvider();
};

// Add middleware to the application
$app = new App($container);

$app->add(new VersionMiddleware());
$app->add(new AuthMiddleware());

// Set up
require __DIR__ . '/dependencies.php';
require __DIR__ . '/../Include/slim/error-handler.php';

// system routes
require __DIR__ . '/routes/database.php';
require __DIR__ . '/routes/issues.php';

// people routes
require __DIR__ . '/routes/search.php';
require __DIR__ . '/routes/persons.php';
require __DIR__ . '/routes/roles.php';
require __DIR__ . '/routes/properties.php';
require __DIR__ . '/routes/users.php';
require __DIR__ . '/routes/families.php';
require __DIR__ . '/routes/groups.php';

// finance routes
require __DIR__ . '/routes/deposits.php';
require __DIR__ . '/routes/payments.php';

// other
require __DIR__ . '/routes/calendar.php';

//timer jobs
require __DIR__ . '/routes/timerjobs.php';

//registration
require __DIR__ . '/routes/register.php';

//cart
require __DIR__ . '/routes/cart.php';

require __DIR__ . '/routes/kiosks.php';

require __DIR__ . '/routes/events.php';



require __DIR__ . '/routes/dashboard.php';

require __DIR__ . '/routes/email.php';
require __DIR__ . '/routes/geocoder.php';

require __DIR__ . '/routes/system/system.php';
require __DIR__ . '/routes/system/system-custom-fields.php';
require __DIR__ . '/routes/system/system-upgrade.php';

require __DIR__ . '/routes/public/public.php';
require __DIR__ . '/routes/public/public-data.php';
require __DIR__ . '/routes/public/public-calendar.php';
require __DIR__ . '/routes/public/public-user.php';

// Run app
$app->run();
