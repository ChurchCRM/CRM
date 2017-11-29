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
 ******************************************************************************/

// Include the function library
require 'Include/Config.php';
$bSuppressSessionTests = true; // DO NOT MOVE
require 'Include/Functions.php';

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Service\SystemService;
use ChurchCRM\UserQuery;
use ChurchCRM\Emails\LockedEmail;
use ChurchCRM\Service\NotificationService;
use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\PersonQuery;

if (!SystemService::isDBCurrent()) {
    Redirect('SystemDBUpdate.php');
    exit;
}

// Get the UserID out of user name submitted in form results
if (isset($_POST['User'])) {
    // Get the information for the selected user
    $UserName = InputUtils::LegacyFilterInput($_POST['User'], 'string', 32);
    $currentUser = UserQuery::create()->findOneByUserName($UserName);
    if ($currentUser == null) {
        // Set the error text
        $sErrorText = gettext('Invalid login or password');
    } // Block the login if a maximum login failure count has been reached
    elseif ($currentUser->isLocked()) {
        $sErrorText = gettext('Too many failed logins: your account has been locked.  Please contact an administrator.');
    } // Does the password match?
    elseif (!$currentUser->isPasswordValid($_POST['Password'])) {
        // Increment the FailedLogins
        $currentUser->setFailedLogins($currentUser->getFailedLogins() + 1);
        $currentUser->save();
        if (!empty($currentUser->getEmail()) && $currentUser->isLocked()) {
            $lockedEmail = new LockedEmail($currentUser);
            $lockedEmail->send();
        }

        // Set the error text
        $sErrorText = gettext('Invalid login or password');
    } else {
        // Set the LastLogin and Increment the LoginCount
        $date = new DateTime('now', new DateTimeZone(SystemConfig::getValue('sTimeZone')));
        $currentUser->setLastLogin($date->format('Y-m-d H:i:s'));
        $currentUser->setLoginCount($currentUser->getLoginCount() + 1);
        $currentUser->setFailedLogins(0);
        $currentUser->save();

        $_SESSION['user'] = $currentUser;

        // Set the User's family id in case EditSelf is enabled
        $_SESSION['iFamID'] = $currentUser->getPerson()->getFamId();

        // Set the UserID
        $_SESSION['iUserID'] = $currentUser->getPersonId();

        // Set the pagination Search Limit
        $_SESSION['SearchLimit'] = $currentUser->getSearchLimit();

        // If user has administrator privilege, override other settings and enable all permissions.
        $_SESSION['bAdmin'] = $currentUser->isAdmin();

        $_SESSION['bAddRecords'] = $currentUser->isAddRecordsEnabled();
        $_SESSION['bEditRecords'] = $currentUser->isEditRecordsEnabled();
        $_SESSION['bDeleteRecords'] = $currentUser->isDeleteRecordsEnabled();
        $_SESSION['bMenuOptions'] = $currentUser->isMenuOptionsEnabled();
        $_SESSION['bManageGroups'] = $currentUser->isManageGroupsEnabled();
        $_SESSION['bFinance'] = $currentUser->isFinanceEnabled();
        $_SESSION['bNotes'] = $currentUser->isNotesEnabled();
        $_SESSION['bEditSelf'] = $currentUser->isEditSelfEnabled();
        $_SESSION['bCanvasser'] = $currentUser->isCanvasserEnabled();

        // Set the FailedLogins
        $_SESSION['iFailedLogins'] = $currentUser->getFailedLogins();

        // Set the LoginCount
        $_SESSION['iLoginCount'] = $currentUser->getLoginCount();

        // Set the Last Login
        $_SESSION['dLastLogin'] = $currentUser->getLastLogin();

        // Set the Style Sheet
        $_SESSION['sStyle'] = $currentUser->getStyle();

        // Create the Cart
        $_SESSION['aPeopleCart'] = [];

        // Create the variable for the Global Message
        $_SESSION['sGlobalMessage'] = '';

        // Set whether or not we need a password change
        $_SESSION['bNeedPasswordChange'] = $currentUser->getNeedPasswordChange();

        // Initialize the last operation time
        $_SESSION['tLastOperation'] = time();

        $_SESSION['bHasMagicQuotes'] = 0;

        // Pledge and payment preferences
        $_SESSION['sshowPledges'] = $currentUser->getShowPledges();
        $_SESSION['sshowPayments'] = $currentUser->getShowPayments();
        $_SESSION['sshowSince'] = $currentUser->getShowSince();
        $_SESSION['idefaultFY'] = CurrentFY(); // Improve the chance of getting the correct fiscal year assigned to new transactions
        $_SESSION['iCurrentDeposit'] = $currentUser->getCurrentDeposit();

        // Search preference
        $_SESSION['bSearchFamily'] = $currentUser->getSearchfamily();

        $systemService = new SystemService();
        $_SESSION['latestVersion'] = $systemService->getLatestRelese();
        NotificationService::updateNotifications();
        Redirect('Menu.php');
        exit;
    }
} elseif (isset($_GET['username'])) {
    $urlUserName = $_GET['username'];
}

