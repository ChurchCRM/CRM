<?php
/*******************************************************************************
 *
 *  filename    : Include/LoadConfigs.php
 *  website     : http://www.churchcrm.io
 *  description : global configuration
 *                   The code in this file used to be part of part of Config.php
 *
 *  Copyright 2001-2005 Phillip Hullquist, Deane Barker, Chris Gebhardt,
 *                      Michael Wilt, Timothy Dearborn
 *
 *
 *  LICENSE:
 *  (C) Free Software Foundation, Inc.
 *
 *  ChurchCRM is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful, but
 *  WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 *  General Public License for more details.
 *
 *  http://www.gnu.org/licenses
 *
 *  This file best viewed in a text editor with tabs stops set to 4 characters.
 *  Please configure your editor to use soft tabs (4 spaces for a tab) instead
 *  of hard tab characters.
 *
 ******************************************************************************/

require_once dirname(__FILE__) . '/../vendor/autoload.php';

use ChurchCRM\Service\SystemService;
use ChurchCRM\Version;
use ChurchCRM\ConfigQuery;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\LocaleInfo;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Propel\Runtime\Propel;
use Propel\Runtime\Connection\ConnectionManagerSingle;

if (!function_exists("mysql_failure")) {
  function mysql_failure($message)
  {
    require("Include/HeaderNotLoggedIn.php");
    ?>
    <div class='container'>
      <h3>ChurchCRM – Setup failure</h3>

      <div class='alert alert-danger text-center' style='margin-top: 20px;'>
        <?= gettext($message) ?>
      </div>
    </div>
    <?php
    require("Include/FooterNotLoggedIn.php");
    exit();
  }
}

$cnInfoCentral = mysqli_connect($sSERVERNAME, $sUSER, $sPASSWORD)
or mysql_failure("Could not connect to MySQL on <strong>" . $sSERVERNAME . "</strong> as <strong>" . $sUSER . "</strong>. Please check the settings in <strong>Include/Config.php</strong>.<br/>MySQL Error: " . mysqli_error($cnInfoCentral));

mysqli_set_charset($cnInfoCentral, "utf8mb4");

mysqli_select_db($cnInfoCentral, $sDATABASE)
or mysql_failure("Could not connect to the MySQL database <strong>" . $sDATABASE . "</strong>. Please check the settings in <strong>Include/Config.php</strong>.<br/>MySQL Error: " . mysqli_error($cnInfoCentral));

// Initialize the session
session_name('CRM@' . $sRootPath);
session_start();

// Avoid consecutive slashes when $sRootPath = '/'
if (strlen($sRootPath) < 2) $sRootPath = '';

// Some webhosts make it difficult to use DOCUMENT_ROOT.  Define our own!
$sDocumentRoot = dirname(dirname(__FILE__));


// ==== ORM
$dbClassName = "\\Propel\\Runtime\\Connection\\ConnectionWrapper";
//DEBUG $dbClassName = "\\Propel\Runtime\Connection\DebugPDO";

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
      'charset' => 'utf8mb4',
      'queries' =>
        array(),
    ),
  'classname' => $dbClassName,
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
$logger->pushHandler(new StreamHandler('/tmp/ChurchCRM.log'));
$serviceContainer->setLogger('defaultLogger', $logger);

$connection = Propel::getConnection();
$query = "SHOW TABLES FROM `$sDATABASE`";
$statement = $connection->prepare($query);
$resultset = $statement->execute();
$results = $statement->fetchAll(\PDO::FETCH_ASSOC);

if (count($results) == 0) {
  $systemService = new SystemService();
  $version = new Version();
  $version->setVersion($systemService->getInstalledVersion());
  $version->setUpdateStart(new DateTime());
  $setupQueries = dirname(__file__) . '/../mysql/install/Install.sql';
  $systemService->playbackSQLtoDatabase($setupQueries);
  $configQueries = dirname(__file__) . '/../mysql/upgrade/update_config.sql';
  $systemService->playbackSQLtoDatabase($configQueries);
  $version->setUpdateEnd(new DateTime());
  $version->save();
}

// Read values from config table into local variables
// **************************************************

$systemConfig = new SystemConfig();
$systemConfig->init(ConfigQuery::create()->find());

$sSQL = "SELECT cfg_name, IFNULL(cfg_value, cfg_default) AS value "
  . "FROM config_cfg WHERE cfg_section='General'";
$rsConfig = mysqli_query($cnInfoCentral, $sSQL);         // Can't use RunQuery -- not defined yet
if ($rsConfig) {
  while (list($cfg_name, $value) = mysqli_fetch_row($rsConfig)) {
    $$cfg_name = $value;
  }
}

if (isset($_SESSION['iUserID'])) {      // Not set on Login.php
  // Load user variables from user config table.
  // **************************************************
  $sSQL = "SELECT ucfg_name, ucfg_value AS value "
    . "FROM userconfig_ucfg WHERE ucfg_per_ID='" . $_SESSION['iUserID'] . "'";
  $rsConfig = mysqli_query($cnInfoCentral, $sSQL);     // Can't use RunQuery -- not defined yet
  if ($rsConfig) {
    while (list($ucfg_name, $value) = mysqli_fetch_row($rsConfig)) {
      $$ucfg_name = $value;
      $_SESSION[$ucfg_name] = $value;
    }
  }
}

$sMetaRefresh = '';  // Initialize to empty

if (isset($sTimeZone)) {
  date_default_timezone_set($sTimeZone);
}

$localeInfo = new LocaleInfo($sLanguage);
setlocale(LC_ALL, $localeInfo->getLocale());

// Get numeric and monetary locale settings.
$aLocaleInfo = $localeInfo->getLocaleInfo();

// This is needed to avoid some bugs in various libraries like fpdf.
// http://www.velanhotels.com/fpdf/FAQ.htm#6
setlocale(LC_NUMERIC, 'C');

$domain = 'messages';
$sLocaleDir = dirname(__FILE__) . '/../locale';

bind_textdomain_codeset($domain, 'UTF-8');
bindtextdomain($domain, $sLocaleDir);
textdomain($domain);

?>
