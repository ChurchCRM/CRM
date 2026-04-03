<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\FiscalYearUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\MiscUtils;
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

$sPageTitle = gettext('Financial Reports');
$sPageSubtitle = gettext('Generate financial statements and giving reports');
if ($sReportType) {
    $sPageTitle .= ': ' . gettext($sReportType);
}
$aBreadcrumbs = PageHeader::breadcrumbs([
    [gettext('Finance'), '/finance/'],
    [gettext('Reports')],
]);
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
<div class="card">
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
          <option value="Pledge Summary"><?= gettext('Pledge Summary') ?></option>
          <option value="Pledge Family Summary"><?= gettext('Pledge Family Summary') ?></option>
          <option value="Pledge Reminders"><?= gettext('Pledge Reminders') ?></option>
          <option value="Voting Members"><?= gettext('Voting Members') ?></option>
          <option value="Giving Report"><?= gettext('Giving Report (Tax Statements)') ?></option>
          <option value="Zero Givers"><?= gettext('Zero Givers') ?></option>
          <option value="Individual Deposit Report"><?= gettext('Individual Deposit Report') ?></option>
          <option value="Advanced Deposit Report"><?= gettext('Advanced Deposit Report') ?></option>
        </select>
      </div>
      <div class="d-flex gap-2">
        <input type="button" class="btn btn-secondary" name="Cancel" value="<?= gettext('Cancel') ?>" onclick="javascript:document.location='v2/dashboard';">
        <input type="submit" class="btn btn-primary" name="Submit1" value="<?= gettext('Next') ?>">
      </div>
    </form>
    <?php
} else {
    $iFYID = $_SESSION['idefaultFY'];
    $iCalYear = date('Y');
    // 2nd Pass - Display filters and other settings
    switch ($sReportType) {
        case 'Giving Report':           $action = 'Reports/TaxReport.php'; break;
        case 'Zero Givers':             $action = 'Reports/ZeroGivers.php'; break;
        case 'Pledge Summary':          $action = 'Reports/PledgeSummary.php'; break;
        case 'Pledge Family Summary':   $action = 'Reports/FamilyPledgeSummary.php'; break;
        case 'Pledge Reminders':        $action = 'Reports/ReminderReport.php'; break;
        case 'Voting Members':          $action = 'Reports/VotingMembers.php'; break;
        case 'Individual Deposit Report': $action = 'Reports/PrintDeposit.php'; break;
        case 'Advanced Deposit Report': $action = 'Reports/AdvancedDeposit.php'; break;
    }
    ?>
    <form method="post" action="<?= $action ?>">
      <input type="hidden" name="ReportType" value="<?= InputUtils::escapeAttribute($sReportType) ?>">

      <h4 class="mb-3"><?= gettext('Filters') ?></h4>

    <?php
    // Filter by Classification and Families
    if (in_array($sReportType, ['Giving Report', 'Pledge Reminders', 'Pledge Family Summary', 'Advanced Deposit Report'])) {
        $sSQL = 'SELECT * FROM list_lst WHERE lst_ID = 1 ORDER BY lst_OptionSequence';
        $rsClassifications = RunQuery($sSQL); ?>
      <div class="mb-3">
        <label class="form-label" for="classList"><?= gettext('Classification') ?>:</label>
        <select name="classList[]" class="form-select" multiple id="classList">
          <?php while ($aRow = mysqli_fetch_array($rsClassifications)) {
              extract($aRow);
              echo '<option value="' . (int)$lst_OptionID . '">' . InputUtils::escapeHTML($lst_OptionName) . '</option>';
          } ?>
        </select>
        <div class="d-flex gap-2 mt-2">
          <button type="button" id="addAllClasses" class="btn btn-sm btn-secondary"><?= gettext('Add All Classes') ?></button>
          <button type="button" id="clearAllClasses" class="btn btn-sm btn-secondary"><?= gettext('Clear All Classes') ?></button>
        </div>
      </div>

      <?php
        $sSQL = 'SELECT fam_ID, fam_Name, fam_Address1, fam_City, fam_State FROM family_fam ORDER BY fam_Name';
        $rsFamilies = RunQuery($sSQL);
        if (!$sDirRoleHead) {
            $sDirRoleHead = '1';
        }
        $head_criteria = ' per_fmr_ID = ' . $sDirRoleHead;
        $head_criteria = str_replace(',', ' OR per_fmr_ID = ', $head_criteria);
        if (intval($sDirRoleSpouse) > 0) {
            $head_criteria .= " OR per_fmr_ID = $sDirRoleSpouse";
        }
        $sSQL = 'SELECT per_FirstName, per_fam_ID FROM person_per WHERE per_fam_ID > 0 AND (' . $head_criteria . ') ORDER BY per_fam_ID';
        $rs_head = RunQuery($sSQL);
        $aHead = [];
        while (list($head_firstname, $head_famid) = mysqli_fetch_row($rs_head)) {
            if ($head_firstname && array_key_exists($head_famid, $aHead)) {
                $aHead[$head_famid] .= ' & ' . $head_firstname;
            } elseif ($head_firstname) {
                $aHead[$head_famid] = $head_firstname;
            }
        }
        ?>
      <div class="mb-3">
        <label class="form-label" for="family"><?= gettext('Filter by Family') ?>:</label>
        <select name="family[]" id="family" multiple class="form-select">
          <?php while ($aRow = mysqli_fetch_array($rsFamilies)) {
              extract($aRow);
              echo '<option value="' . (int)$fam_ID . '">' . InputUtils::escapeHTML($fam_Name);
              if (array_key_exists($fam_ID, $aHead)) {
                  echo ', ' . InputUtils::escapeHTML($aHead[$fam_ID]);
              }
              echo ' ' . InputUtils::escapeHTML(MiscUtils::formatAddressLine($fam_Address1, $fam_City, $fam_State));
          } ?>
        </select>
        <div class="d-flex gap-2 mt-2">
          <button type="button" id="addAllFamilies" class="btn btn-sm btn-secondary"><?= gettext('Add All Families') ?></button>
          <button type="button" id="clearAllFamilies" class="btn btn-sm btn-secondary"><?= gettext('Clear All Families') ?></button>
        </div>
      </div>
    <?php } ?>

    <?php if (in_array($sReportType, ['Giving Report', 'Advanced Deposit Report', 'Zero Givers'])) :
        $today = date('Y-m-d');
        $startVal = $sDateStart ? $sDateStart : $today;
        $endVal = $sDateEnd ? $sDateEnd : $today;
        ?>
      <div class="row g-3 mb-3">
        <div class="col-md-4">
          <label class="form-label" for="DateStart"><?= gettext('Report Start Date') ?></label>
          <input type="text" class="form-control date-picker" name="DateStart" id="DateStart" maxlength="10" value="<?= InputUtils::escapeHTML($startVal) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label" for="DateEnd"><?= gettext('Report End Date') ?></label>
          <input type="text" class="form-control date-picker" name="DateEnd" id="DateEnd" maxlength="10" value="<?= InputUtils::escapeHTML($endVal) ?>">
        </div>
      </div>
      <?php if (in_array($sReportType, ['Giving Report', 'Advanced Deposit Report'])) :
          $depChecked = ($datetype !== 'Payment') ? 'checked' : '';
          $payChecked = ($datetype === 'Payment') ? 'checked' : '';
          ?>
        <div class="mb-3">
          <label class="form-label"><?= gettext('Apply Report Dates To') ?>:</label>
          <div class="d-flex gap-3">
            <div class="form-check">
              <input class="form-check-input" type="radio" name="datetype" value="Deposit" id="datetypeDeposit" <?= $depChecked ?>>
              <label class="form-check-label" for="datetypeDeposit"><?= gettext('Deposit Date (Default)') ?></label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="datetype" value="Payment" id="datetypePayment" <?= $payChecked ?>>
              <label class="form-check-label" for="datetypePayment"><?= gettext('Payment Date') ?></label>
            </div>
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <?php if (in_array($sReportType, ['Pledge Summary', 'Pledge Reminders', 'Pledge Family Summary', 'Voting Members'])) : ?>
      <div class="mb-3">
        <label class="form-label" for="FYID"><?= gettext('Fiscal Year') ?>:</label>
        <?php FiscalYearUtils::renderYearSelect('FYID', $iFYID); ?>
      </div>
    <?php endif; ?>

    <?php if (in_array($sReportType, ['Giving Report', 'Individual Deposit Report', 'Advanced Deposit Report'])) :
        $sSQL = 'SELECT dep_ID, dep_Date, dep_Type FROM deposit_dep ORDER BY dep_ID DESC LIMIT 0,200';
        $rsDeposits = RunQuery($sSQL); ?>
      <div class="mb-3">
        <label class="form-label" for="deposit"><?= gettext('Filter by Deposit') ?>:</label>
        <?php if ($sReportType !== 'Individual Deposit Report') : ?>
          <small class="text-secondary d-block mb-1"><?= gettext('If deposit is selected, date criteria will be ignored.') ?></small>
        <?php endif; ?>
        <select class="form-select" name="deposit" id="deposit">
          <?php if ($sReportType !== 'Individual Deposit Report') : ?>
            <option value="0" selected><?= gettext('All deposits within date range') ?></option>
          <?php endif; ?>
          <?php while ($aRow = mysqli_fetch_array($rsDeposits)) {
              extract($aRow);
              echo '<option value="' . (int)$dep_ID . '">' . (int)$dep_ID . ' — ' . InputUtils::escapeHTML($dep_Date) . ' — ' . InputUtils::escapeHTML($dep_Type) . '</option>';
          } ?>
        </select>
      </div>
    <?php endif; ?>

    <?php if (in_array($sReportType, ['Pledge Summary', 'Pledge Family Summary', 'Giving Report', 'Advanced Deposit Report', 'Pledge Reminders'])) :
        $sSQL = 'SELECT fun_ID, fun_Name, fun_Active FROM donationfund_fun ORDER BY fun_Active, fun_Name';
        $rsFunds = RunQuery($sSQL); ?>
      <div class="mb-3">
        <label class="form-label" for="fundsList"><?= gettext('Filter by Fund') ?>:</label>
        <select name="funds[]" multiple id="fundsList" class="form-select">
          <?php while ($aRow = mysqli_fetch_array($rsFunds)) {
              extract($aRow);
              echo '<option value="' . (int)$fun_ID . '">' . InputUtils::escapeHTML($fun_Name);
              if ($fun_Active === 'false') {
                  echo ' — INACTIVE';
              }
              echo '</option>';
          } ?>
        </select>
        <div class="d-flex gap-2 mt-2">
          <button type="button" id="addAllFunds" class="btn btn-sm btn-secondary"><?= gettext('Add All Funds') ?></button>
          <button type="button" id="clearAllFunds" class="btn btn-sm btn-secondary"><?= gettext('Clear All Funds') ?></button>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($sReportType === 'Advanced Deposit Report') : ?>
      <div class="mb-3">
        <label class="form-label"><?= gettext('Filter by Payment Type') ?>:</label>
        <small class="text-secondary d-block mb-1"><?= gettext('Use Ctrl Key to select multiple') ?></small>
        <select class="form-select" name="method[]" size="5" multiple>
          <option value="0" selected><?= gettext('All Methods') ?></option>
          <option value="CHECK"><?= gettext('Check') ?></option>
          <option value="CASH"><?= gettext('Cash') ?></option>
          <option value="CREDITCARD"><?= gettext('Credit Card') ?></option>
          <option value="BANKDRAFT"><?= gettext('Bank Draft') ?></option>
        </select>
      </div>
    <?php endif; ?>

    <?php if ($sReportType === 'Giving Report') : ?>
      <div class="mb-3">
        <label class="form-label" for="minimum"><?= gettext('Minimum Total Amount:') ?></label>
        <small class="text-secondary d-block mb-1"><?= gettext('0 - No Minimum') ?></small>
        <input class="form-control" style="width:120px" name="minimum" id="minimum" type="text" value="0">
      </div>
    <?php endif; ?>

      <h4 class="mt-4 mb-3"><?= gettext('Other Settings') ?></h4>

    <?php if ($sReportType === 'Pledge Reminders') : ?>
      <div class="mb-3">
        <label class="form-label"><?= gettext('Include') ?>:</label>
        <div class="d-flex gap-3">
          <div class="form-check">
            <input class="form-check-input" type="radio" name="pledge_filter" value="pledge" id="pledgeFilterPledge" checked>
            <label class="form-check-label" for="pledgeFilterPledge"><?= gettext('Only Payments with Pledges') ?></label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="pledge_filter" value="all" id="pledgeFilterAll">
            <label class="form-check-label" for="pledgeFilterAll"><?= gettext('All Payments') ?></label>
          </div>
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label"><?= gettext('Generate') ?>:</label>
        <div class="d-flex gap-3">
          <div class="form-check">
            <input class="form-check-input" type="radio" name="only_owe" value="yes" id="onlyOweYes" checked>
            <label class="form-check-label" for="onlyOweYes"><?= gettext('Only Families with unpaid pledges') ?></label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="only_owe" value="no" id="onlyOweNo">
            <label class="form-check-label" for="onlyOweNo"><?= gettext('All Families') ?></label>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <?php if (in_array($sReportType, ['Giving Report', 'Zero Givers'])) : ?>
      <div class="mb-3">
        <label class="form-label"><?= gettext('Report Heading:') ?></label>
        <div class="d-flex gap-3">
          <?php foreach (['graphic' => gettext('Graphic'), 'address' => gettext('Church Address'), 'none' => gettext('Blank')] as $val => $label) : ?>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="letterhead" value="<?= $val ?>" id="letterhead<?= $val ?>" <?= $val === 'address' ? 'checked' : '' ?>>
              <label class="form-check-label" for="letterhead<?= $val ?>"><?= $label ?></label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label"><?= gettext('Remittance Slip:') ?></label>
        <div class="d-flex gap-3">
          <div class="form-check">
            <input class="form-check-input" type="radio" name="remittance" value="yes" id="remittanceYes">
            <label class="form-check-label" for="remittanceYes"><?= gettext('Yes') ?></label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="remittance" value="no" id="remittanceNo" checked>
            <label class="form-check-label" for="remittanceNo"><?= gettext('No') ?></label>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($sReportType === 'Advanced Deposit Report') : ?>
      <div class="mb-3">
        <label class="form-label"><?= gettext('Sort Data by:') ?></label>
        <div class="d-flex gap-3">
          <?php foreach (['deposit' => gettext('Deposit'), 'fund' => gettext('Fund'), 'family' => gettext('Family')] as $val => $label) : ?>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="sort" value="<?= $val ?>" id="sort<?= $val ?>" <?= $val === 'deposit' ? 'checked' : '' ?>>
              <label class="form-check-label" for="sort<?= $val ?>"><?= $label ?></label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label"><?= gettext('Report Type') ?>:</label>
        <div class="d-flex gap-3">
          <?php foreach (['detail' => gettext('All Data'), 'medium' => gettext('Moderate Detail'), 'summary' => gettext('Summary Data')] as $val => $label) : ?>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="detail_level" value="<?= $val ?>" id="detail<?= $val ?>" <?= $val === 'detail' ? 'checked' : '' ?>>
              <label class="form-check-label" for="detail<?= $val ?>"><?= $label ?></label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($sReportType === 'Voting Members') : ?>
      <div class="mb-3">
        <label class="form-label" for="RequireDonationYears"><?= gettext('Voting members must have made a donation within this many years (0 to not require a donation)') ?>:</label>
        <input class="form-control" style="width:120px" name="RequireDonationYears" id="RequireDonationYears" type="text" value="0">
      </div>
    <?php endif; ?>

    <?php if (in_array($sReportType, ['Pledge Summary', 'Giving Report', 'Individual Deposit Report', 'Advanced Deposit Report', 'Zero Givers'])) : ?>
      <div class="mb-3">
        <label class="form-label"><?= gettext('Output Method:') ?></label>
        <div class="d-flex gap-3">
          <div class="form-check">
            <input class="form-check-input" type="radio" name="output" value="pdf" id="outputPdf" checked>
            <label class="form-check-label" for="outputPdf">PDF</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="output" value="csv" id="outputCsv">
            <label class="form-check-label" for="outputCsv"><?= gettext('CSV') ?></label>
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
  var familyEl = document.getElementById("family");
  if (familyEl && !familyEl.tomselect) new TomSelect(familyEl, { plugins: ["remove_button"] });
  $("#addAllFamilies").click(function () {
      var all = [];
      $("#family > option").each(function () { all.push(this.value); });
      if (familyEl && familyEl.tomselect) { familyEl.tomselect.setValue(all); }
  });
  $("#clearAllFamilies").click(function () {
      if (familyEl && familyEl.tomselect) { familyEl.tomselect.clear(); }
  });

  var classListEl = document.getElementById("classList");
  if (classListEl && !classListEl.tomselect) new TomSelect(classListEl, { plugins: ["remove_button"] });
  $("#addAllClasses").click(function () {
      var all = [];
      $("#classList > option").each(function () { all.push(this.value); });
      if (classListEl && classListEl.tomselect) { classListEl.tomselect.setValue(all); }
  });
  $("#clearAllClasses").click(function () {
      if (classListEl && classListEl.tomselect) { classListEl.tomselect.clear(); }
  });

  var fundsListEl = document.getElementById("fundsList");
  if (fundsListEl && !fundsListEl.tomselect) new TomSelect(fundsListEl, { plugins: ["remove_button"] });
  $("#addAllFunds").click(function () {
      var all = [];
      $("#fundsList > option").each(function () { all.push(this.value); });
      if (fundsListEl && fundsListEl.tomselect) { fundsListEl.tomselect.setValue(all); }
  });
  $("#clearAllFunds").click(function () {
      if (fundsListEl && fundsListEl.tomselect) { fundsListEl.tomselect.clear(); }
  });

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
