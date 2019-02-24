<?php
/*****************************************************************************
 *
 *  VAGRANT settings
 *
 ****************************************************************************/

// Database connection constants
$sSERVERNAME = 'mysql';
$sUSER = 'root';
$sPASSWORD = 'churchcrm';
$sDATABASE = 'churchcrm_test';
$sRootPath = '';
$bLockURL = false;
$URL[0] = 'http://web_server/';

// Sets which PHP errors are reported see http://php.net/manual/en/errorfunc.constants.php
error_reporting(E_ERROR);
//error_reporting(E_ALL);

require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'LoadConfigs.php';
