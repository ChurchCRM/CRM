<?php
/*******************************************************************************
 *
 *  filename    : Include/Config.php
 *  website     : https://churchcrm.io
 *  description : global configuration
 *
 ******************************************************************************/

// Database connection constants
$sSERVERNAME = 'database';
$dbPort = '3306';
$sUSER = 'churchcrm';
$sPASSWORD = 'changeme';
$sDATABASE = 'churchcrm';

// Root path of your ChurchCRM installation ( THIS MUST BE SET CORRECTLY! )
//
// Examples:
// - if you will be accessing from http://www.yourdomain.com/churchcrm then you would enter '/churchcrm' here.
// - if you will be accessing from http://www.yourdomain.com then you would enter '' ... an empty string for a top level installation.
//
// NOTE:
// - the path SHOULD Start with slash, if not ''.
// - the path SHOULD NOT end with slash.
// - the is case sensitive.
$sRootPath = '';

// Primary URL for user access (must include https://, domain, and trailing slash)
$URL[0] = 'http://localhost/';

// Optional: Alternate URLs (uncomment if using multiple domains/ports)
// Alternate URLs are only enforced when URL locking is enabled in System Config admin settings
// Format: https://domain/path/ (with trailing slash)
//$URL[1] = 'https://www.mychurch.org:8080/churchcrm/';
//$URL[2] = 'https://crm.mychurch.org/';

// PHP error reporting level
error_reporting(E_ERROR);

//
// SETTINGS END HERE.  DO NOT MODIFY BELOW THIS LINE
//
// Absolute path must be specified since this file is called
// from scripts located in other directories
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'LoadConfigs.php';
