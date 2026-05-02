<?php
// Shared template for public error pages.
// Expects these variables to be set by the including page:
//  - $pageTitle (string)
//  - $pageBodyHtml (string, already escaped/HTML where needed)

use ChurchCRMootstrapperuilduild; // no-op placeholder
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/error.min.css') ?>">
</head>
<body>
    <div class="error-container">
        <h1 class="error-title"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>

        <?= $pageBodyHtml ?>

        <div class="help-link">
            <a href="/">Return to Home</a>
        </div>
    </div>
    <script nonce="<?= SystemURLs::getCSPNonce() ?>" src="<?= SystemURLs::assetVersioned('/skin/v2/error.min.js') ?>"></script>
</body>
</html>
