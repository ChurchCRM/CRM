<?php

use Propel\Runtime\Propel;
use Propel\Runtime\Connection\ConnectionManagerSingle;

$serviceContainer = Propel::getServiceContainer();
$serviceContainer->checkVersion(2);
$serviceContainer->setAdapterClass('default', 'mysql');
$manager = new ConnectionManagerSingle("default");
$manager->setName('dattabase');
$manager->setConfiguration(array(
  'dsn' => 'mysql:host=database;port=3306;dbname=churchcrm',
  'user' => 'churchcrm',
  'password' => 'changeme',
  'settings' =>
  array(
    'charset' => 'utf8',
    'queries' =>
    array(
    ),
  ),
  'classname' => '\\Propel\\Runtime\\Connection\\ConnectionWrapper',
  'model_paths' =>
  array(
    0 => 'src',
    1 => 'vendor',
  ),
));
$serviceContainer->setConnectionManager($manager);
$serviceContainer->setDefaultDatasource('default');
require_once __DIR__ . '/./loadDatabase.php';
