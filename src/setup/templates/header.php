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

        <link rel="shortcut icon" href="<?= $rootPath ?>/favicon.ico">
        
        <!-- Setup CSS bundle (includes Bootstrap and Font Awesome) -->
        <link rel="stylesheet" href="<?= $rootPath ?>/skin/v2/setup.min.css">
        
        <!-- Setup JS bundle (includes jQuery, Bootstrap, bs-stepper, and setup.js logic) -->
        <script src="<?= $rootPath ?>/skin/v2/setup.min.js" defer></script>

        <title>ChurchCRM: <?= $sPageTitle ?? 'Setup' ?></title>

    </head>
    <body class="hold-transition login-page">

    <script nonce="<?= $nonce ?>">
        // Initialize window.CRM for setup script
        window.CRM = {
            root: "<?= $rootPath ?>"
        };
    </script>
