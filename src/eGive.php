<?php

/*******************************************************************************
 *
 *  filename    : eGive.php
 *  last change : 2009-08-27
 *  description : Tool for importing eGive data
 *
 ******************************************************************************/

// Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

if (!AuthenticationManager::getCurrentUser()->isFinanceEnabled()) {
    RedirectUtils::redirect('Menu.php');
    exit;
}

$now = time();
$dDate = date('Y-m-d', $now);
$lwDate = date('Y-m-d', $now - (6 * 24 * 60 * 60));

$iFYID = CurrentFY();
$iDepositSlipID = InputUtils::legacyFilterInput($_GET['DepositSlipID']);

include 'Include/eGiveConfig.php'; // Specific account information is in here

$familySelectHtml = buildFamilySelect(0, 0, 0);

// if a family is deleted, and donations are found, the egive_egv table is updated at the same time that donations are transferred.  But if there aren't donations at the time, and there's still and egive ID, we need to get that changed.  So, we'll build an array of all the family IDs here, and then NOT cache the egiveID to familyID association in the loop below.  There's probably a nicer way to do this with an SQL join,  but this seems more explicit.

$sSQL = 'SELECT fam_ID FROM family_fam';
$rsFamIDs = RunQuery($sSQL);
while ($aRow = mysqli_fetch_array($rsFamIDs)) {
    extract($aRow);
    $famIDs[] = $fam_ID;
}

// get array of all existing payments into a 'cache' so we don't have to keep querying the DB
$sSQL = 'SELECT egv_egiveID, egv_famID from egive_egv';
$egiveIDs = RunQuery($sSQL);
while ($aRow = mysqli_fetch_array($egiveIDs)) {
    extract($aRow);
    if (in_array($egv_famID, $famIDs)) { // make sure the family still exists
        $egiveID2FamID[$egv_egiveID] = $egv_famID;
    }
}

// get array of all existing donation/fund ids to names so we don't have to keep querying the DB
$sSQL = 'SELECT fun_ID, fun_Name, fun_Description from donationfund_fun';
$fundData = RunQuery($sSQL);
while ($aRow = mysqli_fetch_array($fundData)) {
    extract($aRow);
    $fundID2Name[$fun_ID] = $fun_Name;
    $fundID2Desc[$fun_ID] = $fun_Description;
    if (!$defaultFundId) {
        $defaultFundId = $fun_ID;
    }
}

$sSQL = 'SELECT plg_date, plg_amount, plg_CheckNo, plg_fundID, plg_FamID, plg_comment, plg_GroupKey from pledge_plg where plg_method="EGIVE" AND plg_PledgeOrPayment="Payment";';

$rsPlgIDs = RunQuery($sSQL);
while ($aRow = mysqli_fetch_array($rsPlgIDs)) {
    extract($aRow);

    $key = eGiveExistingKey($plg_CheckNo, $plg_FamID, $plg_date, $plg_fundID, $plg_comment);
    $eGiveExisting[$key] = $amount;
} // end while

// Set the page title and include HTML header
$sPageTitle = gettext('eGive Import');
require 'Include/Header.php';

