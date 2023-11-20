<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

// Set the page title and include HTML header
$sPageTitle = gettext("Family Registration");
require(SystemURLs::getDocumentRoot() . "/Include/HeaderNotLoggedIn.php");
?>

    <div class="register-box" style="width: 600px;">
        <div class="register-logo">
            <?php
            $headerHTML = '<b>Church</b>CRM';
            $sHeader = SystemConfig::getValue("sHeader");
            $sChurchName = SystemConfig::getValue("sChurchName");
            if (!empty($sHeader)) {
                $headerHTML = html_entity_decode($sHeader, ENT_QUOTES);
            } else if (!empty($sChurchName)) {
                $headerHTML = $sChurchName;
            }
            ?>
            <a href="<?= SystemURLs::getRootPath() ?>/"><?= $headerHTML ?></a>
        </div>

        <div class="register-box-body">
            <?= gettext("A new password was sent to you. Please check your email"); ?>
        </div>
    </div>
<?php
// Add the page footer
require(SystemURLs::getDocumentRoot() . "/Include/FooterNotLoggedIn.php");
