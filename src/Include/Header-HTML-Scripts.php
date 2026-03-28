<?php

use ChurchCRM\dto\SystemURLs;

?>
<title>ChurchCRM: <?= $sPageTitle ?></title>

<link rel="icon" href="<?= SystemURLs::getRootPath() ?>/favicon.ico" type="image/x-icon">

<!-- Custom ChurchCRM styles (includes Tabler, DataTables BS5, icons, and bridge overrides) -->
<?php if (isset($localeInfo) && $localeInfo->isRTL()): ?>
<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/churchcrm-rtl.min.css') ?>">
<?php else: ?>
<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/churchcrm.min.css') ?>">
<?php endif; ?>

<!-- Core ChurchCRM bundle (includes jQuery) -->
<script src="<?= SystemURLs::assetVersioned('/skin/v2/churchcrm.min.js') ?>"></script>

<!-- Card Widget Handler for Bootstrap 5 -->
<script src="<?= SystemURLs::assetVersioned('/skin/js/card-widgets.js') ?>"></script>

<script src="<?= SystemURLs::assetVersioned('/skin/external/moment/moment.min.js') ?>"></script>
