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
      <p class="login-header-tagline"><?= gettext('Community Management Platform') ?></p>
    </div>

    <div class="login-card-body">
      <div class="login-form-title">
        <h1><?= gettext('Sign in') ?></h1>
      </div>

      <?php foreach ($doors as $door): ?>
        <a
          id="<?= htmlspecialchars($door['id']) ?>"
          class="<?= $door['primary'] ? 'btn-sign-in' : 'login-door-quiet' ?>"
          href="<?= htmlspecialchars($door['href']) ?>"
        >
          <i class="fa-solid <?= htmlspecialchars($door['icon']) ?>" aria-hidden="true"></i>
          <?= htmlspecialchars($door['label']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php
require SystemURLs::getDocumentRoot() . '/Include/FooterNotLoggedIn.php';
