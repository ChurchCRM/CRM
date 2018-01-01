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
 *******************************************************************************/

require_once dirname(__FILE__).'/../vendor/autoload.php';

use ChurchCRM\ConfigQuery;
use ChurchCRM\dto\LocaleInfo;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\SystemService;
use ChurchCRM\SQLUtils;
use ChurchCRM\Version;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Propel\Runtime\Connection\ConnectionManagerSingle;
use Propel\Runtime\Propel;
use ChurchCRM\Utils\LoggerUtils;

function system_failure($message, $header = 'Setup failure')
{
    require 'Include/HeaderNotLoggedIn.php'; ?>
    <div class='container'>
        <h3>ChurchCRM – <?= _($header) ?></h3>
        <div class='alert alert-danger text-center' style='margin-top: 20px;'>
            <?= gettext($message) ?>
        </div>
    </div>
    <?php
    require 'Include/FooterNotLoggedIn.php';
    exit();
}

function buildConnectionManagerConfig($sSERVERNAME, $sDATABASE, $sUSER, $sPASSWORD, $dbClassName, $dbPort = '3306')
{
    return [
        'dsn' => 'mysql:host=' . $sSERVERNAME . ';port='.$dbPort.';dbname=' . $sDATABASE,
        'user' => $sUSER,
        'password' => $sPASSWORD,
        'settings' => [
            'charset' => 'utf8mb4',
            'queries' => ["SET sql_mode=(SELECT REPLACE(REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''),'NO_ZERO_DATE',''))"],
        ],
        'classname' => $dbClassName,
        'model_paths' => [
            0 => 'src',
            1 => 'vendor',
        ],
    ];
}

try {
    SystemURLs::init($sRootPath, $URL, dirname(dirname(__FILE__)));
} catch (\Exception $e) {
    system_failure($e->getMessage());
}

SystemURLs::checkAllowedURL($bLockURL, $URL);

$cnInfoCentral = mysqli_connect($sSERVERNAME, $sUSER, $sPASSWORD)
or system_failure('Could not connect to MySQL on <strong>'.$sSERVERNAME.'</strong> as <strong>'.$sUSER.'</strong>. Please check the settings in <strong>Include/Config.php</strong>.<br/>MySQL Error: '.mysqli_error($cnInfoCentral));

mysqli_set_charset($cnInfoCentral, 'utf8mb4');

mysqli_select_db($cnInfoCentral, $sDATABASE)
or system_failure('Could not connect to the MySQL database <strong>'.$sDATABASE.'</strong>. Please check the settings in <strong>Include/Config.php</strong>.<br/>MySQL Error: '.mysqli_error($cnInfoCentral));

// Initialize the session
session_cache_limiter('private_no_expire:');
session_name('CRM@'.SystemURLs::getRootPath());
session_start();


// ==== ORM
$dbClassName = "\\Propel\\Runtime\\Connection\\ConnectionWrapper";


$serviceContainer = Propel::getServiceContainer();
$serviceContainer->checkVersion('2.0.0-dev');
$serviceContainer->setAdapterClass('default', 'mysql');
$manager = new ConnectionManagerSingle();
$manager->setConfiguration(buildConnectionManagerConfig($sSERVERNAME, $sDATABASE, $sUSER, $sPASSWORD, $dbClassName));
$manager->setName('default');
$serviceContainer->setConnectionManager('default', $manager);
$serviceContainer->setDefaultDatasource('default');

$connection = Propel::getConnection();
$query = "SHOW TABLES FROM `$sDATABASE`";
$statement = $connection->prepare($query);
$resultset = $statement->execute();
$results = $statement->fetchAll(\PDO::FETCH_ASSOC);

if (count($results) == 0) {
    $systemService = new SystemService();
    $version = new Version();
    $version->setVersion(SystemService::getInstalledVersion());
    $version->setUpdateStart(new DateTime());
    SQLUtils::sqlImport(SystemURLs::getDocumentRoot().'/mysql/install/Install.sql', $connection);
    $version->setUpdateEnd(new DateTime());
    $version->save();
}

// Read values from config table into local variables
// **************************************************

SystemConfig::init(ConfigQuery::create()->find());

// enable logs if we are in debug mode
// **************************************************

// PHP Logs
ini_set('log_errors', 1);
ini_set('error_log', LoggerUtils::buildLogFilePath("php"));

// APP Logs
$logger = LoggerUtils::getAppLogger();

// ORM Logs
$ormLogger = new Logger('ormLogger');
$dbClassName = "\\Propel\\Runtime\\Connection\\DebugPDO";
$manager->setConfiguration(buildConnectionManagerConfig($sSERVERNAME, $sDATABASE, $sUSER, $sPASSWORD, $dbClassName));
$ormLogger->pushHandler(new StreamHandler(LoggerUtils::buildLogFilePath("orm"), LoggerUtils::getLogLevel()));
$serviceContainer->setLogger('defaultLogger', $ormLogger);


if (isset($_SESSION['iUserID'])) {      // Not set on Login.php
    // Load user variables from user config table.
    // **************************************************
    $sSQL = 'SELECT ucfg_name, ucfg_value AS value '
        ."FROM userconfig_ucfg WHERE ucfg_per_ID='".$_SESSION['iUserID']."'";
    $rsConfig = mysqli_query($cnInfoCentral, $sSQL);     // Can't use RunQuery -- not defined yet
    if ($rsConfig) {
        while (list($ucfg_name, $value) = mysqli_fetch_row($rsConfig)) {
            $$ucfg_name = $value;
            $_SESSION[$ucfg_name] = $value;
        }
    }
}

require 'SimpleConfig.php';

?>
