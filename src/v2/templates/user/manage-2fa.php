<?php

use ChurchCRM\dto\SystemURLs;

$sPageTitle = $user->getFullName() . gettext("2 Factor Authentication enrollment");
require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>
<div id="two-factor-enrollment-react-app"> </div>
<script src="<?= SystemURLs::assetVersioned('/skin/v2/two-factor-enrollment.min.js') ?>"></script>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
