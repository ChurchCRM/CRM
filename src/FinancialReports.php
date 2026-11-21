<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\view\PageHeader;

// Security
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isFinanceEnabled(), 'Finance');

$sReportType = '';

if (array_key_exists('ReportType', $_POST)) {
    $sReportType = InputUtils::legacyFilterInput($_POST['ReportType']);
}

if ($sReportType === '' && array_key_exists('ReportType', $_GET)) {
    $sReportType = InputUtils::legacyFilterInput($_GET['ReportType']);
}

if (!in_array($sReportType, ['Zero Givers'], true)) {
    $sReportType = '';
}

$sPageTitle = gettext('Financial Reports');
$sPageSubtitle = gettext('Generate financial statements and giving reports');
if ($sReportType) {
    $sPageTitle .= ': ' . gettext($sReportType);
}
$reportCrumbs = [
    [gettext('Financial Reports'), '/finance/reports'],
];
if ($sReportType !== '') {
    $reportCrumbs[] = [gettext($sReportType)];
}
$aBreadcrumbs = PageHeader::breadcrumbs($reportCrumbs);
require_once __DIR__ . '/Include/Header.php';
// Preserve submitted dates/datetype for both selection and filters views
$sDateStart = '';
$sDateEnd = '';
$datetype = '';
if (array_key_exists('DateStart', $_POST)) {
    $sDateStart = InputUtils::legacyFilterInput($_POST['DateStart'], 'date');
} elseif (array_key_exists('DateStart', $_GET)) {
    $sDateStart = InputUtils::legacyFilterInput($_GET['DateStart'], 'date');
}
if (array_key_exists('DateEnd', $_POST)) {
    $sDateEnd = InputUtils::legacyFilterInput($_POST['DateEnd'], 'date');
} elseif (array_key_exists('DateEnd', $_GET)) {
    $sDateEnd = InputUtils::legacyFilterInput($_GET['DateEnd'], 'date');
}
if (array_key_exists('datetype', $_POST)) {
    $datetype = InputUtils::legacyFilterInput($_POST['datetype']);
} elseif (array_key_exists('datetype', $_GET)) {
    $datetype = InputUtils::legacyFilterInput($_GET['datetype']);
}
?>
<div class="card financial-reports">
  <div class="card-body">
<!-- Styles for this page moved into the project's SCSS: `src/skin/scss/_financial-reports.scss` -->
<?php

// No Records Message if previous report returned no records.
if (array_key_exists('ReturnMessage', $_GET) && $_GET['ReturnMessage'] === 'NoRows') {
    echo '<div class="alert alert-warning" role="alert">';
    echo '<i class="fa-solid fa-triangle-exclamation"></i> ';
    echo '<strong>' . gettext('No Data Found') . '</strong><br>';
    echo gettext('No records were returned from the previous report. Please adjust your filters or date range and try again.');
    echo '</div>';
}

if ($sReportType === '') {
    // First Pass - Choose report type
    ?>
    <form method="post" id="FinancialReports" action="FinancialReports.php">
      <div class="mb-3">
        <label class="form-label" for="FinancialReportTypes"><?= gettext('Report Type') ?>:</label>
        <select class="form-select" name="ReportType" id="FinancialReportTypes">
          <option selected disabled value="0"><?= gettext('Select Report Type') ?></option>
          <option value="Zero Givers"><?= gettext('Zero Givers') ?></option>
        </select>
      </div>
      <div class="d-flex gap-2">
        <a class="btn btn-secondary" href="<?= InputUtils::escapeAttribute(SystemURLs::getRootPath() . '/people/dashboard') ?>"><?= gettext('Cancel') ?></a>
        <input type="submit" class="btn btn-primary" name="Submit1" value="<?= gettext('Next') ?>">
      </div>
    </form>
    <?php
} else {
    // 2nd Pass - Display filters and other settings
    switch ($sReportType) {
        case 'Zero Givers':             $action = 'finance/reports/zero-givers/download'; break;
    }
    ?>
    <form method="post" action="<?= $action ?>">
      <input type="hidden" name="ReportType" value="<?= InputUtils::escapeAttribute($sReportType) ?>">

      <h4 class="mb-3"><?= gettext('Filters') ?></h4>

    <?php if ($sReportType === 'Zero Givers') :
        $today = date('Y-m-d');
        $startVal = $sDateStart !== '' ? $sDateStart : $today;
        $endVal = $sDateEnd !== '' ? $sDateEnd : $today;
        ?>
      <div class="row g-3 mb-3">
        <div class="col-md-4">
          <label class="form-label" for="DateStart"><?= gettext('Report Start Date') ?></label>
          <input type="text" class="form-control date-picker" name="DateStart" id="DateStart" maxlength="10" value="<?= InputUtils::escapeHTML(DateTimeUtils::formatForDatePicker($startVal)) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label" for="DateEnd"><?= gettext('Report End Date') ?></label>
          <input type="text" class="form-control date-picker" name="DateEnd" id="DateEnd" maxlength="10" value="<?= InputUtils::escapeHTML(DateTimeUtils::formatForDatePicker($endVal)) ?>">
        </div>
      </div>

    <?php endif; ?>

      <h4 class="mt-4 mb-3"><?= gettext('Other Settings') ?></h4>

    <?php if ($sReportType === 'Zero Givers') : ?>
      <div class="mb-3">
        <label class="form-label"><?= gettext('Report Heading') ?>:</label>
        <div class="d-flex gap-3">
          <?php foreach (['graphic' => gettext('Graphic'), 'address' => gettext('Church Address'), 'none' => gettext('Blank')] as $val => $label) : ?>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="letterhead" value="<?= $val ?>" id="letterhead<?= $val ?>" <?= $val === 'address' ? 'checked' : '' ?>>
              <label class="form-check-label" for="letterhead<?= $val ?>"><?= $label ?></label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($sReportType === 'Zero Givers') : ?>
      <div class="mb-3">
        <label class="form-label"><?= gettext('Output Method') ?>:</label>
        <div class="d-flex gap-3">
          <div class="form-check">
            <input class="form-check-input" type="radio" name="output" value="pdf" id="outputPdf" checked>
            <label class="form-check-label" for="outputPdf">PDF</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="output" value="csv" id="outputCsv">
            <label class="form-check-label" for="outputCsv">CSV</label>
          </div>
        </div>
      </div>
    <?php else : ?>
      <input type="hidden" name="output" value="pdf">
    <?php endif; ?>

      <div class="d-flex gap-2 mt-3">
        <input type="button" class="btn btn-secondary" name="Cancel" value="<?= gettext('Back') ?>" onclick="javascript:document.location='FinancialReports.php';">
        <input type="submit" class="btn btn-primary" id="createReport" name="Submit2" value="<?= gettext('Create Report') ?>">
      </div>
    </form>
  <?php
}
?>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
$(document).ready(function() {
  // Handle report download - clear"No Data Found" banner when exporting
  $(document).on("click","button[type='submit'], input[type='submit']", function() {
    // Simply hide the No Data Found alert banner when any submit button is clicked
    $(".alert-warning").hide();
  });
  }
);

</script>
</div>
<?php
require_once __DIR__ . '/Include/Footer.php';
