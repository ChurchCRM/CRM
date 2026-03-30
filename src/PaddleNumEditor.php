<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Bootstrapper;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\view\PageHeader;

$iPaddleNumID = InputUtils::legacyFilterInputArr($_GET, 'PaddleNumID', 'int');
$linkBack = RedirectUtils::getLinkBackFromRequest('v2/dashboard');

if ($iPaddleNumID > 0) {
    $sSQL ="SELECT * FROM paddlenum_pn WHERE pn_ID = '$iPaddleNumID'";
    $rsPaddleNum = RunQuery($sSQL);
    $thePaddleNum = mysqli_fetch_array($rsPaddleNum);
    $iCurrentFundraiser = $thePaddleNum['pn_fr_ID'];
} else {
    $iCurrentFundraiser = $_SESSION['iCurrentFundraiser'];
}

if ($iCurrentFundraiser === '') {
    Bootstrapper::systemFailure('No active Fundraiser', 'System Error');
}

// Get the current fundraiser data
if ($iCurrentFundraiser) {
    $sSQL = 'SELECT * from fundraiser_fr WHERE fr_ID = ' . $iCurrentFundraiser;
    $rsDeposit = RunQuery($sSQL);
    extract(mysqli_fetch_array($rsDeposit));
}

// SQL to get multibuy items
$sMultibuyItemsSQL ="SELECT di_ID, di_title FROM donateditem_di WHERE di_multibuy='1' AND di_FR_ID=" . $iCurrentFundraiser;

$sPageTitle = gettext('Buyer Number Editor');
$sPageSubtitle = gettext('Assign and manage fundraiser buyer numbers');

// Is this the second pass?
if (isset($_POST['PaddleNumSubmit']) || isset($_POST['PaddleNumSubmitAndAdd']) || isset($_POST['GenerateStatement'])) {
    //Get all the variables from the request object and assign them locally
    $iNum = (int) InputUtils::legacyFilterInput($_POST['Num']);
    $iPerID = (int) InputUtils::legacyFilterInput($_POST['PerID']);

    $rsMBItems = RunQuery($sMultibuyItemsSQL); // Go through the multibuy items, see if this person bought any
    while ($aRow = mysqli_fetch_array($rsMBItems)) {
        extract($aRow);
        $mbName = 'MBItem' . $di_ID;
        $iMBCount = (int) InputUtils::legacyFilterInput($_POST[$mbName], 'int');
        if ($iMBCount > 0) { // count for this item is positive.  If a multibuy record exists, update it.  If not, create it.
            $sqlNumBought = 'SELECT mb_count from multibuy_mb WHERE mb_per_ID=' . $iPerID . ' AND mb_item_ID=' . (int)$di_ID;
            $rsNumBought = RunQuery($sqlNumBought);
            $numBoughtRow = mysqli_fetch_array($rsNumBought);
            if ($numBoughtRow) {
                $sSQL = 'UPDATE multibuy_mb SET mb_count=' . $iMBCount . ' WHERE mb_per_ID=' . $iPerID . ' AND mb_item_ID=' . (int)$di_ID;
                RunQuery($sSQL);
            } else {
                $sSQL = 'INSERT INTO multibuy_mb (mb_per_ID, mb_item_ID, mb_count) VALUES (' . $iPerID . ',' . (int)$di_ID . ',' . $iMBCount . ')';
                RunQuery($sSQL);
            }
        } else { // count is zero, if it was positive before there is a multibuy record that needs to be deleted
            $sSQL = 'DELETE FROM multibuy_mb WHERE mb_per_ID=' . $iPerID . ' AND mb_item_ID=' . (int)$di_ID;
            RunQuery($sSQL);
        }
    }

    // New PaddleNum
    if (strlen($iPaddleNumID) < 1) {
        $sSQL = 'INSERT INTO paddlenum_pn (pn_fr_ID, pn_Num, pn_per_ID)
                 VALUES (' . (int)$iCurrentFundraiser . ',' . $iNum . ',' . $iPerID . ')';
        $bGetKeyBack = true;
        // Existing record (update)
    } else {
        $sSQL = 'UPDATE paddlenum_pn SET pn_fr_ID = ' . (int)$iCurrentFundraiser . ', pn_Num = ' . $iNum . ', pn_per_ID = ' . $iPerID;
        $sSQL .= ' WHERE pn_ID = ' . $iPaddleNumID;
        $bGetKeyBack = false;
    }

    //Execute the SQL
    RunQuery($sSQL);

    // If this is a new PaddleNum or deposit, get the key back
    if ($bGetKeyBack) {
        $sSQL = 'SELECT MAX(pn_ID) AS iPaddleNumID FROM paddlenum_pn';
        $rsPaddleNumID = RunQuery($sSQL);
        extract(mysqli_fetch_array($rsPaddleNumID));
    }

    if (isset($_POST['PaddleNumSubmit'])) {
        RedirectUtils::redirect('PaddleNumEditor.php?PaddleNumID=' . $iPaddleNumID . '&linkBack=' . $linkBack);
    } elseif (isset($_POST['PaddleNumSubmitAndAdd'])) {
        //Reload to editor to add another record
        RedirectUtils::redirect("PaddleNumEditor.php?CurrentFundraiser=$iCurrentFundraiser&linkBack=" . $linkBack);
    } elseif (isset($_POST['GenerateStatement'])) {
        //Jump straight to generating the statement report
        RedirectUtils::redirect("Reports/FundRaiserStatement.php?PaddleNumID=$iPaddleNumID");
    }
} else {
    //FirstPass
    //Are we editing or adding?
    if (strlen($iPaddleNumID) > 0) {
        //Editing....
        //Get all the data on this record
        $sSQL ="SELECT pn_ID, pn_fr_ID, pn_Num, pn_per_ID,
                           a.per_FirstName as buyerFirstName, a.per_LastName as buyerLastName
             FROM paddlenum_pn
             LEFT JOIN person_per a ON pn_per_ID=a.per_ID
             WHERE pn_ID = '" . $iPaddleNumID ."'";
        $rsPaddleNum = RunQuery($sSQL);
        extract(mysqli_fetch_array($rsPaddleNum));

        $iNum = $pn_Num;
        $iPerID = $pn_per_ID;
    } else {
        //Adding....
        //Set defaults
        $sSQL = 'SELECT COUNT(*) AS topNum FROM paddlenum_pn WHERE pn_fr_ID=' . $iCurrentFundraiser;
        $rsGetMaxNum = RunQuery($sSQL);
        extract(mysqli_fetch_array($rsGetMaxNum));

        $iNum = $topNum + 1;
        $iPerID = 0;
    }
}

