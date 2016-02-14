<?php
/*******************************************************************************
 *
 *  filename    : UserPasswordChange.php
 *  website     : http://www.churchcrm.io
 *  copyright   : Copyright 2001, 2002 Deane Barker
 *  			  Copyright 2004-2012 Michael Wilt
 *
 *  LICENSE:
 *  (C) Free Software Foundation, Inc.
 *
 *  ChurchCRM is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful, but
 *  WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 *  General Public License for more details.
 *
 *  http://www.gnu.org/licenses
 *
 ******************************************************************************/

// Include the function library
require "Include/Config.php";
$bNoPasswordRedirect = true; // Subdue UserPasswordChange redirect to prevent looping
require "Include/Functions.php";

$bAdminOtherUser = false;
$bAdminOther = false;
$bError = false;
$sOldPasswordError = false;
$sNewPasswordError = false; 

// Get the PersonID out of the querystring if they are an admin user; otherwise, use session.
if ($_SESSION['bAdmin'] && isset($_GET["PersonID"]))
{
    $iPersonID = FilterInput($_GET["PersonID"],'int');
    if ($iPersonID != $_SESSION['iUserID'])
        $bAdminOtherUser = true;
}
else
    $iPersonID = $_SESSION['iUserID'];

// Was the form submitted?
if (isset($_POST["Submit"]))
{
    // Assign all the stuff locally
    $sOldPassword = "";
    if (array_key_exists ("OldPassword", $_POST))
	    $sOldPassword = $_POST["OldPassword"];
    $sNewPassword1 = $_POST["NewPassword1"];
    $sNewPassword2 = $_POST["NewPassword2"];

    // Administrators can change other users' passwords without knowing the old ones.
    // No password strength test is done, we assume this administrator knows what the
    // user wants so there is no need to prompt the user to change it on next login.
    if ($bAdminOtherUser)
    {
        // Did they enter a new password in both boxes?
        if (strlen($sNewPassword1) == 0 && strlen($sNewPassword2) == 0) {
            $sNewPasswordError = "<br><font color=\"red\">" . gettext("You must enter a password in both boxes") . "</font>";
            $bError = True;
        }

        // Do the two new passwords match each other?
        elseif ($sNewPassword1 != $sNewPassword2) {
            $sNewPasswordError = "<br><font color=\"red\">" . gettext("You must enter the same password in both boxes") . "</font>";
            $bError = True;
        }

        else {
            // Update the user record with the password hash
            $tmp = $sNewPassword1.$iPersonID;        
		    $sPasswordHashSha256 = hash ("sha256", $tmp);
		    
            $sSQL = "UPDATE user_usr SET".
                    " usr_Password='".$sPasswordHashSha256."',".
                    " usr_NeedPasswordChange='0' ".
                    "WHERE usr_per_ID ='".$iPersonID."'";

            RunQuery($sSQL);

            // Route back to the list
            if (array_key_exists ("FromUserList", $_GET) and $_GET["FromUserList"] == "True") {
                Redirect("UserList.php");
            } else {
                Redirect("Menu.php");
            }
        }
    }

    // Otherwise, a user must know their own existing password to change it.
    else
    {
        // Get the data on this user so we can confirm the old password
        $sSQL = "SELECT * FROM user_usr, person_per ".
                "WHERE per_ID = usr_per_ID AND usr_per_ID = " . $iPersonID;
        extract(mysql_fetch_array(RunQuery($sSQL)));

        // Build the array of bad passwords
        $aBadPasswords = explode(",", strtolower($sDisallowedPasswords));
        $aBadPasswords[] = strtolower($per_FirstName);
        $aBadPasswords[] = strtolower($per_MiddleName);
        $aBadPasswords[] = strtolower($per_LastName);

	    // Note that there are several possible encodings for the password in the database
	    $tmp = $sOldPassword;
	    $sPasswordHashMd5 = md5($tmp);
	    
	    $tmp = $sOldPassword.$usr_per_ID;
	    $sPasswordHash40 = sha1(sha1($tmp).$tmp);
	    
	    $tmp = $sOldPassword.$usr_per_ID;
	    $sPasswordHashSha256 = hash ("sha256", $tmp);
        
    	$bPasswordMatch = ($usr_Password == $sPasswordHashMd5 || $usr_Password == $sPasswordHash40 || $usr_Password == $sPasswordHashSha256);
	    
        // Does the old password match?
        if (!$bPasswordMatch) {
            $sOldPasswordError = "<br><font color=\"red\">" . gettext("Invalid password") . "</font>";
            $bError = True;
        }

        // Did they enter a new password in both boxes?
        elseif (strlen($sNewPassword1) == 0 && strlen($sNewPassword2) == 0) {
            $sNewPasswordError = "<br><font color=\"red\">" . gettext("You must enter your new password in both boxes") . "</font>";
            $bError = True;
        }

        // Do the two new passwords match each other?
        elseif ($sNewPassword1 != $sNewPassword2) {
            $sNewPasswordError = "<br><font color=\"red\">" . gettext("You must enter the same password in both boxes") . "</font>";
            $bError = True;
        }

        // Is the user trying to change to something too obvious?
        elseif (in_array(strtolower($sNewPassword1), $aBadPasswords)) {
            $sNewPasswordError = "<br><font color=\"red\">" . gettext("Your password choice is too obvious. Please choose something else.") . "</font>";
            $bError = True;
        }

        // Is the password valid for length?
        elseif (strlen($sNewPassword1) < $sMinPasswordLength) {
            $sNewPasswordError = "<br><font color=\"red\">" . gettext("Your new password must be at least") . $sMinPasswordLength . gettext("characters") . "</font>";
            $bError = True;
        }

        // Did they actually change their password?
        elseif ($sNewPassword1 == $sOldPassword) {
            $sNewPasswordError = "<br><font color=\"red\">" . gettext("You need to actually change your password (nice try, though!)") . "</font>";
            $bError = True;
        }

        elseif (levenshtein(strtolower($sNewPassword1),strtolower($sOldPassword)) < $sMinPasswordChange) {
            $sNewPasswordError = "<br><font color=\"red\">" . gettext("Your new password is too similar to your old one.  Be more creative!") . "</font>";
            $bError = True;
        }

        // If no errors, update
        if (!$bError) {
            // Update the user record with the password hash
		    $tmp = $sNewPassword1.$usr_per_ID;
		    $sPasswordHashSha256 = hash ("sha256", $tmp);
        	
            $sSQL = "UPDATE user_usr SET".
                    " usr_Password='".$sPasswordHashSha256."',".
                    " usr_NeedPasswordChange='0' ".
                    "WHERE usr_per_ID ='".$iPersonID."'";
            RunQuery($sSQL);

            // Set the session variable so they don't get sent back here
            $_SESSION['bNeedPasswordChange'] = False;

            // Route back to the list
            if ($_GET["FromUserList"] == "True") {
                Redirect("UserList.php");
            } else {
                Redirect("Menu.php");
            }
        }
    }
} else {
	// initialize stuff since this is the first time showing the form
	$sOldPassword = "";
	$sNewPassword1 = "";
    $sNewPassword2 = "";
}

