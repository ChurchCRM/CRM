<?php

function loader($class)
{
    $file = $class . '.php';
    if (file_exists($file)) {
        require $file;
    }
    
}

global $bSuppressSessionTests,$sSERVERNAME,$sUSER,$sPASSWORD,$sDATABASE, $cnInfoCentral,$db_username,$db_password;

ini_set('error_reporting', E_ALL ^ E_DEPRECATED); // or error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
$bSuppressSessionTests = TRUE;
$sSERVERNAME = 'localhost';
$sUSER = $db_username;
$sPASSWORD = $db_password;
$sDATABASE = 'churchcrm_test';
$sRootPath = '/src';
require "./src/Include/LoadConfigs.php";
require "./src/Include/Functions.php";
require './src/Service/SystemService.php';
spl_autoload_register('loader');

