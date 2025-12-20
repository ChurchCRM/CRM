<?php

use ChurchCRM\dto\SystemURLs;

$sPageTitle = gettext("Unsupported Two Factor Authentication Configuration");
require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="card">
    <div class="card-body">

    <div class="card-body">
        <h3><i class="fa-solid fa-triangle-exclamation text-yellow"></i> <?= gettext("Unable To Begin Two Factor Authentication Enrollment") ?></h3>

        <p><?= gettext("Two factor authentication requires ChurchCRM administrators to configure a few parameters") . ":" ?></p>
        <ul>
            <li><?= gettext("System configuration ") . " bEnable2FA " . gettext("Must be set to 'true'") ?></li>
            <li><?= gettext("System Settings") . " → " . gettext("Two-Factor Authentication") . " → sTwoFASecretKey " . gettext("must be configured with an encryption key") ?></li>
        </ul>
    </div>
</div>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
