<?php

/**
 * "Generate Labels" modal for the cart page (#9873).
 *
 * Submits a GET to Reports/PDFLabel.php, which builds mailing labels for the
 * people in the cart. The report remembers every choice in cookies, and the
 * defaults below read them back.
 */

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require_once SystemURLs::getDocumentRoot() . '/Include/LabelFunctions.php';

$bGroupByFamily = getLabelFormCookie('groupbymode') === 'fam';
$bBulkMailPresort = (bool) getLabelFormCookie('bulkmailpresort');
$bBulkMailQuiet = $bBulkMailPresort && (bool) getLabelFormCookie('bulkmailquiet');
$bToParents = (bool) getLabelFormCookie('toparents');
$sLabelType = getLabelFormCookie('labeltype');
$sLabelFont = getLabelFormCookie('labelfont');
$sLabelFontSize = getLabelFormCookie('labelfontsize');
?>
<div class="modal fade" id="cartLabelsModal" tabindex="-1" aria-labelledby="cartLabelsModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form method="get" action="<?= SystemURLs::getRootPath() ?>/Reports/PDFLabel.php" target="_blank" id="cartLabelsForm">
        <div class="modal-header">
          <h5 class="modal-title" id="cartLabelsModalTitle"><?= gettext('Generate Labels') ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= InputUtils::escapeAttribute(gettext('Close')) ?>"></button>
        </div>
        <div class="modal-body">
          <p class="text-secondary"><?= gettext('Mailing labels for the people in your cart. A person with no address of their own is addressed at their family address.') ?></p>
          <div class="row g-3">
            <div class="col-md-6">
              <div class="form-label"><?= gettext('Label Grouping') ?></div>
              <label class="form-check">
                <input class="form-check-input" type="radio" name="groupbymode" value="indiv" <?= $bGroupByFamily ? '' : 'checked' ?>>
                <span class="form-check-label"><?= gettext('All Individuals') ?></span>
              </label>
              <label class="form-check">
                <input class="form-check-input" type="radio" name="groupbymode" value="fam" <?= $bGroupByFamily ? 'checked' : '' ?>>
                <span class="form-check-label"><?= gettext('Grouped by Family') ?></span>
              </label>
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
          <button type="submit" class="btn btn-primary" id="cartLabelsSubmit"><i class="fa-solid fa-tags me-2"></i><?= gettext('Generate Labels') ?></button>
        </div>
      </form>
    </div>
  </div>
</div>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
  (function () {
    const presort = document.getElementById("bulkmailpresort");
    const quiet = document.getElementById("bulkmailquiet");
    const form = document.getElementById("cartLabelsForm");
    const modal = document.getElementById("cartLabelsModal");

    // Quiet presort only means something when presorting.
    presort.addEventListener("change", function () {
      quiet.disabled = !presort.checked;
      if (!presort.checked) {
        quiet.checked = false;
      }
    });

    // The report opens in a new tab; close the dialog so the cart is usable.
    form.addEventListener("submit", function () {
      const instance = bootstrap.Modal.getInstance(modal);
      if (instance) {
        instance.hide();
      }
    });
  })();
</script>
