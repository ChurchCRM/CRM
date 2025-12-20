<?php

require_once __DIR__ . '/../vendor/autoload.php';

use ChurchCRM\Bootstrapper;
use ChurchCRM\KeyManager;
use ChurchCRM\dto\SystemConfig;

// Enable this line to debug the bootstrapper process (database connections, etc).
// this makes a lot of log noise, so don't leave it on for normal production use.
//$debugBootstrapper = true;
Bootstrapper::init($sSERVERNAME, $dbPort, $sUSER, $sPASSWORD, $sDATABASE, $sRootPath, $bLockURL, $URL);

// Initialize KeyManager with 2FA secret from SystemConfig
$twoFASecretKey = SystemConfig::getValue('sTwoFASecretKey');
KeyManager::init($twoFASecretKey);
