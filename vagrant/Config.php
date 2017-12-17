<?php
/*******************************************************************************
 *
 *  VAGRANT settings
 *
 ******************************************************************************/

// Database connection constants
$sSERVERNAME = 'localhost';
$sUSER = 'churchcrm';
$sPASSWORD = 'churchcrm';
$sDATABASE = 'churchcrm';
$sRootPath = '';
$bLockURL = false;
$URL[0] = 'http://192.168.33.10/';

// Sets which PHP errors are reported see http://php.net/manual/en/errorfunc.constants.php
error_reporting(E_ERROR);
//error_reporting(E_ALL);

require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'LoadConfigs.php';
