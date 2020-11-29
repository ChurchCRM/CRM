<?php
/*******************************************************************************
 *
 *  filename    : PledgeEditor.php
 *  last change : 2012-06-29
 *  website     : http://www.churchcrm.io
 *  copyright   : Copyright 2001, 2002, 2003 Deane Barker, Chris Gebhardt
 *                Copyright 2004-2012Michael Wilt
  *
 ******************************************************************************/

//Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\MICRReader;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\Authentication\AuthenticationManager;

if (SystemConfig::getValue('bUseScannedChecks')) { // Instantiate the MICR class
    $micrObj = new MICRReader();
}

$iEnvelope = 0;
$sCheckNoError = '';
$iCheckNo = '';
$sDateError = '';
$sAmountError = '';
$nNonDeductible = [];
$sComment = '';
$tScanString = '';
$dep_Closed = false;
$iAutID = 0;
$iCurrentDeposit = 0;

$nAmount = []; // this will be the array for collecting values for each fund
$sAmountError = [];
$sComment = [];

$checkHash = [];

// Get the list of funds
$sSQL = 'SELECT fun_ID,fun_Name,fun_Description,fun_Active FROM donationfund_fun';
$sSQL .= " WHERE fun_Active = 'true'"; // New donations should show only active funds.

$rsFunds = RunQuery($sSQL);
mysqli_data_seek($rsFunds, 0);
while ($aRow = mysqli_fetch_array($rsFunds)) {
    extract($aRow);
    $fundId2Name[$fun_ID] = $fun_Name;
    $nAmount[$fun_ID] = 0.0;
    $nNonDeductible[$fun_ID] = 0.0;
    $sAmountError[$fun_ID] = '';
    $sComment[$fun_ID] = '';
    if (!isset($defaultFundID)) {
        $defaultFundID = $fun_ID;
    }
    $fundIdActive[$fun_ID] = $fun_Active;
} // end while

// Handle URL via _GET first
if (array_key_exists('PledgeOrPayment', $_GET)) {
    $PledgeOrPayment = InputUtils::LegacyFilterInput($_GET['PledgeOrPayment'], 'string');
}
$sGroupKey = '';
if (array_key_exists('GroupKey', $_GET)) {
    $sGroupKey = InputUtils::LegacyFilterInput($_GET['GroupKey'], 'string');
} // this will only be set if someone pressed the 'edit' button on the Pledge or Deposit line
if (array_key_exists('CurrentDeposit', $_GET)) {
    $iCurrentDeposit = InputUtils::LegacyFilterInput($_GET['CurrentDeposit'], 'integer');
}
$linkBack = InputUtils::LegacyFilterInput($_GET['linkBack'], 'string');
$iFamily = 0;
if (array_key_exists('FamilyID', $_GET)) {
    $iFamily = InputUtils::LegacyFilterInput($_GET['FamilyID'], 'int');
}

$fund2PlgIds = []; // this will be the array cross-referencing funds to existing plg_plgid's

if ($sGroupKey) {
    $sSQL = 'SELECT plg_plgID, plg_fundID, plg_EditedBy from pledge_plg where plg_GroupKey="'.$sGroupKey.'"';
    $rsKeys = RunQuery($sSQL);
    while ($aRow = mysqli_fetch_array($rsKeys)) {
        $onePlgID = $aRow['plg_plgID'];
        $oneFundID = $aRow['plg_fundID'];
        $iOriginalSelectedFund = $oneFundID; // remember the original fund in case we switch to splitting
        $fund2PlgIds[$oneFundID] = $onePlgID;

        // Security: User must have Finance permission or be the one who entered this record originally
        if (!(AuthenticationManager::GetCurrentUser()->isFinanceEnabled() || AuthenticationManager::GetCurrentUser()->getId() == $aRow['plg_EditedBy'])) {
            RedirectUtils::Redirect('Menu.php');
            exit;
        }
    }
}

