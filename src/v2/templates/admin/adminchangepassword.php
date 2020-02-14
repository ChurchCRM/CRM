<?php


use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

//Set the page title
$sPageTitle = gettext("Change Password") .": " . $user->getFullName();
include SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="row">
    <!-- left column -->
    <div class="col-md-8">
        <!-- general form elements -->
        <div class="box box-primary">
            <div class="box-header with-border">
                <?= gettext('Enter new user password. Administratively set passwords are not subject to length or complexity requirements') . '</p>' ?>
            </div>
            <!-- /.box-header -->
            <!-- form start -->
            <form method="post" action="">
                <div class="box-body">
                    <div class="form-group">
                            <label for="NewPassword1"><?= gettext('New Password') ?>:</label>
                        <input type="password" name="NewPassword1" id="NewPassword1" class="form-control" value="<?= $sNewPassword1 ?>">
                    </div>
                    <div class="form-group">
                        <label for="NewPassword2"><?= gettext('Confirm New Password') ?>:</label>
                        <input type="password" name="NewPassword2" id="NewPassword2"  class="form-control" value="<?= $sNewPassword2 ?>"><?= $sNewPasswordError ?>
                    </div>
                </div>
                <!-- /.box-body -->

                <div class="box-footer">
                    <input type="submit" class="btn btn-primary" name="Submit" value="<?= gettext('Save') ?>">
                </div>
            </form>
        </div>
    </div>
</div>

<?php include SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
