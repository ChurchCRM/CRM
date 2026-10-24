<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\view\ChurchLogo;

$sPageTitle = gettext("Password Reset Error");
$sBodyClass = 'page-auth page-login';
require(SystemURLs::getDocumentRoot() ."/Include/HeaderNotLoggedIn.php");
?>
<main id="main-content" class="forgot-password-container">
  <div class="forgot-password-card bg-body text-body">
    <div class="forgot-password-card-logo bg-white rounded"><?= ChurchLogo::img() ?></div>
    <h2 class="text-body"><?= InputUtils::escapeHTML(ChurchMetaData::getChurchName()) ?></h2>
    <p class="login-header-tagline text-secondary"><?= gettext('Account Recovery') ?></p>
    <h3 class="text-body"><i class="fa-solid fa-circle-exclamation me-2"></i><?= gettext('Password Reset Error') ?></h3>
    <p class="text-secondary"><?= gettext('We were unable to process your password reset request.') ?></p>

    <?php if (!empty($message ?? '')): ?>
    <div class="alert alert-warning" role="alert"><?= InputUtils::escapeHTML($message) ?></div>
    <?php endif; ?>

    <div class="alert alert-danger" role="alert">
      <?= gettext('Please try requesting a new password reset link or contact support if you continue to experience issues.') ?>
    </div>

    <div class="d-grid gap-2">
      <a href="<?= SystemURLs::getRootPath() ?>/session/forgot-password/reset-request" class="btn btn-primary">
        <i class="fa-solid fa-rotate me-2"></i><?= gettext('Request Password Reset') ?>
      </a>
      <a href="<?= SystemURLs::getRootPath() ?>/session/begin" class="btn btn-outline-secondary">
        <i class="fa-solid fa-arrow-right-to-bracket me-2"></i><?= gettext('Back to Login') ?>
      </a>
    </div>
  </div>
</main>
<?php
require(SystemURLs::getDocumentRoot() ."/Include/FooterNotLoggedIn.php");