// Handle _POST input if the form was up and a button press came in
if (isset($_POST['PledgeSubmit']) or
    isset($_POST['PledgeSubmitAndAdd']) or
    isset($_POST['MatchFamily']) or
    isset($_POST['MatchEnvelope']) or
    isset($_POST['SetDefaultCheck']) or
    isset($_POST['SetFundTypeSelection'])) {
    $iFamily = InputUtils::LegacyFilterInput($_POST['FamilyID'], 'int');

    $dDate = InputUtils::LegacyFilterInput($_POST['Date']);
    if (!$dDate) {
        if (array_key_exists('idefaultDate', $_SESSION)) {
            $dDate = $_SESSION['idefaultDate'];
        } else {
            $dDate = date('Y-m-d');
        }
    }
    $_SESSION['idefaultDate'] = $dDate;

    // set from drop-down if set, saved session default, or by calcuation
    $iFYID = InputUtils::LegacyFilterInput($_POST['FYID'], 'int');
    if (!$iFYID) {
        $iFYID = $_SESSION['idefaultFY'];
    }
    if (!$iFYID) {
        $iFYID = CurrentFY();
    }
    $_SESSION['idefaultFY'] = $iFYID;

    if (array_key_exists('CheckNo', $_POST)) {
        $iCheckNo = InputUtils::LegacyFilterInput($_POST['CheckNo'], 'int');
    } else {
        $iCheckNo = 0;
    }

    if (array_key_exists('Schedule', $_POST)) {
        $iSchedule = InputUtils::LegacyFilterInput($_POST['Schedule']);
    } else {
        $iSchedule = 'Once';
    }
    $_SESSION['iDefaultSchedule'] = $iSchedule;

    $iMethod = InputUtils::LegacyFilterInput($_POST['Method']);
    if (!$iMethod) {
        if ($sGroupKey) {
            $sSQL = "SELECT DISTINCT plg_method FROM pledge_plg WHERE plg_GroupKey='".$sGroupKey."'";
            $rsResults = RunQuery($sSQL);
            list($iMethod) = mysqli_fetch_row($rsResults);
        } elseif ($iCurrentDeposit) {
            $sSQL = 'SELECT plg_method from pledge_plg where plg_depID="'.$iCurrentDeposit.'" ORDER by plg_plgID DESC LIMIT 1';
            $rsMethod = RunQuery($sSQL);
            $num = mysqli_num_rows($rsMethod);
            if ($num) {    // set iMethod to last record's setting
                extract(mysqli_fetch_array($rsMethod));
                $iMethod = $plg_method;
            } else {
                $iMethod = 'CHECK';
            }
        } else {
            $iMethod = 'CHECK';
        }
    }
    $_SESSION['idefaultPaymentMethod'] = $iMethod;

    $iEnvelope = 0;
    if (array_key_exists('Envelope', $_POST)) {
        $iEnvelope = InputUtils::LegacyFilterInput($_POST['Envelope'], 'int');
    }
} else { // Form was not up previously, take data from existing records or make default values
    if ($sGroupKey) {
        $sSQL = "SELECT COUNT(plg_GroupKey), plg_PledgeOrPayment, plg_fundID, plg_Date, plg_FYID, plg_CheckNo, plg_Schedule, plg_method, plg_depID FROM pledge_plg WHERE plg_GroupKey='".$sGroupKey."' GROUP BY plg_GroupKey";
        $rsResults = RunQuery($sSQL);
        list($numGroupKeys, $PledgeOrPayment, $fundId, $dDate, $iFYID, $iCheckNo, $iSchedule, $iMethod, $iCurrentDeposit) = mysqli_fetch_row($rsResults);


        $sSQL = "SELECT DISTINCT plg_famID, plg_CheckNo, plg_date, plg_method, plg_FYID from pledge_plg where plg_GroupKey='".$sGroupKey."'";
        //	don't know if we need plg_date or plg_method here...  leave it here for now
        $rsFam = RunQuery($sSQL);
        extract(mysqli_fetch_array($rsFam));

        $iFamily = $plg_famID;
        $iCheckNo = $plg_CheckNo;
        $iFYID = $plg_FYID;

        $sSQL = "SELECT plg_plgID, plg_fundID, plg_amount, plg_comment, plg_NonDeductible from pledge_plg where plg_GroupKey='".$sGroupKey."'";

        $rsAmounts = RunQuery($sSQL);
        while ($aRow = mysqli_fetch_array($rsAmounts)) {
            extract($aRow);
            $nAmount[$plg_fundID] = $plg_amount;
            $nNonDeductible[$plg_fundID] = $plg_NonDeductible;
            $sComment[$plg_fundID] = $plg_comment;
        }
    } else {
        if (array_key_exists('idefaultDate', $_SESSION)) {
            $dDate = $_SESSION['idefaultDate'];
        } else {
            $dDate = date('Y-m-d');
        }

        if (array_key_exists('idefaultFY', $_SESSION)) {
            $iFYID = $_SESSION['idefaultFY'];
        } else {
            $iFYID = CurrentFY();
        }
        if (array_key_exists('iDefaultSchedule', $_SESSION)) {
            $iSchedule = $_SESSION['iDefaultSchedule'];
        } else {
            $iSchedule = 'Once';
        }
        if (array_key_exists('idefaultPaymentMethod', $_SESSION)) {
            $iMethod = $_SESSION['idefaultPaymentMethod'];
        } else {
            $iMethod = 'Check';
        }
    }
    if (!$iEnvelope && $iFamily) {
        $sSQL = 'SELECT fam_Envelope FROM family_fam WHERE fam_ID="'.$iFamily.'";';
        $rsEnv = RunQuery($sSQL);
        extract(mysqli_fetch_array($rsEnv));
        if ($fam_Envelope) {
            $iEnvelope = $fam_Envelope;
        }
    }
}

