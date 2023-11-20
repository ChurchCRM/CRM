<?php

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemURLs;

// Set the page title and include HTML header
$sPageTitle = gettext('Login');
require SystemURLs::getDocumentRoot() . '/Include/HeaderNotLoggedIn.php';

?>
<div class="login-box" id="Login">
    <div class="login-logo">
        Church<b>CRM</b>
    </div>

    <!-- /.login-logo -->
    <div class="login-box-body">
        <p class="login-box-msg">
            <b><?= ChurchMetaData::getChurchName() ?></b><br/>
            <?= gettext('Please enter 2FA code') ?>
        </p>

        <form class="form-signin" role="form" method="post" name="TwoFAForm" action="<?= SystemURLs::getRootPath()?>/session/two-factor">
            <div class="form-group has-feedback">
                <input type="text" id="UserBox" name="TwoFACode" class="form-control" placeholder="<?= gettext('2FA Code') ?>" required autofocus>
            </div>
            <div class="row">
                <!-- /.col -->
                <div class="col-xs-5">
                    <button type="submit" class="btn btn-primary btn-block btn-flat"><i
                                class="fa fa-sign-in"></i> <?= gettext('Login') ?></button>
                </div>
            </div>
        </form>
    </div>
</div>
