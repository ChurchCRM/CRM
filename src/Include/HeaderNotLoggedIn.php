<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

if (SystemConfig::debugEnabled()) {
    require_once 'Header-Security.php';
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta http-equiv="Content-Type" content="text/html">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- jQuery JS -->
    <script src="<?= SystemURLs::getRootPath() ?>/skin/external/jquery/jquery.min.js"></script>

    <!-- Custom ChurchCRM styles -->
    <link rel="stylesheet" href="<?= SystemURLs::getRootPath() ?>/skin/churchcrm.min.css">

    <!-- Webpack bundled assets for logged-out pages (includes Bootstrap, AdminLTE, and FontAwesome) -->
    <script src="<?= SystemURLs::getRootPath() ?>/skin/v2/skin-loggedout.js"></script>

    <title>ChurchCRM: <?= $sPageTitle ?></title>

</head>
<body class="hold-transition login-page">

  <script nonce="<?= SystemURLs::getCSPNonce() ?>"  >
    window.CRM = {
      root: "<?= SystemURLs::getRootPath() ?>",
      churchWebSite:"<?= SystemConfig::getValue('sChurchWebSite') ?>"
    };
  </script>
