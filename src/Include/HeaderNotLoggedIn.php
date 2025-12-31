<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

require_once __DIR__ . '/Header-Security.php';
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta http-equiv="Content-Type" content="text/html">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Core ChurchCRM bundle (includes jQuery) -->
    <script src="<?= SystemURLs::assetVersioned('/skin/v2/churchcrm.min.js') ?>"></script>
    <link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/churchcrm.min.css') ?>">

    <script src="<?= SystemURLs::assetVersioned('/skin/external/moment/moment.min.js') ?>"></script>

    <title>ChurchCRM: <?= $sPageTitle ?></title>

</head>
<body class="hold-transition login-page">

  <script nonce="<?= SystemURLs::getCSPNonce() ?>"  >
    // Initialize window.CRM if not already created by webpack bundles
    if (!window.CRM) {
        window.CRM = {};
    }
    
    // Extend window.CRM with server-side configuration (preserving existing properties like notify)
    Object.assign(window.CRM, {
      root: "<?= SystemURLs::getRootPath() ?>",
      churchWebSite:"<?= SystemConfig::getValue('sChurchWebSite') ?>"
    });
  </script>
