<?php

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemURLs;

$sPageTitle = gettext('Login');
require SystemURLs::getDocumentRoot() . '/Include/HeaderNotLoggedIn.php';

?>


<div class="login-container">
  <div class="login-wrapper">
    <!-- Form Section -->
    <div class="login-form-section">
      <div class="login-form-inner">
        <!-- Header with Logo and Church Name -->
        <div class="login-form-header">
          <div class="login-header-logo">
            <img src="<?= SystemURLs::getRootPath() ?>/Images/logo-churchcrm-350.jpg" alt="ChurchCRM" />
          </div>
          <h2 class="login-header-church-name"><?= ChurchMetaData::getChurchName() ?></h2>
          <p class="login-header-tagline"><?= gettext('Security First') ?></p>
        </div>

        <!-- Form Title -->
        <div class="login-form-title">
          <h1><i class="fa-solid fa-shield"></i><?= gettext('Verify your identity') ?></h1>
          <p><?= gettext('Enter the 6-digit code from your authenticator app to complete login') ?></p>
        </div>

        <div class="alert alert-info">
          <i class="fa-solid fa-mobile"></i>
          <div><?= gettext('Enter the 6-digit code from your authenticator app') ?></div>
        </div>

        <?php if (isset($bInvalidCode) && $bInvalidCode) { ?>
          <div class="alert alert-danger">
            <i class="fa-solid fa-circle-exclamation"></i>
            <div><?= gettext('Invalid code. Please try again.') ?></div>
          </div>
        <?php } ?>

        <form method="post" name="TwoFAForm" action="<?= SystemURLs::getRootPath()?>/session/two-factor">
          <div class="mb-3">
            <label for="TwoFACode"><?= gettext('Authentication Code') ?></label>
            <input type="text" id="TwoFACode" name="TwoFACode" placeholder="000000" maxlength="6" inputmode="numeric" required autofocus>
          </div>

          <button type="submit" class="btn-sign-in">
            <i class="fa-solid fa-right-to-bracket"></i><?= gettext('Verify & Sign In') ?>
          </button>
        </form>

        <div class="back-link">
          <a href="<?= SystemURLs::getRootPath() ?>/session/begin"><?= gettext('Use a different account') ?></a>
        </div>
      </div>
    </div>
  </div>
</div>
