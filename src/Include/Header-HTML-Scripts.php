<?php

use ChurchCRM\dto\SystemURLs;

?>
<title>ChurchCRM: <?= $sPageTitle ?></title>

<link rel="icon" href="<?= SystemURLs::getRootPath() ?>/favicon.ico" type="image/x-icon">

<!-- Custom ChurchCRM styles -->
<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/churchcrm.min.css') ?>">

<!-- Core ChurchCRM bundle (includes jQuery) -->
<script src="<?= SystemURLs::assetVersioned('/skin/v2/churchcrm.min.js') ?>"></script>

<script src="<?= SystemURLs::assetVersioned('/skin/external/moment/moment.min.js') ?>"></script>
