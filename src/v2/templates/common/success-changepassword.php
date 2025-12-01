<?php

use ChurchCRM\dto\SystemURLs;

$sPageTitle = gettext("Change Password") . ": " . $user->getFullName();
require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>
Password Change Successful

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
