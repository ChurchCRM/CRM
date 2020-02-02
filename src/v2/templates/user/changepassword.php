<?php


use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

//Set the page title
$sPageTitle = gettext("Change Password") .": " . $user->getFullName();
include SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>
<div id="two-factor-enrollment-react-app"> </div>

<?php include SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
