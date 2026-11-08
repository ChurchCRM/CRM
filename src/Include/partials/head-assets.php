<?php

/**
 * Page title, skin stylesheet, and core bundle, shared by every page shell.
 *
 * Required input: $localeInfo (LocaleInfo), $sPageTitle.
 */

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

$skinStylesheet = $localeInfo->isRTL() ? '/skin/v2/churchcrm-rtl.min.css' : '/skin/v2/churchcrm.min.css';
?>
<title>ChurchCRM: <?= InputUtils::escapeHTML($sPageTitle) ?></title>
<link rel="stylesheet" href="<?= InputUtils::escapeAttribute(SystemURLs::assetVersioned($skinStylesheet)) ?>">
<!-- Core ChurchCRM bundle (includes jQuery) -->
<script src="<?= InputUtils::escapeAttribute(SystemURLs::assetVersioned('/skin/v2/churchcrm.min.js')) ?>"></script>
