<?php
/*******************************************************************************
 *
 *  filename    : Include/Config.php
 *  website     : http://www.churchdb.org
 *  description : global configuration
 *
 ******************************************************************************/

// Database connection constants
$sSERVERNAME = 'localhost';
$sUSER = 'churchcrm';
$sPASSWORD = 'churchcrm';
$sDATABASE = 'churchcrm';

// Root path of your ChurchCRM installation ( THIS MUST BE SET CORRECTLY! )
// For example, if you will be accessing from http://www.yourdomain.com/web/churchcrm
// then you would enter '/web/churchcrm' here.
// Another example, if you will be accessing from http://www.yourdomain.com
// then you would enter '' ... an empty string for a top level installation.
// This path SHOULD NOT end with slash.  This is case sensitive.
$sRootPath = '';

// Set $bLockURL=TRUE to enforce https access by specifying exactly
// which URL's your users may use to log into ChurchCRM.
$bLockURL = TRUE;

// URL[0] is the URL that you prefer most users use when they
// log in.  These are case sensitive.  Only used when $bLockURL = TRUE
$URL[0] = 'http://192.168.33.10/';
// List as many other URL's as may be needed. Number them sequentially.
//$URL[1] = 'https://www.mychurch.org/churchcrm/';
//$URL[2] = 'https://www.mychurch.org:8080/churchcrm/';
//$URL[3] = 'https://www.mychurch.org/churchcrm/';
//$URL[4] = 'https://ssl.sharedsslserver.com/mychurch.org/churchcrm/';
//$URL[5] = 'https://crm.mychurch.org/';

// If you are using a non-standard port number be sure to include the
// port number in the URL. See example $URL[2]

// To enforce https access make sure that "https" is specified in all of the
// the allowable URLs.  Safe exceptions are when you are at the local machine
// and using "localhost" or "127.0.0.1"

// When using a shared SSL certificate provided by your webhost for https access
// you may need to add the shared SSL server name as well as your host name to
// the URL.  See example $URL[4]

// Set error reporting

// Sets which PHP errors are reported see http://php.net/manual/en/errorfunc.constants.php
error_reporting(E_ERROR);

// Rather than display errors on the screen it is more secure to
// send error messages to a file.  Make sure that your web
// server has permission to write to this file.
// Warning: The error_log file can grow very large over time.
// ini_set('log_errors', 1);
// ini_set('error_log','/tmp/churchCRM.log');

//
// SETTINGS END HERE.  DO NOT MODIFY BELOW THIS LINE
//
// Absolute path must be specified since this file is called
// from scripts located in other directories
require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'LoadConfigs.php');
?>
