<?php

require_once __DIR__ . '/../vendor/autoload.php';

use ChurchCRM\Bootstrapper;
use ChurchCRM\KeyManager;
use ChurchCRM\dto\SystemConfig;

/**
 * Safely loads Config.php with graceful handling for missing configuration.
 * Redirects to setup if Config.php doesn't exist.
 * This file should be used by all Slim application entry points.
 */
if (!file_exists(__DIR__ . '/Config.php')) {
    header('Location: ../setup');
    exit;
}

require_once __DIR__ . '/Config.php';

// Enable this line to debug the bootstrapper process (database connections, etc).
// this makes a lot of log noise, so don't leave it on for normal production use.
//$debugBootstrapper = true;
Bootstrapper::init($sSERVERNAME, $dbPort, $sUSER, $sPASSWORD, $sDATABASE, $sRootPath, $bLockURL, $URL);

// Initialize KeyManager with 2FA secret from SystemConfig
$twoFASecretKey = SystemConfig::getValue('sTwoFASecretKey');
KeyManager::init($twoFASecretKey);
