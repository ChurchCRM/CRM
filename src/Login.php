<?php
/*******************************************************************************
 *
 *  filename    : Login.php
 *  description : login page that checks for correct username and password
 *
 *  http://www.churchcrm.io/
 *  Copyright 2001-2002 Phillip Hullquist, Deane Barker,
 *
 *  Updated 2005-03-19 by Everette L Mills: Removed dropdown login box and
 *  added user entered login box
 *
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
require 'Include/Config.php';
$bSuppressSessionTests = TRUE;
require 'Include/Functions.php';
// Initialize the variables

// Is the user requesting to logoff or timed out?
if (isset($_GET["Logoff"]) || isset($_GET['Timeout'])) {
    if (!isset($_SESSION['sshowPledges']) || ($_SESSION['sshowPledges'] == ''))
        $_SESSION['sshowPledges'] = 0;
    if (!isset($_SESSION['sshowPayments']) || ($_SESSION['sshowPayments'] == ''))
        $_SESSION['sshowPayments'] = 0;
    if (!isset($_SESSION['bSearchFamily']) || ($_SESSION['bSearchFamily'] == ''))
        $_SESSION['bSearchFamily'] = 0;

   if (!empty($_SESSION['iUserID'])) {
       $sSQL = "UPDATE user_usr SET usr_showPledges = " . $_SESSION['sshowPledges'] .
                     ", usr_showPayments = " . $_SESSION['sshowPayments'] .
                     ", usr_showSince = '" . $_SESSION['sshowSince'] . "'" .
                     ", usr_defaultFY = '" . $_SESSION['idefaultFY'] . "'" .
                     ", usr_currentDeposit = '" . $_SESSION['iCurrentDeposit'] . "'";
        if ($_SESSION['dCalStart'] != '')
            $sSQL .= ", usr_CalStart = '" . $_SESSION['dCalStart'] . "'";
        if ($_SESSION['dCalEnd'] != '')
            $sSQL .= ", usr_CalEnd = '" . $_SESSION['dCalEnd'] . "'";
        if ($_SESSION['dCalNoSchool1'] != '')
            $sSQL .= ", usr_CalNoSchool1 = '" . $_SESSION['dCalNoSchool1'] . "'";
        if ($_SESSION['dCalNoSchool2'] != '')
            $sSQL .= ", usr_CalNoSchool1 = '" . $_SESSION['dCalNoSchool2'] . "'";
        if ($_SESSION['dCalNoSchool3'] != '')
            $sSQL .= ", usr_CalNoSchool1 = '" . $_SESSION['dCalNoSchool3'] . "'";
        if ($_SESSION['dCalNoSchool4'] != '')
            $sSQL .= ", usr_CalNoSchool1 = '" . $_SESSION['dCalNoSchool4'] . "'";
        if ($_SESSION['dCalNoSchool5'] != '')
            $sSQL .= ", usr_CalNoSchool1 = '" . $_SESSION['dCalNoSchool5'] . "'";
        if ($_SESSION['dCalNoSchool6'] != '')
            $sSQL .= ", usr_CalNoSchool1 = '" . $_SESSION['dCalNoSchool6'] . "'";
        if ($_SESSION['dCalNoSchool7'] != '')
            $sSQL .= ", usr_CalNoSchool1 = '" . $_SESSION['dCalNoSchool7'] . "'";
        if ($_SESSION['dCalNoSchool8'] != '')
            $sSQL .= ", usr_CalNoSchool1 = '" . $_SESSION['dCalNoSchool8'] . "'";
        $sSQL .= ", usr_SearchFamily = '" . $_SESSION['bSearchFamily'] . "'" .
                     " WHERE usr_per_ID = " . $_SESSION['iUserID'];
       RunQuery($sSQL);
   }
}

$iUserID = 0;
// Get the UserID out of user name submitted in form results
if (isset($_POST['User']) && !isset($sErrorText)) {

    // Get the information for the selected user
    $UserName = FilterInput($_POST['User'],'string',32);
    $uSQL = "SELECT usr_per_id FROM user_usr WHERE usr_UserName like '$UserName'";
    $usQueryResult = RunQuery($uSQL);
    $usQueryResultSet = mysql_fetch_array($usQueryResult);
    if ($usQueryResultSet == Null){
        // Set the error text
        $sErrorText = gettext('Invalid login or password');
    }else{
        //Set user Id based on login name provided
        $iUserID = $usQueryResultSet['usr_per_id'];
    }
} else {
    // Nothing submitted yet, must be the first time loading this page.
    // Clear out any old session
    $iUserID = 0;
    $_COOKIE = array();
    $_SESSION = array();
    session_destroy();
}


// Has the form been submitted?
if ($iUserID > 0)
{
    // Get the information for the selected user
    $sSQL = "SELECT * FROM user_usr WHERE usr_per_ID = '$iUserID'";
    extract(mysql_fetch_array(RunQuery($sSQL)));

    $sSQL = "SELECT * FROM person_per WHERE per_ID = '$iUserID'";
    extract(mysql_fetch_array(RunQuery($sSQL)));

    $bPasswordMatch = FALSE;

    // Check the user password
    $sPasswordHashSha256 = hash("sha256", $_POST['Password'].$iUserID);

    // Block the login if a maximum login failure count has been reached
    if ($iMaxFailedLogins > 0 && $usr_FailedLogins >= $iMaxFailedLogins)
    {
        $sErrorText = gettext('Too many failed logins: your account has been locked.  Please contact an administrator.');
    }
    // Does the password match?
    elseif ($usr_Password != $sPasswordHashSha256)
    {
        // Increment the FailedLogins
        $sSQL = 'UPDATE user_usr SET usr_FailedLogins = usr_FailedLogins + 1 '.
                "WHERE usr_per_ID ='$iUserID'";
        RunQuery($sSQL);

        // Set the error text
        $sErrorText = gettext('Invalid login or password');
    }
    else
    {
        // Set the LastLogin and Increment the LoginCount
        $date = new DateTime("now", new DateTimeZone($sTimeZone));
        $sSQL = "UPDATE user_usr SET usr_LastLogin = '".$date->format('Y-m-d H:i:s')."', usr_LoginCount = usr_LoginCount + 1, usr_FailedLogins = 0 WHERE usr_per_ID ='$iUserID'";
        RunQuery($sSQL);

        // Set the User's family id in case EditSelf is enabled
        $_SESSION['iFamID'] = $per_fam_ID;

        // Set the UserID
        $_SESSION['iUserID'] = $usr_per_ID;

        // Set the Actual Name for use in the sidebar
        $_SESSION['UserFirstName'] = $per_FirstName;

        // Set the Actual Name for use in the sidebar
        $_SESSION['UserLastName'] = $per_LastName;

        // Set the pagination Search Limit
        $_SESSION['SearchLimit'] = $usr_SearchLimit;

        // Set the User's email address
        $_SESSION['sEmailAddress'] = $per_Email;

        // If user has administrator privilege, override other settings and enable all permissions.
        if ($usr_Admin)
        {
            $_SESSION['bAddRecords'] = true;
            $_SESSION['bEditRecords'] = true;
            $_SESSION['bDeleteRecords'] = true;
            $_SESSION['bMenuOptions'] = true;
            $_SESSION['bManageGroups'] = true;
            $_SESSION['bFinance'] = true;
            $_SESSION['bNotes'] = true;
            $_SESSION['bCommunication'] = true;
            $_SESSION['bCanvasser'] = true;
            $_SESSION['bAdmin'] = true;
        }
        // Otherwise, set the individual permissions.
        else
        {
            // Set the Add permission
            $_SESSION['bAddRecords'] = $usr_AddRecords;

            // Set the Edit permission
            $_SESSION['bEditRecords'] = $usr_EditRecords;

            // Set the Delete permission
            $_SESSION['bDeleteRecords'] = $usr_DeleteRecords;

            // Set the Menu Option permission
            $_SESSION['bMenuOptions'] = $usr_MenuOptions;

            // Set the ManageGroups permission
            $_SESSION['bManageGroups'] = $usr_ManageGroups;

            // Set the Donations and Finance permission
            $_SESSION['bFinance'] = $usr_Finance;

            // Set the Notes permission
            $_SESSION['bNotes'] = $usr_Notes;

            // Set the Communications permission
            $_SESSION['bCommunication'] = $usr_Communication;

            // Set the EditSelf permission
            $_SESSION['bEditSelf'] = $usr_EditSelf;

            // Set the Canvasser permission
            $_SESSION['bCanvasser'] = $usr_Canvasser;

            // Set the Admin permission
            $_SESSION['bAdmin'] = false;
        }

        // Set the FailedLogins
        $_SESSION['iFailedLogins'] = $usr_FailedLogins;

        // Set the LoginCount
        $_SESSION['iLoginCount'] = $usr_LoginCount;

        // Set the Last Login
        $_SESSION['dLastLogin'] = $usr_LastLogin;

        // Set the Workspace Width
        $_SESSION['iWorkspaceWidth'] = $usr_Workspacewidth;

        // Set the Base Font Size
        $_SESSION['iBaseFontSize'] = $usr_BaseFontSize;

        // Set the Style Sheet
        $_SESSION['sStyle'] = $usr_Style;

        // Create the Cart
        $_SESSION['aPeopleCart'] = array();

        // Create the variable for the Global Message
        $_SESSION['sGlobalMessage'] = '';

        // Set whether or not we need a password change
        $_SESSION['bNeedPasswordChange'] = $usr_NeedPasswordChange;

        // Initialize the last operation time
        $_SESSION['tLastOperation'] = time();

        // Set the Root Path ... used in basic security check
        $_SESSION['sRootPath'] = $sRootPath;

        // If PHP's magic quotes setting is turned off, we want to use a workaround to ensure security.
        if (function_exists('get_magic_quotes_gpc'))
            $_SESSION['bHasMagicQuotes'] = get_magic_quotes_gpc();
        else
            $_SESSION['bHasMagicQuotes'] = 0;

        // Pledge and payment preferences
        $_SESSION['sshowPledges'] = $usr_showPledges;
        $_SESSION['sshowPayments'] = $usr_showPayments;
        $_SESSION['sshowSince'] = $usr_showSince;
        $_SESSION['idefaultFY'] = CurrentFY(); // Improve the chance of getting the correct fiscal year assigned to new transactions
        $_SESSION['iCurrentDeposit'] = $usr_currentDeposit;

        // Church school calendar preferences
        $_SESSION['dCalStart'] = $usr_CalStart;
        $_SESSION['dCalEnd'] = $usr_CalEnd;
        $_SESSION['dCalNoSchool1'] = $usr_CalNoSchool1;
        $_SESSION['dCalNoSchool2'] = $usr_CalNoSchool2;
        $_SESSION['dCalNoSchool3'] = $usr_CalNoSchool3;
        $_SESSION['dCalNoSchool4'] = $usr_CalNoSchool4;
        $_SESSION['dCalNoSchool5'] = $usr_CalNoSchool5;
        $_SESSION['dCalNoSchool6'] = $usr_CalNoSchool6;
        $_SESSION['dCalNoSchool7'] = $usr_CalNoSchool7;
        $_SESSION['dCalNoSchool8'] = $usr_CalNoSchool8;

        // Search preference
        $_SESSION['bSearchFamily'] = $usr_SearchFamily;

        if (isset($bEnableMRBS) && $bEnableMRBS) {
            // set the session variable recognized by MRBS
            $_SESSION["UserName"] = $UserName;

            // Update the MRBS user record to match this churchCRM user
            $iMRBSLevel = 0;
            if ($usr_AddRecords) $iMRBSLevel = 1;
            if ($usr_Admin)      $iMRBSLevel = 2;

            $sSQL = "INSERT INTO mrbs_users (id, level, name, email) VALUES ('$iUserID', '$iMRBSLevel', '$UserName', '$per_Email') ON DUPLICATE KEY UPDATE level='$iMRBSLevel', name='$UserName',email='$per_Email'";
            RunQuery($sSQL);
        }

        $systemService = new SystemService();
        $_SESSION['latestVersion'] = $systemService->getLatestRelese();
        Redirect('CheckVersion.php');
        exit;
    }
}

// Turn ON output buffering
ob_start();

// Set the page title and include HTML header
$sPageTitle = "ChurchCRM - Login";
require ("Include/HeaderNotLoggedIn.php");
?>

<div class="login-box">
    <div class="login-logo">
        <b>Church</b>CRM</a>
    </div>
    <!-- /.login-logo -->
    <div class="login-box-body">
        <p class="login-box-msg"><?= gettext('Please Login') ?></p>

<?php
if (isset($_GET['Timeout']))
    $loginPageMsg = "Your previous session timed out.  Please login again.";

// output warning and error messages
if (isset($sErrorText))
    echo '<div class="alert alert-error">' . $sErrorText . '</div>';
if (isset($loginPageMsg))
    echo '<div class="alert alert-warning">' . $loginPageMsg . '</div>';
?>

<form class="form-signin" role="form" method="post" name="LoginForm" action="Login.php">
    <div class="form-group has-feedback">
        <input type="text" id="UserBox" name="User" class="form-control" placeholder="Email/Username" required autofocus>
        <span class="glyphicon glyphicon-envelope form-control-feedback"></span>
    </div>
    <div class="form-group has-feedback">
        <input type="password" id="PasswordBox" name="Password" class="form-control" placeholder="Password" required autofocus>
        <span class="glyphicon glyphicon-lock form-control-feedback"></span>
    </div>
    <div class="row">
        <div class="col-xs-8">
            <!--<div class="checkbox icheck">
                <label>
                    <input type="checkbox"> Remember Me
                </label>
            </div>-->
        </div>
        <!-- /.col -->
        <div class="col-xs-4">
            <button type="submit" class="btn btn-primary btn-block btn-flat"><?= gettext('Login') ?></button>
        </div>
    </div>
</form>
<?php
// Check if the login page is following thre required URL schema
// including the desired protocol, hiotsname, and path.
// Otherwise redirect to login page.
// An array of authorized URL's is specified in Config.php in the $URL array
checkAllowedURL();
?>
        <!--<a href="#">I forgot my password</a><br>
        <a href="register.html" class="text-center">Register a new membership</a>-->
    </div>
    <!-- /.login-box-body -->
</div>
<!-- /.login-box -->

<div id="not-chrome" class="error-page">
  <div class="callout callout-warning">
    <h4>For the best experience, please use Google Chrome.</h4>
    <p>This software has been tested with Google Chrome... <a href="https://www.google.com/chrome/browser/desktop/"> Download and install Google Chrome</a></p>
  </div>
</div>
<script language="JavaScript" type="text/JavaScript">
  $(document).ready(function () {
    $("#not-chrome").hide();
    var isChrome = /Chrome/.test(navigator.userAgent) && /Google Inc/.test(navigator.vendor);
    if (!isChrome)
      $("#not-chrome").show();

    document.LoginForm.User.focus();
  });
</script>

<?php
// Add the page footer
require ("Include/FooterNotLoggedIn.php");

// Turn OFF output buffering
ob_end_flush();
?>
