<?php


use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\dto\Classification;

require SystemURLs::getDocumentRoot() . '/Include/SimpleConfig.php';

//Set the page title
$sPageTitle = gettext("User API") . " - " . $user->getFullName();
include SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>

<?= $user->getUserName() . " - " . $user->getApiKey()?>

<?php include SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
