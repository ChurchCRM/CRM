<?php

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemURLs;

$sPageTitle = gettext('My Account');
$sBodyClass = 'page-auth page-login';

require SystemURLs::getDocumentRoot() . '/Include/HeaderNotLoggedIn.php';
?>

<div class="login-container">
  <div class="login-wrapper">
    <div class="login-form-section">
      <div class="login-form-inner">
        <!-- Header with Logo and Church Name -->
        <div class="login-form-header">
          <div class="login-header-logo">
            <img src="<?= SystemURLs::getRootPath() ?>/Images/logo-churchcrm-350.jpg" alt="ChurchCRM" />
          </div>
          <h2 class="login-header-church-name"><?= htmlspecialchars(ChurchMetaData::getChurchName()) ?></h2>
        </div>

        <!-- Greeting -->
        <div class="login-form-title">
          <?php if (!empty($userName)): ?>
          <h1><?= gettext('Welcome') ?>, <?= htmlspecialchars($userName) ?></h1>
          <?php else: ?>
          <h1><?= gettext('Welcome') ?></h1>
          <?php endif; ?>
          <p><?= gettext('You can review and verify your family information using the link below. If you need additional access, please contact your church administrator.') ?></p>
        </div>

        <!-- Action Buttons -->
        <div class="d-grid gap-2 mb-3">
          <?php if (!empty($verifyUrl)): ?>
          <a href="<?= htmlspecialchars($verifyUrl) ?>" class="btn btn-primary btn-lg">
            <i class="fa-solid fa-clipboard-check me-2"></i><?= gettext('Verify Family Info') ?>
          </a>
          <?php endif; ?>
          <a href="<?= SystemURLs::getRootPath() ?>/session/end" class="btn btn-outline-secondary">
            <i class="fa-solid fa-right-from-bracket me-2"></i><?= gettext('Log Out') ?>
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
require SystemURLs::getDocumentRoot() . '/Include/FooterNotLoggedIn.php';
