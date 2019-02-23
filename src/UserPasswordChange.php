<?php
/*******************************************************************************
 *
 *  filename    : UserPasswordChange.php
 *  website     : http://www.churchcrm.io
 *  copyright   : Copyright 2001, 2002 Deane Barker
 *  			  Copyright 2004-2012 Michael Wilt
 *
 ******************************************************************************/

// Include the function library
require 'Include/Config.php';
$bNoPasswordRedirect = true; // Subdue UserPasswordChange redirect to prevent looping
require 'Include/Functions.php';

use ChurchCRM\UserQuery;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Emails\PasswordChangeEmail;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

$bAdminOtherUser = false;
$bAdminOther = false;
$bError = false;
$sOldPasswordError = false;
$sNewPasswordError = false;

// Get the PersonID out of the querystring if they are an admin user; otherwise, use session.
if ($_SESSION['user']->isAdmin() && isset($_GET['PersonID'])) {
    $iPersonID = InputUtils::LegacyFilterInput($_GET['PersonID'], 'int');
    if ($iPersonID != $_SESSION['user']->getId()) {
        $bAdminOtherUser = true;
    }
} else {
    $iPersonID = $_SESSION['user']->getId();
}

// Was the form submitted?

if (isset($_POST['Submit'])) {
    // Assign all the stuff locally
    $sOldPassword = '';
    if (array_key_exists('OldPassword', $_POST)) {
        $sOldPassword = $_POST['OldPassword'];
    }
    $sNewPassword1 = $_POST['NewPassword1'];
    $sNewPassword2 = $_POST['NewPassword2'];

    // Administrators can change other users' passwords without knowing the old ones.
    // No password strength test is done, we assume this administrator knows what the
    // user wants so there is no need to prompt the user to change it on next login.
    if ($bAdminOtherUser) {
        // Did they enter a new password in both boxes?
        if (strlen($sNewPassword1) == 0 && strlen($sNewPassword2) == 0) {
            $sNewPasswordError = '<br><font color="red">'.gettext('You must enter a password in both boxes').'</font>';
            $bError = true;
        }

        // Do the two new passwords match each other?
        elseif ($sNewPassword1 != $sNewPassword2) {
            $sNewPasswordError = '<br><font color="red">'.gettext('You must enter the same password in both boxes').'</font>';
            $bError = true;
        } else {
            // Update the user record with the password hash
            $curUser = UserQuery::create()->findPk($iPersonID);
            $curUser->updatePassword($sNewPassword1);
            $curUser->setNeedPasswordChange(false);
            $curUser->save();
            $curUser->createTimeLineNote("password-changed-admin");

            if (!empty($curUser->getEmail())) {
                $email = new PasswordChangeEmail($curUser, $sNewPassword1);
                if (!$email->send()) {
                    LoggerUtils::getAppLogger()->warn($email->getError());
                }
            }

            // Route back to the list
            if (array_key_exists('FromUserList', $_GET) and $_GET['FromUserList'] == 'True') {
                RedirectUtils::Redirect('UserList.php');
            } else {
                RedirectUtils::Redirect('Menu.php');
            }
        }
    }

    // Otherwise, a user must know their own existing password to change it.
    else {
        $curUser = UserQuery::create()->findPk($iPersonID);

        // Build the array of bad passwords
        $aBadPasswords = explode(',', strtolower(SystemConfig::getValue('aDisallowedPasswords')));
        $aBadPasswords[] = strtolower($curUser->getPerson()->getFirstName());
        $aBadPasswords[] = strtolower($curUser->getPerson()->getMiddleName());
        $aBadPasswords[] = strtolower($curUser->getPerson()->getLastName());

        $bPasswordMatch = $curUser->isPasswordValid($sOldPassword);

        // Does the old password match?
        if (!$bPasswordMatch) {
            $sOldPasswordError = '<br><font color="red">'.gettext('Invalid password').'</font>';
            $bError = true;
        }

        // Did they enter a new password in both boxes?
        elseif (strlen($sNewPassword1) == 0 && strlen($sNewPassword2) == 0) {
            $sNewPasswordError = '<br><font color="red">'.gettext('You must enter your new password in both boxes').'</font>';
            $bError = true;
        }

        // Do the two new passwords match each other?
        elseif ($sNewPassword1 != $sNewPassword2) {
            $sNewPasswordError = '<br><font color="red">'.gettext('You must enter the same password in both boxes').'</font>';
            $bError = true;
        }

        // Is the user trying to change to something too obvious?
        elseif (in_array(strtolower($sNewPassword1), $aBadPasswords)) {
            $sNewPasswordError = '<br><font color="red">'.gettext('Your password choice is too obvious. Please choose something else.').'</font>';
            $bError = true;
        }

        // Is the password valid for length?
        elseif (strlen($sNewPassword1) < SystemConfig::getValue('iMinPasswordLength')) {
            $sNewPasswordError = '<br><font color="red">'.gettext('Your new password must be at least').' '.SystemConfig::getValue('iMinPasswordLength').' '.gettext('characters').'</font>';
            $bError = true;
        }

        // Did they actually change their password?
        elseif ($sNewPassword1 == $sOldPassword) {
            $sNewPasswordError = '<br><font color="red">'.gettext('You need to actually change your password (nice try, though!)').'</font>';
            $bError = true;
        } elseif (levenshtein(strtolower($sNewPassword1), strtolower($sOldPassword)) < SystemConfig::getValue('iMinPasswordChange')) {
            $sNewPasswordError = '<br><font color="red">'.gettext('Your new password is too similar to your old one.  Be more creative!').'</font>';
            $bError = true;
        }

        // If no errors, update
        if (!$bError) {
            // Update the user record with the password hash
            $curUser->updatePassword($sNewPassword1);
            $curUser->setNeedPasswordChange(false);
            $curUser->save();
            $_SESSION['user'] = $curUser;
            $curUser->createTimeLineNote("password-changed");

            // Route back to the list
            if ($_GET['FromUserList'] == 'True') {
                RedirectUtils::Redirect('UserList.php');
            } else {
                RedirectUtils::Redirect('Menu.php');
            }
        }
    }
} else {
    // initialize stuff since this is the first time showing the form
    $sOldPassword = '';
    $sNewPassword1 = '';
    $sNewPassword2 = '';
}

