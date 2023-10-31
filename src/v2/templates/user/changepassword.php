<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

//Set the page title
$sPageTitle = gettext("Change Password") . ": " . $user->getFullName();
include SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="row">
    <!-- left column -->
    <div class="col-md-8">
        <!-- general form elements -->
        <div class="card card-primary">
            <div class="card-header with-border">
                <?= gettext('Enter your current password, then your new password twice.  Passwords must be at least') . ' ' . SystemConfig::getValue('iMinPasswordLength') . ' ' . gettext('characters in length.') ?>
            </div>
            <!-- /.box-header -->
            <!-- form start -->
            <form method="post" action="<?= SystemURLs::getRootPath()?>/v2/user/current/changepassword" id="passwordChangeForm">
                <div class="card-body">
                    <div class="form-group">
                        <label for="OldPassword"><?= gettext('Old Password') ?>:</label>
                        <input type="password" name="OldPassword" id="OldPassword" class="form-control" value="<?= $sOldPassword ?>" autofocus><span id="oldPasswordError" class="form-field-error"><?= $sOldPasswordError ?></span>
                    </div>
                    <div class="form-group">
                            <label for="NewPassword1"><?= gettext('New Password') ?>:</label>
                        <input type="password" name="NewPassword1" id="NewPassword1" class="form-control" value="<?= $sNewPassword1 ?>">
                    </div>
                    <div class="form-group">
                        <label for="NewPassword2"><?= gettext('Confirm New Password') ?>:</label>
                        <input type="password" name="NewPassword2" id="NewPassword2"  class="form-control" value="<?= $sNewPassword2 ?>"><span id="NewPasswordError" class="form-field-error"><?= $sNewPasswordError ?></span>
                    </div>
                </div>
                <!-- /.box-body -->

                <div class="card-footer">
                    <input type="submit" class="btn btn-primary" name="Submit" value="<?= gettext('Save') ?>">
                </div>
            </form>
        </div>
    </div>
</div>
<script src="<?= SystemURLs::getRootPath() ?>/skin/js/PasswordChange.js"></script>
<?php include SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
