<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\CSRFUtils;

$sPageTitle = gettext('Change Password') . ': ' . $user->getFullName();
if ($isForced) {
    require SystemURLs::getDocumentRoot() . '/Include/HeaderNotLoggedIn.php';
} else {
    require SystemURLs::getDocumentRoot() . '/Include/Header.php';
}
?>

<?php if ($isForced): ?>
<div class="login-container">
    <div class="login-wrapper">
        <div class="login-form-section">
            <div class="login-form-inner">
                <!-- Header with Logo -->
                <div class="login-form-header">
                    <div class="login-header-logo">
                        <img src="<?= SystemURLs::getRootPath() ?>/Images/logo-churchcrm-350.jpg" alt="ChurchCRM" />
                    </div>
                </div>

                <!-- Form Title -->
                <div class="login-form-title">
                    <h1><i class="fa-solid fa-key"></i><?= gettext('Password Change Required') ?></h1>
                    <p><?= gettext('You must set a new password before continuing.') ?></p>
                </div>

                <!-- Password Change Form -->
                <form method="post" action="<?= SystemURLs::getRootPath() ?>/v2/user/current/changepassword" id="passwordChangeForm">
                    <?= CSRFUtils::getTokenInputField('user_change_password') ?>
                    
                    <div class="mb-3">
                        <label for="OldPassword"><?= gettext('Current Password') ?>:</label>
                        <input type="password" name="OldPassword" id="OldPassword" class="form-control" placeholder="<?= gettext('Enter your current password') ?>" autofocus>
                        <?php if (!empty($sOldPasswordError ?? '')): ?>
                            <span class="form-field-error"><?= $sOldPasswordError ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="NewPassword1"><?= gettext('New Password') ?>:</label>
                        <input type="password" name="NewPassword1" id="NewPassword1" class="form-control" placeholder="<?= gettext('Enter your new password') ?>">
                    </div>

                    <div class="mb-3">
                        <label for="NewPassword2"><?= gettext('Confirm New Password') ?>:</label>
                        <input type="password" name="NewPassword2" id="NewPassword2" class="form-control" placeholder="<?= gettext('Confirm your new password') ?>">
                        <?php if (!empty($sNewPasswordError ?? '')): ?>
                            <span class="form-field-error"><?= $sNewPasswordError ?></span>
                        <?php endif; ?>
                    </div>

                    <p class="text-muted small mb-3">
                        <?= gettext('Passwords must be at least') ?> <?= SystemConfig::getValue('iMinPasswordLength') ?> <?= gettext('characters in length.') ?>
                    </p>

                    <button type="submit" name="Submit" class="btn-sign-in"><?= gettext('Set New Password') ?></button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<div class="row">
    <!-- left column -->
    <div class="col-md-8">
        <!-- general form elements -->
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <?= gettext('Enter your current password, then your new password twice.  Passwords must be at least') . ' ' . SystemConfig::getValue('iMinPasswordLength') . ' ' . gettext('characters in length.') ?>
            </div>
            <!-- form start -->
            <form method="post" action="<?= SystemURLs::getRootPath()?>/v2/user/current/changepassword" id="passwordChangeForm">
                <?= CSRFUtils::getTokenInputField('user_change_password') ?>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="OldPassword"><?= gettext('Old Password') ?>:</label>
                        <input type="password" name="OldPassword" id="OldPassword" class="form-control" autofocus><span id="oldPasswordError" class="form-field-error"><?= $sOldPasswordError ?? '' ?></span>
                    </div>
                    <div class="mb-3">
                            <label for="NewPassword1"><?= gettext('New Password') ?>:</label>
                        <input type="password" name="NewPassword1" id="NewPassword1" class="form-control">
                    </div>
                    <div class="mb-3">
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