if ($PledgeOrPayment == 'Pledge') { // Don't assign the deposit slip if this is a pledge
    $iCurrentDeposit = 0;
} else { // its a deposit
    if ($iCurrentDeposit > 0) {
        $_SESSION['iCurrentDeposit'] = $iCurrentDeposit;
    } else {
        $iCurrentDeposit = $_SESSION['iCurrentDeposit'];
    }

    // Get the current deposit slip data
    if ($iCurrentDeposit) {
        $sSQL = 'SELECT dep_Closed, dep_Date, dep_Type from deposit_dep WHERE dep_ID = '.$iCurrentDeposit;
        $rsDeposit = RunQuery($sSQL);
        extract(mysqli_fetch_array($rsDeposit));
    }
}

if ($iMethod == 'CASH' || $iMethod == 'CHECK') {
    $dep_Type = 'Bank';
} elseif ($iMethod == 'CREDITCARD') {
    $dep_Type = 'CreditCard';
} elseif ($iMethod == 'BANKDRAFT') {
    $dep_Type = 'BankDraft';
}

if ($PledgeOrPayment == 'Payment') {
    $bEnableNonDeductible = SystemConfig::getValue('bEnableNonDeductible'); // this could/should be a config parm?  regardless, having a non-deductible amount for a pledge doesn't seem possible
}

