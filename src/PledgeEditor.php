<?php

require_once 'Include/Config.php';
require_once 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\MICRFunctions;
use ChurchCRM\model\ChurchCRM\Deposit;
use ChurchCRM\model\ChurchCRM\DepositQuery;
use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\Pledge;
use ChurchCRM\model\ChurchCRM\PledgeQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

if (SystemConfig::getValue('bUseScannedChecks')) { // Instantiate the MICR class
    $micrObj = new MICRFunctions();
}

$iEnvelope = 0;
$sCheckNoError = '';
$iCheckNo = '';
$sDateError = '';
$sAmountError = '';
$sNonDeductibleError = [];
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
$sSQL = 'SELECT fun_ID,fun_Name,fun_Active FROM donationfund_fun';
$sSQL .= " WHERE fun_Active = 'true'"; // New donations should show only active funds.

$rsFunds = RunQuery($sSQL);
mysqli_data_seek($rsFunds, 0);
while ($aRow = mysqli_fetch_array($rsFunds)) {
    $fun_ID = $aRow['fun_ID'];
    $fundId2Name[$fun_ID] = $aRow['fun_Name'];
    $nAmount[$fun_ID] = 0.0;
    $nNonDeductible[$fun_ID] = 0.0;
    $sAmountError[$fun_ID] = '';
    $sNonDeductibleError[$fun_ID] = '';
    $sComment[$fun_ID] = '';
    if (!isset($defaultFundID)) {
        $defaultFundID = $fun_ID;
    }
    $fundIdActive[$fun_ID] = $aRow['fun_Active'];
} // end while

// Handle URL via _GET first
if (array_key_exists('PledgeOrPayment', $_GET)) {
    $PledgeOrPayment = InputUtils::legacyFilterInput($_GET['PledgeOrPayment'], 'string');
}
$sGroupKey = '';
if (array_key_exists('GroupKey', $_GET)) {
    $sGroupKey = InputUtils::legacyFilterInput($_GET['GroupKey'], 'string');
} // this will only be set if someone pressed the 'edit' button on the Pledge or Deposit line
if (array_key_exists('CurrentDeposit', $_GET)) {
    $iCurrentDeposit = InputUtils::legacyFilterInput($_GET['CurrentDeposit'], 'int');
}
$linkBack = InputUtils::legacyFilterInput($_GET['linkBack'], 'string');
$iFamily = 0;
if (array_key_exists('FamilyID', $_GET)) {
    $iFamily = InputUtils::legacyFilterInput($_GET['FamilyID'], 'int');
}

$fund2PlgIds = []; // this will be the array cross-referencing funds to existing plg_plgid's

if ($sGroupKey) {
    $sSQL = 'SELECT plg_plgID, plg_fundID, plg_EditedBy from pledge_plg where plg_GroupKey="' . $sGroupKey . '"';
    $rsKeys = RunQuery($sSQL);
    while ($aRow = mysqli_fetch_array($rsKeys)) {
        $onePlgID = $aRow['plg_plgID'];
        $oneFundID = $aRow['plg_fundID'];
        $iOriginalSelectedFund = $oneFundID; // remember the original fund in case we switch to splitting
        $fund2PlgIds[$oneFundID] = $onePlgID;

        // Security: User must have Finance permission or be the one who entered this record originally
        if (!(AuthenticationManager::getCurrentUser()->isFinanceEnabled() || AuthenticationManager::getCurrentUser()->getId() == $aRow['plg_EditedBy'])) {
            RedirectUtils::redirect('v2/dashboard');
        }
    }
}

