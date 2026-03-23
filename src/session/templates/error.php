<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\dto\ChurchMetaData;

$sPageTitle = gettext("Password Reset");
require(SystemURLs::getDocumentRoot() ."/Include/HeaderNotLoggedIn.php");
?>


<div class="login-container">
  <div class="login-wrapper">
    <!-- Error Section -->
    <div class="login-form-section">
      <!-- Header with Logo and Church Name -->
      <div class="login-form-header">
        <div class="login-header-logo">
          <img src="<?= SystemURLs::getRootPath() ?>/Images/logo-churchcrm-350.jpg" alt="ChurchCRM" />
        </div>
        <h2 class="login-header-church-name"><?= ChurchMetaData::getChurchName() ?></h2>
        <p class="login-header-tagline"><?= gettext('Help & Support') ?></p>
      </div>

      <div class="alert alert-danger" role="alert">
        <h4 class="alert-heading"><i class="fa-solid fa-circle-exclamation"></i> <?= gettext("Password Reset Error") ?></h4>
        <p><?= gettext("We were unable to process your password reset request. Please try requesting a new password reset link.") ?></p>

        <div class="alert-buttons">
          <a href="<?= SystemURLs::getRootPath() ?>/session/forgot-password/reset-request" class="btn btn-primary">
            <i class="fa-solid fa-refresh"></i> <?= gettext("Request Password Reset") ?>
          </a>
          <a href="<?= SystemURLs::getRootPath() ?>/session/begin" class="btn btn-secondary">
            <i class="fa-solid fa-sign-in"></i> <?= gettext("Back to Login") ?>
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
require(SystemURLs::getDocumentRoot() ."/Include/FooterNotLoggedIn.php");
