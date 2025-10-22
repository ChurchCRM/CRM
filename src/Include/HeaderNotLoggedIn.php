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

    <!-- Webpack-bundled CSS (includes Bootstrap, FontAwesome, Select2, etc) -->
    <link rel="stylesheet" href="<?= SystemURLs::getRootPath() ?>/skin/v2/churchcrm.min.css">

    <!-- Webpack-bundled JavaScript (includes jQuery, Bootstrap, plugins, etc) -->
    <!-- CRITICAL: No defer - must load immediately so jQuery is available for page-specific scripts -->
    <script src="<?= SystemURLs::getRootPath() ?>/skin/v2/churchcrm.js"></script>

    <title>ChurchCRM: <?= $sPageTitle ?></title>

</head>
<body class="hold-transition login-page">

  <script nonce="<?= SystemURLs::getCSPNonce() ?>"  >
    window.CRM = {
      root: "<?= SystemURLs::getRootPath() ?>",
      churchWebSite:"<?= SystemConfig::getValue('sChurchWebSite') ?>"
    };
  </script>
