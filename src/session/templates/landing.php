<?php

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\ChurchLogoService;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\view\ChurchLogo;

$sPageTitle = gettext('Sign in');
$sBodyClass = 'page-auth page-login';
require SystemURLs::getDocumentRoot() . '/Include/HeaderNotLoggedIn.php';

?>

<div class="login-container">
  <div class="login-card">
    <div class="login-card-header">
      <div class="login-header-logo">
        <?= ChurchLogo::img() ?>
      </div>
      <?php if (!ChurchLogoService::hasCustomLogo()): ?>
      <h2 class="login-header-church-name"><?= InputUtils::escapeHTML(ChurchMetaData::getChurchName()) ?></h2>
      <?php endif; ?>
    </div>

    <div class="login-card-body">
      <nav class="login-doors" aria-label="<?= InputUtils::escapeAttribute(gettext('Sign in')) ?>">
        <?php foreach ($doors as $door): ?>
          <a
            id="<?= InputUtils::escapeAttribute($door['id']) ?>"
            class="login-door<?= $door['primary'] ? ' login-door-primary' : '' ?>"
            href="<?= InputUtils::escapeAttribute($door['href']) ?>"
          >
            <i class="fa-solid <?= InputUtils::escapeAttribute($door['icon']) ?>" aria-hidden="true"></i>
            <span><?= InputUtils::escapeHTML($door['label']) ?></span>
          </a>
        <?php endforeach; ?>
      </nav>
      <?php require __DIR__ . '/partials/church-contact.php'; ?>
    </div>
  </div>
</div>

<?php
require SystemURLs::getDocumentRoot() . '/Include/FooterNotLoggedIn.php';
