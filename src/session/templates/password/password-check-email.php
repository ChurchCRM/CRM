<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\dto\ChurchMetaData;

$sPageTitle = gettext("Password Reset Successful");
require(SystemURLs::getDocumentRoot() ."/Include/HeaderNotLoggedIn.php");
?>


<div class="login-container">
  <div class="login-wrapper">
    <!-- Success Section -->
    <div class="login-form-section">
      <div class="login-form-inner">
        <!-- Header with Logo and Church Name -->
        <div class="login-form-header">
          <div class="login-header-logo">
            <img src="<?= SystemURLs::getRootPath() ?>/Images/logo-churchcrm-350.jpg" alt="ChurchCRM" />
          </div>
          <h2 class="login-header-church-name"><?= ChurchMetaData::getChurchName() ?></h2>
          <p class="login-header-tagline"><?= gettext('Password Recovery') ?></p>
        </div>

        <div class="success-box">
          <div class="success-icon">
            <i class="fa-solid fa-check"></i>
          </div>
          <h2><?= gettext("Password Reset Successful") ?></h2>
          <p><?= gettext("A new password has been generated and sent to your email address.") ?></p>
          <span class="small"><?= gettext("Please check your email (including spam/junk folder) for your temporary password.") ?></span>
          <span class="small"><?= gettext("Once you receive the email, you can log in with your temporary password and change it to something you prefer.") ?></span>
        </div>

        <a href="<?= SystemURLs::getRootPath() ?>/session/begin" class="btn-sign-in">
          <i class="fa-solid fa-right-to-bracket"></i>
          <?= gettext("Go to Login") ?>
        </a>
      </div>
    </div>
  </div>
</div>
<?php
require(SystemURLs::getDocumentRoot() ."/Include/FooterNotLoggedIn.php");