if (isset($_POST['ApiGet'])) {
    $startDate = $_POST['StartDate'];
    $endDate = $_POST['EndDate'];

    $url = $eGiveURL . '/api/login/?apiKey=' . $eGiveApiKey;
    //var_dump($url);
    $fp = fopen($url, 'r');

    //$meta_data = stream_get_meta_data($fp);
    //foreach($meta_data['wrapper_data'] as $response) {
    //}

    $json = stream_get_contents($fp);
    fclose($fp);

    $api_error = 1;
    $logon = get_api_data($json);
    //$status = $logon["status"];
    //$message = $login["message"];

    if ($logon && $logon['status'] == 'success') {
        $api_error = 0;
        $token = $logon['token'];

        $url = $eGiveURL . '/api/transactions/' . $eGiveOrgID . '/' . $startDate;
        if ($endDate) {
            $url .= '/' . $endDate;
        }
        $url .= '/?token=' . $token;

        //var_dump($url);
        $fp = fopen($url, 'r');

        $json = stream_get_contents($fp);
        fclose($fp);
        $data = get_api_data($json, true);
        if ($data && $data['status'] == 'success') {
            $api_error = 0;

            // each transaction has these fields: 'transactionID' 'envelopeID' 'giftID' 'frequency' 'amount'
            // 'giverID' 'giverName' 'giverEmail' 'dateCompleted' 'breakouts'
            $importCreated = 0;
            $importNoChange = 0;
            $importError = 0;

            foreach ($data['transactions'] as $trans) {
                $transId = $trans['transactionID'];
                $name = $trans['giverName'];
                $totalAmount = $trans['amount'];
                $breakouts = $trans['breakouts'];
                $dateCompleted = $trans['dateCompleted'];
                $egiveID = $trans['giverID'];
                $frequency = $trans['frequency'];
                $dateTime = explode(' ', $dateCompleted);
                $date = yearFirstDate($dateTime[0]);
                $famID = 0;

                if ($egiveID2FamID && array_key_exists($egiveID, $egiveID2FamID)) {
                    $famID = $egiveID2FamID[$egiveID];
                } else {
                    $patterns[0] = '/\s+/'; // any whitespace
                    $patterns[1] = '/\./'; // or dots
                    $nameWithUnderscores = preg_replace($patterns, '_', $name);
                    $egiveID2NameWithUnderscores[$egiveID] = $nameWithUnderscores;
                }

                unset($amount);
                unset($eGiveFund);

                foreach ($breakouts as $breakout) {
                    $am = $breakout[0];
                    if ($am) {
                        $eGiveFundName = $breakout[1];
                        $fundId = getFundId($eGiveFundName);

                        if ($eGiveFund[$fundId]) {
                            $eGiveFund[$fundId] .= ',' . $eGiveFundName;
                        } else {
                            $eGiveFund[$fundId] = $eGiveFundName;
                        }

                        if ($amount[$fundId]) {
                            $amount[$fundId] += $am;
                        } else {
                            $amount[$fundId] = $am;
                        }

                        $totalAmount -= $am;
                    }
                }

                if ($totalAmount) {
                    $eGiveFundName = 'unspecified';

                    $fundId = getFundId($eGiveFundName);
                    if ($eGiveFund[$fundId]) {
                        $eGiveFund[$fundId] .= ',' . $eGiveFundName;
                    } else {
                        $eGiveFund[$fundId] = $eGiveFundName;
                    }

                    if ($amount[$fundId]) {
                        $amount[$fundId] += $totalAmount;
                    } else {
                        $amount[$fundId] = $totalAmount;
                    }
                }

                if ($amount) { // eGive records can be 'zero' for two reasons:  a) intentional zero to suspend giving, or b) rejected bank transfer
                    ksort($amount, SORT_NUMERIC);
                    $fundIds = implode(',', array_keys($amount));
                    $groupKey = genGroupKey($transId, $famID, $fundIds, $date);

                    foreach ($amount as $fundId => $am) {
                        $comment = $eGiveFund[$fundId];
                        if ($famID) {
                            updateDB($famID, $transId, $date, $name, $am, $fundId, $comment, $frequency, $groupKey);
                        } else {
                            $missingValue = $transId . '|' . $date . '|' . $egiveID . '|' . $name . '|' . $am . '|' . $fundId . '|' . $comment . '|' . $frequency . '|' . $groupKey;
                            $giftDataMissingEgiveID[] = $missingValue;
                            ++$importError;
                        }
                    }
                }
            }
        }
    }
    $url = $eGiveURL . '/api/logout/?apiKey=' . $eGiveApiKey;
    $fp = fopen($url, 'r');

    $json = stream_get_contents($fp);
    fclose($fp);

    // don't know if it makes sense to check the logout success here...  we've already gotten data, cratering the transaction because the logout didn't work seems dumb.  In fact, I don't even check the logout success....  because of that very reason.
    $logout = json_decode($json, true);

    $_SESSION['giftDataMissingEgiveID'] = $giftDataMissingEgiveID;
    $_SESSION['egiveID2NameWithUnderscores'] = $egiveID2NameWithUnderscores;
    if (!$api_error) {
        importDoneFixOrContinue();
    }
} elseif (isset($_POST['ReImport'])) {
    $giftDataMissingEgiveID = $_SESSION['giftDataMissingEgiveID'];
    $egiveID2NameWithUnderscores = $_SESSION['egiveID2NameWithUnderscores'];

    $importCreated = 0;
    $importNoChange = 0;
    $importError = 0;
    foreach ($egiveID2NameWithUnderscores as $egiveID => $nameWithUnderscores) {
        $famID = $_POST['MissingEgive_FamID_' . $nameWithUnderscores];
        $doUpdate = $_POST['MissingEgive_Set_' . $nameWithUnderscores];
        if ($famID) {
            if ($doUpdate) {
                $sSQL = "INSERT INTO egive_egv (egv_egiveID, egv_famID, egv_DateEntered, egv_EnteredBy) VALUES ('" . $egiveID . "','" . $famID . "','" . date('YmdHis') . "','" . AuthenticationManager::getCurrentUser()->getId() . "');";
                RunQuery($sSQL);
            }

            foreach ($giftDataMissingEgiveID as $data) {
                $fields = explode('|', $data);
                if ($fields[2] == $egiveID) {
                    $transId = $fields[0];
                    $date = $fields[1];
                    $name = $fields[3];
                    $amount = $fields[4];
                    $fundId = $fields[5];
                    $comment = $fields[6];
                    $frequency = $fields[7];
                    $groupKey = $fields[8];

                    updateDB($famID, $transId, $date, $name, $amount, $fundId, $comment, $frequency, $groupKey);
                }
            }
        } else {
            ++$importError;
        }
    }
    $_SESSION['giftDataMissingEgiveID'] = $giftDataMissingEgiveID;
    $_SESSION['egiveID2NameWithUnderscores'] = $egiveID2NameWithUnderscores;

    importDoneFixOrContinue();
} else {
    ?>
    <table cellpadding="3" align="left">
    <tr><td>
        <form method="post" action="eGive.php?DepositSlipID=<?php echo $iDepositSlipID ?>" enctype="multipart/form-data">
        <class="LabelColumn"><b><?= gettext('Start Date: ') ?></b>
            <class="TextColumn"><input type="text" name="StartDate" value="<?= $lwDate ?>" maxlength="10" id="StartDate" size="11" class="date-picker"><span style="color: red;"><?php echo $sDateError ?></span><br>
            <class="LabelColumn"><b><?= gettext('End Date: ') ?></b>
            <class="TextColumn"><input type="text" name="EndDate" value="<?= $dDate ?>" maxlength="10" id="EndDate" size="11" class="date-picker"><span style="color: red;"><?php echo $sDateError ?></span><br><br>
        <input type="submit" class="btn btn-default" value="<?= gettext('Import eGive') ?>" name="ApiGet">
        <br><br><br>
        </form>
        </td>
    </tr>
    <?php
}

