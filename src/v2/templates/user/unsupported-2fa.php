<?php

use ChurchCRM\dto\SystemURLs;

//Set the page title
$sPageTitle = gettext("Unsupported Two Factor Authentication Configuration");
require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="card">
    <div class="card-body">

    <div class="card-body">
        <h3><i class="fa fa-warning text-yellow"></i> <?= gettext("Unable To Begin Two Factor Authentication Enrollment") ?></h3>

        <p><?= gettext("Two factor authentication requires ChurchCRM administrators to configure a few parameters") . ":" ?></p>
        <ul>
            <li><?= gettext("System configuration ") . " bEnable2FA " . gettext("Must be set to 'true'") ?></li>
            <li><?= gettext("Include/Config.php must define an encryption key for storing 2FA secret keys in the database by setting a value for") ?>: $TwoFASecretKey</li>
        </ul>
    </div>
</div>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php';  ?>