// Handle _POST input if the form was up and a button press came in
if (
    isset($_POST['PledgeSubmit']) ||
    isset($_POST['PledgeSubmitAndAdd']) ||
    isset($_POST['MatchFamily']) ||
    isset($_POST['MatchEnvelope']) ||
    isset($_POST['SetDefaultCheck']) ||
    isset($_POST['SetFundTypeSelection'])
) {
    $iFamily = InputUtils::legacyFilterInput($_POST['FamilyID'], 'int');

    $dDate = InputUtils::legacyFilterInput($_POST['Date']);
    if (!$dDate) {
        if (array_key_exists('idefaultDate', $_SESSION)) {
            $dDate = $_SESSION['idefaultDate'];
        } else {
            $dDate = date('Y-m-d');
        }
    }
    $_SESSION['idefaultDate'] = $dDate;

    // set from drop-down if set, saved session default, or by calculation
    $iFYID = InputUtils::legacyFilterInput($_POST['FYID'], 'int');
    if (!$iFYID) {
        $iFYID = $_SESSION['idefaultFY'];
    }
    if (!$iFYID) {
        $iFYID = CurrentFY();
    }
    $_SESSION['idefaultFY'] = $iFYID;

    if (array_key_exists('CheckNo', $_POST)) {
        $iCheckNo = InputUtils::legacyFilterInput($_POST['CheckNo'], 'int');
    } else {
        $iCheckNo = 0;
    }

    if (array_key_exists('Schedule', $_POST)) {
        $iSchedule = InputUtils::legacyFilterInput($_POST['Schedule']);
    } else {
        $iSchedule = 'Once';
    }
    $_SESSION['iDefaultSchedule'] = $iSchedule;

    $iMethod = InputUtils::legacyFilterInput($_POST['Method']);
    if (!$iMethod) {
        if ($sGroupKey) {
            $sSQL = "SELECT DISTINCT plg_method FROM pledge_plg WHERE plg_GroupKey='" . $sGroupKey . "'";
            $rsResults = RunQuery($sSQL);
            list($iMethod) = mysqli_fetch_row($rsResults);
        } elseif ($iCurrentDeposit) {
            $sSQL = 'SELECT plg_method from pledge_plg where plg_depID="' . $iCurrentDeposit . '" ORDER by plg_plgID DESC LIMIT 1';
            $rsMethod = RunQuery($sSQL);
            $num = mysqli_num_rows($rsMethod);
            if ($num) {    // set iMethod to last record's setting
                $previousPledgeMethodArray = mysqli_fetch_array($rsMethod);
                $iMethod = $previousPledgeMethodArray['plg_method'];
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
        $iEnvelope = InputUtils::legacyFilterInput($_POST['Envelope'], 'int');
    }
} else { // Form was not up previously, take data from existing records or make default values
    if ($sGroupKey) {
        $sSQL = "SELECT COUNT(plg_GroupKey), plg_PledgeOrPayment, plg_fundID, plg_Date, plg_FYID, plg_CheckNo, plg_Schedule, plg_method, plg_depID FROM pledge_plg WHERE plg_GroupKey='" . $sGroupKey . "' GROUP BY plg_GroupKey";
        $rsResults = RunQuery($sSQL);
        list($numGroupKeys, $PledgeOrPayment, $fundId, $dDate, $iFYID, $iCheckNo, $iSchedule, $iMethod, $iCurrentDeposit) = mysqli_fetch_row($rsResults);

        $sSQL = "SELECT DISTINCT plg_famID, plg_CheckNo, plg_FYID from pledge_plg where plg_GroupKey='" . $sGroupKey . "'";
        $rsFam = RunQuery($sSQL);
        $fam_NameArray = mysqli_fetch_array($rsFam);
        $iFamily = $fam_NameArray['plg_famID'];
        $iCheckNo = $fam_NameArray['plg_CheckNo'];
        $iFYID = $fam_NameArray['plg_FYID'];

        $sSQL = "SELECT plg_fundID, plg_amount, plg_comment, plg_NonDeductible from pledge_plg where plg_GroupKey='" . $sGroupKey . "'";
        $rsAmounts = RunQuery($sSQL);
        while ($aRow = mysqli_fetch_array($rsAmounts)) {
            $plg_fundID = $aRow['plg_fundID'];
            $nAmount[$plg_fundID] = $aRow['plg_amount'];
            $nNonDeductible[$plg_fundID] = $aRow['plg_NonDeductible'];
            $sComment[$plg_fundID] = $aRow['plg_comment'];
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
        $family = FamilyQuery::create()->findPk((int)$iFamily);
        if ($family !== null && $family->getEnvelope()) {
            $iEnvelope = $family->getEnvelope();
        }
    }
}

if ($PledgeOrPayment === 'Pledge') { // Don't assign the deposit slip if this is a pledge
    $iCurrentDeposit = 0;
} else { // its a deposit
    if ($iCurrentDeposit > 0) {
        $_SESSION['iCurrentDeposit'] = $iCurrentDeposit;
    } else {
        $iCurrentDeposit = $_SESSION['iCurrentDeposit'];
    }

    // Get the current deposit slip data
    if ($iCurrentDeposit) {
        $deposit = DepositQuery::create()->findPk((int)$iCurrentDeposit);
        if ($deposit !== null) {
            $dep_Closed = $deposit->getClosed();
            $dep_Date = $deposit->getDate();
            $dep_Type = $deposit->getType();
        }
    }
}

if ($iMethod === 'CASH' || $iMethod === 'CHECK') {
    $dep_Type = 'Bank';
} elseif ($iMethod === 'CREDITCARD') {
    $dep_Type = 'CreditCard';
} elseif ($iMethod === 'BANKDRAFT') {
    $dep_Type = 'BankDraft';
}

if ($PledgeOrPayment === 'Payment') {
    $bEnableNonDeductible = SystemConfig::getValue('bEnableNonDeductible'); // this could/should be a config param?  regardless, having a non-deductible amount for a pledge doesn't seem possible
}

if (isset($_POST['PledgeSubmit']) || isset($_POST['PledgeSubmitAndAdd'])) {
    //Initialize the error flag
    $bErrorFlag = false;
    // make sure at least one fund has a non-zero number
    $nonZeroFundAmountEntered = 0;
    foreach ($fundId2Name as $fun_id => $fun_name) {
        //$fun_active = $fundActive[$fun_id];
        $rawAmount = InputUtils::legacyFilterInput($_POST[$fun_id . '_Amount']);
        $nAmount[$fun_id] = (float)$rawAmount;
        
        // Validate amount is within DECIMAL(8,2) range: -999999.99 to 999999.99
        if (abs($nAmount[$fun_id]) > 999999.99) {
            $sAmountError[$fun_id] = gettext("Amount exceeds maximum allowed value (999999.99).");
            $bErrorFlag = true;
            $nAmount[$fun_id] = 0.0; // Reset to 0 to prevent database error
        }
        
        $sComment[$fun_id] = InputUtils::legacyFilterInput($_POST[$fun_id . '_Comment']);
        if ($nAmount[$fun_id] > 0) {
            ++$nonZeroFundAmountEntered;
        }

        if ($bEnableNonDeductible) {
            $rawNonDeductible = InputUtils::legacyFilterInput($_POST[$fun_id . '_NonDeductible']);
            $nNonDeductible[$fun_id] = (float)$rawNonDeductible;
            
            // Validate non-deductible is within DECIMAL(8,2) range
            if (abs($nNonDeductible[$fun_id]) > 999999.99) {
                $sNonDeductibleError[$fun_id] = gettext("NonDeductible amount exceeds maximum allowed value (999999.99).");
                $bErrorFlag = true;
                $nNonDeductible[$fun_id] = 0.0;
            }
            
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
        $tScanString = InputUtils::legacyFilterInput($_POST['ScanInput']);
    } else {
        $tScanString = '';
    }
    $iAutID = 0;
    if (array_key_exists('AutoPay', $_POST)) {
        $iAutID = InputUtils::legacyFilterInput($_POST['AutoPay']);
    }
    //$iEnvelope = InputUtils::legacyFilterInput($_POST["Envelope"], 'int');

    if ($PledgeOrPayment === 'Payment' && !$iCheckNo && $iMethod === 'CHECK') {
        $sCheckNoError = '<span class="text-danger">' . gettext('Must specify non-zero check number') . '</span>';
        $bErrorFlag = true;
    }

    // detect check inconsistencies
    if ($PledgeOrPayment === 'Payment' && $iCheckNo) {
        if ($iMethod === 'CASH') {
            $sCheckNoError = '<span class="text-danger">' . gettext("Check number not valid for 'CASH' payment") . '</span>';
            $bErrorFlag = true;
        } elseif ($iMethod === 'CHECK' && !$sGroupKey) {
            $chkKey = $iFamily . '|' . $iCheckNo;
            if (array_key_exists($chkKey, $checkHash)) {
                $text = "Check number '" . $iCheckNo . "' for selected family already exists.";
                $sCheckNoError = '<span class="text-danger">' . gettext($text) . '</span>';
                $bErrorFlag = true;
            }
        }
    }

    // Validate Date
    if (strlen($dDate) > 0) {
        list($iYear, $iMonth, $iDay) = sscanf($dDate, '%04d-%02d-%02d');
        if (!checkdate($iMonth, $iDay, $iYear)) {
            $sDateError = '<span class="text-danger">' . gettext('Not a valid date') . '</span>';
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
                    $sSQL = "UPDATE pledge_plg SET plg_famID = '" . $iFamily . "',plg_FYID = '" . $iFYID . "',plg_date = '" . $dDate . "', plg_amount = '" . $nAmount[$fun_id] . "', plg_schedule = '" . $iSchedule . "', plg_method = '" . $iMethod . "', plg_comment = '" . $sComment[$fun_id] . "'";
                    $sSQL .= ", plg_DateLastEdited = '" . date('YmdHis') . "', plg_EditedBy = " . AuthenticationManager::getCurrentUser()->getId() . ", plg_CheckNo = '" . $iCheckNo . "', plg_scanString = '" . $tScanString . "', plg_aut_ID='" . $iAutID . "', plg_NonDeductible='" . $nNonDeductible[$fun_id] . "' WHERE plg_plgID='" . $fund2PlgIds[$fun_id] . "'";
                } else { // delete that record
                    $sSQL = 'DELETE FROM pledge_plg WHERE plg_plgID =' . $fund2PlgIds[$fun_id];
                }
            } elseif ($nAmount[$fun_id] > 0) {
                if ($iMethod != 'CHECK') {
                    $iCheckNo = 'NULL';
                }
                if (!$sGroupKey) {
                    if ($iMethod === 'CHECK') {
                        $sGroupKey = genGroupKey($iCheckNo, $iFamily, $fun_id, $dDate);
                    } elseif ($iMethod === 'BANKDRAFT') {
                        if (!$iAutID) {
                            $iAutID = 'draft';
                        }
                        $sGroupKey = genGroupKey($iAutID, $iFamily, $fun_id, $dDate);
                    } elseif ($iMethod === 'CREDITCARD') {
                        if (!$iAutID) {
                            $iAutID = 'credit';
                        }
                        $sGroupKey = genGroupKey($iAutID, $iFamily, $fun_id, $dDate);
                    } else {
                        $sGroupKey = genGroupKey('cash', $iFamily, $fun_id, $dDate);
                    }
                }
                $pledge = new Pledge();
                $pledge
                    ->setFamId($iFamily)
                    ->setFyId($iFYID)
                    ->setDate($dDate)
                    ->setAmount($nAmount[$fun_id])
                    ->setSchedule($iSchedule)
                    ->setMethod($iMethod)
                    ->setComment($sComment[$fun_id])
                    ->setDateLastEdited(date('YmdHis'))
                    ->setEditedBy(AuthenticationManager::getCurrentUser()->getId())
                    ->setPledgeOrPayment($PledgeOrPayment)
                    ->setFundId($fun_id)
                    ->setDepId($iCurrentDeposit)
                    ->setCheckNo($iCheckNo)
                    ->setScanString($tScanString)
                    ->setAutId($iAutID)
                    ->setNondeductible($nNonDeductible[$fun_id])
                    ->setGroupKey($sGroupKey);
                $pledge->save();
            }
            if (isset($sSQL)) {
                RunQuery($sSQL);
                unset($sSQL);
            }
        } // end foreach of $fundId2Name
        if (isset($_POST['PledgeSubmit'])) {
            // Check for redirection to another page after saving information: (ie. PledgeEditor.php?previousPage=prev.php?a=1;b=2;c=3)
            if ($linkBack != '') {
                RedirectUtils::redirect($linkBack);
            } else {
                //Send to the view of this pledge
                RedirectUtils::redirect('PledgeEditor.php?PledgeOrPayment=' . $PledgeOrPayment . '&GroupKey=' . $sGroupKey . '&linkBack=' . $linkBack);
            }
        } elseif (isset($_POST['PledgeSubmitAndAdd'])) {
            //Reload to editor to add another record
            RedirectUtils::redirect("PledgeEditor.php?CurrentDeposit=$iCurrentDeposit&PledgeOrPayment=" . $PledgeOrPayment . '&linkBack=' . $linkBack);
        }
    } // end if !$bErrorFlag
} elseif (isset($_POST['MatchFamily']) || isset($_POST['MatchEnvelope']) || isset($_POST['SetDefaultCheck'])) {
    //$iCheckNo = 0;
    // Take care of match-family first- select the family based on the scanned check
    if (SystemConfig::getValue('bUseScannedChecks') && isset($_POST['MatchFamily'])) {
        $tScanString = InputUtils::legacyFilterInput($_POST['ScanInput']);

        $routeAndAccount = $micrObj->findRouteAndAccount($tScanString); // use routing and account number for matching

        if ($routeAndAccount) {
            $family = FamilyQuery::create()->filterByScanCheck($routeAndAccount)->findOne();
            if ($family !== null) {
                $iFamily = $family->getId();
            }

            $iCheckNo = $micrObj->findCheckNo($tScanString);
        } else {
            $iFamily = InputUtils::legacyFilterInput($_POST['FamilyID'], 'int');
            $iCheckNo = InputUtils::legacyFilterInput($_POST['CheckNo'], 'int');
        }
    } elseif (isset($_POST['MatchEnvelope'])) {
        // Match envelope is similar to match check- use the envelope number to choose a family

        $iEnvelope = InputUtils::legacyFilterInput($_POST['Envelope'], 'int');
        if ($iEnvelope && strlen($iEnvelope) > 0) {
            $family = FamilyQuery::create()->filterByEnvelope((int)$iEnvelope)->findOne();
            if ($family !== null) {
                $iFamily = $family->getId();
            }
        }
    } else {
        $iFamily = InputUtils::legacyFilterInput($_POST['FamilyID']);
        $iCheckNo = InputUtils::legacyFilterInput($_POST['CheckNo'], 'int');
    }

    // Handle special buttons at the bottom of the form.
    if (isset($_POST['SetDefaultCheck'])) {
        $tScanString = InputUtils::legacyFilterInput($_POST['ScanInput']);
        $routeAndAccount = $micrObj->findRouteAndAccount($tScanString); // use routing and account number for matching
        $iFamily = InputUtils::legacyFilterInput($_POST['FamilyID'], 'int');
        $family = \ChurchCRM\model\ChurchCRM\FamilyQuery::create()->findOneById($iFamily);
        $family->setScanCheck($routeAndAccount);
        $family->save();
    }
}

// Set Current Deposit setting for user
if ($iCurrentDeposit) {
    $currentUser = AuthenticationManager::getCurrentUser();
    $currentUser->setCurrentDeposit($iCurrentDeposit);
    $currentUser->save();
}

if ($PledgeOrPayment === 'Pledge') {
    $sPageTitle = '<i class="fa-solid fa-file-signature text-warning mr-2"></i>' . gettext('New Pledge');
    $cardHeaderClass = 'bg-warning';
    $cardHeaderTextClass = 'text-dark';
    $formTypeLabel = gettext('Pledge');
} elseif ($iCurrentDeposit) {
    $dep_DateFormatted = ($dep_Date instanceof \DateTime) ? $dep_Date->format('Y-m-d') : $dep_Date;
    $sPageTitle = '<i class="fa-solid fa-hand-holding-dollar text-primary mr-2"></i>' . gettext('New Payment') . ' - ' . $dep_Type . gettext(' Deposit #') . $iCurrentDeposit . " ($dep_DateFormatted)";
    $cardHeaderClass = 'bg-primary';
    $cardHeaderTextClass = 'text-white';
    $formTypeLabel = gettext('Payment');

    $checksFit = SystemConfig::getValue('iChecksPerDepositForm');

    $sSQL = 'SELECT plg_FamID, plg_plgID, plg_checkNo, plg_method from pledge_plg where plg_method="CHECK" and plg_depID=' . $iCurrentDeposit;
    $rsChecksThisDep = RunQuery($sSQL);
    $depositCount = 0;
    while ($aRow = mysqli_fetch_array($rsChecksThisDep)) {
        $chkKey = $aRow['plg_FamID'] . '|' . $aRow['plg_checkNo'];
        if ($aRow['plg_method'] === 'CHECK' && (!array_key_exists($chkKey, $checkHash))) {
            $checkHash[$chkKey] = $aRow['plg_plgID'];
            ++$depositCount;
        }
    }

    //$checkCount = mysqli_num_rows ($rsChecksThisDep);
    $roomForDeposits = $checksFit - $depositCount;
    if ($roomForDeposits <= 0) {
        $sPageTitle .= '<span class="text-danger">';
    }
    $sPageTitle .= ' (' . $roomForDeposits . gettext(' more entries will fit.') . ')';
    if ($roomForDeposits <= 0) {
        $sPageTitle .= '</span>';
    }
} else { // not a pledge and a current deposit hasn't been created yet
    if ($sGroupKey) {
        $sPageTitle = '<i class="fa-solid fa-pen-to-square text-info mr-2"></i>' . gettext('Edit Payment');
    } else {
        $sPageTitle = '<i class="fa-solid fa-hand-holding-dollar text-primary mr-2"></i>' . gettext('New Payment') . ' - ' . gettext('New Deposit Will Be Created');
    }
    $cardHeaderClass = 'bg-primary';
    $cardHeaderTextClass = 'text-white';
    $formTypeLabel = gettext('Payment');
} // end if $PledgeOrPayment

if ($dep_Closed && $sGroupKey && $PledgeOrPayment === 'Payment') {
    $sPageTitle .= ' &nbsp; <span class="text-danger">' . gettext('Deposit closed') . '</span>';
}

//$familySelectHtml = buildFamilySelect($iFamily, $sDirRoleHead, $sDirRoleSpouse);
$sFamilyName = '';
if ($iFamily) {
    $family = FamilyQuery::create()->findPk((int)$iFamily);
    if ($family !== null) {
        $sFamilyName = $family->getName() . ' ' . FormatAddressLine($family->getAddress1(), $family->getCity(), $family->getState());
    }
}

require_once 'Include/Header.php';

?>

<form method="post" action="PledgeEditor.php?CurrentDeposit=<?= $iCurrentDeposit ?>&GroupKey=<?= $sGroupKey ?>&PledgeOrPayment=<?= $PledgeOrPayment ?>&linkBack=<?= $linkBack ?>" name="PledgeEditor">

    <!-- Mode Indicator Banner -->
    <div class="row mb-3">
        <div class="col-lg-12">
            <div class="alert <?= $PledgeOrPayment === 'Pledge' ? 'alert-warning' : 'alert-primary' ?> d-flex align-items-center" role="alert">
                <i class="fa-solid <?= $PledgeOrPayment === 'Pledge' ? 'fa-file-signature' : 'fa-hand-holding-dollar' ?> fa-2x mr-3"></i>
                <div>
                    <strong><?= $PledgeOrPayment === 'Pledge' ? gettext('Recording a Pledge') : gettext('Recording a Payment') ?></strong>
                    <p class="mb-0 small">
                        <?= $PledgeOrPayment === 'Pledge' 
                            ? gettext('A pledge is a commitment to give. It is not tied to a deposit slip.') 
                            : gettext('A payment is an actual donation received. It will be added to the current deposit.') ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header <?= $cardHeaderClass ?> <?= $cardHeaderTextClass ?> with-border">
                    <h3 class="card-title"><?= $formTypeLabel ?> <?= gettext('Details') ?></h3>
                </div>
                <div class="card-body">
                    <input type="hidden" name="FamilyID" id="FamilyID" value="<?= $iFamily ?>">
                    <input type="hidden" name="PledgeOrPayment" id="PledgeOrPayment" value="<?= $PledgeOrPayment ?>">

                    <div class="col-lg-12">
                        <label for="FamilyName"><?= gettext('Family') ?></label>
                        <select class="form-control" id="FamilyName" name="FamilyName">
                            <option selected><?= $sFamilyName ?></option>
                        </select>
                    </div>

                    <div class="col-lg-6">
                        <?php if (!$dDate) {
                            $dDate = ($dep_Date instanceof \DateTime) ? $dep_Date->format('Y-m-d') : $dep_Date;
                        } ?>
                        <label for="Date"><?= gettext('Date') ?></label>
                        <input class="form-control" data-provide="datepicker" data-date-format='yyyy-mm-dd' type="text" name="Date" value="<?= $dDate ?>"><span class="text-danger"><?= $sDateError ?></span>
                        <label for="FYID"><?= gettext('Fiscal Year') ?></label>
                        <?php PrintFYIDSelect('FYID', $iFYID) ?>

                        <?php if ($dep_Type === 'Bank' && SystemConfig::getValue('bUseDonationEnvelopes')) {
                            ?>
                            <label for="Envelope"><?= gettext('Envelope Number') ?></label>
                            <input class="form-control" type="number" name="Envelope" size=8 id="Envelope" value="<?= $iEnvelope ?>">
                            <?php if (!$dep_Closed) {
                                ?>
                                <input class="form-control" type="submit" class="btn btn-secondary" value="<?= gettext('Find family->') ?>" name="MatchEnvelope">
                                <?php
                            } ?>

                            <?php
                        } ?>

                        <?php if ($PledgeOrPayment === 'Pledge') {
                            ?>

                            <label for="Schedule"><?= gettext('Payment Schedule') ?></label>
                            <select name="Schedule" class="form-control">
                                <option value="0"><?= gettext('Select Schedule') ?></option>
                                <option value="Weekly" <?php if ($iSchedule === 'Weekly') {
                                                            echo 'selected';
                                                       } ?>><?= gettext('Weekly') ?>
                                </option>
                                <option value="Monthly" <?php if ($iSchedule === 'Monthly') {
                                                            echo 'selected';
                                                        } ?>><?= gettext('Monthly') ?>
                                </option>
                                <option value="Quarterly" <?php if ($iSchedule === 'Quarterly') {
                                                                echo 'selected';
                                                          } ?>><?= gettext('Quarterly') ?>
                                </option>
                                <option value="Once" <?php if ($iSchedule === 'Once') {
                                                            echo 'selected';
                                                     } ?>><?= gettext('Once') ?>
                                </option>
                                <option value="Other" <?php if ($iSchedule === 'Other') {
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
                            <?php if ($PledgeOrPayment === 'Pledge' || $dep_Type === 'Bank' || !$iCurrentDeposit) {
                                ?>
                                <option value="CHECK" <?php if ($iMethod === 'CHECK') {
                                                            echo 'selected';
                                                      } ?>><?= gettext('Check'); ?>
                                </option>
                                <option value="CASH" <?php if ($iMethod === 'CASH') {
                                                            echo 'selected';
                                                     } ?>><?= gettext('Cash'); ?>
                                </option>
                                <?php
                            } ?>
                            <?php if ($PledgeOrPayment === 'Pledge' || $dep_Type === 'CreditCard' || !$iCurrentDeposit) {
                                ?>
                                <option value="CREDITCARD" <?php if ($iMethod === 'CREDITCARD') {
                                                                echo 'selected';
                                                           } ?>><?= gettext('Credit Card') ?>
                                </option>
                                <?php
                            } ?>
                            <?php if ($PledgeOrPayment === 'Pledge' || $dep_Type === 'BankDraft' || !$iCurrentDeposit) {
                                ?>
                                <option value="BANKDRAFT" <?php if ($iMethod === 'BANKDRAFT') {
                                                                echo 'selected';
                                                          } ?>><?= gettext('Bank Draft') ?>
                                </option>
                                <?php
                            } ?>
                            <?php if ($PledgeOrPayment === 'Pledge') {
                                ?>
                                <option value="EGIVE" <?= $iMethod === 'EGIVE' ? 'selected' : '' ?>>
                                    <?= gettext('eGive') ?>
                                </option>
                                <?php
                            } ?>
                        </select>

                        <?php if ($PledgeOrPayment === 'Payment' && $dep_Type === 'Bank') {
                            ?>
                            <div id="checkNumberGroup">
                                <label for="CheckNo"><?= gettext('Check') ?> #</label>
                                <input class="form-control" type="number" name="CheckNo" id="CheckNo" value="<?= $iCheckNo ?>" /><span class="text-danger"><?= $sCheckNoError ?></span>
                            </div>
                            <?php
                        } ?>

                        <label for="TotalAmount"><?= gettext('Total $') ?></label>
                        <input class="form-control" type="number" step="any" name="TotalAmount" id="TotalAmount" disabled />

                    </div>

                    <div class="col-lg-6">
                        <?php if (SystemConfig::getValue('bUseScannedChecks') && ($dep_Type === 'Bank' || $PledgeOrPayment === 'Pledge')) {
                            ?>
                            <td class="text-center <?= $PledgeOrPayment === 'Pledge' ? 'LabelColumn' : 'PaymentLabelColumn' ?>"><?= gettext('Scan check') ?>
                                <textarea name="ScanInput" rows="2" cols="70"><?= $tScanString ?></textarea>
                            </td>
                            <?php
                        } ?>
                    </div>

                    <div class="col-lg-6">
                        <?php if (SystemConfig::getValue('bUseScannedChecks') && $dep_Type === 'Bank') {
                            ?>
                            <input type="submit" class="btn btn-secondary" value="<?= gettext('find family from check account #') ?>" name="MatchFamily">
                            <input type="submit" class="btn btn-secondary" value="<?= gettext('Set default check account number for family') ?>" name="SetDefaultCheck">
                            <?php
                        } ?>
                    </div>

                    <div class="col-lg-12">
                        <?php if (!$dep_Closed) {
                            ?>
                            <br />
                            <input type="submit" id="saveBtn" class="btn btn-secondary" value="<?= gettext('Save') ?>" name="PledgeSubmit">
                            <?php if (AuthenticationManager::getCurrentUser()->isAddRecordsEnabled()) {
                                echo '<input id="save-n-add" type="submit" class="btn btn-primary" value="' . gettext('Save and Add') . '" name="PledgeSubmitAndAdd">';
                            } ?>
                            <?php
                        } ?>
                        <?php if (!$dep_Closed) {
                            $cancelText = 'Cancel';
                        } else {
                            $cancelText = 'Return';
                        } ?>
                        <input type="button" class="btn btn-danger" value="<?= gettext($cancelText) ?>" name="PledgeCancel" onclick="javascript:document.location='<?= $linkBack ? $linkBack : 'v2/dashboard' ?>';">
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header <?= $cardHeaderClass ?> <?= $cardHeaderTextClass ?> with-border">
                    <h3 class="card-title"><?= $formTypeLabel ?> <?= gettext('Fund Allocation') ?></h3>
                </div>
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th class="<?= $PledgeOrPayment === 'Pledge' ? 'LabelColumn' : 'PaymentLabelColumn' ?>"><?= gettext('Fund Name') ?></th>
                                <th class="<?= $PledgeOrPayment === 'Pledge' ? 'LabelColumn' : 'PaymentLabelColumn' ?>"><?= gettext('Amount') ?></th>

                                <?php if ($bEnableNonDeductible) {
                                    ?>
                                    <th class="<?= $PledgeOrPayment === 'Pledge' ? 'LabelColumn' : 'PaymentLabelColumn' ?>"><?= gettext('Non-deductible amount') ?></th>
                                    <?php
                                } ?>

                                <th class="<?= $PledgeOrPayment === 'Pledge' ? 'LabelColumn' : 'PaymentLabelColumn' ?>"><?= gettext('Comment') ?></th>
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
                                        <span class="text-danger"><?= $sAmountError[$fun_id] ?></span>
                                    </td>
                                    <?php
                                    if ($bEnableNonDeductible) {
                                        ?>
                                        <td class="TextColumn">
                                            <input type="number" step="any" name="<?= $fun_id ?>_NonDeductible" id="<?= $fun_id ?>_NonDeductible" value="<?= ($nNonDeductible[$fun_id] ? $nNonDeductible[$fun_id] : "") ?>" />
                                            <br>
                                            <span class="text-danger"><?= $sNonDeductibleError[$fun_id] ?></span>
                                        </td>
                                        <?php
                                    } ?>
                                    <td class="TextColumn">
                                        <input type="text" size=40 name="<?= $fun_id ?>_Comment" id="<?= $fun_id ?>_Comment" value="<?= $sComment[$fun_id] ?>">
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

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    $(document).ready(function() {

        $("#FamilyName").select2({
            minimumInputLength: 2,
            ajax: {
                url: function(params) {
                    var a = window.CRM.root + '/api/families/search/' + params.term;
                    return a;
                },
                dataType: 'json',
                delay: 250,
                data: "",
                processResults: function(data, params) {
                    var results = [];
                    var families = data?.Families ?? [];
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

        $("#FamilyName").on("select2:select", function(e) {
            $('[name=FamilyID]').val(e.params.data.id);
        });

        $(".FundAmount").change(function() {
            CalculateTotal();
        });

        $("#Method").change(function() {
            EvalCheckNumberGroup();
        });

        EvalCheckNumberGroup();
        CalculateTotal();
    });

    function EvalCheckNumberGroup() {
        if ($("#Method option:selected").val() === "CHECK") {
            $("#checkNumberGroup").show();
        } else {
            $("#checkNumberGroup").hide();
            $("#CheckNo").val('');
        }
    }

    function CalculateTotal() {
        var Total = 0;
        $(".FundAmount").each(function(object) {
            var FundAmount = Number($(this).val());
            if (FundAmount > 0) {
                Total += FundAmount;
            }
        });
        $("#TotalAmount").val(Total);
    }
</script>
<?php
require_once 'Include/Footer.php';
