<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\dto\ChurchMetaData;

$sPageTitle = gettext("Password Reset Successful");
require(SystemURLs::getDocumentRoot() . "/Include/HeaderNotLoggedIn.php");
?>

    <div class="register-box register-box-600">
        <div class="register-logo">
            <a href="<?= SystemURLs::getRootPath() ?>"><?= ChurchMetaData::getChurchName() ?></a>
        </div>

        <div class="register-box-body">
            <div class="alert alert-success" role="alert">
                <h4 class="alert-heading">
                    <i class="fa-solid fa-check-circle"></i> <?= gettext("Password Reset Successful") ?>
                </h4>
                <p><?= gettext("A new password has been generated and sent to your email address.") ?></p>
                <hr>
                <p class="small mb-2">
                    <?= gettext("Please check your email (including spam/junk folder) for your temporary password.") ?>
                </p>
                <p class="small mb-0">
                    <?= gettext("Once you receive the email, you can log in with your temporary password and change it to something you prefer.") ?>
                </p>
                <hr>
                <p class="mb-0">
                    <a href="<?= SystemURLs::getRootPath() ?>/session/begin" class="btn btn-sm btn-primary">
                        <i class="fa-solid fa-sign-in"></i> <?= gettext("Go to Login") ?>
                    </a>
                </p>
            </div>
        </div>
    </div>
<?php
require(SystemURLs::getDocumentRoot() . "/Include/FooterNotLoggedIn.php");
