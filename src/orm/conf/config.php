<?php

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Propel\Runtime\Propel;
use Propel\Runtime\Connection\ConnectionManagerSingle;

$serviceContainer = Propel::getServiceContainer();
$serviceContainer->checkVersion('2.0.0-dev');
$serviceContainer->setAdapterClass('default', 'mysql');
$manager = new ConnectionManagerSingle();
$manager->setConfiguration(array(
  'dsn' => 'mysql:host=' . $sSERVERNAME . ';port=3306;dbname=' . $sDATABASE,
  'user' => $sUSER,
  'password' => $sPASSWORD,
  'settings' =>
    array(
      'charset' => 'utf8',
      'queries' =>
        array(),
    ),
  'classname' => '\\Propel\\Runtime\\Connection\\ConnectionWrapper',
 // DEBUG 'classname' => '\\Propel\Runtime\Connection\DebugPDO',
  'model_paths' =>
    array(
      0 => 'src',
      1 => 'vendor',
    ),
));
$manager->setName('default');
$serviceContainer->setConnectionManager('default', $manager);
$serviceContainer->setDefaultDatasource('default');
$logger = new Logger('defaultLogger');
$logger->pushHandler(new StreamHandler('/tmp/ChurchCrm-orm.log'));
$serviceContainer->setLogger('defaultLogger', $logger);
