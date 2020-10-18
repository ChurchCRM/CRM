<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\dto\ChurchMetaData;

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
            <?= gettext('Please Login') ?>
        </p>

        <?php
        if (isset($_GET['Timeout'])) {
            $loginPageMsg = gettext('Your previous session timed out.  Please login again.');
        }

        // output warning and error messages
        if (isset($sErrorText)) {
            echo '<div class="alert alert-error">' . $sErrorText . '</div>';
        }
        if (isset($loginPageMsg)) {
            echo '<div class="alert alert-warning">' . $loginPageMsg . '</div>';
        }
        ?>

        <form class="form-signin" role="form" method="post" name="LoginForm" action="<?= $localAuthNextStepURL ?>">
            <div class="form-group has-feedback">
                <input type="text" id="UserBox" name="User" class="form-control" value="<?= $prefilledUserName ?>"
                   placeholder="<?= gettext('Email/Username') ?>" required autofocus>
            </div>
            <div class="form-group has-feedback">
                <input type="password" id="PasswordBox" name="Password" class="form-control" data-toggle="password"
                   placeholder="<?= gettext('Password') ?>" required autofocus>
                <br/>
                <?php if (SystemConfig::getBooleanValue('bEnableLostPassword')) {
            ?>
                    <span class="text-right"><a
                                href="<?= $forgotPasswordURL ?>"><?= gettext("I forgot my password") ?></a></span>
                    <?php
        } ?>
            </div>
            <div class="row">
                <!-- /.col -->
                <div class="col-xs-5">
                    <button type="submit" class="btn btn-primary btn-block btn-flat"><i
                                class="fa fa-sign-in"></i> <?= gettext('Login') ?></button>
                </div>
            </div>
        </form>

        <?php if (SystemConfig::getBooleanValue('bEnableSelfRegistration')) {
            ?>
            <a href="<?= SystemURLs::getRootPath() ?>/external/register/" class="text-center btn bg-olive"><i
                        class="fa fa-user-plus"></i> <?= gettext('Register a new Family'); ?></a><br>
            <?php
        } ?>
        <!--<a href="external/family/verify" class="text-center">Verify Family Info</a> -->

    </div>

<!-- /.login-box-body -->
</div>