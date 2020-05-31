<?php

namespace ChurchCRM
{
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
  use ChurchCRM\Utils\RedirectUtils;
  use ChurchCRM\Authentication\AuthenticationManager;

  class Bootstrapper
  {
      private static $manager;
      private static $dbClassName;
      private static $databaseServerName;
      private static $databasePort;
      private static $databaseUser;
      private static $databasePassword;
      private static $databaseName;
      private static $rootPath;
      private static $lockURL;
      private static $allowableURLs;
      /**
       *
       * @var Logger
       */
      private static $bootStrapLogger;
      private static $serviceContainer;

      public static function init($sSERVERNAME, $dbPort, $sUSER, $sPASSWORD, $sDATABASE, $sRootPath, $bLockURL, $URL)
      {
          global $debugBootstrapper;
          self::$databaseServerName = $sSERVERNAME;
          self::$databaseUser = $sUSER;
          self::$databasePassword = $sPASSWORD;
          self::$databasePort = $dbPort;
          self::$databaseName = $sDATABASE;
          self::$rootPath = $sRootPath;
          self::$lockURL = $bLockURL;
          self::$allowableURLs = $URL;

          try {
              SystemURLs::init($sRootPath, $URL, dirname(dirname(__FILE__)));
          } catch (\Exception $e) {
              Bootstrapper::system_failure($e->getMessage());
          }
          if ($debugBootstrapper) {
              self::$bootStrapLogger = LoggerUtils::getAppLogger(Logger::DEBUG);
          } else {
              self::$bootStrapLogger = LoggerUtils::getAppLogger(Logger::INFO);
          }

          self::$bootStrapLogger->debug("Starting ChurchCRM");
          SystemURLs::checkAllowedURL($bLockURL, $URL);
          self::initMySQLI();
          self::initPropel();

          if (self::isDatabaseEmpty()) {
              self::installChurchCRMSchema();
          }
          self::initSession();
          SystemConfig::init(ConfigQuery::create()->find());
          self::configureLogging();
          self::configureUserEnvironment();
          self::ConfigureLocale();
          if (!self::isDBCurrent()) {
              if (!strpos($_SERVER['SCRIPT_NAME'], "SystemDBUpdate")) {
                  self::$bootStrapLogger->info("Database is not current, redirecting to SystemDBUpdate");
                  RedirectUtils::Redirect('SystemDBUpdate.php');
              } else {
                  self::$bootStrapLogger->debug("Database is not current, not redirecting to SystemDBUpdate since we're already on it");
              }
          }
          LoggerUtils::ResetAppLoggerLevel();
      }
      /***
       * Gets a LocaleInfo object for the currently configured system sLanguage
       *
       * @return ChurchCRM\LocaleInfo
       */
      public static function GetCurrentLocale()
      {
          return new LocaleInfo(SystemConfig::getValue('sLanguage'));
      }

      private static function ConfigureLocale()
      {
          global $aLocaleInfo,$localeInfo;
          if (SystemConfig::getValue('sTimeZone')) {
              self::$bootStrapLogger->debug("Setting TimeZone to: " . SystemConfig::getValue('sTimeZone'));
              date_default_timezone_set(SystemConfig::getValue('sTimeZone'));
          }

          $localeInfo = Bootstrapper::GetCurrentLocale();
          self::$bootStrapLogger->debug("Setting locale to: " . $localeInfo->getLocale());
          setlocale(LC_ALL, $localeInfo->getLocale());

          // Get numeric and monetary locale settings.
          $aLocaleInfo = $localeInfo->getLocaleInfo();

          // This is needed to avoid some bugs in various libraries like fpdf.
          // http://www.velanhotels.com/fpdf/FAQ.htm#6
          setlocale(LC_NUMERIC, 'C');

          $domain = 'messages';
          $sLocaleDir = SystemURLs::getDocumentRoot() . '/locale/textdomain';
          self::$bootStrapLogger->debug("Setting local text domain bind to: " . $sLocaleDir);
          bind_textdomain_codeset($domain, 'UTF-8');
          bindtextdomain($domain, $sLocaleDir);
          textdomain($domain);
          self::$bootStrapLogger->debug("Locale configuration complete");
      }

