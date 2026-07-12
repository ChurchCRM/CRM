<?php
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\MiscUtils;

$sRootPath = $sRootPath ?? SystemURLs::getRootPath();
require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>
<form method="post" action="<?= $sRootPath ?>/fundraiser/<?= (int) $fundraiserId ?>/donated-items/editor<?= $itemId > 0 ? '/' . $itemId : '' ?>" name="DonatedItemEditor">
  <div class="card">
    <div class="card-body">
      <?= CSRFUtils::getTokenInputField('donated_item_editor') ?>
      <div class="mb-3">
        <div class="row">
          <div class="col-md-4 col-6">

            <div class="mb-3">
              <label class="form-label"><?= gettext('Item') ?>:</label>
              <input type="text" name="Item" id="Item" value="<?= InputUtils::escapeAttribute($sItem) ?>" class="form-control">
            </div>

            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" name="Multibuy" value="1" id="Multibuy" <?= $bMultibuy ? 'checked' : '' ?>>
              <label class="form-check-label" for="Multibuy">
                <?= gettext('Sell to everyone') ?> (<?= gettext('Multiple items') ?>)
              </label>
            </div>

            <div class="mb-3">
              <label class="form-label"><?= gettext('Donor') ?>:</label>
              <select name="Donor" id="Donor" class="form-select">
                <option value="0"><?= gettext('Unassigned') ?></option>
                <?php foreach ($people as $person): ?>
                  <option value="<?= (int) $person['per_ID'] ?>"
                          <?= (int) $person['per_ID'] == $iDonor ? 'selected' : '' ?>>
                    <?= InputUtils::escapeHTML($person['per_LastName']) ?>, <?= InputUtils::escapeHTML($person['per_FirstName']) ?>
                    <?= InputUtils::escapeHTML(MiscUtils::formatAddressLine($person['fam_Address1'], $person['fam_City'], $person['fam_State'])) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <script nonce="<?= SystemURLs::getCSPNonce() ?>">
              $(document).ready(function() {
                var donorEl = document.getElementById("Donor");
                if (donorEl && !donorEl.tomselect) new TomSelect(donorEl);
              });
            </script>

            <div class="mb-3">
              <label class="form-label"><?= gettext('Title') ?>:</label>
              <input type="text" name="Title" id="Title" value="<?= InputUtils::escapeAttribute($sTitle) ?>" class="form-control">
            </div>

            <div class="mb-3">
              <label class="form-label"><?= gettext('Estimated Price') ?>:</label>
              <input type="text" name="EstPrice" id="EstPrice" value="<?= InputUtils::escapeAttribute($nEstPrice) ?>" class="form-control">
            </div>

            <div class="mb-3">
              <label class="form-label"><?= gettext('Material Value') ?>:</label>
              <input type="text" name="MaterialValue" id="MaterialValue" value="<?= InputUtils::escapeAttribute($nMaterialValue) ?>" class="form-control">
            </div>

            <div class="mb-3">
              <label class="form-label"><?= gettext('Minimum Price') ?>:</label>
              <input type="text" name="MinimumPrice" id="MinimumPrice" value="<?= InputUtils::escapeAttribute($nMinimumPrice) ?>" class="form-control">
            </div>

          </div>

          <div class="col-md-4 col-6">

            <div class="mb-3">
              <label class="form-label"><?= gettext('Buyer') ?>:</label>
              <?php if ($bMultibuy): ?>
                <?= gettext('Multiple') ?>
              <?php else: ?>
                <select name="Buyer" class="form-select">
                  <option value="0"><?= gettext('Unassigned') ?></option>
                  <?php foreach ($buyers as $buyer): ?>
                    <option value="<?= (int) $buyer['pn_per_ID'] ?>"
                            <?= (int) $buyer['pn_per_ID'] == $iBuyer ? 'selected' : '' ?>>
                      <?= (int) $buyer['pn_Num'] ?>: <?= InputUtils::escapeHTML($buyer['buyerFirstName']) ?> <?= InputUtils::escapeHTML($buyer['buyerLastName']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              <?php endif; ?>
            </div>

            <div class="mb-3">
              <label class="form-label"><?= gettext('Final Price') ?>:</label>
              <input type="text" name="SellPrice" id="SellPrice" value="<?= InputUtils::escapeAttribute($nSellPrice) ?>" class="form-control">
            </div>

            <?php if ($itemId > 0): ?>
            <div class="mb-3">
              <label class="form-label"><?= gettext('Replicate item') ?></label>
              <div class="input-group">
                <input type="text" name="NumberCopies" id="NumberCopies" value="0" class="form-control">
                <button type="button" class="btn btn-primary" onclick="
                  var count = document.getElementById('NumberCopies').value;
                  var form = document.createElement('form');
                  form.method = 'post';
                  form.action = '<?= $sRootPath ?>/fundraiser/<?= (int) $fundraiserId ?>/donated-items/<?= (int) $itemId ?>/replicate';
                  var csrfInput = document.createElement('input');
                  csrfInput.type = 'hidden';
                  csrfInput.name = 'csrf_token';
                  csrfInput.value = '<?= CSRFUtils::generateToken("donated_item_replicate") ?>';
                  form.appendChild(csrfInput);
                  var input = document.createElement('input');
                  input.type = 'hidden';
                  input.name = 'Count';
                  input.value = count;
                  form.appendChild(input);
                  document.body.appendChild(form);
                  form.submit();
                "><?= gettext('Go') ?></button>
              </div>
            </div>
            <?php endif; ?>

          </div>

          <div class="col-md-6 col-12">
            <div class="mb-3">
              <label class="form-label"><?= gettext('Description') ?>:</label>
              <textarea name="Description" rows="5" cols="90" class="form-control"><?= InputUtils::escapeHTML($sDescription) ?></textarea>
            </div>

            <div class="mb-3">
              <label class="form-label"><?= gettext('Picture URL') ?>:</label>
              <textarea name="PictureURL" rows="1" cols="90" class="form-control"><?= InputUtils::escapeHTML($sPictureURL) ?></textarea>
            </div>

            <?php if (!empty($sPictureURL)): ?>
              <div class="mb-3"><img src="<?= InputUtils::escapeAttribute($sPictureURL) ?>" alt=""></div>
            <?php endif; ?>
          </div>

        </div><!-- row -->
      </div>

      <div class="mb-3 text-center">
        <input type="submit" class="btn btn-primary" value="<?= gettext('Save') ?>" name="DonatedItemSubmit">
        <?php if ($canAddRecords): ?>
          <input type="submit" class="btn btn-primary" value="<?= gettext('Save and Add') ?>" name="DonatedItemSubmitAndAdd">
        <?php endif; ?>
        <a href="<?= $sRootPath ?>/fundraiser/editor/<?= (int) $fundraiserId ?>" class="btn btn-secondary">
          <?= gettext('Cancel') ?>
        </a>
      </div>

    </div>
  </div>
</form>
<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
