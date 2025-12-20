<?php
/*******************************************************************************
 *
 *  filename    : Include/Config.php
 *  website     : https://churchcrm.io
 *  description : global configuration (subdirectory install template)
 *
 ******************************************************************************/

// Database connection constants
$sSERVERNAME = 'database';
$dbPort = '3306';
$sUSER = 'churchcrm';
$sPASSWORD = 'changeme';
$sDATABASE = 'churchcrm';

// Root path of your ChurchCRM installation for subdirectory deployments
$sRootPath = '/churchcrm';

// Set $bLockURL=TRUE to enforce https access by specifying exactly
// which URL's your users may use to log into ChurchCRM.
$bLockURL = false;

// URL[0] is the URL that you prefer most users use when they
// log in.  These are case sensitive.
$URL[0] = 'http://localhost/churchcrm/';

// Sets which PHP errors are reported see http://php.net/manual/en/errorfunc.constants.php
error_reporting(E_ERROR);

//
// SETTINGS END HERE.  DO NOT MODIFY BELOW THIS LINE
//
// Absolute path must be specified since this file is called
// from scripts located in other directories
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'LoadConfigs.php';
