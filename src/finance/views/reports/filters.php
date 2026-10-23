<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\FiscalYearUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\MiscUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

$today = date('Y-m-d');
$fyStart = $today;
$fyEnd = $today;
if ($reportType === 'Giving Report') {
    $fyMonth = (int) \ChurchCRM\dto\SystemConfig::getIntValue('iFYMonth');
    $y = (int) date('Y');
    $m = (int) date('n');
    $fyYear = ($m >= $fyMonth) ? $y : $y - 1;
    $fyStart = sprintf('%04d-%02d-01', $fyYear, $fyMonth);
    $endMonth = $fyMonth === 1 ? 12 : $fyMonth - 1;
    $endYear = $fyMonth === 1 ? $fyYear : $fyYear + 1;
    $fyEnd = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $endYear, $endMonth)));
}
$startVal = $sDateStart ?: ($reportType === 'Giving Report' ? $fyStart : $today);
$endVal = $sDateEnd ?: ($reportType === 'Giving Report' ? $fyEnd : $today);

$jobTitles = [
    'Giving Report' => gettext('Send year-end giving statements'),
    'Zero Givers' => gettext('See who has not given'),
    'Pledge Family Summary' => gettext('Pledge family summary'),
    'Pledge Reminders' => gettext('Send pledge reminders'),
    'Voting Members' => gettext('Voting members'),
    'Individual Deposit Report' => gettext('Print a deposit slip'),
    'Advanced Deposit Report' => gettext('Custom deposit report'),
];
$pageTitle = $jobTitles[$reportType] ?? gettext($reportType);
?>

<div class="card">
  <div class="card-header">
    <h3 class="card-title"><?= InputUtils::escapeHTML($pageTitle) ?></h3>
  </div>
  <div class="card-body">
<?php if ($reportType === 'Giving Report') : ?>
    <p class="text-secondary mb-3"><?= gettext('Dates are set to the current fiscal year. Adjust only if you need a different range, then generate the statements.') ?></p>
<?php endif; ?>
<?php if ($returnMessage === 'NoRows') : ?>
    <div class="alert alert-warning" role="alert">
      <i class="fa-solid fa-triangle-exclamation"></i>
      <strong><?= gettext('No Data Found') ?></strong><br>
      <?= gettext('No records were returned. Adjust the dates or filters and try again.') ?>
    </div>
<?php endif; ?>

    <form method="post" action="<?= InputUtils::escapeAttribute($sRootPath . '/' . $action) ?>">
      <input type="hidden" name="ReportType" value="<?= InputUtils::escapeAttribute($reportType) ?>">

      <div class="row g-3 mb-3">
<?php if (in_array($reportType, ['Giving Report', 'Advanced Deposit Report', 'Zero Givers'], true)) : ?>
        <div class="col-md-4">
          <label class="form-label" for="DateStart"><?= gettext('Start date') ?></label>
          <input type="text" class="form-control date-picker" name="DateStart" id="DateStart" maxlength="10" value="<?= InputUtils::escapeHTML($startVal) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label" for="DateEnd"><?= gettext('End date') ?></label>
          <input type="text" class="form-control date-picker" name="DateEnd" id="DateEnd" maxlength="10" value="<?= InputUtils::escapeHTML($endVal) ?>">
        </div>
<?php endif; ?>

<?php if (in_array($reportType, ['Pledge Reminders', 'Pledge Family Summary', 'Voting Members'], true)) : ?>
        <div class="col-md-4">
          <label class="form-label" for="FYID"><?= gettext('Fiscal year') ?></label>
          <?php FiscalYearUtils::renderYearSelect('FYID', $iFYID); ?>
        </div>
<?php endif; ?>

