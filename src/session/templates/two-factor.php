<?php

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemURLs;

$sPageTitle = gettext('Login');
require SystemURLs::getDocumentRoot() . '/Include/HeaderNotLoggedIn.php';

?>
<div class="login-box">
    <!-- /.login-logo -->
    <div class="card card-outline card-primary">
        <div class="card-header text-center">
            <a href="<?= SystemURLs::getRootPath() ?>" class="h1"><img src="<?= SystemURLs::getRootPath() ?>/Images/logo-churchcrm-350.jpg" alt="ChurchCRM" style="max-width:330px; height:auto;" /></a>
        </div>
        <div class="card-body">
            <p class="login-box-msg">
                <b><?= ChurchMetaData::getChurchName() ?></b><br/>
                <?= gettext('Two-Factor Authentication') ?>
            </p>

            <div class="alert alert-info d-flex" role="alert">
                <div class="mr-3">
                    <i class="fa fa-mobile-alt" aria-hidden="true"></i>
                </div>
                <div>
                    <small><?= gettext('Enter the 6-digit code from your authenticator app') ?></small>
                </div>
            </div>

            <form class="form-signin" role="form" method="post" name="TwoFAForm" action="<?= SystemURLs::getRootPath()?>/session/two-factor">
                <div class="input-group mb-3">
                    <input type="text" id="TwoFACode" name="TwoFACode" class="form-control form-control-lg" placeholder="000000" maxlength="6" inputmode="numeric" style="font-size: 1.75em; letter-spacing: 0.25em; text-align: center; font-weight: 300;" required autofocus>
                    <div class="input-group-append">
                        <div class="input-group-text" style="padding-right: 15px;">
                            <span class="fa-solid fa-lock"></span>
                        </div>
                    </div>
                </div>

                <?php if (isset($bInvalidCode) && $bInvalidCode) { ?>
                    <div class="alert alert-danger d-flex align-items-center mb-3" role="alert">
                        <i class="fa fa-exclamation-circle mr-2"></i>
                        <div><?= gettext('Invalid code. Please try again.') ?></div>
                    </div>
                <?php } ?>

                <div class="row">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fa-solid fa-right-to-bracket mr-2"></i><?= gettext('Verify & Sign In') ?>
                        </button>
                    </div>
                </div>
            </form>

            <p class="mt-3 text-muted small text-center mb-0">
                <a href="<?= SystemURLs::getRootPath() ?>/session/begin" class="text-primary"><?= gettext('Use a different account') ?></a>
            </p>
        </div>
        <!-- /.card-body -->
    </div>
    <!-- /.card -->
</div>
