<?php

$sSERVERNAME = getenv('SHEPHERD_DB_HOST') ?: 'database';
$dbPort = getenv('SHEPHERD_DB_PORT') ?: '3306';
$sUSER = getenv('SHEPHERD_DB_USER') ?: 'shepherd';
$sPASSWORD = getenv('SHEPHERD_DB_PASSWORD') ?: '';
$sDATABASE = getenv('SHEPHERD_DB_NAME') ?: 'shepherd';
$sRootPath = getenv('SHEPHERD_ROOT_PATH') === false ? '/shepherd' : getenv('SHEPHERD_ROOT_PATH');
$bLockURL = false;
$publicUrl = rtrim(getenv('SHEPHERD_PUBLIC_URL') ?: 'https://elkinsparkchurch.com', '/');
$URL[0] = $publicUrl . $sRootPath . '/';
error_reporting(E_ERROR);

require_once __DIR__ . '/LoadConfigs.php';
