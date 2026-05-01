<?php

require_once __DIR__ . '/../vendor/autoload.php';

use ChurchCRM\Bootstrapper;
use ChurchCRM\Config\ConfigLoader;
use ChurchCRM\Utils\KeyManagerUtils;
use ChurchCRM\dto\SystemConfig;

/**
 * Safely loads and validates Config.php with graceful handling for missing configuration.
 * Redirects to setup if Config.php doesn't exist.
 * This file should be used by all Slim application entry points.
 */
if (!file_exists(__DIR__ . '/Config.php')) {
    header('Location: ../setup');
    exit;
}

// Load and validate configuration
try {
    $config = ConfigLoader::loadFromConfigPhp(__DIR__ . '/Config.php');
    $sSERVERNAME = $config->getDbServerName();
    $dbPort = $config->getDbServerPort();
    $sUSER = $config->getDbUser();
    $sPASSWORD = $config->getDbPassword();
    $sDATABASE = $config->getDbName();
    $sRootPath = $config->getRootPath();
    $URL = [$config->getUrl()];
} catch (RuntimeException $e) {
    header('Location: ../config-error.php?error=' . urlencode($e->getMessage()));
    exit;
}

// Enable this line to debug the bootstrapper process (database connections, etc).
// this makes a lot of log noise, so don't leave it on for normal production use.
//$debugBootstrapper = true;
Bootstrapper::init($sSERVERNAME, $dbPort, $sUSER, $sPASSWORD, $sDATABASE, $sRootPath, $bLockURL, $URL);

// Initialize KeyManager with 2FA secret from SystemConfig
$twoFASecretKey = SystemConfig::getValue('sTwoFASecretKey');

// Auto-generate encryption key if not yet set (required for 2FA enrollment)
if (empty($twoFASecretKey)) {
    $twoFASecretKey = bin2hex(random_bytes(32));
    SystemConfig::setValue('sTwoFASecretKey', $twoFASecretKey);
}

KeyManagerUtils::init($twoFASecretKey);
