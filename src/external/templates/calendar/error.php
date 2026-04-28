<?php

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

/** @var string $title   Short headline, already translated. */
/** @var string $message Longer explanation, already translated. */
/** @var string $icon    Optional Tabler icon class (e.g. 'ti-calendar-x'). */

$sPageTitle = $title;
$sBodyClass = 'antialiased d-flex flex-column';
$icon ??= 'ti-calendar-off';
$rootPath = SystemURLs::getRootPath();
$churchName = ChurchMetaData::getChurchName();
$logoURL = ChurchMetaData::getChurchLogoURL();

require SystemURLs::getDocumentRoot() . '/Include/HeaderNotLoggedIn.php';
?>

<div class="page page-center">
  <div class="container-tight py-4">
    <div class="text-center mb-4">
      <a href="<?= InputUtils::escapeAttribute($rootPath) ?>/" class="text-decoration-none d-inline-block">
        <img src="<?= InputUtils::escapeAttribute($logoURL) ?>"
             alt="<?= InputUtils::escapeAttribute($churchName ?: 'ChurchCRM') ?>"
             class="mb-2"
             style="max-width: 280px; height: auto;">
        <?php if ($churchName !== ''): ?>
          <div class="h3 text-body mt-2 mb-0"><?= InputUtils::escapeHTML($churchName) ?></div>
        <?php endif; ?>
      </a>
    </div>
    <div class="card card-md">
      <div class="card-status-top bg-warning"></div>
      <div class="card-body text-center py-5">
        <div class="mb-3">
          <i class="ti <?= InputUtils::escapeAttribute($icon) ?> text-warning" style="font-size: 3.5rem; line-height: 1;"></i>
        </div>
        <h2 class="h2 mb-2"><?= InputUtils::escapeHTML($title) ?></h2>
        <p class="text-body-secondary mb-4 mx-auto" style="max-width: 32rem;">
          <?= InputUtils::escapeHTML($message) ?>
        </p>
        <a href="<?= InputUtils::escapeAttribute($rootPath) ?>/" class="btn btn-primary">
          <i class="ti ti-home me-1"></i><?= gettext('Go to home') ?>
        </a>
      </div>
    </div>
  </div>
</div>

<?php require SystemURLs::getDocumentRoot() . '/Include/FooterNotLoggedIn.php'; ?>
