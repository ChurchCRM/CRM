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

// Derive the install prefix from SCRIPT_NAME so redirects work for root installs
// and subdirectory installs of any depth (e.g. /apps/churchcrm/api/index.php).
// SCRIPT_NAME is set by the web server to the path of the executing script:
//   /churchcrm/api/index.php  →  dirname → /churchcrm/api  →  dirname → /churchcrm
//   /api/index.php            →  dirname → /api             →  dirname → /  → ''
$_crm_script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
$_crm_root   = dirname(dirname($_crm_script));
if ($_crm_root === '/' || $_crm_root === '.') {
    $_crm_root = '';
}

if (!file_exists(__DIR__ . '/Config.php')) {
    header("Location: {$_crm_root}/setup");
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
    $URL = $config->getUrls();
} catch (RuntimeException $e) {
    $errorParam = urlencode($e->getMessage());
    header("Location: {$_crm_root}/errors/config-error.php?error={$errorParam}");
    exit;
}

// Lock URL setting is admin-configurable via SystemConfig (defaults to false)
$bLockURL = false;

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
