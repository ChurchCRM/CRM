<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\view\ChurchLogo;

$sPageTitle = gettext("Password Reset Successful");
$sBodyClass = 'page-auth page-login';
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
            <?= ChurchLogo::img() ?>
          </div>
          <h2 class="login-header-church-name"><?= InputUtils::escapeHTML(ChurchMetaData::getChurchName()) ?></h2>
          <p class="login-header-tagline"><?= gettext('Password Recovery') ?></p>
        </div>

        <div class="success-box">
          <div class="success-icon">
            <i class="fa-solid fa-check"></i>
          </div>
          <h2><?= gettext("Password Reset Successful") ?></h2>
          <p><?= gettext('Your password has been changed. You can now sign in with your new password.') ?></p>
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
