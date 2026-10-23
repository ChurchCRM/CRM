<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><?= gettext('Tax statements') ?></h3>
  </div>
  <div class="card-body">
    <p class="text-secondary"><?= gettext('Choose the calendar year, then continue to the year-end wizard or generate the PDF.') ?></p>
    <form method="get" action="<?= InputUtils::escapeAttribute($sRootPath . '/finance/reports/year-end') ?>">
      <input type="hidden" name="step" value="3">
      <div class="mb-3" style="max-width:200px">
        <label class="form-label" for="Year"><?= gettext('Calendar year') ?></label>
        <input type="number" name="year" id="Year" class="form-control" value="<?= (int)$year ?>">
      </div>
      <div class="d-flex gap-2">
        <a class="btn btn-secondary" href="<?= $sRootPath ?>/finance/reports"><?= gettext('Back') ?></a>
        <button type="submit" class="btn btn-primary"><?= gettext('Continue') ?></button>
      </div>
    </form>
  </div>
</div>
<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
