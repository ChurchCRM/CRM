<?php

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\view\ChurchLogo;

$sPageTitle = gettext('Reset your password');
$sBodyClass = 'page-auth page-login';
require SystemURLs::getDocumentRoot() . '/Include/HeaderNotLoggedIn.php';
?>
<main id="main-content" class="forgot-password-container">
  <div class="forgot-password-card bg-body text-body">
    <div class="forgot-password-card-logo bg-white rounded"><?= ChurchLogo::img() ?></div>
    <h2 class="text-body"><?= InputUtils::escapeHTML(ChurchMetaData::getChurchName()) ?></h2>
    <p class="login-header-tagline text-secondary"><?= gettext('Account Recovery') ?></p>
    <h3 class="text-body"><?= gettext('Reset your password') ?></h3>
    <?php if ($error !== null): ?>
      <div id="password-error" class="alert alert-danger" role="alert"><?= InputUtils::escapeHTML($error) ?></div>
    <?php endif; ?>
    <form id="setPasswordForm" method="post" action="<?= InputUtils::escapeAttribute($sRootPath . '/session/forgot-password/set/' . rawurlencode($token)) ?>">
      <?= CSRFUtils::getTokenInputField() ?>
      <div class="mb-3">
        <label for="password" class="form-label text-body"><?= gettext('New Password') ?></label>
        <input id="password" name="password" type="password" class="form-control bg-body text-body" autocomplete="new-password" required minlength="<?= (int) $minPasswordLength ?>" aria-describedby="password-help">
        <div id="password-help" class="form-text"><?= InputUtils::escapeHTML(gettext('Your new password must be at least') . ' ' . $minPasswordLength . ' ' . gettext('characters')) ?></div>
      </div>
      <div class="mb-3">
        <label for="passwordConfirmation" class="form-label text-body"><?= gettext('Confirm Password') ?></label>
        <input id="passwordConfirmation" name="passwordConfirmation" type="password" class="form-control bg-body text-body" autocomplete="new-password" required>
      </div>
      <button id="setPassword" type="submit" class="btn-reset"><?= gettext('Reset Password') ?></button>
      <div class="form-footer"><a href="<?= $sRootPath ?>/session/begin"><?= gettext('Back to login') ?></a></div>
    </form>
  </div>
</main>
<?php require SystemURLs::getDocumentRoot() . '/Include/FooterNotLoggedIn.php';
