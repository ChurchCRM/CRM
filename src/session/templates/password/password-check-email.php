<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\view\ChurchLogo;

$sPageTitle = gettext("Password Reset Successful");
$sBodyClass = 'page-auth page-login';
require(SystemURLs::getDocumentRoot() ."/Include/HeaderNotLoggedIn.php");
?>
<main id="main-content" class="forgot-password-container">
  <div class="forgot-password-card">
    <div class="forgot-password-card-logo"><?= ChurchLogo::img() ?></div>
    <h2><?= InputUtils::escapeHTML(ChurchMetaData::getChurchName()) ?></h2>
    <p class="login-header-tagline"><?= gettext('Password Recovery') ?></p>

    <div class="alert alert-success text-center" role="status">
      <i class="fa-solid fa-circle-check fa-2x mb-2 d-block"></i>
      <h3 class="h5 mb-2"><?= gettext("Password Reset Successful") ?></h3>
      <?= gettext('Your password has been changed. You can now sign in with your new password.') ?>
    </div>

    <div class="d-grid">
      <a href="<?= AuthenticationManager::getStaffSignInURL() ?>" class="btn btn-primary">
        <i class="fa-solid fa-right-to-bracket me-2"></i><?= gettext("Go to Login") ?>
      </a>
    </div>
  </div>
</main>
<?php
require(SystemURLs::getDocumentRoot() ."/Include/FooterNotLoggedIn.php");
