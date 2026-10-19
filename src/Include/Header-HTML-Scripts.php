<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

?>
<title>Shepherd — <?= InputUtils::escapeHTML($sPageTitle) ?></title>

<link rel="icon" href="<?= SystemURLs::getRootPath() ?>/skin/images/shepherd-logo.png" type="image/png">

<!-- Custom ChurchCRM styles (includes Tabler, DataTables BS5, icons, and bridge overrides) -->
<?php
// $localeInfo is always initialised by every including header
// (Header.php, HeaderNotLoggedIn.php).
// The isset() guard is a safety net for any direct or future unknown includer.
?>
<?php if (isset($localeInfo) && $localeInfo->isRTL()): ?>
<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/churchcrm-rtl.min.css') ?>">
<?php else: ?>
<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/churchcrm.min.css') ?>">
<?php endif; ?>

<!-- Core ChurchCRM bundle (includes jQuery) -->
<script src="<?= SystemURLs::assetVersioned('/skin/v2/churchcrm.min.js') ?>"></script>

<!-- Card Widget Handler for Bootstrap 5 -->
<script src="<?= SystemURLs::assetVersioned('/skin/js/card-widgets.js') ?>"></script>
