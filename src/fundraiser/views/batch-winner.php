<?php
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

$sRootPath = $sRootPath ?? SystemURLs::getRootPath();
require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>
<div class="card">
  <div class="card-body">
    <form method="post" action="<?= $sRootPath ?>/fundraiser/<?= (int) $fundraiserId ?>/batch-winner" name="BatchWinnerEntry">
      <div class="table-responsive">
        <table class="table table-bordered align-middle">
          <thead>
            <tr>
              <th><?= gettext('Item') ?></th>
              <th><?= gettext('Winner') ?></th>
              <th><?= gettext('Price') ?></th>
            </tr>
          </thead>
          <tbody>
            <?php for ($row = 0; $row < 10; $row++): ?>
              <tr>
                <td>
                  <select class="form-select" name="Item<?= $row ?>">
                    <option value="0"><?= gettext('Unassigned') ?></option>
                    <?php foreach ($donatedItems as $item): ?>
                      <option value="<?= (int) $item['di_ID'] ?>">
                        <?= InputUtils::escapeHTML($item['di_Item']) ?> <?= InputUtils::escapeHTML($item['di_title']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td>
                  <select class="form-select" name="Paddle<?= $row ?>">
                    <option value="0"><?= gettext('Unassigned') ?></option>
                    <?php foreach ($paddles as $paddle): ?>
                      <option value="<?= (int) $paddle['pn_per_ID'] ?>">
                        <?= (int) $paddle['pn_Num'] ?> <?= InputUtils::escapeHTML($paddle['buyerFirstName']) ?> <?= InputUtils::escapeHTML($paddle['buyerLastName']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td><input type="text" class="form-control" name="SellPrice<?= $row ?>" value=""></td>
              </tr>
            <?php endfor; ?>
          </tbody>
        </table>
      </div>
      <div class="d-flex gap-2 mt-3">
        <input type="submit" class="btn btn-primary" value="<?= gettext('Enter Winners') ?>" name="EnterWinners">
        <a href="<?= $sRootPath ?>/fundraiser/editor/<?= (int) $fundraiserId ?>" class="btn btn-secondary">
          <?= gettext('Cancel') ?>
        </a>
      </div>
    </form>
  </div>
</div>
<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