// Set the page title and include HTML header
$sPageTitle = gettext('User Password Change');
require 'Include/Header.php';

if ($_SESSION['user']->getNeedPasswordChange()) {
    ?>
    <div class="alert alert-danger alert-dismissible">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
        <h4><i class="fa fa-ban"></i> Alert!</h4>
        <?= gettext('Your account record indicates that you need to change your password before proceding.') ?>
        </div>
<?php
} ?>

<div class="row">
    <!-- left column -->
    <div class="col-md-8">
        <!-- general form elements -->
        <div class="box box-primary">
            <div class="box-header with-border">
                <?php if (!$bAdminOtherUser) {
        echo '<p>'.gettext('Enter your current password, then your new password twice.  Passwords must be at least').' '.SystemConfig::getValue('iMinPasswordLength').' '.gettext('characters in length.').'</p>';
    } else {
        echo '<p>'.gettext('Enter a new password for this user.').'</p>';
    }
                ?>
            </div>
            <!-- /.box-header -->
            <!-- form start -->
            <form method="post" action="UserPasswordChange.php?<?= 'PersonID='.$iPersonID ?>&FromUserList=<?= array_key_exists('FromUserList', $_GET) ? $_GET['FromUserList'] : '' ?>">
                <div class="box-body">
                    <?php if (!$bAdminOtherUser) {
                    ?>
                    <div class="form-group">
                        <label for="OldPassword"><?= gettext('Old Password') ?>:</label>
                        <input type="password" name="OldPassword" id="OldPassword" class="form-control" value="<?= $sOldPassword ?>" autofocus><?= $sOldPasswordError ?>
                    </div>
                    <?php
                } ?>
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

<?php require 'Include/Footer.php' ?>
