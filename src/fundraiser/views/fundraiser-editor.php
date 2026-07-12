<?php
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\Utils\InputUtils;

$sRootPath = $sRootPath ?? SystemURLs::getRootPath();
// Normalise date to string for form display
$dateValue = ($dDate instanceof \DateTime) ? $dDate->format('Y-m-d') : (string) $dDate;
require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>
<div class="card mb-3">
  <div class="card-body">
    <form method="post" action="<?= $sRootPath ?>/fundraiser/editor<?= $fundraiserId > 0 ? '/' . $fundraiserId : '' ?>" name="FundRaiserEditor">

      <div class="mb-3">
        <label class="form-label" for="Date"><?= gettext('Date') ?>:</label>
        <input type="text" name="Date" value="<?= InputUtils::escapeAttribute($dateValue) ?>" maxlength="10" id="Date" class="form-control date-picker" style="max-width:180px">
        <?php if (!empty($sDateError)): ?>
          <div class="text-danger small"><?= InputUtils::escapeHTML($sDateError) ?></div>
        <?php endif; ?>
      </div>

      <div class="mb-3">
        <label class="form-label" for="Title"><?= gettext('Title') ?>:</label>
        <input type="text" class="form-control" name="Title" id="Title" value="<?= InputUtils::escapeAttribute($sTitle) ?>">
      </div>

      <div class="mb-3">
        <label class="form-label" for="Description"><?= gettext('Description') ?>:</label>
        <textarea class="form-control" name="Description" id="Description" rows="5"><?= InputUtils::escapeHTML($sDescription) ?></textarea>
      </div>

      <div class="d-flex gap-2">
        <input type="submit" class="btn btn-primary" value="<?= gettext('Save') ?>" name="FundRaiserSubmit">
        <input type="button" class="btn btn-secondary" value="<?= gettext('Cancel') ?>" onclick="document.location='<?= $sRootPath ?>/fundraiser/';">
      </div>

    </form>
  </div>
</div>

<?php if ($fundraiserId > 0): ?>
<div class="card mb-3">
  <div class="card-body">
    <div class="d-flex flex-wrap gap-2">
      <a href="<?= $sRootPath ?>/fundraiser/<?= $fundraiserId ?>/donated-items/editor" class="btn btn-success">
        <i class="ti ti-plus me-1"></i><?= gettext('Add Donated Item') ?>
      </a>
      <div class="dropdown">
        <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
          <i class="ti ti-file-text me-1"></i><?= gettext('Reports') ?>
        </button>
        <div class="dropdown-menu">
          <a class="dropdown-item" href="<?= $sRootPath ?>/fundraiser/<?= $fundraiserId ?>/reports/catalog">
            <i class="ti ti-book me-2"></i><?= gettext('Generate Catalog') ?>
          </a>
          <a class="dropdown-item" href="<?= $sRootPath ?>/fundraiser/<?= $fundraiserId ?>/reports/bid-sheets">
            <i class="ti ti-list me-2"></i><?= gettext('Generate Bid Sheets') ?>
          </a>
          <a class="dropdown-item" href="<?= $sRootPath ?>/fundraiser/<?= $fundraiserId ?>/reports/certificates">
            <i class="ti ti-certificate me-2"></i><?= gettext('Generate Certificates') ?>
          </a>
        </div>
      </div>
      <a href="<?= $sRootPath ?>/fundraiser/<?= $fundraiserId ?>/batch-winner" class="btn btn-secondary">
        <i class="ti ti-trophy me-1"></i><?= gettext('Batch Winner Entry') ?>
      </a>
    </div>
  </div>
</div>
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><?= gettext('Donated items for this fundraiser') ?></h3>
  </div>
  <div class="card-body" style="overflow: visible;">
    <table class="table table-vcenter table-hover w-100">
      <thead>
        <tr>
          <th><?= gettext('Item') ?></th>
          <th><?= gettext('Multiple') ?></th>
          <th><?= gettext('Donor') ?></th>
          <th><?= gettext('Buyer') ?></th>
          <th><?= gettext('Title') ?></th>
          <th><?= gettext('Sale Price') ?></th>
          <th><?= gettext('Est. Value') ?></th>
          <th><?= gettext('Material') ?></th>
          <th><?= gettext('Minimum') ?></th>
          <th class="w-1 no-export"><?= gettext('Actions') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($donatedItems !== null && $donatedItems->count() > 0): ?>
          <?php foreach ($donatedItems as $item): ?>
            <?php
              $itemName = $item->getItem() ?: '~';
              $donor = $item->getDonorId() ? ($personMap[(int) $item->getDonorId()] ?? null) : null;
              $buyer = $item->getBuyerId() ? ($personMap[(int) $item->getBuyerId()] ?? null) : null;
              $donorFirstName = $donor ? $donor->getFirstName() : '';
              $donorLastName  = $donor ? $donor->getLastName() : '';
              $buyerFirstName = $buyer ? $buyer->getFirstName() : '';
              $buyerLastName  = $buyer ? $buyer->getLastName() : '';
            ?>
            <tr>
              <td><?= InputUtils::escapeHTML($itemName) ?></td>
              <td><?= $item->getMultibuy() ? '<span class="badge bg-info">X</span>' : '' ?></td>
              <td><?= InputUtils::escapeHTML($donorFirstName) . ' ' . InputUtils::escapeHTML($donorLastName) ?></td>
              <td>
                <?php if ($item->getMultibuy()): ?>
                  <span class="text-body-secondary"><?= gettext('Multiple') ?></span>
                <?php else: ?>
                  <?= InputUtils::escapeHTML($buyerFirstName) . ' ' . InputUtils::escapeHTML($buyerLastName) ?>
                <?php endif; ?>
              </td>
              <td><?= InputUtils::escapeHTML($item->getTitle()) ?></td>
              <td class="text-end"><?= InputUtils::escapeHTML($item->getSellprice()) ?></td>
              <td class="text-end"><?= InputUtils::escapeHTML($item->getEstprice()) ?></td>
              <td class="text-end"><?= InputUtils::escapeHTML($item->getMaterialValue()) ?></td>
              <td class="text-end"><?= InputUtils::escapeHTML($item->getMinimum()) ?></td>
              <td class="w-1">
                <div class="dropdown">
                  <button class="btn btn-sm btn-ghost-secondary" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
                    <i class="ti ti-dots-vertical"></i>
                  </button>
                  <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item" href="<?= $sRootPath ?>/fundraiser/<?= $fundraiserId ?>/donated-items/editor/<?= (int) $item->getId() ?>">
                      <i class="ti ti-pencil me-2"></i><?= gettext('Edit') ?>
                    </a>
                    <div class="dropdown-divider"></div>
                    <form method="post" action="<?= $sRootPath ?>/fundraiser/<?= $fundraiserId ?>/donated-items/<?= (int) $item->getId() ?>/delete"
                          onsubmit="return confirm('<?= gettext('Delete this item?') ?>')">
                      <?= CSRFUtils::getTokenInputField('donated_item_delete') ?>
                      <button type="submit" class="dropdown-item text-danger border-0 bg-transparent">
                        <i class="ti ti-trash me-2"></i><?= gettext('Delete') ?>
                      </button>
                    </form>
                  </div>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
