<?php

use ChurchCRM\dto\SystemURLs;

header("CRM: would redirect");

if (file_exists('Include/Config.php')) {
    require_once 'Include/Config.php';
} else {
    header('Location: setup');
    exit();
}

function dashesToCamelCase($string, $capitalizeFirstCharacter = false)
{
    $str = str_replace(' ', '', ucwords(str_replace('-', ' ', $string)));

    if (!$capitalizeFirstCharacter) {
        $str[0] = strtolower($str[0]);
    }

    return $str;
}

function endsWith($haystack, $needle)
{
    // search forward starting from end minus needle length characters
    return $needle === '' || (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== false);
}

$hasSession = isset($_SESSION['user']);
$redirectTo = ($hasSession) ? '/menu' : '/login';

// Get the current request path and convert it into a magic filename
// e.g. /list-events => /ListEvents.php
$shortName = str_replace(SystemURLs::getRootPath().'/', '', $_SERVER['REQUEST_URI']);
$fileName = dashesToCamelCase($shortName, true).'.php';

if (strtolower($shortName) == 'index.php' || strtolower($fileName) == 'index.php') {
    // Index.php -> Menu.php or Login.php
    header('Location: '.SystemURLs::getRootPath().$redirectTo);
    exit;
} elseif (!$hasSession) {
    // Must show login form if no session
    require 'Login.php';
} elseif (file_exists($shortName)) {
    // Try actual path
    require $shortName;
} elseif (file_exists($fileName)) {
    // Try magic filename
    require $fileName;
} elseif (strpos($_SERVER['REQUEST_URI'], 'js') || strpos($_SERVER['REQUEST_URI'], 'css')) { // if this is a CSS or JS file that we can't find, return 404
    header($_SERVER['SERVER_PROTOCOL'].' 404 Not Found', true, 404);
    exit;
} else {
    header('Location: index.php');
    exit;
}
