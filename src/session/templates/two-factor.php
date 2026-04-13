<?php

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemURLs;

$sPageTitle = gettext('Login');
$sBodyClass = 'page-auth page-login';
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

        <div class="alert alert-info" id="codeAlert">
          <i class="fa-solid fa-mobile" id="codeAlertIcon"></i>
          <div id="codeAlertText"><?= gettext('Enter the 6-digit code from your authenticator app') ?></div>
        </div>

        <?php if (isset($bInvalidCode) && $bInvalidCode) { ?>
          <div class="alert alert-danger">
            <i class="fa-solid fa-circle-exclamation"></i>
            <div><?= gettext('Invalid code. Please try again.') ?></div>
          </div>
        <?php } ?>

        <form method="post" name="TwoFAForm" action="<?= SystemURLs::getRootPath()?>/session/two-factor">
          <div class="mb-3">
            <label for="TwoFACode" id="codeLabel"><?= gettext('Authentication Code') ?></label>
            <input type="text" id="TwoFACode" name="TwoFACode" placeholder="000000" maxlength="6" inputmode="numeric" required autofocus>
          </div>

          <button type="submit" class="btn-sign-in">
            <i class="fa-solid fa-right-to-bracket"></i><?= gettext('Verify & Sign In') ?>
          </button>
        </form>

        <button type="button" class="btn btn-outline-secondary w-100 mt-3" id="toggleRecoveryMode">
          <i class="fa-solid fa-key me-1"></i><?= gettext('Use a recovery code instead') ?>
        </button>

        <div class="back-link">
          <a href="<?= SystemURLs::getRootPath() ?>/session/begin"><?= gettext('Use a different account') ?></a>
        </div>

        <script nonce="<?= SystemURLs::getCSPNonce() ?>">
        (function () {
          var recoveryMode = false;
          document.getElementById('toggleRecoveryMode').addEventListener('click', function () {
            recoveryMode = !recoveryMode;
            var input = document.getElementById('TwoFACode');
            var label = document.getElementById('codeLabel');
            var alertIcon = document.getElementById('codeAlertIcon');
            var alertText = document.getElementById('codeAlertText');
            if (recoveryMode) {
              input.placeholder = 'XXXXXX-XXXXXX';
              input.removeAttribute('maxlength');
              input.setAttribute('inputmode', 'text');
              input.value = '';
              label.textContent = <?= json_encode(gettext('Recovery Code')) ?>;
              alertIcon.className = 'fa-solid fa-key';
              alertText.textContent = <?= json_encode(gettext('Enter one of your recovery codes to access your account')) ?>;
              this.innerHTML = '<i class="fa-solid fa-mobile me-1"></i>' + <?= json_encode(gettext('Use authenticator app instead')) ?>;
            } else {
              input.placeholder = '000000';
              input.setAttribute('maxlength', '6');
              input.setAttribute('inputmode', 'numeric');
              input.value = '';
              label.textContent = <?= json_encode(gettext('Authentication Code')) ?>;
              alertIcon.className = 'fa-solid fa-mobile';
              alertText.textContent = <?= json_encode(gettext('Enter the 6-digit code from your authenticator app')) ?>;
              this.innerHTML = '<i class="fa-solid fa-key me-1"></i>' + <?= json_encode(gettext('Use a recovery code instead')) ?>;
            }
            input.focus();
          });
        })();
        </script>
      </div>
    </div>
  </div>
</div>
