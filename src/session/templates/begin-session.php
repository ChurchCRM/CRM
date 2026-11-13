<?php

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\ChurchLogoService;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\view\ChurchLogo;

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
        <?= ChurchLogo::img() ?>
      </div>
      <?php if (!ChurchLogoService::hasCustomLogo()): ?>
      <h2 class="login-header-church-name"><?= InputUtils::escapeHTML(ChurchMetaData::getChurchName()) ?></h2>
      <?php endif; ?>
      <p class="login-header-tagline"><?= gettext('Community Management Platform') ?></p>
    </div>

    <!-- Card body -->
    <div class="login-card-body">

      <div class="login-form-title">
        <h1>
          <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
          <?= gettext('Login to your account') ?>
        </h1>
        <p><?= gettext('Welcome back! Please enter your details to continue') ?></p>
      </div>

      <!-- Sign In form -->
      <?php if (isset($sErrorText)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($sErrorText) ?></div>
      <?php endif; ?>
      <?php if (isset($loginPageMsg)): ?>
        <div class="alert alert-warning"><?= htmlspecialchars($loginPageMsg) ?></div>
      <?php endif; ?>

      <form method="post" name="LoginForm" action="<?= $localAuthNextStepURL ?>">
        <div class="mb-3">
          <label for="UserBox" class="form-label"><?= gettext('Username or Email Address') ?></label>
          <input
            type="text"
            id="UserBox"
            name="User"
            class="form-control"
            placeholder="<?= htmlspecialchars(gettext('Enter your username or email'), ENT_QUOTES, 'UTF-8') ?>"
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
