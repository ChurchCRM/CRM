<?php

// Load composer autoloader first so we can use VersionUtils utility
require_once __DIR__ . '/vendor/autoload.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\MiscUtils;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\Utils\VersionUtils;

// Get required PHP version from composer.json (single source of truth)
// Throws RuntimeException if system state cannot be determined
try {
    $requiredPhp = VersionUtils::getRequiredPhpVersion();
} catch (\RuntimeException $e) {
    // System cannot determine PHP requirements - fail loudly with clear error
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo"Critical System Error:" . $e->getMessage() ."\n\n";
    echo"Please contact your system administrator or check your ChurchCRM installation.";
    exit(1);
}

$phpVersion = phpversion();
if (version_compare($phpVersion, $requiredPhp, '<')) {
    header('Location: php-error.php');
    exit;
}

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
// Strip query string from shortName so file lookups work correctly
$shortName = explode('?', $shortName)[0];
$fileName = MiscUtils::dashesToCamelCase($shortName, true) . '.php';

// Guard against redirect loops when a reverse proxy (e.g. FrankenPHP without a
// custom Caddyfile) forwards Slim sub-application requests to this file instead of
// the sub-app's own index.php.  Without this check, an unauthenticated request for
// /session/begin would cause ensureAuthentication() to redirect back to /session/begin
// indefinitely ("too many redirects").
//
// Strategy: if the first path segment resolves to a directory that contains its own
// index.php entry point, delegate to that entry point instead of running the legacy
// router.  This automatically covers all current and future sub-apps without a
// hard-coded allow-list that must be kept in sync.
$firstSegment = explode('/', trim($shortName, '/'))[0] ?? '';
if ($firstSegment !== '') {
    $subAppIndex = __DIR__ . '/' . $firstSegment . '/index.php';
    if (is_dir(__DIR__ . '/' . $firstSegment) && file_exists($subAppIndex)) {
        // Delegate to the sub-app's Slim entry point — it manages its own auth.
        require $subAppIndex;
        exit;
    }
}

// First, ensure that the user is authenticated.
AuthenticationManager::ensureAuthentication();

// On a fresh install (sChurchName empty), redirect admin users to complete setup.
if (empty(SystemConfig::getValue('sChurchName'))) {
    try {
        $currentUser = AuthenticationManager::getCurrentUser();
        if ($currentUser->isAdmin()) {
            RedirectUtils::redirect('admin/system/church-info');
        }
    } catch (\Throwable) {
        // Not logged in or session error — ensureAuthentication() above handles it
    }
}

if (strtolower($shortName) === 'index.php' || strtolower($fileName) === 'index.php') {
    // Index.php -> v2/dashboard
    RedirectUtils::redirect('v2/dashboard');
} elseif (is_file($shortName)) {
    // Try actual path
    require $shortName;
} elseif (file_exists($fileName)) {
    // Try magic filename
    require $fileName;
} elseif (strpos($_SERVER['REQUEST_URI'], 'js') || strpos($_SERVER['REQUEST_URI'], 'css')) { // if this is a CSS or JS file that we can't find, return 404
    header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found', true, 404);
    exit;
} else {
    RedirectUtils::redirect('index.php');
}
