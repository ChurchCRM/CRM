<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\view\ChurchLogo;

$sPageTitle = gettext('Login');
$sBodyClass = 'page-auth page-login';
require SystemURLs::getDocumentRoot() . '/Include/HeaderNotLoggedIn.php';

$bRecovery = isset($bRecoveryMode) && $bRecoveryMode;
$rootPath = SystemURLs::getRootPath();
?>
<main id="main-content" class="forgot-password-container">
  <div class="forgot-password-card">
    <div class="forgot-password-card-logo"><?= ChurchLogo::img() ?></div>
    <h2><?= InputUtils::escapeHTML(ChurchMetaData::getChurchName()) ?></h2>
    <p class="login-header-tagline"><?= gettext('Security First') ?></p>

    <?php if ($bRecovery) : ?>
      <h3><i class="fa-solid fa-key me-2"></i><?= gettext('Use Recovery Code') ?></h3>
      <p><?= gettext('Enter one of your backup recovery codes to access your account') ?></p>
    <?php else : ?>
      <h3><i class="fa-solid fa-shield me-2"></i><?= gettext('Verify your identity') ?></h3>
      <p><?= gettext('Enter the 6-digit code from your authenticator app to complete login') ?></p>
    <?php endif; ?>

    <?php if (isset($bInvalidCode) && $bInvalidCode) : ?>
      <div class="alert alert-danger" role="alert">
        <i class="fa-solid fa-circle-exclamation me-2"></i><?= gettext('Invalid code. Please try again.') ?>
      </div>
    <?php endif; ?>

    <form method="post" name="TwoFAForm" action="<?= $rootPath ?>/session/two-factor<?= $bRecovery ? '?recovery' : '' ?>">
      <div class="mb-3">
        <?php if ($bRecovery) : ?>
          <label for="TwoFACode" class="form-label"><?= gettext('Recovery Code') ?></label>
          <input type="text" id="TwoFACode" name="TwoFACode" class="form-control" placeholder="xxxxxxxx-xxxxxxxx" maxlength="20" autocomplete="off" required autofocus>
        <?php else : ?>
          <label for="TwoFACode" class="form-label"><?= gettext('Authentication Code') ?></label>
          <input type="text" id="TwoFACode" name="TwoFACode" class="form-control" placeholder="000000" maxlength="6" inputmode="numeric" autocomplete="one-time-code" required autofocus>
        <?php endif; ?>
      </div>

      <button type="submit" class="btn-reset">
        <i class="fa-solid fa-right-to-bracket me-2"></i><?= gettext('Verify & Sign In') ?>
      </button>
    </form>

    <div class="form-footer justify-content-center">
      <?php if ($bRecovery) : ?>
        <a href="<?= $rootPath ?>/session/two-factor"><?= gettext('Use authenticator app instead') ?></a>
      <?php else : ?>
        <a href="<?= $rootPath ?>/session/two-factor?recovery"><?= gettext('Use a recovery code instead') ?></a>
      <?php endif; ?>
    </div>
    <div class="form-footer justify-content-center">
      <a href="<?= AuthenticationManager::getStaffSignInURL() ?>"><?= gettext('Use a different account') ?></a>
    </div>
  </div>
</main>
<?php
require SystemURLs::getDocumentRoot() . '/Include/FooterNotLoggedIn.php';
