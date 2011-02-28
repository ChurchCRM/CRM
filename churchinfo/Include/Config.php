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
$sUSER = 'churchinfo';
$sPASSWORD = 'churchinfo';
$sDATABASE = 'churchinfo';

// Root path of your ChurchInfo installation ( THIS MUST BE SET CORRECTLY! )
// For example, if you will be accessing from http://www.yourdomain.com/web/churchinfo
// then you would enter '/web/churchinfo' here.
// Another example, if you will be accessing from http://www.yourdomain.com
// then you would enter '' ... an empty string for a top level installation.
// This path SHOULD NOT end with slash.  This is case sensitive.
$sRootPath = '/churchinfo';

// Set $bLockURL=TRUE to enforce https access by specifying exactly
// which URL's your users may use to log into ChurchInfo.
$bLockURL = FALSE;

// URL[0] is the URL that you prefer most users use when they
// log in.  These are case sensitive.  Only used when $bLockURL = TRUE
$URL[0] = 'https://mychurch.org/churchinfo/Default.php';
// List as many other URL's as may be needed. Number them sequentially.
//$URL[1] = 'http://localhost/churchinfo/Default.php';
//$URL[2] = 'http://localhost:8080/churchinfo/Default.php';
//$URL[3] = 'http://127.0.0.1/churchinfo/Default.php';
//$URL[4] = 'https://www.mychurch.org/churchinfo/Default.php';
//$URL[5] = 'https://mychurch.org/churchinfo/Default.php';
//$URL[6] = 'https://ssl.sharedsslserver.com/mychurch.org/churchinfo/Default.php';

// If you are using a non-standard port number be sure to include the 
// port number in the URL. See example $URL[2]

// To enforce https access make sure that "https" is specified in all of the
// the allowable URLs.  Safe exceptions are when you are at the local machine
// and using "localhost" or "127.0.0.1"

// When using a shared SSL certificate provided by your webhost for https access
// you may need to add the shared SSL server name as well as your host name to
// the URL.  See example $URL[6]


// Set error reporting

// Turn off all error reporting
error_reporting(0);

// Turn on all error reporting
// error_reporting(-1);

// Report all errors except E_NOTICE
// error_reporting(E_STRICT & E_ALL & ~E_NOTICE);

// For security it is good practice to avoid displaying error messages to users.
// While debugging you may temporarily use ini_set('display_errors', 1)
ini_set('display_errors', 0);

// Rather than display errors on the screen it is more secure to
// send error messages to a file.  Make sure that your web 
// server has permission to write to this file.
// Warning: The error_log file can grow very large over time.
// ini_set('log_errors', 1);
// ini_set('error_log','/tmp/churchinfo.log');

//
// SETTINGS END HERE.  DO NOT MODIFY BELOW THIS LINE
//

// Absolute path must be specified since this file is called 
// from scripts located in other directories
require (dirname(__FILE__).DIRECTORY_SEPARATOR.'LoadConfigs.php');
?>
