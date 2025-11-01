<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

require_once 'Header-Security.php';
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

    <!-- Core ChurchCRM bundle -->
    <script src="<?= SystemURLs::getRootPath() ?>/skin/v2/churchcrm.min.js"></script>
    <link rel="stylesheet" href="<?= SystemURLs::getRootPath() ?>/skin/v2/churchcrm.min.css">

    <title>ChurchCRM: <?= $sPageTitle ?></title>

</head>
<body class="hold-transition login-page">

  <script nonce="<?= SystemURLs::getCSPNonce() ?>"  >
    window.CRM = {
      root: "<?= SystemURLs::getRootPath() ?>",
      churchWebSite:"<?= SystemConfig::getValue('sChurchWebSite') ?>"
    };
  </script>
