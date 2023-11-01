<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

// Set the page title and include HTML header
$sPageTitle = "ChurchCRM - Family Verification";
require(SystemURLs::getDocumentRoot() . "/Include/HeaderNotLoggedIn.php");
?>

    <div class="register-box">
        <div class="register-logo">
            <a href="<?= SystemURLs::getRootPath() ?>/"><b>Church</b>CRM</a><br/>
            <span><?= SystemConfig::getValue("sChurchName") .  $token ?></span>
        </div>

        <div class="register-box-body">
            <p class="login-box-msg"><?= gettext("Please enter the following to start your family's verification") ?></p>

            <form action="<?= SystemURLs::getRootPath() ?>/external/verify/" method="post">
                <div class="form-group has-feedback">
                    <input name="firstName" type="text" class="form-control" placeholder="<?= gettext("First Name") ?>" required>
                    <span class="fa fa-user form-control-feedback"></span>
                </div>
                <div class="form-group has-feedback">
                    <input name="lastName" type="text" class="form-control" placeholder="<?= gettext("Last Name") ?>" required>
                    <span class="fa fa-user form-control-feedback"></span>
                </div>
                <div class="form-group has-feedback">
                    <input name="zip" type="text" class="form-control" placeholder="<?= gettext("Zip") ?>" required>
                    <span class="fa fa-user form-control-feedback"></span>
                </div>
                <div class="form-group has-feedback">
                    <input name="email" type="text" class="form-control" placeholder="<?= gettext("Email") ?>" required>
                    <span class="fa fa-user form-control-feedback"></span>
                </div>
                <div class="row">
                    <div class="col-xs-12 text-center">
                        <button type="submit" class="btn bg-olive"><?= gettext("Next"); ?></button>
                    </div>
                    <!-- /.col -->
                </div>
            </form>
        </div>
        <!-- /.form-box -->
    </div>

<?php
// Add the page footer
require(SystemURLs::getDocumentRoot() . "/Include/FooterNotLoggedIn.php");
