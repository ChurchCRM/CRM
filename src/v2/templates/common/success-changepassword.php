<?php

use ChurchCRM\dto\SystemURLs;

$sPageTitle = gettext("Change Password") . ": " . $user->getFullName();
include SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>
Password Change Successful

<?php
include SystemURLs::getDocumentRoot() . '/Include/Footer.php';
