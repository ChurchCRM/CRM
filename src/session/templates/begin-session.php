<?php

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

// Set the page title and include HTML header
$sPageTitle = gettext('Login');
require SystemURLs::getDocumentRoot() . '/Include/HeaderNotLoggedIn.php';

?>

<!-- /.login-box-body -->
    <div class="login-box">
        <!-- /.login-logo -->
        <div class="card card-outline card-primary">
            <div class="card-header text-center">
                <a href="<?= SystemURLs::getRootPath() ?>" class="h1">Church<b>CRM</b></a>
            </div>
            <div class="card-body">
                <p class="login-box-msg"> <b><?= ChurchMetaData::getChurchName() ?></b><br/>
                    <?= gettext('Please Login') ?>
                </p>

                <?php
                if (isset($_GET['Timeout'])) {
                    $loginPageMsg = gettext('Your previous session timed out.  Please login again.');
                }

                    // output warning and error messages
                if (isset($sErrorText)) {
                    echo '<div class="callout callout-danger">' . $sErrorText . '</div>';
                }
                if (isset($loginPageMsg)) {
                    echo '<div class="callout callout-warning">' . $loginPageMsg . '</div>';
                }
                ?>
                <form class="form-signin" role="form" method="post" name="LoginForm" action="<?= $localAuthNextStepURL ?>">
                    <div class="input-group mb-3">
                        <input type="text" id="UserBox" name="User"  class="form-control" placeholder="<?= gettext('Email/Username') ?>" value="<?= $prefilledUserName ?>" required autofocus>
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <span class="fas fa-envelope"></span>
                            </div>
                        </div>
                    </div>
                    <div class="input-group mb-3">
                        <input type="password" id="PasswordBox" name="Password" class="form-control" placeholder="<?= gettext('Password') ?>" required autofocus>
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <span class="fas fa-lock"></span>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-8">
                            <div class="icheck-primary">
                                <input type="checkbox" id="remember">
                                <label for="remember">
                                    Remember Me
                                </label>
                            </div>
                        </div>
                        <!-- /.col -->
                        <div class="col-4">
                            <button type="submit" class="btn btn-primary btn-block">Sign In</button>
                        </div>
                        <!-- /.col -->
                    </div>
                </form>


                <!-- /.social-auth-links -->
                <?php if (SystemConfig::getBooleanValue('bEnableLostPassword')) { ?>
                <p class="mb-1">
                    <a href="<?= $forgotPasswordURL ?>"><?= gettext("I forgot my password") ?></a>
                </p>
                <?php }
                if (SystemConfig::getBooleanValue('bEnableSelfRegistration')) { ?>
                <p class="mb-0">
                    <a href="<?= SystemURLs::getRootPath() ?>/external/register/" class="text-center"><?= gettext('Register a new Family'); ?></a>
                </p>
                <?php } ?>
            </div>
            <!-- /.card-body -->
        </div>
        <!-- /.card -->
    </div>
    <!-- /.login-box -->
</div>
<?php
require SystemURLs::getDocumentRoot() . '/Include/FooterNotLoggedIn.php';
