<?php

// Load composer autoloader first so we can use VersionUtils utility
require_once __DIR__ . '/vendor/autoload.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
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

// Derive install prefix from SCRIPT_NAME — works for root and nested subdir installs.
// Computed before any redirect so both the PHP-version and setup redirects resolve correctly.
//   /churchcrm/index.php  →  dirname → /churchcrm
//   /index.php            →  dirname → /  → ''
$_idx_script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
$_idx_root   = dirname($_idx_script);
if ($_idx_root === '/' || $_idx_root === '.') {
    $_idx_root = '';
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

mb_internal_encoding('UTF-8');

// Resolve $candidate against $docRoot and return the real, on-disk path only if
// it exists as a regular file *inside* $docRoot. Returns null for anything that
// escapes $docRoot (e.g. "../" traversal) or doesn't resolve to a real file.
function _idx_resolveSafeRequirePath(string $candidate, string $docRoot): ?string
{
    $docRootReal = realpath($docRoot);
    if ($docRootReal === false) {
        return null;
    }

    $candidateReal = realpath($docRoot . '/' . $candidate);
    if ($candidateReal === false || !is_file($candidateReal)) {
        return null;
    }

    if (!str_starts_with($candidateReal, $docRootReal . DIRECTORY_SEPARATOR)) {
        return null;
    }

    return $candidateReal;
}

// Get the current request path with query string stripped.
$_idx_requestPath = strtok($_SERVER['REQUEST_URI'], '?');
$shortName = str_replace(SystemURLs::getRootPath() . '/', '', $_idx_requestPath);

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

// Legacy URL shims — redirect removed pages to their MVC replacements.
// $shortName is already the path-only portion, so bookmarks like
// /UserEditor.php?PersonID=5 resolve correctly.
$_legacyBase = $shortName;
if ($_legacyBase === 'UserEditor.php') {
    if (!empty($_GET['PersonID'])) {
        RedirectUtils::redirect('admin/system/users/' . (int) $_GET['PersonID'] . '/edit');
    } elseif (!empty($_GET['NewPersonID'])) {
        RedirectUtils::redirect('admin/system/users/new?personId=' . (int) $_GET['NewPersonID']);
    } else {
        RedirectUtils::redirect('admin/system/users/new');
    }
} elseif ($_legacyBase === 'SettingsUser.php') {
    RedirectUtils::redirect('admin/system/users');
}
unset($_legacyBase);

$_idx_docRoot = SystemURLs::getDocumentRoot();
$_idx_safeShortPath = _idx_resolveSafeRequirePath($shortName, $_idx_docRoot);

if (strtolower($shortName) === 'index.php') {
    RedirectUtils::redirect('v2/dashboard');
} elseif ($_idx_safeShortPath !== null) {
    require $_idx_safeShortPath;
} elseif (strpos($_SERVER['REQUEST_URI'], 'js') || strpos($_SERVER['REQUEST_URI'], 'css')) {
    header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found', true, 404);
    exit;
} else {
    RedirectUtils::redirect('index.php');
}
