<?php

/*******************************************************************************
 *
 *  filename    : Include/LoadConfigs.php
 *  website     : https://churchcrm.io
 *  description : global configuration
 *                   The code in this file used to be part of part of Config.php
 *
 *  Copyright 2001-2005 Phillip Hullquist, Deane Barker, Chris Gebhardt,
 *                      Michael Wilt, Timothy Dearborn
 *
 *******************************************************************************/

require_once __DIR__ . '/../vendor/autoload.php';

use ChurchCRM\Bootstrapper;
use ChurchCRM\KeyManager;

// enable this line to debug the bootstrapper process (database connections, etc).
// this makes a lot of log noise, so don't leave it on for normal production use.
//$debugBootstrapper = true;
Bootstrapper::init($sSERVERNAME, $dbPort, $sUSER, $sPASSWORD, $sDATABASE, $sRootPath, $bLockURL, $URL);
KeyManager::init($TwoFASecretKey);
