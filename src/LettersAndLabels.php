<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\LabelSheetService;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\view\PageHeader;

$sPageTitle = gettext('Letters and Mailing Labels');
$sPageSubtitle = gettext('Generate mailing labels and form letters');
$aBreadcrumbs = PageHeader::breadcrumbs([
    [gettext('People Reports'), '/people/reports'],
    [gettext('Letters and Labels')],
]);
require_once __DIR__ . '/Include/Header.php';

$sRootPath = SystemURLs::getRootPath();
$sLabelType = (string) ($_COOKIE['labeltype'] ?? '');
$sLabelFont = (string) ($_COOKIE['labelfont'] ?? '');
$sLabelFontSize = (string) ($_COOKIE['labelfontsize'] ?? '');
?>
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><?= gettext('Family Labels') ?></h3>
  </div>
  <form method="get" action="<?= InputUtils::escapeAttribute($sRootPath . '/people/labels/newsletter') ?>" target="_blank">
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-6 col-lg-3">
          <label class="form-label" for="labeltype"><?= gettext('Label Type') ?></label>
          <select class="form-select" name="labeltype" id="labeltype">
            <?php foreach (LabelSheetService::labelTypes() as $type) { ?>
              <option value="<?= InputUtils::escapeAttribute($type) ?>" <?= $sLabelType === $type ? 'selected' : '' ?>><?= InputUtils::escapeHTML(gettext($type)) ?></option>
            <?php } ?>
          </select>
        </div>
        <div class="col-md-6 col-lg-3">
          <label class="form-label" for="labelfont"><?= gettext('Font') ?></label>
          <select class="form-select" name="labelfont" id="labelfont">
            <?php foreach (LabelSheetService::fontNames() as $font) { ?>
              <option value="<?= InputUtils::escapeAttribute($font) ?>" <?= $sLabelFont === $font ? 'selected' : '' ?>><?= InputUtils::escapeHTML($font) ?></option>
            <?php } ?>
          </select>
        </div>
        <div class="col-md-6 col-lg-3">
          <label class="form-label" for="labelfontsize"><?= gettext('Font Size') ?></label>
          <select class="form-select" name="labelfontsize" id="labelfontsize">
            <?php foreach (LabelSheetService::fontSizes() as $size) { ?>
              <option value="<?= InputUtils::escapeAttribute((string) $size) ?>" <?= $sLabelFontSize === (string) $size ? 'selected' : '' ?>><?= InputUtils::escapeHTML(gettext((string) $size)) ?></option>
            <?php } ?>
          </select>
        </div>
        <div class="col-md-6 col-lg-3">
          <label class="form-label" for="recipientnamingmethod"><?= gettext('Recipient Naming Method') ?></label>
          <select class="form-select" name="recipientnamingmethod" id="recipientnamingmethod">
            <option value="salutationutility"><?= gettext('Salutation Utility') ?></option>
            <option value="familyname"><?= gettext('Family Name') ?></option>
          </select>
        </div>
      </div>
    </div>
    <div class="card-footer d-flex flex-wrap gap-2">
      <button type="submit" class="btn btn-primary"><i class="fa-solid fa-file-pdf me-2"></i><?= gettext('Newsletter labels') ?></button>
      <button type="submit" class="btn btn-secondary" formaction="<?= InputUtils::escapeAttribute($sRootPath . '/people/labels/confirm') ?>"><i class="fa-solid fa-file-pdf me-2"></i><?= gettext('Confirm data labels') ?></button>
      <a class="btn btn-link ms-auto" href="<?= InputUtils::escapeAttribute($sRootPath . '/v2/dashboard') ?>"><?= gettext('Cancel') ?></a>
    </div>
  </form>
</div>
<?php
require_once __DIR__ . '/Include/Footer.php';