      private static function initMySQLI()
      {
          global $cnInfoCentral; // need to stop using this everywhere....
          self::$bootStrapLogger->debug("Initializing MySQLi to ". self::$databaseServerName . " as " . self::$databaseUser);
          // Due to mysqli handling connections on 'localhost' via socket only, we need to tease out this case and handle
          // TCP/IP connections separately defaulting self::$databasePort to 3306 for the general case when self::$databasePort is not set.
          if (self::$databaseServerName == "localhost") {
              self::$bootStrapLogger->debug("Connecting to localhost with no port");
              $cnInfoCentral = mysqli_connect(self::$databaseServerName, self::$databaseUser, self::$databasePassword);
          } else {
              if (!isset(self::$databasePort)) {
                  self::$bootStrapLogger->debug("MySQL connection did not specify a port.  Using 3306 as defualt");
                  self::$databasePort=3306;
              }
              // Connect via TCP to specified port and pass a 'null' for database name.
              // We specify the database name in a different call, ie 'mysqli_select_db()' just below here
              self::$bootStrapLogger->debug("Connectiong to ". self::$databaseServerName . " on port " . self::$databasePort . " as " . self::$databaseUser);
              $cnInfoCentral = mysqli_connect(self::$databaseServerName, self::$databaseUser, self::$databasePassword, null, self::$databasePort);
          }
          self::testMYSQLI();
          mysqli_set_charset($cnInfoCentral, 'utf8mb4');
          self::$bootStrapLogger->debug("Selecting database: " . self::$databaseName);
          mysqli_select_db($cnInfoCentral, self::$databaseName)
      or Bootstrapper::system_failure('Could not connect to the MySQL database <strong>'.self::$databaseName.'</strong>. Please check the settings in <strong>Include/Config.php</strong>.<br/>MySQL Error: '.mysqli_error($cnInfoCentral));
          self::$bootStrapLogger->debug("Database selected: " . self::$databaseName);
      }
      private static function testMYSQLI()
      {
          global $cnInfoCentral; // need to stop using this everywhere....
          // Do we have a connection to the database? If not, log it and tell the user
          if (!$cnInfoCentral) {
              // Sanitise the mysqli_connect_error if required.
              $sMYSQLERROR="none captured";
              if (strlen(mysqli_connect_error())>0) {
                  $sMYSQLERROR=mysqli_connect_error();
              }
              // If connecting via a socket, convert self::$databasePort to something sensible.
              if (self::$databaseServerName == "localhost") {
                  self::$databasePort = "Unix socket";
              }
              // Need to initialise otherwise logging etc will fail!
              if (!SystemConfig::isInitialized()) {
                  SystemConfig::init();
              }
              // Log the error to the application log, and show an error page to user.
              LoggerUtils::getAppLogger()->error("ERROR connecting to database at '".self::$databaseServerName."' on port '".self::$databasePort."' as user '".self::$databaseUser."' -  MySQL Error: '".$sMYSQLERROR."'");
              Bootstrapper::system_failure('Could not connect to MySQL on <strong>'.self::$databaseServerName.'</strong> on port <strong>'.self::$databasePort.'</strong> as <strong>'.self::$databaseUser.'</strong>. Please check the settings in <strong>Include/Config.php</strong>.<br/>MySQL Error: '.$sMYSQLERROR, 'Database Connection Failure');
          }
      }
      private static function initPropel()
      {
          self::$bootStrapLogger->debug("Initializing Propel ORM");
          // ==== ORM
          self::$dbClassName = "\\Propel\\Runtime\\Connection\\ConnectionWrapper";
          self::$serviceContainer = Propel::getServiceContainer();
          self::$serviceContainer->checkVersion('2.0.0-dev');
          self::$serviceContainer->setAdapterClass('default', 'mysql');
          self::$manager = new ConnectionManagerSingle();
          self::$manager->setConfiguration(self::buildConnectionManagerConfig());
          self::$manager->setName('default');
          self::$serviceContainer->setConnectionManager('default', self::$manager);
          self::$serviceContainer->setDefaultDatasource('default');
          self::$bootStrapLogger->debug("Initialized Propel ORM");
      }
      private static function isDatabaseEmpty()
      {
          self::$bootStrapLogger->debug("Checking for ChurchCRM Datbase tables");
          $connection = Propel::getConnection();
          $query = "SHOW TABLES FROM `".self::$databaseName."`";
          $statement = $connection->prepare($query);
          $resultset = $statement->execute();
          $results = $statement->fetchAll(\PDO::FETCH_ASSOC);
          if (count($results) == 0) {
              self::$bootStrapLogger->debug("No database tables found");
              return true;
          }
          self::$bootStrapLogger->debug("Found " . count($results) . " Database tables");
          return false;
      }
      private static function installChurchCRMSchema()
      {
          self::$bootStrapLogger->info("Installing ChurchCRM Schema");
          $connection = Propel::getConnection();
          $version = new Version();
          $version->setVersion(SystemService::getInstalledVersion());
          $version->setUpdateStart(new \DateTime());
          SQLUtils::sqlImport(SystemURLs::getDocumentRoot().'/mysql/install/Install.sql', $connection);
          $version->setUpdateEnd(new \DateTime());
          $version->save();
          self::$bootStrapLogger->info("Installed ChurchCRM Schema version: " . SystemService::getInstalledVersion());
      }
      public static function initSession()
      {
          // Initialize the session
          $sessionName = 'CRM@'.SystemURLs::getRootPath();
          session_cache_limiter('private_no_expire:');
          session_name($sessionName);
          session_start();
          self::$bootStrapLogger->debug("Session initialized: " . $sessionName);
      }
      private static function configureLogging()
      {

       // PHP Logs
          $phpLogPath = LoggerUtils::buildLogFilePath("php");
          self::$bootStrapLogger->debug("Configuring PHP logs at :" .$phpLogPath);
          ini_set('log_errors', 1);
          ini_set('error_log', $phpLogPath);

          // ORM Logs
          $ormLogPath = LoggerUtils::buildLogFilePath("orm");
          $ormLogger = new Logger('ormLogger');
          self::$bootStrapLogger->debug("Configuring ORM logs at :" .$ormLogPath);
          self::$dbClassName = "\\Propel\\Runtime\\Connection\\DebugPDO";
          self::$manager->setConfiguration(self::buildConnectionManagerConfig());
          $ormLogger->pushHandler(new StreamHandler($ormLogPath, LoggerUtils::getLogLevel()));
          self::$serviceContainer->setLogger('defaultLogger', $ormLogger);
      }

