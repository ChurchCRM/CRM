<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= gettext('Kiosk Registration Disabled') ?></title>
    <link rel="stylesheet" href="<?= $sRootPath ?>/skin/external/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="<?= $sRootPath ?>/skin/v2/kiosk-registration-closed.min.css">
</head>
<body class="kiosk-registration-closed">
    <div class="kiosk-message-container">
        <div class="kiosk-message-icon">🔒</div>
        <h1 class="kiosk-message-title"><?= gettext('Kiosk Registration Disabled') ?></h1>
        <p class="kiosk-message-text"><?= gettext('New kiosk device registration is currently disabled. Please contact your system administrator to enable kiosk registration.') ?></p>
        <a href="javascript:location.reload()" class="kiosk-retry-btn"><?= gettext('Try Again') ?></a>
    </div>
</body>
</html>
