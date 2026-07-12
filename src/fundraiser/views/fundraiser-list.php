<?php
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

$sRootPath = $sRootPath ?? SystemURLs::getRootPath();
require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>
<div class="card mb-3">
  <div class="card-body">
    <form method="get" action="<?= $sRootPath ?>/fundraiser/" name="FindFundRaiser">
      <div class="row g-3 align-items-end">
        <div class="col-auto">
          <label class="form-label" for="dateStart"><?= gettext('Date Start') ?>:</label>
          <input type="text" class="form-control date-picker" name="dateStart" maxlength="10" id="dateStart" value="<?= InputUtils::escapeAttribute($dateStart) ?>">
        </div>
        <div class="col-auto">
          <label class="form-label" for="dateEnd"><?= gettext('Date End') ?>:</label>
          <input type="text" class="form-control date-picker" name="dateEnd" maxlength="10" id="dateEnd" value="<?= InputUtils::escapeAttribute($dateEnd) ?>">
        </div>
        <div class="col-auto d-flex gap-2">
          <input type="submit" class="btn btn-primary" value="<?= gettext('Apply Filters') ?>" name="FindFundRaiserSubmit">
          <input type="button" class="btn btn-secondary" value="<?= gettext('Clear Filters') ?>" onclick="document.location='<?= $sRootPath ?>/fundraiser/';">
        </div>
      </div>
    </form>
  </div>
</div>
<div class="card">
  <div class="card-body">
    <table id="fundraisers" class="table table-bordered data-table w-100">
      <thead>
        <tr>
          <th><?= gettext('Title') ?></th>
          <th><?= gettext('Date') ?></th>
          <th class="w-1 no-export"><?= gettext('Actions') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($fundraisers as $fundraiser): ?>
        <tr>
          <td><?= InputUtils::escapeHTML($fundraiser->getTitle()) ?></td>
          <td><?= $fundraiser->getDate()?->format($sDateFormat) ?? '' ?></td>
          <td class="w-1">
            <div class="dropdown">
              <button class="btn btn-sm btn-ghost-secondary" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
                <i class="ti ti-dots-vertical"></i>
              </button>
              <div class="dropdown-menu dropdown-menu-end">
                <a class="dropdown-item" href="<?= $sRootPath ?>/fundraiser/editor/<?= $fundraiser->getId() ?>">
                  <i class="ti ti-pencil me-2"></i><?= gettext('Edit') ?>
                </a>
                <div class="dropdown-divider"></div>
                <form method="post" action="<?= $sRootPath ?>/fundraiser/<?= $fundraiser->getId() ?>/delete" class="d-inline"
                      onsubmit="return confirm(<?= htmlspecialchars(json_encode(gettext('Are you sure you want to delete this fundraiser?'))) ?>)">
                  <?= \ChurchCRM\Utils\CSRFUtils::getTokenInputField('fundraiser_delete') ?>
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
  </div>
</div>
<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
