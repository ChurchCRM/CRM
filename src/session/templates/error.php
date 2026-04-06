<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\dto\ChurchMetaData;

$sPageTitle = gettext("Password Reset Error");
$sBodyClass = 'page-auth page-login';
require(SystemURLs::getDocumentRoot() ."/Include/HeaderNotLoggedIn.php");
?>

<div class="login-container">
  <div class="login-wrapper">
    <div class="login-form-section">
      <!-- Header with Logo and Church Name -->
      <div class="login-form-header">
        <div class="login-header-logo">
          <img src="<?= SystemURLs::getRootPath() ?>/Images/logo-churchcrm-350.jpg" alt="ChurchCRM" />
        </div>
        <h2 class="login-header-church-name"><?= ChurchMetaData::getChurchName() ?></h2>
        <p class="login-header-tagline"><?= gettext('Account Recovery') ?></p>
      </div>

      <!-- Error Title -->
      <div class="login-form-title">
        <h1><i class="fa-solid fa-circle-exclamation"></i><?= gettext('Password Reset Error') ?></h1>
        <p><?= gettext('We were unable to process your password reset request.') ?></p>
      </div>

      <!-- Error Alert Message -->
      <div class="alert alert-danger" role="alert">
        <p><?= gettext('Please try requesting a new password reset link or contact support if you continue to experience issues.') ?></p>
      </div>

      <!-- Action Buttons -->
      <div class="alert-buttons">
        <a href="<?= SystemURLs::getRootPath() ?>/session/forgot-password/reset-request" class="btn btn-primary">
          <i class="fa-solid fa-refresh me-2"></i><?= gettext('Request Password Reset') ?>
        </a>
        <a href="<?= SystemURLs::getRootPath() ?>/session/begin" class="btn btn-secondary">
          <i class="fa-solid fa-sign-in me-2"></i><?= gettext('Back to Login') ?>
        </a>
      </div>
    </div>
  </div>
</div>

<?php
require(SystemURLs::getDocumentRoot() ."/Include/FooterNotLoggedIn.php");
