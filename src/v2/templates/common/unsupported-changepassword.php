<?php

use ChurchCRM\dto\SystemURLs;

$sPageTitle = gettext("Change Password") . ": " . $user->getFullName();
require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>
Password change for the current authentication provider is unavailable.

Please contact your system administrator for instructions on changing your password

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
