<?php

$phpVersion = phpversion();
if (version_compare($phpVersion, '8.1.0', '<=')) {
    $redirectHeader = 'Location: php-error.html';
    if ($phpVersion) {
        header('X-PHP-Version: ' . $phpVersion);
        $redirectHeader .= '?phpVersion=' . $phpVersion;
    }
    header($redirectHeader);

    exit;
}

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\MiscUtils;

header('CRM: would redirect');

if (file_exists('Include/Config.php')) {
    require_once 'Include/Config.php';
} else {
    header('Location: setup');
    exit;
}

/* Set internal character encoding to UTF-8 */
mb_internal_encoding('UTF-8');

// Get the current request path and convert it into a magic filename
// e.g. /list-events => /ListEvents.php
$shortName = str_replace(SystemURLs::getRootPath() . '/', '', $_SERVER['REQUEST_URI']);
$fileName = MiscUtils::dashesToCamelCase($shortName, true) . '.php';

if (!empty($_GET['location'])) {
    $_SESSION['location'] = $_GET['location'];
}

// First, ensure that the user is authenticated.
AuthenticationManager::ensureAuthentication();

if (strtolower($shortName) === 'index.php' || strtolower($fileName) === 'index.php') {
    // Index.php -> Menu.php
    header('Location: ' . SystemURLs::getRootPath() . '/Menu.php');
    exit;
} elseif (file_exists($shortName)) {
    // Try actual path
    require $shortName;
} elseif (file_exists($fileName)) {
    // Try magic filename
    require $fileName;
} elseif (strpos($_SERVER['REQUEST_URI'], 'js') || strpos($_SERVER['REQUEST_URI'], 'css')) { // if this is a CSS or JS file that we can't find, return 404
    header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found', true, 404);
    exit;
} else {
    header('Location: index.php');
    exit;
}
