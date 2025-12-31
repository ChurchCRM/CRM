<?php

// Load composer autoloader first so we can use PhpVersion utility
require_once __DIR__ . '/vendor/autoload.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\MiscUtils;
use ChurchCRM\Utils\PhpVersion;

// Get required PHP version from composer.json (single source of truth)
// Throws RuntimeException if system state cannot be determined
try {
    $requiredPhp = PhpVersion::getRequiredPhpVersion();
} catch (\RuntimeException $e) {
    // System cannot determine PHP requirements - fail loudly with clear error
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Critical System Error: " . $e->getMessage() . "\n\n";
    echo "Please contact your system administrator or check your ChurchCRM installation.";
    exit(1);
}

$phpVersion = phpversion();
if (version_compare($phpVersion, $requiredPhp, '<')) {
    header('Location: php-error.php');
    exit;
}

header('CRM: would redirect');

if (file_exists('Include/Config.php')) {
    require_once __DIR__ . '/Include/Config.php';
} else {
    header('Location: setup');
    exit;
}

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
    // Index.php -> v2/dashboard
    header('Location: ' . SystemURLs::getRootPath() . '/v2/dashboard');
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
