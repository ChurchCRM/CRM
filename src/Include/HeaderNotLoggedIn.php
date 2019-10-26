<?php
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\dto\SystemConfig;

require_once 'Header-Security.php';

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <!--<meta http-equiv="pragma" content="no-cache">-->
    <meta http-equiv="Content-Type" content="text/html">
    <!-- Tell the browser to be responsive to screen width -->
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">

    <!-- Bootstrap 3.3.5 -->
    <link rel="stylesheet" href="<?= SystemURLs::getRootPath() ?>/skin/external/bootstrap/bootstrap.min.css">
    <!-- Custom ChurchCRM styles -->
    <link rel="stylesheet" href="<?= SystemURLs::getRootPath() ?>/skin/churchcrm.min.css">

    <!-- jQuery JS -->
    <script src="<?= SystemURLs::getRootPath() ?>/skin/external/jquery/jquery.min.js"></script>

    <title>ChurchCRM: <?= $sPageTitle ?></title>

</head>
<body class="hold-transition login-page">

  <script nonce="<?= SystemURLs::getCSPNonce() ?>"  >
    window.CRM = {
      root: "<?= SystemURLs::getRootPath() ?>",
      churchWebSite:"<?= SystemConfig::getValue('sChurchWebSite') ?>"
    };
  </script>
