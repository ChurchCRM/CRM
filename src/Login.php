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

use ChurchCRM\Service\SystemService;
use ChurchCRM\UserQuery;

$systemService = new SystemService();

// Is the user requesting to logoff or timed out?
if (isset($_GET["Logoff"]) || isset($_GET['Timeout'])) {
    if (!isset($_SESSION['sshowPledges']) || ($_SESSION['sshowPledges'] == ''))
        $_SESSION['sshowPledges'] = 0;
    if (!isset($_SESSION['sshowPayments']) || ($_SESSION['sshowPayments'] == ''))
        $_SESSION['sshowPayments'] = 0;
    if (!isset($_SESSION['bSearchFamily']) || ($_SESSION['bSearchFamily'] == ''))
        $_SESSION['bSearchFamily'] = 0;

   if (!empty($_SESSION['iUserID'])) {
    $currentUser = UserQuery::create()->findOneByPersonId($_SESSION['iUserID']);
    $currentUser->setShowPledges($_SESSION['sshowPledges']);
    $currentUser->setShowPayments($_SESSION['sshowPayments']);
    $currentUser->setShowSince($_SESSION['sshowSince']);
    $currentUser->setDefaultFY($_SESSION['idefaultFY'] );
    $currentUser->setCurrentDeposit($_SESSION['iCurrentDeposit']);

    if ($_SESSION['dCalStart'] != '')
        $currentUser->setCalStart ($_SESSION['dCalStart']);
    if ($_SESSION['dCalEnd'] != '')
        $currentUser->setCalEnd ($_SESSION['dCalEnd']);
    if ($_SESSION['dCalNoSchool1'] != '')
        $currentUser->setCalNoSchool1 ($_SESSION['dCalNoSchool1']);
    if ($_SESSION['dCalNoSchool2'] != '')
        $currentUser->dCalNoSchool2 ($_SESSION['dCalNoSchool2']);
    if ($_SESSION['dCalNoSchool3'] != '')
        $currentUser->dCalNoSchool3 ($_SESSION['dCalNoSchool3']);
    if ($_SESSION['dCalNoSchool4'] != '')
        $currentUser->dCalNoSchool4 ($_SESSION['dCalNoSchool4']);
    if ($_SESSION['dCalNoSchool5'] != '')
        $currentUser->dCalNoSchool5 ($_SESSION['dCalNoSchool5']);
    if ($_SESSION['dCalNoSchool6'] != '')
        $currentUser->dCalNoSchool6 ($_SESSION['dCalNoSchool6']);
    if ($_SESSION['dCalNoSchool7'] != '')
        $currentUser->dCalNoSchool7 ($_SESSION['dCalNoSchool7']);
    if ($_SESSION['dCalNoSchool8'] != '')
        $currentUser->dCalNoSchool8 ($_SESSION['dCalNoSchool8']);
    $currentUser->setSearchfamily($_SESSION['bSearchFamily']);
    $currentUser->save();
   }
}

$currentUser = 0;
// Get the UserID out of user name submitted in form results
if (isset($_POST['User']) && !isset($sErrorText)) {

    // Get the information for the selected user
    $UserName = FilterInput($_POST['User'],'string',32);
    $currentUser = UserQuery::create()->findOneByUserName($UserName);
    if ($currentUser == Null){
        // Set the error text
        $sErrorText = gettext('Invalid login or password');
    }
} else {
    // Nothing submitted yet, must be the first time loading this page.
    // Clear out any old session
    $currentUser = 0;
    $_COOKIE = array();
    $_SESSION = array();
    session_destroy();
}


