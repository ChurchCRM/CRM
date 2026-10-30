<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header-Security.php';
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= gettext('Database Upgrade') ?></title>
    <link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/churchcrm.min.css') ?>">
</head>
<body class="page-auth">
<main class="container py-5">
    <div class="card"><div class="card-body">
        <h1><?= gettext('Database Upgrade') ?></h1>
        <p><?= gettext('Your database is being updated. Please keep this page open until the upgrade finishes.') ?></p>
        <a href="<?= InputUtils::escapeAttribute($sRootPath . '/external/system/db-upgrade') ?>"><?= gettext('Continue') ?></a>
    </div></div>
</main>
</body>
</html>
