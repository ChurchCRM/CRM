<?php

// Load composer autoloader first so we can use VersionUtils utility
require_once __DIR__ . '/vendor/autoload.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\PathUtils;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\Utils\VersionUtils;

// Derive install prefix from SCRIPT_NAME — works for root and nested subdir installs.
// Computed before any redirect so the config-error, PHP-version, and setup
// redirects below all resolve correctly.
//   /churchcrm/index.php  →  dirname → /churchcrm
//   /index.php            →  dirname → /  → ''
$_idx_script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
$_idx_root   = dirname($_idx_script);
if ($_idx_root === '/' || $_idx_root === '.') {
    $_idx_root = '';
}

// Get required PHP version from composer.json (single source of truth)
// Throws RuntimeException if system state cannot be determined
try {
    $requiredPhp = VersionUtils::getRequiredPhpVersion();
} catch (\RuntimeException $e) {
    header("Location: {$_idx_root}/errors/config-error.php?error=" . rawurlencode($e->getMessage()));
    exit;
}

$phpVersion = phpversion();
if (version_compare($phpVersion, $requiredPhp, '<')) {
    header("Location: {$_idx_root}/errors/php-error.php");
    exit;
}

if (file_exists(__DIR__ . '/Include/Config.php')) {
    require_once __DIR__ . '/Include/Config.php';
} else {
    header("Location: {$_idx_root}/setup");
    exit;
}

// Get the current request path with query string stripped.
$_idx_requestPath = strtok($_SERVER['REQUEST_URI'], '?');
$shortName = str_replace(SystemURLs::getRootPath() . '/', '', $_idx_requestPath);

// First, ensure that the user is authenticated.
AuthenticationManager::ensureAuthentication();

// On a fresh install (sChurchName empty), redirect admin users to complete setup.
// getCurrentUser() is safe to call unguarded here: ensureAuthentication() above
// already guarantees an authenticated session, or it would have redirected/exited.
if (empty(SystemConfig::getValue('sChurchName'))) {
    $currentUser = AuthenticationManager::getCurrentUser();
    if ($currentUser->isAdmin()) {
        RedirectUtils::redirect('admin/system/church-info');
    }
}

if ($shortName === '' || strtolower($shortName) === 'index.php') {
    RedirectUtils::redirect('v2/dashboard');
} elseif (($_idx_safeShortPath = PathUtils::resolveSafeRequirePath($shortName)) !== null) {
    require $_idx_safeShortPath;
} elseif (in_array(strtolower(pathinfo($shortName, PATHINFO_EXTENSION)), ['js', 'css'], true)) {
    // Missing static asset (e.g. a stale webpack chunk hash after a deploy) —
    // bare 404, no need for a full error page.
    http_response_code(404);
    exit;
} else {
    // Self-contained error page (see src/errors/.htaccess) — no Header/Footer
    // or session/DB state required, same pattern as php-error.php and setup.
    RedirectUtils::redirect('errors/not-found.php?path=' . rawurlencode($shortName));
}
