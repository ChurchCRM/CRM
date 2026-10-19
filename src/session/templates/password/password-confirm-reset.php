<?php

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\Utils\InputUtils;

$sPageTitle = gettext('Password Reset');
$sBodyClass = 'page-auth page-login';
$resetAction = $sRootPath . '/session/forgot-password/set/' . rawurlencode((string) $token);
require SystemURLs::getDocumentRoot() . '/Include/HeaderNotLoggedIn.php';
?>

<div class="login-container">
  <div class="login-wrapper">
    <div class="login-form-section">
      <div class="login-form-inner">
        <div class="login-form-header">
          <div class="login-header-logo">
            <img src="<?= InputUtils::escapeAttribute(SystemURLs::getRootPath()) ?>/Images/logo-churchcrm-350.jpg" alt="ChurchCRM">
          </div>
          <h2 class="login-header-church-name"><?= InputUtils::escapeHTML(ChurchMetaData::getChurchName()) ?></h2>
          <p class="login-header-tagline"><?= gettext('Password Recovery') ?></p>
        </div>

        <div class="login-form-title">
          <h1><i class="fa-solid fa-key"></i><?= gettext('Password Reset') ?></h1>
          <p><?= gettext('Please confirm the password reset of this user') ?></p>
        </div>

        <form method="post" action="<?= InputUtils::escapeAttribute($resetAction) ?>">
          <?= CSRFUtils::getTokenInputField() ?>
          <button class="btn-sign-in" type="submit">
            <i class="fa-solid fa-key"></i>
            <?= gettext('Reset Password') ?>
          </button>
          <a class="btn btn-secondary w-100 mt-2" href="<?= InputUtils::escapeAttribute(SystemURLs::getRootPath()) ?>/session/begin">
            <?= gettext('Back to login') ?>
          </a>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require SystemURLs::getDocumentRoot() . '/Include/FooterNotLoggedIn.php'; ?>
