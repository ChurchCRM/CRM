<?php

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\ChurchLogoService;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\view\ChurchLogo;

/** @var string $homeUrl */
?>
<a href="<?= InputUtils::escapeAttribute($homeUrl) ?>" class="navbar-brand py-2">
  <?php if (ChurchLogoService::hasCustomLogo()): ?>
  <?= ChurchLogo::img(['class' => 'navbar-brand-image crm-brand-logo']) ?>
  <?php else: ?>
  <img src="<?= SystemURLs::getRootPath() ?>/Images/churchcrm-symbol-ink-blue.svg"
       alt="<?= InputUtils::escapeAttribute(ChurchMetaData::getChurchName() ?: 'ChurchCRM') ?>"
       class="navbar-brand-image crm-brand-logo crm-brand-logo-light">
  <img src="<?= SystemURLs::getRootPath() ?>/Images/churchcrm-symbol-paper-blue.svg"
       alt="<?= InputUtils::escapeAttribute(ChurchMetaData::getChurchName() ?: 'ChurchCRM') ?>"
       class="navbar-brand-image crm-brand-logo crm-brand-logo-dark">
  <span class="navbar-brand-text ps-2 fs-4 fw-bold">
    <?= InputUtils::escapeHTML(ChurchMetaData::getChurchName() ?: 'ChurchCRM') ?>
  </span>
  <?php endif; ?>
</a>
