<?php

use ChurchCRM\Bootstrapper;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

require_once __DIR__ . '/Header-Security.php';

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

    <script src="<?= SystemURLs::assetVersioned('/skin/external/moment/moment.min.js') ?>"></script>

    <title>ChurchCRM: <?= $sPageTitle ?></title>

    <style>
      html, body {
        height: 100%;
      }

      body {
        display: flex;
        flex-direction: column;
      }

      body > *:not(.auth-footer) {
        flex: 1;
      }
    </style>

</head>
<body class="antialiased page-auth">

  <script nonce="<?= SystemURLs::getCSPNonce() ?>"  >
    // Initialize window.CRM if not already created by webpack bundles
    if (!window.CRM) {
        window.CRM = {};
    }
    
    // Extend window.CRM with server-side configuration (preserving existing properties like notify)
    Object.assign(window.CRM, {
      root:"<?= SystemURLs::getRootPath() ?>",
      churchWebSite:"<?= SystemConfig::getValue('sChurchWebSite') ?>"
    });
  </script>