//Get People for the drop-down
$sPeopleSQL = 'SELECT per_ID, per_FirstName, per_LastName, fam_Address1, fam_City, fam_State FROM person_per JOIN family_fam on per_fam_id=fam_id ORDER BY per_LastName, per_FirstName';

$aBreadcrumbs = PageHeader::breadcrumbs([
    [gettext('Fundraiser'), '/FindFundRaiser.php'],
    [gettext('Buyer Number')],
]);
require_once __DIR__ . '/Include/Header.php';

?>
<div class="card">
  <div class="card-body">
    <form method="post" action="PaddleNumEditor.php?<?= 'CurrentFundraiser=' . $iCurrentFundraiser . '&PaddleNumID=' . $iPaddleNumID . '&linkBack=' . $linkBack ?>" name="PaddleNumEditor">

      <div class="d-flex gap-2 mb-4">
        <input type="submit" class="btn btn-primary" value="<?= gettext('Save') ?>" name="PaddleNumSubmit">
        <input type="submit" class="btn btn-secondary" value="<?= gettext('Generate Statement') ?>" name="GenerateStatement">
        <?php if (AuthenticationManager::getCurrentUser()->isAddRecordsEnabled()) : ?>
          <input type="submit" class="btn btn-secondary" value="<?= gettext('Save and Add') ?>" name="PaddleNumSubmitAndAdd">
        <?php endif; ?>
        <input type="button" class="btn btn-secondary" value="<?= gettext('Back') ?>" name="PaddleNumCancel" onclick="document.location='<?= RedirectUtils::escapeRedirectUrl($linkBack, 'v2/dashboard') ?>';">
      </div>

      <div class="row">
        <div class="col-md-6">
          <div class="mb-3">
            <label class="form-label" for="Num"><?= gettext('Number') ?>:</label>
            <input type="text" class="form-control" name="Num" id="Num" value="<?= $iNum ?>">
          </div>

          <div class="mb-3">
            <label class="form-label" for="PerID"><?= gettext('Buyer') ?>:</label>
            <select class="form-select" name="PerID" id="PerID">
              <option value="0" selected><?= gettext('Unassigned') ?></option>
              <?php
              $rsPeople = RunQuery($sPeopleSQL);
              while ($aRow = mysqli_fetch_array($rsPeople)) {
                  extract($aRow);
                  echo '<option value="' . (int)$per_ID . '"';
                  if ($iPerID === $per_ID) {
                      echo ' selected';
                  }
                  echo '>' . InputUtils::escapeHTML($per_LastName) . ', ' . InputUtils::escapeHTML($per_FirstName);
                  echo ' ' . InputUtils::escapeHTML(FormatAddressLine($fam_Address1, $fam_City, $fam_State));
              }
              ?>
            </select>
          </div>
        </div>

        <div class="col-md-6">
          <?php
          $rsMBItems = RunQuery($sMultibuyItemsSQL);
          while ($aRow = mysqli_fetch_array($rsMBItems)) {
              extract($aRow);
              $sqlNumBought = 'SELECT mb_count from multibuy_mb WHERE mb_per_ID=' . $iPerID . ' AND mb_item_ID=' . $di_ID;
              $rsNumBought = RunQuery($sqlNumBought);
              $numBoughtRow = mysqli_fetch_array($rsNumBought);
              $mb_count = $numBoughtRow ? $numBoughtRow['mb_count'] : 0;
              ?>
            <div class="mb-3">
              <label class="form-label" for="MBItem<?= (int)$di_ID ?>"><?= InputUtils::escapeHTML($di_title) ?></label>
              <input type="text" class="form-control" name="MBItem<?= (int)$di_ID ?>" id="MBItem<?= (int)$di_ID ?>" value="<?= (int)$mb_count ?>">
            </div>
          <?php } ?>
        </div>
      </div>

    </form>
  </div>
</div>
<?php
require_once __DIR__ . '/Include/Footer.php';
