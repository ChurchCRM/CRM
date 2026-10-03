<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Bootstrapper;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Plugin\PluginManager;
use ChurchCRM\Service\ImpersonationService;
use ChurchCRM\Utils\InputUtils;

require_once __DIR__ . '/Header-Security.php';

// Initialize plugin system so active plugins (e.g. GA4) can inject head content
$pluginsPath = SystemURLs::getDocumentRoot() . '/plugins';
PluginManager::init($pluginsPath);

$localeInfo = Bootstrapper::getCurrentLocale(); // always returns a LocaleInfo object

// Admin masquerade (#9843). This header is used by pages a *logged-in* user can
// be sent to — most importantly /external/limited-access, where AuthMiddleware
// and PageInit confine an EditSelf-exclusive user — as well as by pages with no
// session at all (login, password reset, 404, the Bootstrapper error page). The
// banner must follow the session, so it is rendered here too, guarded by both
// "a user is authenticated" and "that session is a masquerade" so it can never
// appear on an anonymous page.
$_isImpersonating = ImpersonationService::isActive() && AuthenticationManager::isUserAuthenticated();
?>
<!DOCTYPE html>
<html<?= $localeInfo->isRTL() ? ' dir="rtl"' : '' ?>>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta http-equiv="Content-Type" content="text/html">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Core ChurchCRM bundle (includes jQuery) -->
    <script src="<?= SystemURLs::assetVersioned('/skin/v2/churchcrm.min.js') ?>"></script>
    <?php if ($localeInfo->isRTL()): ?>
    <link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/churchcrm-rtl.min.css') ?>">
    <?php else: ?>
    <link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/churchcrm.min.css') ?>">
    <?php endif; ?>

    <script src="<?= SystemURLs::assetVersioned('/skin/external/moment/moment.min.js') ?>"></script>

    <title>ChurchCRM: <?= InputUtils::escapeHTML($sPageTitle) ?></title>

    <?= PluginManager::getPluginHeadContent() ?>

</head>
<body class="antialiased <?= InputUtils::escapeAttribute($sBodyClass ?? 'page-auth') ?><?= $_isImpersonating ? ' impersonating' : '' ?>">
<?php require __DIR__ . '/ImpersonationBanner.php'; ?>

  <script nonce="<?= SystemURLs::getCSPNonce() ?>"  >
    // Initialize window.CRM if not already created by webpack bundles
    if (!window.CRM) {
        window.CRM = {};
    }
    
    // Extend window.CRM with server-side configuration (preserving existing properties like notify)
    Object.assign(window.CRM, {
      root:"<?= SystemURLs::getRootPath() ?>",
      churchWebSite:<?= SystemConfig::getValueForJs('sChurchWebSite') ?>,
      lang:<?= InputUtils::jsonEncodeForScript($localeInfo->getLanguageCode()) ?>,
      isRTL:<?= $localeInfo->isRTL() ? 'true' : 'false' ?>,
      systemLocale:<?= InputUtils::jsonEncodeForScript($localeInfo->getSystemLocale()) ?>,
      locale:<?= InputUtils::jsonEncodeForScript($localeInfo->getLocale()) ?>,
      shortLocale:<?= InputUtils::jsonEncodeForScript($localeInfo->getShortLocale()) ?>
    });
  </script>
