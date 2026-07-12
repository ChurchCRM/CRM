<?php
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\Utils\InputUtils;

$sRootPath = $sRootPath ?? SystemURLs::getRootPath();
require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>
<div class="card-body">
  <form method="post" action="<?= $sRootPath ?>/fundraiser/<?= (int) $fundraiserId ?>/reports/statement">
    <div class="d-flex flex-wrap gap-2 mb-3">
      <a class="btn btn-secondary" href="<?= $sRootPath ?>/fundraiser/<?= (int) $fundraiserId ?>/paddle-numbers?selectAll=1">
        <?= gettext('Select all') ?>
      </a>
      <a class="btn btn-secondary" href="<?= $sRootPath ?>/fundraiser/<?= (int) $fundraiserId ?>/paddle-numbers">
        <?= gettext('Select none') ?>
      </a>
      <a class="btn btn-secondary" href="<?= $sRootPath ?>/fundraiser/<?= (int) $fundraiserId ?>/paddle-numbers/editor">
        <?= gettext('Add Buyer') ?>
      </a>
      <input type="submit" class="btn btn-primary" value="<?= gettext('Generate Statements for Selected') ?>" name="GenerateStatements">
    </div>

    <table class="table">
      <thead>
        <tr>
          <th><?= gettext('Select') ?></th>
          <th><?= gettext('Number') ?></th>
          <th><?= gettext('Buyer') ?></th>
          <th class="text-center no-export w-1"><?= gettext('Actions') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($paddleNums as $aRow): ?>
          <?php extract($aRow); ?>
          <tr>
            <td>
              <input class="form-check-input" type="checkbox" name="Chk<?= (int) $pn_ID ?>"
                     <?= $selectAll ? 'checked="yes"' : '' ?>>
            </td>
            <td>
              <a href="<?= $sRootPath ?>/fundraiser/<?= (int) $fundraiserId ?>/paddle-numbers/editor/<?= (int) $pn_ID ?>">
                <?= (int) $pn_Num ?>
              </a>
            </td>
            <td>
              <?= InputUtils::escapeHTML($buyerFirstName) . ' ' . InputUtils::escapeHTML($buyerLastName) ?>&nbsp;
            </td>
            <td class="w-1">
              <div class="dropdown">
                <button class="btn btn-sm btn-ghost-secondary" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
                  <i class="ti ti-dots-vertical"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                  <a class="dropdown-item" href="<?= $sRootPath ?>/fundraiser/<?= (int) $fundraiserId ?>/paddle-numbers/editor/<?= (int) $pn_ID ?>">
                    <i class="ti ti-pencil me-2"></i><?= gettext('Edit') ?>
                  </a>
                  <div class="dropdown-divider"></div>
                  <form method="post" action="<?= $sRootPath ?>/fundraiser/<?= (int) $fundraiserId ?>/paddle-numbers/<?= (int) $pn_ID ?>/delete"
                        onsubmit="return confirm('<?= gettext('Delete this paddle number?') ?>')">
                    <?= CSRFUtils::getTokenInputField('paddle_num_delete') ?>
                    <button type="submit" class="dropdown-item text-danger border-0 bg-transparent">
                      <i class="ti ti-trash me-2"></i><?= gettext('Delete') ?>
                    </button>
                  </form>
                </div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </form>
</div>
<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
