<?php
/*******************************************************************************
 *
 *  filename    : .ddev/Config.ddev.php
 *  website     : https://churchcrm.io
 *  description : ChurchCRM configuration for DDEV local development
 *
 *  This file is copied to src/Include/Config.php automatically by the
 *  DDEV post-start hook defined in .ddev/config.yaml.
 *
 ******************************************************************************/

// DDEV database connection — default DDEV MariaDB credentials
$sSERVERNAME = 'db';
$dbPort = '3306';
$sUSER = 'db';
$sPASSWORD = 'db';
$sDATABASE = 'db';

// Root path — top-level installation (no subdirectory)
$sRootPath = '';

// Lock URL enforcement disabled for local development
$bLockURL = false;

// Primary URL — matches the DDEV project name 'churchcrm'
// Access both http:// and https:// via DDEV's built-in router
$URL[0] = 'https://churchcrm.ddev.site/';
$URL[1] = 'http://churchcrm.ddev.site/';

// PHP error reporting for development
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

//
// SETTINGS END HERE.  DO NOT MODIFY BELOW THIS LINE
//
// Absolute path must be specified since this file is called
// from scripts located in other directories
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'LoadConfigs.php';