// Set the page title and include HTML header
$sPageTitle = gettext("User Password Change");
require "Include/Header.php";

if ($_SESSION['bNeedPasswordChange']) { ?>
    <div class="alert alert-danger alert-dismissible">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
        <h4><i class="icon fa fa-ban"></i> Alert!</h4>
        <?= gettext("Your account record indicates that you need to change your password before proceding.") ?>
        </div>
<?php } ?>

<div class="row">
    <!-- left column -->
    <div class="col-md-8">
        <!-- general form elements -->
        <div class="box box-primary">
            <div class="box-header with-border">
                <?php if (!$bAdminOtherUser)
                    echo "<p>" . gettext("Enter your current password, then your new password twice.  Passwords must be at least") . ' ' . $sMinPasswordLength . ' ' . gettext("characters in length.") . "</p>";
                else
                    echo "<p>" . gettext("Enter a new password for this user.") . "</p>";
                ?>
            </div>
            <!-- /.box-header -->
            <!-- form start -->
            <form method="post" action="UserPasswordChange.php?<?= "PersonID=" . $iPersonID ?>&FromUserList=<?= array_key_exists ("FromUserList", $_GET) ? $_GET["FromUserList"] : '' ?>">
                <div class="box-body">
                    <?php if (!$bAdminOtherUser) { ?>
                    <div class="form-group">
                        <label for="OldPassword"><?= gettext("Old Password:") ?></label>
                        <input type="password" name="OldPassword" id="OldPassword" class="form-control" value="<?= $sOldPassword ?>" autofocus><?= $sOldPasswordError ?>
                    </div>
                    <?php } ?>
                    <div class="form-group">
                        <label for="NewPassword1"><?= gettext("New Password:") ?></label>
                        <input type="password" name="NewPassword1" id="NewPassword1" class="form-control" value="<?= $sNewPassword1 ?>">
                    </div>
                    <div class="form-group">
                        <label for="NewPassword2"><?= gettext("Confirm New Password:") ?></label>
                        <input type="password" name="NewPassword2" id="NewPassword2"  class="form-control" value="<?= $sNewPassword2 ?>"><?= $sNewPasswordError ?>
                    </div>
                </div>
                <!-- /.box-body -->

                <div class="box-footer">
                    <input type="submit" class="btn btn-primary" name="Submit" value="<?= gettext("Save") ?>">
                </div>
            </form>
        </div>
    </div>
</div>

<?php require "Include/Footer.php" ?>
