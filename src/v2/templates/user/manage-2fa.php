<?php

use ChurchCRM\dto\SystemURLs;

//Set the page title
$sPageTitle = $user->getFullName() . gettext("2 Factor Authentication enrollment");
include SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>
<div id="two-factor-enrollment-react-app"> </div>
<script src="<?= SystemURLs::getRootPath() ?>/skin/js-react/two-factor-enrollment-app.js"></script>
<?php include SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