function updateDB($famID, $transId, $date, $name, $amount, $fundId, $comment, $frequency, $groupKey)
{
    global $eGiveExisting;
    global $iFYID;
    global $iDepositSlipID;
    global $importCreated;
    global $importNoChange;

    $keyExisting = eGiveExistingKey($transId, $famID, $date, $fundId, $comment);
    if ($eGiveExisting && array_key_exists($keyExisting, $eGiveExisting)) {
        ++$importNoChange;
    } elseif ($famID) { //  insert a new record
        $sSQL = "INSERT INTO pledge_plg (plg_famID, plg_FYID, plg_date, plg_amount, plg_schedule, plg_method, plg_comment, plg_DateLastEdited, plg_EditedBy, plg_PledgeOrPayment, plg_fundID, plg_depID, plg_CheckNo, plg_NonDeductible, plg_GroupKey) VALUES ('" . $famID . "','" . $iFYID . "','" . $date . "','" . $amount . "','" . $frequency . "','EGIVE','" . $comment . "','" . date('YmdHis') . "'," . AuthenticationManager::getCurrentUser()->getId() . ",'Payment'," . $fundId . ",'" . $iDepositSlipID . "','" . $transId . "','0','" . $groupKey . "')";
        ++$importCreated;
        RunQuery($sSQL);
    }
}

function getFundId($eGiveFundName)
{
    global $fundID2Name;
    global $fundID2Desc;
    global $defaultFundId;

    foreach ($fundID2Name as $fun_ID => $fun_Name) {
        if (preg_match("%$fun_Name%i", $eGiveFundName)) {
            return $fun_ID;
        }
    }

    foreach ($fundID2Desc as $fun_ID => $fun_Desc) {
        $descWords = explode(' ', $fun_Desc);
        foreach ($descWords as $desc) {
            if (preg_match("%$desc%i", $eGiveFundName)) {
                return $fun_ID;
            }
        }
    }

    return $defaultFundId;
}

