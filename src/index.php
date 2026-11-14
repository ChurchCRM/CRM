<?php

// Load composer autoloader first so we can use VersionUtils utility
require_once __DIR__ . '/vendor/autoload.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Portal\Auth\PortalSessionStore;
use ChurchCRM\Slim\SlimUtils;
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
// Strip the install-root prefix to derive the app-relative path.
$_idx_rootPrefix = SystemURLs::getRootPath() . '/';
$shortName = str_starts_with($_idx_requestPath, $_idx_rootPrefix)
    ? substr($_idx_requestPath, strlen($_idx_rootPrefix))
    : substr($_idx_requestPath, 1);

$_idx_isWebRoot = $shortName === '' || strtolower($shortName) === 'index.php';
$_idx_safeShortPath = PathUtils::resolveSafeRequirePath($shortName);

// The web root (member sign-in design §7): a member session goes to the portal.
// Signed out, it goes to the portal page while member sign-in or self-registration is on,
// and to the staff sign-in while both member sign-in and self-registration are off.
if ($_idx_isWebRoot && !AuthenticationManager::validateUserSessionIsActive(false)) {
    if (PortalSessionStore::has()) {
        RedirectUtils::redirect('portal/');
    }
    $portalEntryEnabled = SystemConfig::getBooleanValue('bMemberSignIn')
        || SystemConfig::getBooleanValue('bEnableSelfRegistration');
    RedirectUtils::redirect($portalEntryEnabled ? 'portal/login' : AuthenticationManager::getStaffSignInURL());
}

// Only a real page is remembered for after sign-in.
AuthenticationManager::ensureAuthentication($_idx_safeShortPath !== null);

// On a fresh install (sChurchName empty), redirect admin users to complete setup.
if (empty(SystemConfig::getValue('sChurchName'))) {
    try {
        $currentUser = AuthenticationManager::getCurrentUser();
        if ($currentUser->isAdmin()) {
            RedirectUtils::redirect('admin/system/church-info');
        }
    } catch (\Throwable) {
        // ensureAuthentication() above should have handled any auth failure;
        // swallow here to avoid a 500 on edge-case provider errors.
    }
}

if ($_idx_isWebRoot) {
    RedirectUtils::redirect('v2/dashboard');
} elseif ($_idx_safeShortPath !== null) {
    require $_idx_safeShortPath;
} elseif (in_array(strtolower(pathinfo($shortName, PATHINFO_EXTENSION)), ['js', 'css'], true)) {
    // Missing static asset (e.g. a stale webpack chunk hash after a deploy) —
    // bare 404, no need for a full error page.
    http_response_code(404);
    exit;
} elseif (AuthenticationManager::validateUserSessionIsActive(false)) {
    // A signed-in user keeps the application header and menu on an unknown page.
    if (AuthenticationManager::getCurrentUser()->isEditSelfExclusive()) {
        RedirectUtils::redirect(SystemURLs::getRootPath() . '/portal/');
    }
    http_response_code(404);
    $sPageTitle = gettext('Page Not Found');
    require __DIR__ . '/Include/Header.php';
    $code = 404;
    $title = gettext('Page Not Found');
    $message = SlimUtils::notFoundMessage();
    require __DIR__ . '/v2/templates/common/error-page.php';
    require __DIR__ . '/Include/Footer.php';
} else {
    // Signed out: the self-contained page needs no Header/Footer or session/DB state.
    RedirectUtils::redirect('errors/not-found.php?path=' . rawurlencode($shortName));
}
