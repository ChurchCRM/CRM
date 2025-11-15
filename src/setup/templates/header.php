<?php
// Standalone header for setup - no Config.php dependencies
$rootPath = $GLOBALS['CHURCHCRM_SETUP_ROOT_PATH'] ?? '';
$nonce = base64_encode(random_bytes(16));
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta http-equiv="Content-Type" content="text/html">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Core ChurchCRM bundle (includes jQuery) -->
    <script src="<?= $rootPath ?>/skin/v2/churchcrm.min.js"></script>
    <link rel="stylesheet" href="<?= $rootPath ?>/skin/v2/churchcrm.min.css">

    <script src="<?= $rootPath ?>/skin/external/moment/moment.min.js"></script>

    <title>ChurchCRM: <?= $sPageTitle ?? 'Setup' ?></title>

</head>
<body class="hold-transition login-page">

  <script nonce="<?= $nonce ?>">
    // Initialize window.CRM if not already created by webpack bundles
    if (!window.CRM) {
        window.CRM = {};
    }
    
    // Extend window.CRM with server-side configuration
    Object.assign(window.CRM, {
      root: "<?= $rootPath ?>"
    });
  </script>
