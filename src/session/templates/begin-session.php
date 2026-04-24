<?php

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

$sPageTitle = gettext('Login');
$sBodyClass = 'page-auth page-login';
require SystemURLs::getDocumentRoot() . '/Include/HeaderNotLoggedIn.php';

?>

<?php
$hasSelfReg = SystemConfig::getBooleanValue('bEnableSelfRegistration');
?>

<div class="login-container <?php if ($hasSelfReg) echo 'login-container-split'; ?>">
  <div class="login-wrapper <?php if ($hasSelfReg) echo 'login-wrapper-split'; ?>">
    <!-- Login Form Section -->
    <div class="<?php if ($hasSelfReg) echo 'login-form-column'; else echo 'login-form-section'; ?>">
      <div class="login-form-inner">
        <!-- Header with Logo and Church Name -->
        <div class="login-form-header">
          <div class="login-header-logo">
            <img src="<?= SystemURLs::getRootPath() ?>/Images/logo-churchcrm-350.jpg" alt="ChurchCRM" />
          </div>
          <h2 class="login-header-church-name"><?= ChurchMetaData::getChurchName() ?></h2>
          <p class="login-header-tagline"><?= gettext('Community Management Platform') ?></p>
        </div>

        <!-- Form Title -->
        <div class="login-form-title">
          <h1><i class="fa-solid fa-right-to-bracket"></i><?= gettext('Login to your account') ?></h1>
          <p><?= gettext('Welcome back! Please enter your details to continue') ?></p>
        </div>

        <?php
        if (isset($_GET['Timeout'])) {
            $loginPageMsg = gettext('Your previous session timed out. Please login again.');
        }

        // output warning and error messages
        if (isset($sErrorText)) {
            echo '<div class="alert alert-danger">' . htmlspecialchars($sErrorText) . '</div>';
        }
        if (isset($loginPageMsg)) {
            echo '<div class="alert alert-warning">' . htmlspecialchars($loginPageMsg) . '</div>';
        }
        ?>

        <form method="post" name="LoginForm" action="<?= $localAuthNextStepURL ?>">
          <div class="mb-3">
            <label for="UserBox" class="form-label"><?= gettext('Email address') ?></label>
            <input type="text" id="UserBox" name="User" class="form-control" placeholder="name@example.com" value="<?= htmlspecialchars($prefilledUserName) ?>" required autofocus>
          </div>

          <div class="mb-3">
            <label for="PasswordBox" class="form-label"><?= gettext('Password') ?></label>
            <input type="password" id="PasswordBox" name="Password" class="form-control" placeholder="<?= gettext('Enter your password') ?>" required>
          </div>

          <div class="form-footer">
            <span></span>
            <?php if (SystemConfig::getBooleanValue('bEnableLostPassword') && SystemConfig::isEmailEnabled()) { ?>
              <a href="<?= htmlspecialchars($forgotPasswordURL) ?>"><?= gettext('Forgot password?') ?></a>
            <?php } ?>
          </div>

          <button type="submit" class="btn-sign-in"><?= gettext('Sign in') ?></button>
        </form>

        <?php if (!SystemConfig::getBooleanValue('bEnableSelfRegistration')) { ?>
          <div class="signup-link">
            <p><?= gettext('Need help?') ?> <a href="<?= SystemURLs::getRootPath() ?>/external/register/"><?= gettext('Contact us') ?></a></p>
          </div>
        <?php } ?>
      </div>
    </div><!-- end login-form-column / login-form-section -->

    <!-- Right Column: Registration Info (sibling of login-form-column) -->
    <?php if (SystemConfig::getBooleanValue('bEnableSelfRegistration')) { ?>
      <div class="login-register-column">
        <div class="register-content">
          <div class="register-icon">
            <i class="fa-solid fa-user-plus"></i>
          </div>
          <h2 class="register-title"><?= gettext('New to') ?> <?= ChurchMetaData::getChurchName() ?>?</h2>
          <p class="register-text">
            <?= gettext('Join our community and stay connected with') ?> <strong><?= ChurchMetaData::getChurchName() ?></strong>
          </p>
          <ul class="register-benefits">
            <li><i class="fa-solid fa-check"></i> <?= gettext('Easy registration') ?></li>
            <li><i class="fa-solid fa-check"></i> <?= gettext('Stay informed') ?></li>
            <li><i class="fa-solid fa-check"></i> <?= gettext('Connect with others') ?></li>
          </ul>
          <a href="<?= SystemURLs::getRootPath() ?>/external/register/" class="btn-register">
            <i class="fa-solid fa-people-roof me-2"></i><?= gettext('Register Your Family') ?>
          </a>
        </div>
      </div>
    <?php } ?>
  </div><!-- end login-wrapper -->
</div><!-- end login-container -->
<?php
require SystemURLs::getDocumentRoot() . '/Include/FooterNotLoggedIn.php';
