<?php

use ChurchCRM\Bootstrapper;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Plugin\PluginManager;
use ChurchCRM\Utils\InputUtils;

require_once __DIR__ . '/Header-Security.php';

// Initialize plugin system so active plugins (e.g. GA4) can inject head content
$pluginsPath = SystemURLs::getDocumentRoot() . '/plugins';
PluginManager::init($pluginsPath);

$localeInfo = Bootstrapper::getCurrentLocale(); // always returns a LocaleInfo object
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

    <title>ChurchCRM: <?= InputUtils::escapeHTML($sPageTitle) ?></title>

    <?= PluginManager::getPluginHeadContent() ?>

</head>
<body class="antialiased <?= InputUtils::escapeAttribute($sBodyClass ?? 'page-auth') ?>">

  <script nonce="<?= SystemURLs::getCSPNonce() ?>">
    window.CRM.applyPageConfig(<?= InputUtils::jsonEncodeForScript([
        'root' => SystemURLs::getRootPath(),
        'churchWebSite' => SystemConfig::getValue('sChurchWebSite'),
        'lang' => $localeInfo->getLanguageCode(),
        'isRTL' => $localeInfo->isRTL(),
        'systemLocale' => $localeInfo->getSystemLocale(),
        'locale' => $localeInfo->getLocale(),
        'shortLocale' => $localeInfo->getShortLocale(),
        'localeConfig' => $localeInfo->getLocaleConfigArray(),
    ]) ?>);
  </script>
