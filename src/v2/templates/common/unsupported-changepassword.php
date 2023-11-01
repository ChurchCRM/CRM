<?php

use ChurchCRM\dto\SystemURLs;

//Set the page title
$sPageTitle = gettext("Change Password") . ": " . $user->getFullName();
include SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>
Password change for the current authentication provider is unavailable.

Please contact your system administrator for instructions on changing your password

<?php include SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