      public static function GetDSN()
      {
          return 'mysql:host=' . self::$databaseServerName . ';port='.self::$databasePort.';dbname=' . self::$databaseName;
      }

      private static function buildConnectionManagerConfig()
      {
          if (is_null(self::$databasePort)) {
              self::$databasePort = 3306;
          }
          return [
            'dsn' => Bootstrapper::GetDSN(),
            'user' => self::$databaseUser,
            'password' => self::$databasePassword,
            'settings' => [
                'charset' => 'utf8mb4',
                'queries' => ["SET sql_mode=(SELECT REPLACE(REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''),'NO_ZERO_DATE',''))"],
            ],
            'classname' => self::$dbClassName,
            'model_paths' => [
                0 => 'src',
                1 => 'vendor',
            ],
        ];
      }
      private static function configureUserEnvironment()  // TODO: This function needs to stop creating global variable-variables.
      {
          global $cnInfoCentral;
          if (AuthenticationManager::ValidateUserSessionIsActive(false)) {      // set on POST to /session/begin
              // Load user variables from user config table.
              // **************************************************
              $sSQL = 'SELECT ucfg_name, ucfg_value AS value '
              ."FROM userconfig_ucfg WHERE ucfg_per_ID='".AuthenticationManager::GetCurrentUser()->getId()."'";
              $rsConfig = mysqli_query($cnInfoCentral, $sSQL);     // Can't use RunQuery -- not defined yet
              if ($rsConfig) {
                  while (list($ucfg_name, $value) = mysqli_fetch_row($rsConfig)) {
                      //TODO:  THESE Variable-Variables must go awawy
                      // VV's will not work when set here; so all must be refactored away in all use cases throughout the code.
                      $$ucfg_name = $value;
                      $_SESSION[$ucfg_name] = $value;
                  }
              }
          }
      }
      private static function system_failure($message, $header = 'Setup failure')
      {
          $sPageTitle = $header;
          if (!SystemConfig::isInitialized()) {
              SystemConfig::init();
          }
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
      public static function isDBCurrent()
      {
          if (SystemService::getDBVersion() == SystemService::getInstalledVersion()) {
              self::$bootStrapLogger->debug("Database version matches installed version: " . SystemService::getDBVersion(). " == " .SystemService::getInstalledVersion());
              return true;
          } else {
              self::$bootStrapLogger->debug("Database version does not match installed version: " . SystemService::getDBVersion(). " == " .SystemService::getInstalledVersion());
              return false;
          }
      }
  }
}
