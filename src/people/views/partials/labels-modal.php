<?php

/**
 * "Generate Labels" dialog, shared by the cart (#9873) and the People Reports (#10343).
 *
 * Submits a GET to Reports/PDFLabel.php. The report remembers every choice in
 * cookies, and the defaults below read them back.
 *
 * Set before including:
 *   $labelsIntro         text above the options
 *   $labelsHiddenFields  extra query values, name => scalar or list (a People Report's slug and filters)
 *   $labelsGrouping      when set, the source decides the grouping and this text replaces the choice
 */

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require_once SystemURLs::getDocumentRoot() . '/Include/LabelFunctions.php';

$labelsHiddenFields ??= [];
$labelsGrouping ??= null;

$bGroupByFamily = getLabelFormCookie('groupbymode') === 'fam';
$bBulkMailPresort = (bool) getLabelFormCookie('bulkmailpresort');
$bBulkMailQuiet = $bBulkMailPresort && (bool) getLabelFormCookie('bulkmailquiet');
$bToParents = (bool) getLabelFormCookie('toparents');
$sLabelType = getLabelFormCookie('labeltype');
$sLabelFont = getLabelFormCookie('labelfont');
$sLabelFontSize = getLabelFormCookie('labelfontsize');
?>
<div class="modal fade" id="labelsModal" tabindex="-1" aria-labelledby="labelsModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form method="get" action="<?= SystemURLs::getRootPath() ?>/Reports/PDFLabel.php" target="_blank" id="labelsForm">
        <?php foreach ($labelsHiddenFields as $name => $value) {
            foreach (is_array($value) ? $value : [$value] as $item) { ?>
          <input type="hidden" name="<?= InputUtils::escapeAttribute(is_array($value) ? $name . '[]' : $name) ?>" value="<?= InputUtils::escapeAttribute((string) $item) ?>">
        <?php }
        } ?>
        <div class="modal-header">
          <h5 class="modal-title" id="labelsModalTitle"><?= gettext('Generate Labels') ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= InputUtils::escapeAttribute(gettext('Close')) ?>"></button>
        </div>
        <div class="modal-body">
          <p class="text-secondary"><?= InputUtils::escapeHTML($labelsIntro) ?></p>
          <div class="row g-3">
            <div class="col-md-6">
              <div class="form-label"><?= gettext('Label Grouping') ?></div>
              <?php if ($labelsGrouping !== null) { ?>
              <p id="labelsGrouping" class="mb-0"><?= InputUtils::escapeHTML($labelsGrouping) ?></p>
              <?php } else { ?>
              <label class="form-check">
                <input class="form-check-input" type="radio" name="groupbymode" value="indiv" <?= $bGroupByFamily ? '' : 'checked' ?>>
                <span class="form-check-label"><?= gettext('All Individuals') ?></span>
              </label>
              <label class="form-check">
                <input class="form-check-input" type="radio" name="groupbymode" value="fam" <?= $bGroupByFamily ? 'checked' : '' ?>>
                <span class="form-check-label"><?= gettext('Grouped by Family') ?></span>
              </label>
              <?php } ?>
            </div>
            <div class="col-md-6">
              <div class="form-label"><?= gettext('Options') ?></div>
              <label class="form-check">
                <input class="form-check-input" type="checkbox" name="bulkmailpresort" id="bulkmailpresort" value="1" <?= $bBulkMailPresort ? 'checked' : '' ?>>
                <span class="form-check-label"><?= gettext('Bulk Mail Presort') ?></span>
              </label>
              <label class="form-check">
                <input class="form-check-input" type="checkbox" name="bulkmailquiet" id="bulkmailquiet" value="1" <?= $bBulkMailQuiet ? 'checked' : '' ?> <?= $bBulkMailPresort ? '' : 'disabled' ?>>
                <span class="form-check-label"><?= gettext('Quiet Presort') ?></span>
              </label>
              <label class="form-check">
                <input class="form-check-input" type="checkbox" name="toparents" id="toparents" value="1" <?= $bToParents ? 'checked' : '' ?>>
                <span class="form-check-label"><?= gettext('To the parents of') ?></span>
              </label>
              <label class="form-check">
                <input class="form-check-input" type="checkbox" name="onlyfull" id="onlyfull" value="1" checked>
                <span class="form-check-label"><?= gettext('Ignore Incomplete Addresses') ?></span>
              </label>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="labeltype"><?= gettext('Label Type') ?></label>
              <select class="form-select" name="labeltype" id="labeltype">
                <?php foreach (getLabelTypes() as $type) { ?>
                  <option value="<?= InputUtils::escapeAttribute($type) ?>" <?= $sLabelType === $type ? 'selected' : '' ?>><?= InputUtils::escapeHTML(gettext($type)) ?></option>
                <?php } ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="labelfont"><?= gettext('Font') ?></label>
              <select class="form-select" name="labelfont" id="labelfont">
                <?php foreach (getLabelFontNames() as $font) { ?>
                  <option value="<?= InputUtils::escapeAttribute($font) ?>" <?= $sLabelFont === $font ? 'selected' : '' ?>><?= InputUtils::escapeHTML($font) ?></option>
                <?php } ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="labelfontsize"><?= gettext('Font Size') ?></label>
              <select class="form-select" name="labelfontsize" id="labelfontsize">
                <?php foreach (getLabelFontSizes() as $size) { ?>
                  <option value="<?= InputUtils::escapeAttribute((string) $size) ?>" <?= $sLabelFontSize === (string) $size ? 'selected' : '' ?>><?= InputUtils::escapeHTML(gettext((string) $size)) ?></option>
                <?php } ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="startrow"><?= gettext('Start Row') ?></label>
              <input type="number" class="form-control" name="startrow" id="startrow" min="1" max="99" value="1">
            </div>
            <div class="col-md-4">
              <label class="form-label" for="startcol"><?= gettext('Start Column') ?></label>
              <input type="number" class="form-control" name="startcol" id="startcol" min="1" max="99" value="1">
            </div>
            <div class="col-md-4">
              <label class="form-label" for="filetype"><?= gettext('File Type') ?></label>
              <select class="form-select" name="filetype" id="filetype">
                <option value="PDF">PDF</option>
                <option value="CSV">CSV</option>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= gettext('Cancel') ?></button>
          <button type="submit" class="btn btn-primary" id="labelsSubmit"><i class="fa-solid fa-tags me-2"></i><?= gettext('Generate Labels') ?></button>
        </div>
      </form>
    </div>
  </div>
</div>
<script src="<?= SystemURLs::assetVersioned('/skin/v2/people-labels-modal.min.js') ?>" defer nonce="<?= SystemURLs::getCSPNonce() ?>"></script>
