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

if (isset($_GET['Timeout'])) {
    $loginPageMsg = gettext('Your previous session timed out. Please login again.');
}

$contactPhone   = ChurchMetaData::getChurchPhone();
$contactEmail   = ChurchMetaData::getChurchEmail();
$contactWebsite = ChurchMetaData::getChurchWebSite();
?>

<div class="login-container">
  <div class="login-card">

    <!-- Card header: logo + church name -->
    <div class="login-card-header">
      <div class="login-header-logo">
        <img src="<?= SystemURLs::getRootPath() ?>/Images/logo-churchcrm-350.jpg" alt="ChurchCRM" />
      </div>
      <h2 class="login-header-church-name"><?= ChurchMetaData::getChurchName() ?></h2>
      <p class="login-header-tagline"><?= gettext('Community Management Platform') ?></p>
    </div>

    <!-- Card body -->
    <div class="login-card-body">

      <?php if ($hasSelfReg): ?>
        <!--
          Segmented pill control — visible only when self-registration is enabled.
          "Register" opens the registration page in a new tab.
        -->
        <div class="login-tab-control" role="tablist" aria-label="<?= gettext('Account options') ?>">
          <button
            class="login-tab-btn active"
            id="tab-signin"
            role="tab"
            aria-selected="true"
          >
            <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
            <?= gettext('Sign In') ?>
          </button>
          <a
            href="<?= SystemURLs::getRootPath() ?>/external/register/"
            class="login-tab-btn"
            id="tab-register"
            role="tab"
            aria-selected="false"
            target="_blank"
            rel="noopener noreferrer"
          >
            <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
            <?= gettext('Register') ?>
          </a>
        </div>
      <?php else: ?>
        <!-- No pill when self-registration is disabled — plain form title -->
        <div class="login-form-title">
          <h1>
            <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
            <?= gettext('Login to your account') ?>
          </h1>
          <p><?= gettext('Welcome back! Please enter your details to continue') ?></p>
        </div>
      <?php endif; ?>

      <!-- Sign In form -->
      <?php if (isset($sErrorText)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($sErrorText) ?></div>
      <?php endif; ?>
      <?php if (isset($loginPageMsg)): ?>
        <div class="alert alert-warning"><?= htmlspecialchars($loginPageMsg) ?></div>
      <?php endif; ?>

      <form method="post" name="LoginForm" action="<?= $localAuthNextStepURL ?>">
        <div class="mb-3">
          <label for="UserBox" class="form-label"><?= gettext('Email address') ?></label>
          <input
            type="text"
            id="UserBox"
            name="User"
            class="form-control"
            placeholder="name@example.com"
            value="<?= htmlspecialchars($prefilledUserName) ?>"
            required
            autofocus
          >
        </div>

        <div class="mb-3">
          <label for="PasswordBox" class="form-label"><?= gettext('Password') ?></label>
          <input
            type="password"
            id="PasswordBox"
            name="Password"
            class="form-control"
            placeholder="<?= gettext('Enter your password') ?>"
            required
          >
        </div>

        <div class="form-footer">
          <span></span>
          <?php if (SystemConfig::getBooleanValue('bEnableLostPassword') && SystemConfig::isEmailEnabled()): ?>
            <a href="<?= htmlspecialchars($forgotPasswordURL) ?>"><?= gettext('Forgot password?') ?></a>
          <?php endif; ?>
        </div>

        <button type="submit" class="btn-sign-in"><?= gettext('Sign in') ?></button>
      </form>

      <?php if (!$hasSelfReg && ($contactPhone || $contactEmail || $contactWebsite)): ?>
        <!-- Compact church contact links — only when self-reg is off -->
        <div class="login-contact-footer">
          <?php if ($contactPhone): ?>
            <a href="tel:<?= htmlspecialchars($contactPhone) ?>">
              <i class="fa-solid fa-phone" aria-hidden="true"></i>
              <?= htmlspecialchars($contactPhone) ?>
            </a>
          <?php endif; ?>
          <?php if ($contactEmail): ?>
            <a href="mailto:<?= htmlspecialchars($contactEmail) ?>">
              <i class="fa-solid fa-envelope" aria-hidden="true"></i>
              <?= htmlspecialchars($contactEmail) ?>
            </a>
          <?php endif; ?>
          <?php if ($contactWebsite): ?>
            <a href="<?= htmlspecialchars($contactWebsite) ?>" target="_blank" rel="noopener noreferrer">
              <i class="fa-solid fa-globe" aria-hidden="true"></i>
              <?= htmlspecialchars($contactWebsite) ?>
            </a>
          <?php endif; ?>
        </div>
      <?php endif; ?>

    </div><!-- .login-card-body -->
  </div><!-- .login-card -->
</div><!-- .login-container -->

<?php
require SystemURLs::getDocumentRoot() . '/Include/FooterNotLoggedIn.php';