// Has the form been submitted?
if ($currentUser != Null)
{
    $bPasswordMatch = FALSE;
    
    // Check the user password
    $sPasswordHashSha256 = hash("sha256", $_POST['Password'].$currentUser->getPersonId());
    
    // Block the login if a maximum login failure count has been reached
    if ($iMaxFailedLogins > 0 && $currentUser->getFailedLogins() >= $iMaxFailedLogins)
    {
        $sErrorText = gettext('Too many failed logins: your account has been locked.  Please contact an administrator.');
    }
    // Does the password match?
    elseif ($currentUser->getPassword() != $sPasswordHashSha256)
    {
        // Increment the FailedLogins
        $currentUser->setFailedLogins($currentUser->getFailedLogins()+1);
        $currentUser->save();
        
        // Set the error text
        $sErrorText = gettext('Invalid login or password');
    }
    else
    {
        // Set the LastLogin and Increment the LoginCount
        $date = new DateTime("now", new DateTimeZone($sTimeZone));
        $currentUser->setLastLogin($date->format('Y-m-d H:i:s'));
        $currentUser->setLoginCount($currentUser->getLoginCount() +1);
        $currentUser->setFailedLogins(0);
        $currentUser->save();       

        // Set the User's family id in case EditSelf is enabled
        $_SESSION['iFamID'] = $currentUser->getPerson()->getFamId();

        // Set the UserID
        $_SESSION['iUserID'] = $currentUser->getPersonId();

        // Set the Actual Name for use in the sidebar
        $_SESSION['UserFirstName'] = $currentUser->getPerson()->getFirstName();

        // Set the Actual Name for use in the sidebar
        $_SESSION['UserLastName'] = $currentUser->getPerson()->getLastName();

        // Set the pagination Search Limit
        $_SESSION['SearchLimit'] = $currentUser->getSearchLimit();

        // Set the User's email address
        $_SESSION['sEmailAddress'] = $currentUser->getPerson()->getEmail();

        // If user has administrator privilege, override other settings and enable all permissions.
        if ($currentUser->getAdmin())
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
            $_SESSION['bAddRecords'] = $currentUser->getAddRecords();

            // Set the Edit permission
            $_SESSION['bEditRecords'] = $currentUser->getEditRecords();

            // Set the Delete permission
            $_SESSION['bDeleteRecords'] = $currentUser->getDeleteRecords();

            // Set the Menu Option permission
            $_SESSION['bMenuOptions'] = $currentUser->getMenuOptions();

            // Set the ManageGroups permission
            $_SESSION['bManageGroups'] = $currentUser->getManageGroups();

            // Set the Donations and Finance permission
            $_SESSION['bFinance'] = $currentUser->getFinance();

            // Set the Notes permission
            $_SESSION['bNotes'] = $currentUser->getNotes();

            // Set the Communications permission
            $_SESSION['bCommunication'] = $currentUser->getCommunication();

            // Set the EditSelf permission
            $_SESSION['bEditSelf'] = $currentUser->getEditSelf();

            // Set the Canvasser permission
            $_SESSION['bCanvasser'] = $currentUser->getCanvasser();

            // Set the Admin permission
            $_SESSION['bAdmin'] = false;
        }

        // Set the FailedLogins
        $_SESSION['iFailedLogins'] = $currentUser->getFailedLogins();

        // Set the LoginCount
        $_SESSION['iLoginCount'] = $currentUser->getLoginCount();

        // Set the Last Login
        $_SESSION['dLastLogin'] = $currentUser->getLastLogin();

        // Set the Workspace Width
        $_SESSION['iWorkspaceWidth'] = $currentUser->getWorkspaceWidth();

        // Set the Base Font Size
        $_SESSION['iBaseFontSize'] = $currentUser->getBaseFontsize();

        // Set the Style Sheet
        $_SESSION['sStyle'] = $currentUser->getStyle();

        // Create the Cart
        $_SESSION['aPeopleCart'] = array();

        // Create the variable for the Global Message
        $_SESSION['sGlobalMessage'] = '';

        // Set whether or not we need a password change
        $_SESSION['bNeedPasswordChange'] = $currentUser->getNeedPasswordChange();

        // Initialize the last operation time
        $_SESSION['tLastOperation'] = time();

        // Set the Root Path ... used in basic security check
        $_SESSION['sRootPath'] = $sRootPath;
        $_SESSION['$sEnableGravatarPhotos'] = $sEnableGravatarPhotos;

        $_SESSION['bHasMagicQuotes'] = 0;

        // Pledge and payment preferences
        $_SESSION['sshowPledges'] = $currentUser->getShowPledges();
        $_SESSION['sshowPayments'] = $currentUser->getShowPayments();
        $_SESSION['sshowSince'] = $currentUser->getShowSince();
        $_SESSION['idefaultFY'] = CurrentFY(); // Improve the chance of getting the correct fiscal year assigned to new transactions
        $_SESSION['iCurrentDeposit'] = $currentUser->getCurrentDeposit();

        // Church school calendar preferences
        $_SESSION['dCalStart'] = $currentUser->getCalStart();
        $_SESSION['dCalEnd'] = $currentUser->getCalEnd();
        $_SESSION['dCalNoSchool1'] = $currentUser->getCalNoSchool1();
        $_SESSION['dCalNoSchool2'] = $currentUser->getCalNoSchool2();
        $_SESSION['dCalNoSchool3'] = $currentUser->getCalNoSchool3();
        $_SESSION['dCalNoSchool4'] = $currentUser->getCalNoSchool4();
        $_SESSION['dCalNoSchool5'] = $currentUser->getCalNoSchool5();
        $_SESSION['dCalNoSchool6'] = $currentUser->getCalNoSchool6();
        $_SESSION['dCalNoSchool7'] = $currentUser->getCalNoSchool7();
        $_SESSION['dCalNoSchool8'] = $currentUser->getCalNoSchool8();

        // Search preference
        $_SESSION['bSearchFamily'] = $currentUser->getSearchfamily();

        $_SESSION['latestVersion'] = $systemService->getLatestRelese();
        Redirect('CheckVersion.php');
        exit;
    }
}

// Turn ON output buffering
ob_start();

$enableSelfReg = $systemConfig->getRawConfig("sEnableSelfRegistration")->getBooleanValue();

// Set the page title and include HTML header
$sPageTitle = gettext("ChurchCRM - Login");
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
        <input type="text" id="UserBox" name="User" class="form-control" placeholder="<?= gettext("Email/Username")?>" required autofocus>
        <span class="glyphicon glyphicon-envelope form-control-feedback"></span>
    </div>
    <div class="form-group has-feedback">
        <input type="password" id="PasswordBox" name="Password" class="form-control" placeholder="<?= gettext("Password") ?>" required autofocus>
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
            <button type="submit" class="btn btn-primary btn-block btn-flat"><i class="fa fa-sign-in"></i> <?= gettext('Login') ?></button>
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
        <!--<a href="external/user/password">I forgot my password</a><br> -->
        <?php if ($enableSelfReg) { ?>
        <a href="external/family/register" class="text-center btn bg-olive"><i class="fa fa-user-plus"></i> <?= gettext("Register a new Family");?></a><br>
        <?php } ?>
      <!--<a href="external/family/verify" class="text-center">Verify Family Info</a> -->
    </div>
    <!-- /.login-box-body -->
</div>
<!-- /.login-box -->

<script>
  var $buoop = {vs:{i:11,f:30,o:25,s:7},c:2};
  function $buo_f(){
    var e = document.createElement("script");
    e.src = "//browser-update.org/update.min.js";
    document.body.appendChild(e);
  };
  try {document.addEventListener("DOMContentLoaded", $buo_f,false)}
  catch(e){window.attachEvent("onload", $buo_f)}
</script>

<?php
// Add the page footer
require ("Include/FooterNotLoggedIn.php");

// Turn OFF output buffering
ob_end_flush();
?>
