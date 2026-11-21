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
      <nav class="login-doors" aria-label="<?= htmlspecialchars(gettext('Sign in')) ?>">
        <?php foreach ($doors as $door): ?>
          <a
            id="<?= htmlspecialchars($door['id']) ?>"
            class="login-door<?= $door['primary'] ? ' login-door-primary' : '' ?>"
            href="<?= htmlspecialchars($door['href']) ?>"
          >
            <i class="fa-solid <?= htmlspecialchars($door['icon']) ?>" aria-hidden="true"></i>
            <span><?= htmlspecialchars($door['label']) ?></span>
          </a>
        <?php endforeach; ?>
      </nav>
    </div>
  </div>
</div>

<?php
require SystemURLs::getDocumentRoot() . '/Include/FooterNotLoggedIn.php';
