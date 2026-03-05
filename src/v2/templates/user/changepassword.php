<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\CSRFUtils;

$sPageTitle = gettext("Change Password") . ": " . $user->getFullName();
if ($isForced) {
    require SystemURLs::getDocumentRoot() . '/Include/HeaderNotLoggedIn.php';
} else {
    require SystemURLs::getDocumentRoot() . '/Include/Header.php';
}
?>

<?php if ($isForced): ?>
<div class="login-box">
    <div class="card card-outline card-warning">
        <div class="card-header text-center">
            <a href="<?= SystemURLs::getRootPath() ?>" class="h1">
                <img src="<?= SystemURLs::getRootPath() ?>/Images/logo-churchcrm-350.jpg" alt="ChurchCRM" style="max-width:280px; height:auto;" />
            </a>
        </div>
        <div class="card-body">
            <p class="login-box-msg">
                <strong><?= gettext('Password Change Required') ?></strong><br>
                <small class="text-muted"><?= gettext('You must set a new password before continuing.') ?></small>
            </p>
            <form method="post" action="<?= SystemURLs::getRootPath() ?>/v2/user/current/changepassword" id="passwordChangeForm">
                <?= CSRFUtils::getTokenInputField('user_change_password') ?>
                <div class="input-group mb-3">
                    <input type="password" name="OldPassword" id="OldPassword" class="form-control" placeholder="<?= gettext('Current Password') ?>" autofocus>
                    <div class="input-group-append"><div class="input-group-text"><i class="fa fa-lock"></i></div></div>
                    <?php if (!empty($sOldPasswordError ?? '')): ?>
                        <span class="form-field-error"><?= $sOldPasswordError ?></span>
                    <?php endif; ?>
                </div>
                <div class="input-group mb-3">
                    <input type="password" name="NewPassword1" id="NewPassword1" class="form-control" placeholder="<?= gettext('New Password') ?>">
                    <div class="input-group-append"><div class="input-group-text"><i class="fa fa-key"></i></div></div>
                </div>
                <div class="input-group mb-3">
                    <input type="password" name="NewPassword2" id="NewPassword2" class="form-control" placeholder="<?= gettext('Confirm New Password') ?>">
                    <div class="input-group-append"><div class="input-group-text"><i class="fa fa-key"></i></div></div>
                    <?php if (!empty($sNewPasswordError ?? '')): ?>
                        <span class="form-field-error"><?= $sNewPasswordError ?></span>
                    <?php endif; ?>
                </div>
                <p class="text-muted small mb-3">
                    <?= gettext('Passwords must be at least') ?> <?= SystemConfig::getValue('iMinPasswordLength') ?> <?= gettext('characters in length.') ?>
                </p>
                <button type="submit" name="Submit" class="btn btn-warning btn-block"><?= gettext('Set New Password') ?></button>
            </form>
        </div>
    </div>
</div>
<?php else: ?>
<div class="row">
    <!-- left column -->
    <div class="col-md-8">
        <!-- general form elements -->
        <div class="card card-primary">
            <div class="card-header">
                <?= gettext('Enter your current password, then your new password twice.  Passwords must be at least') . ' ' . SystemConfig::getValue('iMinPasswordLength') . ' ' . gettext('characters in length.') ?>
            </div>
            <!-- form start -->
            <form method="post" action="<?= SystemURLs::getRootPath()?>/v2/user/current/changepassword" id="passwordChangeForm">
                <?= CSRFUtils::getTokenInputField('user_change_password') ?>
                <div class="card-body">
                    <div class="form-group">
                        <label for="OldPassword"><?= gettext('Old Password') ?>:</label>
                        <input type="password" name="OldPassword" id="OldPassword" class="form-control" autofocus><span id="oldPasswordError" class="form-field-error"><?= $sOldPasswordError ?? '' ?></span>
                    </div>
                    <div class="form-group">
                            <label for="NewPassword1"><?= gettext('New Password') ?>:</label>
                        <input type="password" name="NewPassword1" id="NewPassword1" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="NewPassword2"><?= gettext('Confirm New Password') ?>:</label>
                        <input type="password" name="NewPassword2" id="NewPassword2"  class="form-control"><span id="NewPasswordError" class="form-field-error"><?= $sNewPasswordError ?? '' ?></span>
                    </div>
                </div>

                <div class="card-footer">
                    <input type="submit" class="btn btn-primary" name="Submit" value="<?= gettext('Save') ?>">
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<script src="<?= SystemURLs::assetVersioned('/skin/js/PasswordChange.js') ?>"></script>
<?php
if ($isForced) {
    require SystemURLs::getDocumentRoot() . '/Include/FooterNotLoggedIn.php';
} else {
    require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
}
