<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require __DIR__ . '/partials/head-assets.php';
?>
<link rel="icon" href="<?= InputUtils::escapeAttribute(SystemURLs::getRootPath()) ?>/favicon.ico" type="image/x-icon">

<!-- Card Widget Handler for Bootstrap 5 -->
<script src="<?= InputUtils::escapeAttribute(SystemURLs::assetVersioned('/skin/js/card-widgets.js')) ?>"></script>
