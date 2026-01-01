<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\dto\ChurchMetaData;

$sPageTitle = gettext("Password Reset");
require(SystemURLs::getDocumentRoot() . "/Include/HeaderNotLoggedIn.php");
?>
  <div class="register-box register-box-600">
    <div class="register-logo">
      <a href="<?= SystemURLs::getRootPath() ?>"><?= ChurchMetaData::getChurchName() ?></a>
    </div>

    <div class="register-box-body">
      <div class="alert alert-danger" role="alert">
        <h4 class="alert-heading"><i class="fa-solid fa-exclamation-circle"></i> <?= gettext("Password Reset Error") ?></h4>
        <p><?= gettext("We were unable to process your password reset request. Please try requesting a new password reset link.") ?></p>
        <hr>
        <p class="mb-0">
          <a href="<?= SystemURLs::getRootPath() ?>/session/forgot-password/reset-request" class="btn btn-sm btn-primary">
            <i class="fa-solid fa-refresh"></i> <?= gettext("Request Password Reset") ?>
          </a>
          <a href="<?= SystemURLs::getRootPath() ?>/session/begin" class="btn btn-sm btn-secondary">
            <i class="fa-solid fa-sign-in"></i> <?= gettext("Back to Login") ?>
          </a>
        </p>
      </div>
    </div>
  </div>

<?php
require(SystemURLs::getDocumentRoot() . "/Include/FooterNotLoggedIn.php");
