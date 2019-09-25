<?php
/*******************************************************************************
 *
 *  filename    : Login.php
 *  website     : http://www.churchcrm.io
 *  description : page header used for most pages
 *
 *  Copyright 2017 Philippe Logel
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
use ChurchCRM\Utils\RedirectUtils;

$urlUserName =""; //initialize this variable so that PHP Strict doesn't warn about it
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

        $_SESSION['bManageGroups'] = $currentUser->isManageGroupsEnabled();
        $_SESSION['bFinance'] = $currentUser->isFinanceEnabled();

        // Create the Cart
        $_SESSION['aPeopleCart'] = [];

        // Create the variable for the Global Message
        $_SESSION['sGlobalMessage'] = '';

        // Initialize the last operation time
        $_SESSION['tLastOperation'] = time();

        $_SESSION['bHasMagicQuotes'] = 0;

        // Pledge and payment preferences
        $_SESSION['sshowPledges'] = $currentUser->getShowPledges();
        $_SESSION['sshowPayments'] = $currentUser->getShowPayments();
        $_SESSION['idefaultFY'] = CurrentFY(); // Improve the chance of getting the correct fiscal year assigned to new transactions
        $_SESSION['iCurrentDeposit'] = $currentUser->getCurrentDeposit();

        $systemService = new SystemService();
        NotificationService::updateNotifications();
        $redirectLocation = $_SESSION['location'];
        if (isset($redirectLocation)) {
            RedirectUtils::Redirect($redirectLocation);
            exit;
        } else {
            RedirectUtils::Redirect('Menu.php');
            exit;
        }
    }
} elseif (isset($_GET['username'])) {
    $urlUserName = $_GET['username'];
}

$id = 0;
$type ="";

// we hold down the last id
if (isset($_SESSION['user'])) {
    $id = $_SESSION['user']->getId();
}

// we hold down the last type of login : lock or nothing
if (isset($_SESSION['iLoginType'])) {
    $type = $_SESSION['iLoginType'];
}


if (isset($_GET['session']) && $_GET['session'] == "Lock") {// We are in a Lock session
    $type = $_SESSION['iLoginType']  = "Lock";
}

if (empty($urlUserName)) {
    if (isset($_SESSION['user'])) {
        $user = $_SESSION['user'];
        $urlUserName = $user->getUserName();
    } elseif (isset($_SESSION['username'])) {
        $urlUserName = $_SESSION['username'];
    }
}

if (isset($_SESSION['location'])) {
    $LocationFromSession = $_SESSION['location'];
}

// we destroy the session
session_destroy();

// we reopen a new one
session_start() ;

    // we restore only this part
$_SESSION['iLoginType'] = $type;
$_SESSION['username'] = $urlUserName;
$LocationFromGet = "";
if (array_key_exists("location", $_GET)) {
    $LocationFromGet =InputUtils::FilterString(urldecode($_GET['location']));
}
if (substr($LocationFromGet, 0, 1) == "/") {
    $LocationFromGet = substr($LocationFromGet, 1);
}

if (isset($LocationFromSession) && $LocationFromSession != '') {
    $_SESSION['location'] = $LocationFromSession;
}

if (isset($LocationFromGet) && $LocationFromGet != '') {
    $_SESSION['location'] = $LocationFromGet;
}
if ($type == "Lock" && $id > 0) {// this point is important for the photo in a lock session
    $person = PersonQuery::Create()
              ->findOneByID($id);
} else {
    $type = $_SESSION['iLoginType'] = "";
}

// Set the page title and include HTML header
$sPageTitle = gettext('Login');
require 'Include/HeaderNotLoggedIn.php';

?>
<div class="login-box" id="Login">
    <div class="login-logo">
        Church<b>CRM</b>
    </div>

    <!-- /.login-logo -->
    <div class="login-box-body">
        <p class="login-box-msg">
            <b><?= ChurchMetaData::getChurchName() ?></b><br/>
            <?= gettext('Please Login') ?>
        </p>

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

        <form class="form-signin" role="form" method="post" name="LoginForm" action="Login.php">
            <div class="form-group has-feedback">
                <input type="text" id="UserBox" name="User" class="form-control" value="<?= $urlUserName ?>"
                   placeholder="<?= gettext('Email/Username') ?>" required autofocus>
            </div>
            <div class="form-group has-feedback">
                <input type="password" id="PasswordBox" name="Password" class="form-control" data-toggle="password"
                   placeholder="<?= gettext('Password') ?>" required autofocus>
                <br/>
                <?php if (SystemConfig::getBooleanValue('bEnableLostPassword')) {
            ?>
                    <span class="text-right"><a
                                href="external/password/"><?= gettext("I forgot my password") ?></a></span>
                    <?php
        } ?>
            </div>
            <div class="row">
                <!-- /.col -->
                <div class="col-xs-5">
                    <button type="submit" class="btn btn-primary btn-block btn-flat"><i
                                class="fa fa-sign-in"></i> <?= gettext('Login') ?></button>
                </div>
            </div>
        </form>

        <?php if (SystemConfig::getBooleanValue('bEnableSelfRegistration')) {
            ?>
            <a href="<?= SystemURLs::getRootPath() ?>/external/register/" class="text-center btn bg-olive"><i
                        class="fa fa-user-plus"></i> <?= gettext('Register a new Family'); ?></a><br>
            <?php
        } ?>
        <!--<a href="external/family/verify" class="text-center">Verify Family Info</a> -->

    </div>

<!-- /.login-box-body -->
</div>
<!-- /.login-box -->
<div class="lockscreen-wrapper" id="Lock">
    <div class="login-logo">
        Church<b>CRM</b>
    </div>

    <p class="login-box-msg">
        <b><?= ChurchMetaData::getChurchName() ?></b><br/>
            <?= gettext('Please Login') ?>
    </p>



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
      <?php if ($_SESSION['iLoginType'] == "Lock") {
            ?>
      <img src="<?= str_replace(SystemURLs::getDocumentRoot(), "", $person->getPhoto()->getThumbnailURI()) ?>" alt="User Image">
      <?php
        } ?>
    </div>
    <!-- /.lockscreen-image -->

    <!-- lockscreen credentials (contains the form) -->
    <form class="lockscreen-credentials" role="form" method="post" name="LoginForm" action="Login.php">
      <div class="input-group">
        <input type="hidden" id="UserBoxLock" name="User" class="form-control" value="<?= $urlUserName ?>">

        <input type="password" id="PasswordBoxLock" name="Password" class="form-control" placeholder="<?= gettext('Password')?>">

        <div class="input-group-btn">
          <button type="submit"  class="btn btn-default"><i class="fa fa-arrow-right text-muted"></i></button>
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
    <a href="#" id="Login-div-appear"><?= gettext("Or sign in as a different user") ?></a>
  </div>
<!-- /.login-box-body -->
</div>
<!-- /.lockscreen-wrapper -->
<script  src="<?= SystemURLs::getRootPath() ?>/skin/external/bootstrap-show-password/bootstrap-show-password.min.js"></script>
<script  src="<?= SystemURLs::getRootPath() ?>/skin/js/Login.js"></script>
<?php require 'Include/FooterNotLoggedIn.php'; ?>
