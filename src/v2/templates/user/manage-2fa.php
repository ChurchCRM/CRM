<?php

use ChurchCRM\dto\SystemURLs;

$sPageTitle = $user->getFullName() . ' - ' . gettext("Two-Factor Authentication");
require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>
<div id="two-factor-enrollment-app" data-csrf-token="<?= \ChurchCRM\Utils\InputUtils::escapeAttribute(\ChurchCRM\Utils\CSRFUtils::generateToken()) ?>"></div>
<script src="<?= SystemURLs::assetVersioned('/skin/v2/two-factor-enrollment.min.js') ?>"></script>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
