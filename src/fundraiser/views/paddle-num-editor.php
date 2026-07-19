<?php
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\MiscUtils;

$sRootPath = $sRootPath ?? SystemURLs::getRootPath();
require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>
<div class="card mb-3">
  <div class="card-body">
    <form method="post" action="<?= $sRootPath ?>/fundraiser/<?= (int) $fundraiserId ?>/paddle-numbers/editor<?= $paddleId > 0 ? '/' . $paddleId : '' ?>" name="PaddleNumEditor">

      <?= CSRFUtils::getTokenInputField('paddle_num_editor') ?>

      <?php if (!empty($multibuyItems)): ?>
      <div class="row">
        <div class="col-md-6">
          <div class="mb-3">
            <label class="form-label" for="Num"><?= gettext('Number') ?>:</label>
            <input type="text" class="form-control" name="Num" id="Num" value="<?= (int) $iNum ?>">
          </div>

          <div class="mb-3">
            <label class="form-label" for="PerID"><?= gettext('Buyer') ?>:</label>
            <select class="form-select" name="PerID" id="PerID">
              <option value="0"><?= gettext('Unassigned') ?></option>
              <?php foreach ($people as $person): ?>
                <option value="<?= (int) $person['per_ID'] ?>"
                        <?= (int) $person['per_ID'] === $iPerID ? 'selected' : '' ?>>
                  <?= InputUtils::escapeHTML($person['per_LastName']) ?>, <?= InputUtils::escapeHTML($person['per_FirstName']) ?>
                  <?= InputUtils::escapeHTML(MiscUtils::formatAddressLine($person['fam_Address1'], $person['fam_City'], $person['fam_State'])) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="col-md-6">
          <h4 class="text-body-secondary mb-2 small"><?= gettext('Multibuy Items') ?></h4>
          <?php foreach ($multibuyItems as $mbItem): ?>
            <div class="mb-3">
              <label class="form-label" for="MBItem<?= (int) $mbItem['di_ID'] ?>">
                <?= InputUtils::escapeHTML($mbItem['di_title']) ?>
              </label>
              <input type="text" class="form-control"
                     name="MBItem<?= (int) $mbItem['di_ID'] ?>"
                     id="MBItem<?= (int) $mbItem['di_ID'] ?>"
                     value="<?= (int) $mbItem['mb_count'] ?>">
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php else: ?>
      <div class="row">
        <div class="mb-3 col-md-3">
          <label class="form-label" for="Num"><?= gettext('Number') ?>:</label>
          <input type="text" class="form-control" name="Num" id="Num" value="<?= (int) $iNum ?>">
        </div>
        <div class="mb-3 col-md-9">
          <label class="form-label" for="PerID"><?= gettext('Buyer') ?>:</label>
          <select class="form-select" name="PerID" id="PerID">
            <option value="0"><?= gettext('Unassigned') ?></option>
            <?php foreach ($people as $person): ?>
              <option value="<?= (int) $person['per_ID'] ?>"
                      <?= (int) $person['per_ID'] === $iPerID ? 'selected' : '' ?>>
                <?= InputUtils::escapeHTML($person['per_LastName']) ?>, <?= InputUtils::escapeHTML($person['per_FirstName']) ?>
                <?= InputUtils::escapeHTML(MiscUtils::formatAddressLine($person['fam_Address1'], $person['fam_City'], $person['fam_State'])) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <?php endif; ?>

      <div class="d-flex flex-wrap gap-2">
        <input type="submit" class="btn btn-primary" value="<?= gettext('Save') ?>" name="PaddleNumSubmit">
        <?php if ($canAddRecords): ?>
          <input type="submit" class="btn btn-secondary" value="<?= gettext('Save and Add') ?>" name="PaddleNumSubmitAndAdd">
        <?php endif; ?>
        <a href="<?= $sRootPath ?>/fundraiser/<?= (int) $fundraiserId ?>/paddle-numbers" class="btn btn-secondary">
          <?= gettext('Back') ?>
        </a>
        <input type="submit" class="btn btn-outline-secondary ms-auto" value="<?= gettext('Generate Statement') ?>" name="GenerateStatement">
      </div>

    </form>
  </div>
</div>
<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