// we hold down the last id
$id = $_SESSION['iUserID'];

// we destroy the session
session_destroy();

// we reopen it
session_start() ;
$_SESSION['iUserID'] = $id;

// Set the page title and include HTML header
$sPageTitle = gettext('Login');
require 'Include/HeaderNotLoggedIn.php';

$person = PersonQuery::Create()
            ->findOneByID($_SESSION['iUserID']);

$user = UserQuery::Create()
            ->findOneByPersonId($_SESSION['iUserID']);
            
$urlUserName = $user->getUserName();


?>
<div class="lockscreen-wrapper">
    <div class="login-logo">
        Church<b>CRM</b>        
    </div>
    
    <div>
    <?php
        if (isset($_GET['Timeout'])) {
            $loginPageMsg = gettext('Your previous session timed out.  Please login again.');
        }

        // output warning and error messages
        if (isset($sErrorText)) {
            echo '<div class="alert alert-error">' . $sErrorText . '</div>';
        }
        if (isset($loginPageMsg)) {
            echo '<div class="alert alert-warning">' . $loginPageMsg . '</div>';
        }
        ?>
    </div>
    
    <div class="lockscreen-name text-center"><?= $urlUserName ?></div>
    
    <div class="lockscreen-item">
    
    <!-- lockscreen image -->
    <div class="lockscreen-image">
      <img src="<?= str_replace(SystemURLs::getDocumentRoot(), "", $person->getPhoto()->getPhotoURI()) ?>" alt="User Image">
    </div>
    <!-- /.lockscreen-image -->

    <!-- lockscreen credentials (contains the form) -->
    <form class="lockscreen-credentials" role="form" method="post" name="LoginForm" action="Suspend.php">
      <div class="input-group">
        <input type="hidden" id="UserBox" name="User" class="form-control" value="<?= $urlUserName ?>">

        <input type="password" id="PasswordBox" name="Password" class="form-control" placeholder="<?= gettext('Password')?>">
        <!--type="password" id="PasswordBox" name="Password" class="form-control" data-toggle="password"
                   placeholder="<?= gettext('Password') ?>" required autofocus-->

        <div class="input-group-btn">
          <button type="submit"  class="btn"><i class="fa fa-arrow-right text-muted"></i></button>
        </div>
      </div>
    </form>
    <!-- /.lockscreen credentials -->
  </div>
  <!-- /.lockscreen-item -->
  <div class="help-block text-center">
    <?= gettext("Enter your password to retrieve your session") ?>
  </div>
  <div class="text-center">
    <a href="Login.php"><?= gettext("Or sign in as a different user") ?></a>
  </div>
<!-- /.login-box-body -->
</div>
<!-- /.login-box -->
<script type="text/javascript" src="<?= SystemURLs::getRootPath() ?>/skin/external/bootstrap-show-password/bootstrap-show-password.min.js"></script>
<script>
    var $buoop = {vs: {i: 13, f: -2, o: -2, s: 9, c: -2}, unsecure: true, api: 4};
    function $buo_f() {
        var e = document.createElement("script");
        e.src = "//browser-update.org/update.min.js";
        document.body.appendChild(e);
    }

    try {
        document.addEventListener("DOMContentLoaded", $buo_f, false)
    }
    catch (e) {
        window.attachEvent("onload", $buo_f)
    }
    
    $('#password').password('toggle');
    $("#password").password({
        eyeOpenClass: 'glyphicon-eye-open',
        eyeCloseClass: 'glyphicon-eye-close'
    });    
</script>

<?php require 'Include/FooterNotLoggedIn.php'; ?>