if (isset($_POST['PledgeSubmit']) || isset($_POST['PledgeSubmitAndAdd'])) {
    //Initialize the error flag
    $bErrorFlag = false;
    // make sure at least one fund has a non-zero numer
    $nonZeroFundAmountEntered = 0;
    foreach ($fundId2Name as $fun_id => $fun_name) {
        //$fun_active = $fundActive[$fun_id];
        $nAmount[$fun_id] = InputUtils::LegacyFilterInput($_POST[$fun_id.'_Amount']);
        $sComment[$fun_id] = InputUtils::LegacyFilterInput($_POST[$fun_id.'_Comment']);
        if ($nAmount[$fun_id] > 0) {
            ++$nonZeroFundAmountEntered;
        }

        if ($bEnableNonDeductible) {
            $nNonDeductible[$fun_id] = InputUtils::LegacyFilterInput($_POST[$fun_id.'_NonDeductible']);
            //Validate the NonDeductible Amount
            if ($nNonDeductible[$fun_id] > $nAmount[$fun_id]) { //Validate the NonDeductible Amount
                $sNonDeductibleError[$fun_id] = gettext("NonDeductible amount can't be greater than total amount.");
                $bErrorFlag = true;
            }
        }
    } // end foreach

    if (!$nonZeroFundAmountEntered) {
        $sAmountError[$fun_id] = gettext('At least one fund must have a non-zero amount.');
        $bErrorFlag = true;
    }


    if (array_key_exists('ScanInput', $_POST)) {
        $tScanString = InputUtils::LegacyFilterInput($_POST['ScanInput']);
    } else {
        $tScanString = '';
    }
    $iAutID = 0;
    if (array_key_exists('AutoPay', $_POST)) {
        $iAutID = InputUtils::LegacyFilterInput($_POST['AutoPay']);
    }
    //$iEnvelope = InputUtils::LegacyFilterInput($_POST["Envelope"], 'int');

    if ($PledgeOrPayment == 'Payment' && !$iCheckNo && $iMethod == 'CHECK') {
        $sCheckNoError = '<span style="color: red; ">'.gettext('Must specify non-zero check number').'</span>';
        $bErrorFlag = true;
    }

    // detect check inconsistencies
    if ($PledgeOrPayment == 'Payment' && $iCheckNo) {
        if ($iMethod == 'CASH') {
            $sCheckNoError = '<span style="color: red; ">'.gettext("Check number not valid for 'CASH' payment").'</span>';
            $bErrorFlag = true;
        } elseif ($iMethod == 'CHECK' && !$sGroupKey) {
            $chkKey = $iFamily.'|'.$iCheckNo;
            if (array_key_exists($chkKey, $checkHash)) {
                $text = "Check number '".$iCheckNo."' for selected family already exists.";
                $sCheckNoError = '<span style="color: red; ">'.gettext($text).'</span>';
                $bErrorFlag = true;
            }
        }
    }

    // Validate Date
    if (strlen($dDate) > 0) {
        list($iYear, $iMonth, $iDay) = sscanf($dDate, '%04d-%02d-%02d');
        if (!checkdate($iMonth, $iDay, $iYear)) {
            $sDateError = '<span style="color: red; ">'.gettext('Not a valid date').'</span>';
            $bErrorFlag = true;
        }
    }

    //If no errors, then let's update...
    if (!$bErrorFlag && !$dep_Closed) {
        // Only set PledgeOrPayment when the record is first created
        // loop through all funds and create non-zero amount pledge records
        foreach ($fundId2Name as $fun_id => $fun_name) {
            if (!$iCheckNo) {
                $iCheckNo = 0;
            }
            unset($sSQL);
            if ($fund2PlgIds && array_key_exists($fun_id, $fund2PlgIds)) {
                if ($nAmount[$fun_id] > 0) {
                    $sSQL = "UPDATE pledge_plg SET plg_famID = '".$iFamily."',plg_FYID = '".$iFYID."',plg_date = '".$dDate."', plg_amount = '".$nAmount[$fun_id]."', plg_schedule = '".$iSchedule."', plg_method = '".$iMethod."', plg_comment = '".$sComment[$fun_id]."'";
                    $sSQL .= ", plg_DateLastEdited = '".date('YmdHis')."', plg_EditedBy = ".AuthenticationManager::GetCurrentUser()->getId().", plg_CheckNo = '".$iCheckNo."', plg_scanString = '".$tScanString."', plg_aut_ID='".$iAutID."', plg_NonDeductible='".$nNonDeductible[$fun_id]."' WHERE plg_plgID='".$fund2PlgIds[$fun_id]."'";
                } else { // delete that record
                    $sSQL = 'DELETE FROM pledge_plg WHERE plg_plgID ='.$fund2PlgIds[$fun_id];
                }
            } elseif ($nAmount[$fun_id] > 0) {
                if ($iMethod != 'CHECK') {
                    $iCheckNo = 'NULL';
                }
                if (!$sGroupKey) {
                    if ($iMethod == 'CHECK') {
                        $sGroupKey = genGroupKey($iCheckNo, $iFamily, $fun_id, $dDate);
                    } elseif ($iMethod == 'BANKDRAFT') {
                        if (!$iAutID) {
                            $iAutID = 'draft';
                        }
                        $sGroupKey = genGroupKey($iAutID, $iFamily, $fun_id, $dDate);
                    } elseif ($iMethod == 'CREDITCARD') {
                        if (!$iAutID) {
                            $iAutID = 'credit';
                        }
                        $sGroupKey = genGroupKey($iAutID, $iFamily, $fun_id, $dDate);
                    } else {
                        $sGroupKey = genGroupKey('cash', $iFamily, $fun_id, $dDate);
                    }
                }
                $sSQL = "INSERT INTO pledge_plg (plg_famID, plg_FYID, plg_date, plg_amount, plg_schedule, plg_method, plg_comment, plg_DateLastEdited, plg_EditedBy, plg_PledgeOrPayment, plg_fundID, plg_depID, plg_CheckNo, plg_scanString, plg_aut_ID, plg_NonDeductible, plg_GroupKey)
			VALUES ('".$iFamily."','".$iFYID."','".$dDate."','".$nAmount[$fun_id]."','".$iSchedule."','".$iMethod."','".$sComment[$fun_id]."'";
                $sSQL .= ",'".date('YmdHis')."',".AuthenticationManager::GetCurrentUser()->getId().",'".$PledgeOrPayment."',".$fun_id.','.$iCurrentDeposit.','.$iCheckNo.",'".$tScanString."','".$iAutID."','".$nNonDeductible[$fun_id]."','".$sGroupKey."')";
            }
            if (isset($sSQL)) {
                RunQuery($sSQL);
                unset($sSQL);
            }
        } // end foreach of $fundId2Name
        if (isset($_POST['PledgeSubmit'])) {
            // Check for redirection to another page after saving information: (ie. PledgeEditor.php?previousPage=prev.php?a=1;b=2;c=3)
            if ($linkBack != '') {
                RedirectUtils::Redirect($linkBack);
            } else {
                //Send to the view of this pledge
                RedirectUtils::Redirect('PledgeEditor.php?PledgeOrPayment='.$PledgeOrPayment.'&GroupKey='.$sGroupKey.'&linkBack=', $linkBack);
            }
        } elseif (isset($_POST['PledgeSubmitAndAdd'])) {
            //Reload to editor to add another record
            RedirectUtils::Redirect("PledgeEditor.php?CurrentDeposit=$iCurrentDeposit&PledgeOrPayment=".$PledgeOrPayment.'&linkBack=', $linkBack);
        }
    } // end if !$bErrorFlag
} elseif (isset($_POST['MatchFamily']) || isset($_POST['MatchEnvelope']) || isset($_POST['SetDefaultCheck'])) {

    //$iCheckNo = 0;
    // Take care of match-family first- select the family based on the scanned check
    if (SystemConfig::getValue('bUseScannedChecks') && isset($_POST['MatchFamily'])) {
        $tScanString = InputUtils::LegacyFilterInput($_POST['ScanInput']);

        $routeAndAccount = $micrObj->FindRouteAndAccount($tScanString); // use routing and account number for matching

        if ($routeAndAccount) {
            $sSQL = 'SELECT fam_ID FROM family_fam WHERE fam_scanCheck="'.$routeAndAccount.'"';
            $rsFam = RunQuery($sSQL);
            extract(mysqli_fetch_array($rsFam));
            $iFamily = $fam_ID;

            $iCheckNo = $micrObj->FindCheckNo($tScanString);
        } else {
            $iFamily = InputUtils::LegacyFilterInput($_POST['FamilyID'], 'int');
            $iCheckNo = InputUtils::LegacyFilterInput($_POST['CheckNo'], 'int');
        }
    } elseif (isset($_POST['MatchEnvelope'])) {
        // Match envelope is similar to match check- use the envelope number to choose a family

        $iEnvelope = InputUtils::LegacyFilterInput($_POST['Envelope'], 'int');
        if ($iEnvelope && strlen($iEnvelope) > 0) {
            $sSQL = 'SELECT fam_ID FROM family_fam WHERE fam_Envelope='.$iEnvelope;
            $rsFam = RunQuery($sSQL);
            $numRows = mysqli_num_rows($rsFam);
            if ($numRows) {
                extract(mysqli_fetch_array($rsFam));
                $iFamily = $fam_ID;
            }
        }
    } else {
        $iFamily = InputUtils::LegacyFilterInput($_POST['FamilyID']);
        $iCheckNo = InputUtils::LegacyFilterInput($_POST['CheckNo'], 'int');
    }

    // Handle special buttons at the bottom of the form.
    if (isset($_POST['SetDefaultCheck'])) {
        $tScanString = InputUtils::LegacyFilterInput($_POST['ScanInput']);
        $routeAndAccount = $micrObj->FindRouteAndAccount($tScanString); // use routing and account number for matching
        $iFamily = InputUtils::LegacyFilterInput($_POST['FamilyID'], 'int');
        $sSQL = 'UPDATE family_fam SET fam_scanCheck="'.$routeAndAccount.'" WHERE fam_ID = '.$iFamily;
        RunQuery($sSQL);
    }
}

// Set Current Deposit setting for user
if ($iCurrentDeposit) {
    /* @var $currentUser \ChurchCRM\User */
    $currentUser = AuthenticationManager::GetCurrentUser();
    $currentUser->setCurrentDeposit($iCurrentDeposit);
    $currentUser->save();
}

//Set the page title
if ($PledgeOrPayment == 'Pledge') {
    $sPageTitle = gettext('Pledge Editor');
} elseif ($iCurrentDeposit) {
    $sPageTitle = gettext('Payment Editor: ').$dep_Type.gettext(' Deposit Slip #').$iCurrentDeposit." ($dep_Date)";

    $checksFit = SystemConfig::getValue('iChecksPerDepositForm');

    $sSQL = 'SELECT plg_FamID, plg_plgID, plg_checkNo, plg_method from pledge_plg where plg_method="CHECK" and plg_depID='.$iCurrentDeposit;
    $rsChecksThisDep = RunQuery($sSQL);
    $depositCount = 0;
    while ($aRow = mysqli_fetch_array($rsChecksThisDep)) {
        extract($aRow);
        $chkKey = $plg_FamID.'|'.$plg_checkNo;
        if ($plg_method == 'CHECK' && (!array_key_exists($chkKey, $checkHash))) {
            $checkHash[$chkKey] = $plg_plgID;
            ++$depositCount;
        }
    }

    //$checkCount = mysqli_num_rows ($rsChecksThisDep);
    $roomForDeposits = $checksFit - $depositCount;
    if ($roomForDeposits <= 0) {
        $sPageTitle .= '<font color=red>';
    }
    $sPageTitle .= ' ('.$roomForDeposits.gettext(' more entries will fit.').')';
    if ($roomForDeposits <= 0) {
        $sPageTitle .= '</font>';
    }
} else { // not a plege and a current deposit hasn't been created yet
    if ($sGroupKey) {
        $sPageTitle = gettext('Payment Editor - Modify Existing Payment');
    } else {
        $sPageTitle = gettext('Payment Editor - New Deposit Slip Will Be Created');
    }
} // end if $PledgeOrPayment

if ($dep_Closed && $sGroupKey && $PledgeOrPayment == 'Payment') {
    $sPageTitle .= ' &nbsp; <font color=red>'.gettext('Deposit closed').'</font>';
}

//$familySelectHtml = buildFamilySelect($iFamily, $sDirRoleHead, $sDirRoleSpouse);
$sFamilyName = '';
if ($iFamily) {
    $sSQL = 'SELECT fam_Name, fam_Address1, fam_City, fam_State FROM family_fam WHERE fam_ID ='.$iFamily;
    $rsFindFam = RunQuery($sSQL);
    while ($aRow = mysqli_fetch_array($rsFindFam)) {
        extract($aRow);
        $sFamilyName = $fam_Name.' '.FormatAddressLine($fam_Address1, $fam_City, $fam_State);
    }
}

require 'Include/Header.php';

?>

<form method="post" action="PledgeEditor.php?CurrentDeposit=<?= $iCurrentDeposit ?>&GroupKey=<?= $sGroupKey ?>&PledgeOrPayment=<?= $PledgeOrPayment ?>&linkBack=<?= $linkBack ?>" name="PledgeEditor">


<div class="row">
  <div class="col-lg-6">
    <div class="box">
      <div class="box-header with-border">
        <h3 class="box-title"><?= gettext("Payment Details") ?></h3>
      </div>
      <div class="box-body">
        <input type="hidden" name="FamilyID" id="FamilyID" value="<?= $iFamily ?>">
        <input type="hidden" name="PledgeOrPayment" id="PledgeOrPayment" value="<?= $PledgeOrPayment ?>">

        <div class="col-lg-12">
          <label for="FamilyName"><?= gettext('Family') ?></label>
          <select class="form-control"   id="FamilyName" name="FamilyName" >
            <option selected ><?= $sFamilyName ?></option>
          </select>
        </div>

        <div class="col-lg-6">
          <?php	if (!$dDate) {
    $dDate = $dep_Date;
} ?>
          <label for="Date"><?= gettext('Date') ?></label>
          <input class="form-control" data-provide="datepicker" data-date-format='yyyy-mm-dd' type="text" name="Date" value="<?= $dDate ?>" ><font color="red"><?= $sDateError ?></font>
          <label for="FYID"><?= gettext('Fiscal Year') ?></label>
           <?php PrintFYIDSelect($iFYID, 'FYID') ?>

          <?php if ($dep_Type == 'Bank' && SystemConfig::getValue('bUseDonationEnvelopes')) {
    ?>
          <label for="Envelope"><?= gettext('Envelope Number') ?></label>
          <input  class="form-control" type="number" name="Envelope" size=8 id="Envelope" value="<?= $iEnvelope ?>">
          <?php if (!$dep_Closed) {
        ?>
            <input class="form-control" type="submit" class="btn btn-default" value="<?= gettext('Find family->') ?>" name="MatchEnvelope">
          <?php
    } ?>

        <?php
} ?>

            <?php if ($PledgeOrPayment == 'Pledge') {
        ?>

        <label for="Schedule"><?= gettext('Payment Schedule') ?></label>
          <select name="Schedule" class="form-control">
              <option value="0"><?= gettext('Select Schedule') ?></option>
              <option value="Weekly" <?php if ($iSchedule == 'Weekly') {
            echo 'selected';
        } ?>><?= gettext('Weekly') ?>
              </option>
              <option value="Monthly" <?php if ($iSchedule == 'Monthly') {
            echo 'selected';
        } ?>><?= gettext('Monthly') ?>
              </option>
              <option value="Quarterly" <?php if ($iSchedule == 'Quarterly') {
            echo 'selected';
        } ?>><?= gettext('Quarterly') ?>
              </option>
              <option value="Once" <?php if ($iSchedule == 'Once') {
            echo 'selected';
        } ?>><?= gettext('Once') ?>
              </option>
              <option value="Other" <?php if ($iSchedule == 'Other') {
            echo 'selected';
        } ?>><?= gettext('Other') ?>
              </option>
          </select>

          <?php
    } ?>

      </div>


      <div class="col-lg-6">
        <label for="Method"><?= gettext('Payment by') ?></label>
        <select class="form-control" name="Method" id="Method">
          <?php if ($PledgeOrPayment == 'Pledge' || $dep_Type == 'Bank' || !$iCurrentDeposit) {
        ?>
            <option value="CHECK" <?php if ($iMethod == 'CHECK') {
            echo 'selected';
        } ?>><?= gettext('Check'); ?>
            </option>
            <option value="CASH" <?php if ($iMethod == 'CASH') {
            echo 'selected';
        } ?>><?= gettext('Cash'); ?>
            </option>
              <?php
    } ?>
          <?php if ($PledgeOrPayment == 'Pledge' || $dep_Type == 'CreditCard' || !$iCurrentDeposit) {
        ?>
            <option value="CREDITCARD" <?php if ($iMethod == 'CREDITCARD') {
            echo 'selected';
        } ?>><?= gettext('Credit Card') ?>
            </option>
          <?php
    } ?>
          <?php if ($PledgeOrPayment == 'Pledge' || $dep_Type == 'BankDraft' || !$iCurrentDeposit) {
        ?>
            <option value="BANKDRAFT" <?php if ($iMethod == 'BANKDRAFT') {
            echo 'selected';
        } ?>><?= gettext('Bank Draft') ?>
            </option>
          <?php
    } ?>
          <?php if ($PledgeOrPayment == 'Pledge') {
        ?>
            <option value="EGIVE" <?= $iMethod == 'EGIVE' ? 'selected' : '' ?>>
             <?=gettext('eGive') ?>
            </option>
          <?php
    } ?>
        </select>



        <?php if ($PledgeOrPayment == 'Payment' && $dep_Type == 'Bank') {
        ?>
          <div id="checkNumberGroup">
          <label for="CheckNo"><?= gettext('Check') ?> #</label>
          <input class="form-control" type="number" name="CheckNo" id="CheckNo" value="<?= $iCheckNo ?>"/><font color="red"><?= $sCheckNoError ?></font>
          </div>
        <?php
    } ?>


        <label for="TotalAmount"><?= gettext('Total $') ?></label>
        <input class="form-control"  type="number" step="any" name="TotalAmount" id="TotalAmount" disabled />

    </div>

    <div class="col-lg-6">
       <?php if (SystemConfig::getValue('bUseScannedChecks') && ($dep_Type == 'Bank' || $PledgeOrPayment == 'Pledge')) {
        ?>
          <td align="center" class="<?= $PledgeOrPayment == 'Pledge' ? 'LabelColumn' : 'PaymentLabelColumn' ?>"><?= gettext('Scan check') ?>
          <textarea name="ScanInput" rows="2" cols="70"><?= $tScanString ?></textarea></td>
        <?php
    } ?>
    </div>

    <div class="col-lg-6">
      <?php if (SystemConfig::getValue('bUseScannedChecks') && $dep_Type == 'Bank') {
        ?>
        <input type="submit" class="btn btn-default" value="<?= gettext('find family from check account #') ?>" name="MatchFamily">
        <input type="submit" class="btn btn-default" value="<?= gettext('Set default check account number for family') ?>" name="SetDefaultCheck">
      <?php
    } ?>
    </div>

    <div class="col-lg-12">
    <?php if (!$dep_Closed) {
        ?>
        <input type="submit" class="btn " value="<?= gettext('Save') ?>" name="PledgeSubmit">
        <?php if (AuthenticationManager::GetCurrentUser()->isAddRecordsEnabled()) {
            echo '<input type="submit" class="btn btn-primary value="'.gettext('Save and Add').'" name="PledgeSubmitAndAdd">';
        } ?>
          <?php
    } ?>
    <?php if (!$dep_Closed) {
        $cancelText = 'Cancel';
    } else {
        $cancelText = 'Return';
    } ?>
    <input type="button" class="btn btn-danger" value="<?= gettext($cancelText) ?>" name="PledgeCancel" onclick="javascript:document.location='<?= $linkBack ? $linkBack : 'Menu.php' ?>';">
    </div>
  </div>
</div>
  </div>

  <div class="col-lg-6">
    <div class="box">
      <div class="box-header with-border">
        <h3 class="box-title"><?= gettext("Fund Split") ?></h3>
      </div>
        <div class="box-body">
          <table class="table">
            <thead>
              <tr>
                <th class="<?= $PledgeOrPayment == 'Pledge' ? 'LabelColumn' : 'PaymentLabelColumn' ?>"><?= gettext('Fund Name') ?></th>
                <th class="<?= $PledgeOrPayment == 'Pledge' ? 'LabelColumn' : 'PaymentLabelColumn' ?>"><?= gettext('Amount') ?></th>

                <?php if ($bEnableNonDeductible) {
        ?>
                  <th class="<?= $PledgeOrPayment == 'Pledge' ? 'LabelColumn' : 'PaymentLabelColumn' ?>"><?= gettext('Non-deductible amount') ?></th>
                <?php
    } ?>

                <th class="<?= $PledgeOrPayment == 'Pledge' ? 'LabelColumn' : 'PaymentLabelColumn' ?>"><?= gettext('Comment') ?></th>
             </tr>
            </thead>
            <tbody>
              <?php
              foreach ($fundId2Name as $fun_id => $fun_name) {
                  ?>
                <tr>
                  <td class="TextColumn"><?= $fun_name ?></td>
                  <td class="TextColumn">
                    <input class="FundAmount" type="number" step="any" name="<?= $fun_id ?>_Amount" id="<?= $fun_id ?>_Amount" value="<?= ($nAmount[$fun_id] ? $nAmount[$fun_id] : "") ?>"><br>
                    <font color="red"><?= $sAmountError[$fun_id] ?></font>
                  </td>
                  <?php
                    if ($bEnableNonDeductible) {
                        ?>
                      <td class="TextColumn">
                        <input type="number" step="any" name="<?= $fun_id ?>_NonDeductible" id="<?= $fun_id ?>_NonDeductible" value="<?= ($nNonDeductible[$fun_id] ? $nNonDeductible[$fun_id] : "")?>" />
                        <br>
                        <font color="red"><?= $sNonDeductibleError[$fun_id]?></font>
                      </td>
                    <?php
                    } ?>
                  <td class="TextColumn">
                    <input  type="text" size=40 name="<?= $fun_id ?>_Comment" id="<?= $fun_id ?>_Comment" value="<?= $sComment[$fun_id] ?>">
                  </td>
                </tr>
              <?php
              } ?>
            </tbody>
          </table>
        </div>
    </div>
  </div>
</div>


</form>

 <script nonce="<?= SystemURLs::getCSPNonce() ?>" >
  $(document).ready(function() {

    $("#FamilyName").select2({
      minimumInputLength: 2,
      ajax: {
          url: function (params){
            var a = window.CRM.root + '/api/families/search/'+ params.term;
            return a;
          },
          dataType: 'json',
          delay: 250,
          data: "",
          processResults: function (data, params) {
            var results = [];
            var families = JSON.parse(data).Families
            $.each(families, function(key, object) {
              results.push({
                id: object.Id,
                text: object.displayName
              });
            });
            return {
              results: results
            };
          }
        }
    });

    $("#FamilyName").on("select2:select", function (e) {
      $('[name=FamilyID]').val(e.params.data.id);
    });

    $(".FundAmount").change(function(){
      CalculateTotal();
    });

    $("#Method").change(function() {
      EvalCheckNumberGroup();
    });

    EvalCheckNumberGroup();
    CalculateTotal();
  });

  function EvalCheckNumberGroup()
  {
    if ($("#Method option:selected").val()==="CHECK") {
      $("#checkNumberGroup").show();
    }
    else
    {
      $("#checkNumberGroup").hide();
      $("#CheckNo").val('');
    }
  }
  function CalculateTotal() {
    var Total = 0;
      $(".FundAmount").each(function(object){
        var FundAmount = Number($(this).val());
        if (FundAmount >0 )
        {
          Total += FundAmount;
        }
      });
      $("#TotalAmount").val(Total);
  }
</script>


<?php require 'Include/Footer.php' ?>
