<?php
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

$sRootPath = $sRootPath ?? SystemURLs::getRootPath();
require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>
<div class="card">
  <div class="card-body">
    <p class="text-body-secondary mb-4">
      <?= gettext('This action will automatically add paddle numbers for all item donors in this fundraiser who do not yet have a buyer number assigned.') ?>
    </p>
    <p><strong><?= InputUtils::escapeHTML($fundraiser->getTitle()) ?></strong></p>
    <form method="post" action="<?= $sRootPath ?>/fundraiser/<?= (int) $fundraiserId ?>/donors">
      <div class="d-flex gap-2">
        <input type="submit" class="btn btn-primary" value="<?= gettext('Add Donors to Buyer List') ?>">
        <a href="<?= $sRootPath ?>/fundraiser/editor/<?= (int) $fundraiserId ?>" class="btn btn-secondary">
          <?= gettext('Cancel') ?>
        </a>
      </div>
    </form>
  </div>
</div>
<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