<?php if (in_array($reportType, ['Giving Report', 'Individual Deposit Report', 'Advanced Deposit Report'], true)) : ?>
        <div class="col-md-4">
          <label class="form-label" for="deposit"><?= gettext('Deposit') ?></label>
          <select class="form-select" name="deposit" id="deposit">
            <?php if ($reportType !== 'Individual Deposit Report') : ?>
              <option value="0" selected><?= gettext('All deposits in date range') ?></option>
            <?php endif; ?>
            <?php foreach ($deposits as $row) : ?>
              <option value="<?= (int)$row['dep_ID'] ?>"><?= (int)$row['dep_ID'] ?> — <?= InputUtils::escapeHTML($row['dep_Date']) ?> — <?= InputUtils::escapeHTML($row['dep_Type']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
<?php endif; ?>
      </div>

<?php if (in_array($reportType, ['Giving Report', 'Advanced Deposit Report'], true)) : ?>
      <div class="mb-3">
        <label class="form-label"><?= gettext('Apply dates to') ?></label>
        <div class="d-flex gap-3">
          <div class="form-check">
            <input class="form-check-input" type="radio" name="datetype" value="Deposit" id="datetypeDeposit" <?= $datetype !== 'Payment' ? 'checked' : '' ?>>
            <label class="form-check-label" for="datetypeDeposit"><?= gettext('Deposit date') ?></label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="datetype" value="Payment" id="datetypePayment" <?= $datetype === 'Payment' ? 'checked' : '' ?>>
            <label class="form-check-label" for="datetypePayment"><?= gettext('Payment date') ?></label>
          </div>
        </div>
      </div>
<?php endif; ?>

<?php if (in_array($reportType, ['Giving Report', 'Zero Givers'], true)) : ?>
      <div class="row g-3 mb-3">
        <div class="col-md-4">
          <label class="form-label"><?= gettext('Heading') ?></label>
          <select class="form-select" name="letterhead">
            <option value="address" selected><?= gettext('Church address') ?></option>
            <option value="graphic"><?= gettext('Graphic') ?></option>
            <option value="none"><?= gettext('Blank') ?></option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label"><?= gettext('Remittance slip') ?></label>
          <select class="form-select" name="remittance">
            <option value="no" selected><?= gettext('No') ?></option>
            <option value="yes"><?= gettext('Yes') ?></option>
          </select>
        </div>
      </div>
<?php endif; ?>

      <details class="mb-3">
        <summary class="text-secondary"><?= gettext('Advanced filters') ?></summary>
        <div class="mt-3">

<?php if (in_array($reportType, ['Giving Report', 'Pledge Reminders', 'Pledge Family Summary', 'Advanced Deposit Report'], true)) : ?>
          <div class="mb-3">
            <label class="form-label" for="classList"><?= gettext('Classification') ?></label>
            <select name="classList[]" class="form-select" multiple id="classList">
              <?php foreach ($classifications as $row) : ?>
                <option value="<?= (int)$row['lst_OptionID'] ?>"><?= InputUtils::escapeHTML($row['lst_OptionName']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label" for="family"><?= gettext('Family') ?></label>
            <select name="family[]" id="family" multiple class="form-select">
              <?php foreach ($families as $row) : ?>
                <option value="<?= (int)$row['fam_ID'] ?>">
                  <?= InputUtils::escapeHTML($row['fam_Name']) ?>
                  <?php if (isset($heads[$row['fam_ID']])) : ?>, <?= InputUtils::escapeHTML($heads[$row['fam_ID']]) ?><?php endif; ?>
                  <?= InputUtils::escapeHTML(MiscUtils::formatAddressLine($row['fam_Address1'], $row['fam_City'], $row['fam_State'])) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
<?php endif; ?>

<?php if (in_array($reportType, ['Pledge Family Summary', 'Giving Report', 'Advanced Deposit Report', 'Pledge Reminders'], true) && !empty($fundGroups)) : ?>
          <div class="mb-3">
            <label class="form-label" for="fundsList"><?= gettext('Fund') ?></label>
            <select name="funds[]" multiple id="fundsList" class="form-select">
              <?php foreach ($fundGroups as $catLabel => $catFunds) : ?>
                <?php if ($hasCategories) : ?><optgroup label="<?= InputUtils::escapeAttribute($catLabel !== '' ? $catLabel : gettext('Uncategorized')) ?>"><?php endif; ?>
                  <?php foreach ($catFunds as $fund) : ?>
                    <option value="<?= (int) $fund->getId() ?>"><?= InputUtils::escapeHTML($fund->getName()) ?><?= $fund->getActive() === 'false' ? ' — ' . gettext('INACTIVE') : '' ?></option>
                  <?php endforeach; ?>
                <?php if ($hasCategories) : ?></optgroup><?php endif; ?>
              <?php endforeach; ?>
            </select>
          </div>
<?php endif; ?>

<?php if ($reportType === 'Advanced Deposit Report') : ?>
          <div class="mb-3">
            <label class="form-label"><?= gettext('Payment type') ?></label>
            <select class="form-select" name="method[]" size="5" multiple>
              <option value="0" selected><?= gettext('All methods') ?></option>
              <option value="CHECK"><?= gettext('Check') ?></option>
              <option value="CASH"><?= gettext('Cash') ?></option>
              <option value="CREDITCARD"><?= gettext('Credit Card') ?></option>
              <option value="BANKDRAFT"><?= gettext('Bank Draft') ?></option>
            </select>
          </div>
<?php endif; ?>

<?php if ($reportType === 'Giving Report') : ?>
          <div class="mb-3">
            <label class="form-label" for="minimum"><?= gettext('Minimum total') ?></label>
            <input class="form-control" name="minimum" id="minimum" type="text" value="0" style="max-width:120px">
          </div>
<?php endif; ?>

<?php if ($reportType === 'Pledge Reminders') : ?>
          <div class="mb-3">
            <label class="form-label"><?= gettext('Include') ?></label>
            <div class="d-flex gap-3">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="pledge_filter" value="pledge" id="pledgeFilterPledge" checked>
                <label class="form-check-label" for="pledgeFilterPledge"><?= gettext('Only payments with pledges') ?></label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="pledge_filter" value="all" id="pledgeFilterAll">
                <label class="form-check-label" for="pledgeFilterAll"><?= gettext('All payments') ?></label>
              </div>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label"><?= gettext('Generate') ?></label>
            <div class="d-flex gap-3">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="only_owe" value="yes" id="onlyOweYes" checked>
                <label class="form-check-label" for="onlyOweYes"><?= gettext('Only families with unpaid pledges') ?></label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="only_owe" value="no" id="onlyOweNo">
                <label class="form-check-label" for="onlyOweNo"><?= gettext('All families') ?></label>
              </div>
            </div>
          </div>
<?php endif; ?>

        </div>
      </details>

      <div class="d-flex gap-2 mt-4">
        <a class="btn btn-secondary" href="<?= InputUtils::escapeAttribute($sRootPath . '/finance/reports') ?>"><?= gettext('Back') ?></a>
        <button type="submit" class="btn btn-primary">
          <?= $reportType === 'Giving Report' ? gettext('Generate statements') : gettext('Generate') ?>
        </button>
      </div>
    </form>
  </div>
</div>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