function importDoneFixOrContinue()
{
    global $importCreated;
    global $importNoChange;
    global $importError;
    global $iDepositSlipID;
    global $missingEgiveIDCount;
    global $egiveID2NameWithUnderscores;
    global $familySelectHtml; ?>
    <form method="post" action="eGive.php?DepositSlipID=<?= $iDepositSlipID ?>">
    <?php
    if ($importError) { // the only way we can fail to import data is if we're missing the egive IDs, so build a table, with text input, and prompt for it.?>
        <p>New eGive Name(s) and ID(s) have been imported and must be associated with the appropriate Family.  Use the pulldown in the <b>Family</b> column to select the Family, based on the eGive name, and then press the Re-Import button.<br><br>If you cannot make the assignment now, you can safely go Back to the Deposit Slip, and Re-import this data at a later time.  Its possible you may need to view eGive data using the Web View in order to make an accurate Family assignment.</p>
        <table border=1>
        <tr><td><b>eGive Name</b></td><td><b>eGive ID</b></td><td><b>Family</b></td><td><b>Set eGive ID into Family</b></td></tr>
        <?php

        foreach ($egiveID2NameWithUnderscores as $egiveID => $nameWithUnderscores) {
            $name = preg_replace('/_/', ' ', $nameWithUnderscores);
            echo '<tr>';
            echo '<td>' . $name . '&nbsp;</td>'; ?>
            <td><class="TextColumn"><input type="text" name="MissingEgive_ID_<?= $nameWithUnderscores ?>" value="<?= $egiveID ?>" maxlength="10"></td>
            <td class="TextColumn">
            <select name="MissingEgive_FamID_<?= $nameWithUnderscores ?>">
            <option value="0" selected><?= gettext('Unassigned') ?></option>
            <?php
            echo $familySelectHtml; ?>
            </select>
            </td>
            <td><input type="checkbox" name="MissingEgive_Set_<?= $nameWithUnderscores ?>" value="1" checked></td>
            <?php
            echo '</tr>';
        } ?>
        </table><br>

        <input type="submit" class="btn btn-default" value="<?= gettext('Re-import to selected family') ?>" name="ReImport">
        <?php
    } ?>

    <p class="MediumLargeText"> <?= gettext('Data import results: ') . $importCreated . gettext(' gifts were imported, ') . $importNoChange . gettext(' gifts unchanged, and ') . $importError . gettext(' gifts not imported due to problems') ?></p>
    <input type="button" class="btn btn-default" value="<?= gettext('Back to Deposit Slip') ?>" onclick="javascript:document.location='DepositSlipEditor.php?DepositSlipID=<?= $iDepositSlipID ?>'"
    <?php
}

function get_api_data($json)
{
    $result = json_decode($json, true);

    $rc = json_last_error();
    switch ($rc) {
        case JSON_ERROR_DEPTH:
            $error = ' - Maximum stack depth exceeded';
            break;
        case JSON_ERROR_CTRL_CHAR:
            $error = ' - Unexpected control character found';
            break;
        case JSON_ERROR_SYNTAX:
            $error = ' - Syntax error, malformed JSON';
            break;
        case JSON_ERROR_NONE:
        default:
            $error = '';
    }

    if (empty($error)) {
        return $result;
    } else {
        ?>
        <span style="color: red;"><?= gettext("Fatal error in eGive API datastream: '") . $error ?>"'</span><br><br>
        <input type="button" class="btn btn-default" value="<?= gettext('Back to Deposit Slip') ?>" onclick="javascript:document.location='DepositSlipEditor.php?DepositSlipID=<?= $iDepositSlipID ?>'"
        <?php
        return 0;
    }
}
?>

<?php

require 'Include/Footer.php';

function yearFirstDate($date)
{
    $dateArray = explode('/', $date); // this date is in mm/dd/yy format.  churchCRM needs it in yyyy-mm-dd format
    if (strlen($dateArray[2]) == 2) {
        $dateArray[2] += 2000;
    }
    $dateArray[0] = sprintf('%02d', $dateArray[0]);
    $dateArray[1] = sprintf('%02d', $dateArray[1]);
    $dateCI = $dateArray[2] . '-' . $dateArray[0] . '-' . $dateArray[1];

    return $dateCI;
}

function eGiveExistingKey($transId, $famID, $date, $fundId, $comment)
{
    $key = $transId . '|' . $famID . '|' . $date . '|' . $fundId . '|' . $comment;

    return $key;
}

?>
