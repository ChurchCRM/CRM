<?php

// Include the function library
require "Include/Config.php";
require "Include/Functions.php";

//Security
if (!isset($_SESSION['iUserID']))
{
	Redirect("Default.php");
	exit;
}

	$iFamily = FilterInput($_POST["FamilyID"],'int');
	
   	$dDate = FilterInput($_POST["Date"]);
	if (!$dDate) {
		if (array_key_exists ('idefaultDate', $_SESSION))
			$dDate = $_SESSION['idefaultDate'];
		else
			$dDate = date ("Y-m-d");
	}

	if (isset($_POST["FundSplit"])) {
		$iSelectedFund = FilterInput($_POST["FundSplit"]);
	} 

	// set from drop-down if set, saved session default, or by calcuation
	$iFYID = FilterInput($_POST["FYID"], 'int');
	if (!$iFYID) {
		$iFYID =  $_SESSION['idefaultFY'];
	}
	if (!$iFYID) {
		$iFYID = CurrentFY();
	}
	
	if (array_key_exists ("CheckNo", $_POST))
		$iCheckNo = FilterInput($_POST["CheckNo"], 'int');
	else
		$iCheckNo = 0;
	
	if (array_key_exists ("Schedule", $_POST))
		$iSchedule = FilterInput($_POST["Schedule"]);
	else
		$iSchedule='Once';
	$_SESSION['iDefaultSchedule'] = $iSchedule;
	
	$iMethod = FilterInput($_POST["Method"]);
	
	if (!$iMethod) {
		if ($sGroupKey) 
		{
			$sSQL = "SELECT DISTINCT plg_method FROM pledge_plg WHERE plg_GroupKey='" . $sGroupKey . "'";
			$rsResults = RunQuery($sSQL);
			list($iMethod) = mysql_fetch_row($rsResults);
		} 
		elseif ($iCurrentDeposit) 
		{
			$sSQL = "SELECT plg_method from pledge_plg where plg_depID=\"" . $iCurrentDeposit . "\" ORDER by plg_plgID DESC LIMIT 1";
			$rsMethod = RunQuery($sSQL);
			$num = mysql_num_rows($rsMethod);
			if ($num) 
			{	// set iMethod to last record's setting
				extract(mysql_fetch_array($rsMethod));  
				$iMethod = $plg_method;
			} 
			else 
			{
				$iMethod = "CHECK";
			}
		} 
		else 
		{
			$iMethod = "CHECK";
		}
	}

	$iEnvelope = 0;
	if (array_key_exists ("Envelope", $_POST))
		$iEnvelope = FilterInput($_POST["Envelope"], 'int');
	$iTotalAmount = FilterInput($_POST["TotalAmount"]);
	if (array_key_exists ("OneComment", $_POST))
		$sOneComment = FilterInput($_POST["OneComment"]);
	else
		$sOneComment = "";
	if ($iSelectedFund) {
		$nAmount[$iSelectedFund] = $iTotalAmount;
		$sComment[$iSelectedFund] = $sOneComment;
	}

//Initialize the error flag
$bErrorFlag = false;

if (!$iSelectedFund) { // split
	// make sure at least one fund has a non-zero numer
	$nonZeroFundAmountEntered = 0;
	foreach ($fundId2Name as $fun_id => $fun_name) {
		//$fun_active = $fundActive[$fun_id];
		$nAmount[$fun_id] = FilterInput($_POST[$fun_id . "_Amount"]);
		$sComment[$fun_id] = FilterInput($_POST[$fun_id . "_Comment"]);
		if ($nAmount[$fun_id] > 0) {
			++$nonZeroFundAmountEntered;
		}

		if ($bEnableNonDeductible) {
			$nNonDeductible[$fun_id] = FilterInput($_POST[$fun_id . "_NonDeductible"]);
			//Validate the NonDeductible Amount
			if ($nNonDeductible[$fun_id] > $nAmount[$fun_id]) { //Validate the NonDeductible Amount
				$sNonDeductibleError[$fun_id] = gettext("NonDeductible amount can't be greater than total amount.");
			$bErrorFlag = true;
			echo "bErrorFlag 111";
			}
		}
	} // end foreach

	if (!$nonZeroFundAmountEntered) {
		$sAmountError[$fun_id] = gettext("At least one fund must have a non-zero amount.");
		$bErrorFlag = true;
		echo "bErrorFlag 118";
	}
}

if (array_key_exists ("ScanInput", $_POST))
	$tScanString = FilterInput($_POST["ScanInput"]);
else
	$tScanString = "";
$iAutID = 0;
if (array_key_exists ("AutoPay", $_POST))
	$iAutID = FilterInput($_POST["AutoPay"]);
//$iEnvelope = FilterInput($_POST["Envelope"], 'int');

if ($PledgeOrPayment=='Payment' and !$iCheckNo and $iMethod == "CHECK") {
	$sCheckNoError = "<span style=\"color: red; \">" . gettext("Must specify non-zero check number") . "</span>";
	$bErrorFlag = true;
	echo "bErrorFlag 133";
}

// detect check inconsistencies
if ($PledgeOrPayment=='Payment' and $iCheckNo) {
	if ($iMethod == "CASH") {
		$sCheckNoError = "<span style=\"color: red; \">" . gettext("Check number not valid for 'CASH' payment") . "</span>";
		$bErrorFlag = true;
		echo "bErrorFlag 139";
	} elseif ($iMethod=='CHECK' and !$sGroupKey) {
		$chkKey = $iFamily . "|" . $iCheckNo;
		if (array_key_exists($chkKey, $checkHash)) {
			$text = "Check number '" . $iCheckNo . "' for selected family already exists.";
			$sCheckNoError = "<span style=\"color: red; \">" . gettext($text) . "</span>";
			$bErrorFlag = true;
			echo "bErrorFlag 146";
		}
	}
}

// Validate Date
if (strlen($dDate) > 0) {
	list($iYear, $iMonth, $iDay) = sscanf($dDate,"%04d-%02d-%02d");
	if ( !checkdate($iMonth,$iDay,$iYear) ) {
		$sDateError = "<span style=\"color: red; \">" . gettext("Not a valid Date") . "</span>";
		$bErrorFlag = true;
		echo "bErrorFlag 156";
	}
}

//If no errors, then let's update...
if (!$bErrorFlag and !$dep_Closed) {
	echo "no errors, update!";
	// Only set PledgeOrPayment when the record is first created
	// loop through all funds and create non-zero amount pledge records
	foreach ($fundId2Name as $fun_id => $fun_name) {
		if (!$iCheckNo) { $iCheckNo = 0; }
		unset($sSQL);
		if ($fund2PlgIds and array_key_exists($fun_id, $fund2PlgIds)) {
			if ($nAmount[$fun_id] > 0) {
				$sSQL = "UPDATE pledge_plg SET plg_famID = '" . $iFamily . "',plg_FYID = '" . $iFYID . "',plg_date = '" . $dDate . "', plg_amount = '" . $nAmount[$fun_id] . "', plg_schedule = '" . $iSchedule . "', plg_method = '" . $iMethod . "', plg_comment = '" . $sComment[$fun_id] . "'";
				$sSQL .= ", plg_DateLastEdited = '" . date("YmdHis") . "', plg_EditedBy = " . $_SESSION['iUserID'] . ", plg_CheckNo = '" . $iCheckNo . "', plg_scanString = '" . $tScanString . "', plg_aut_ID='" . $iAutID . "', plg_NonDeductible='" . $nNonDeductible[$fun_id] . "' WHERE plg_plgID='" . $fund2PlgIds[$fun_id] . "'";
			} else { // delete that record
				$sSQL = "DELETE FROM pledge_plg WHERE plg_plgID =" . $fund2PlgIds[$fun_id];
			}
		} elseif ($nAmount[$fun_id] > 0) {
			if ($iMethod <> "CHECK") {
				$iCheckNo = "NULL";
			}
			if (!$sGroupKey) {
				if ($iMethod == "CHECK") {
					$sGroupKey = genGroupKey($iCheckNo, $iFamily, $fun_id, $dDate);
				} elseif ($iMethod == "BANKDRAFT") {
					if (!$iAutID) {
						$iAutID = "draft";
					}
					$sGroupKey = genGroupKey($iAutID, $iFamily, $fun_id, $dDate);
				} elseif ($iMethod == "CREDITCARD") {
					if (!$iAutID) {
						$iAutID = "credit";
					}
					$sGroupKey = genGroupKey($iAutID, $iFamily, $fun_id, $dDate);
				} else {
					$sGroupKey = genGroupKey("cash", $iFamily, $fun_id, $dDate);
				} 
			}
			$sSQL = "INSERT INTO pledge_plg (plg_famID, plg_FYID, plg_date, plg_amount, plg_schedule, plg_method, plg_comment, plg_DateLastEdited, plg_EditedBy, plg_PledgeOrPayment, plg_fundID, plg_depID, plg_CheckNo, plg_scanString, plg_aut_ID, plg_NonDeductible, plg_GroupKey)
		VALUES ('" . $iFamily . "','" . $iFYID . "','" . $dDate . "','" . $nAmount[$fun_id] . "','" . $iSchedule . "','" . $iMethod  . "','" . $sComment[$fun_id] . "'";
			$sSQL .= ",'" . date("YmdHis") . "'," . $_SESSION['iUserID'] . ",'" . $PledgeOrPayment . "'," . $fun_id . "," . $iCurrentDeposit . "," . $iCheckNo . ",'" . $tScanString . "','" . $iAutID  . "','" . $nNonDeductible[$fun_id] . "','" . $sGroupKey . "')";
			
			echo "SQL: ".$sSQL;
		}
		if (isset ($sSQL)) {
			RunQuery($sSQL);
			unset($sSQL);
		}
		
	} // end foreach of $fundId2Name
	
	
	//Process the Currency Denominations for this deposit
	if ($iMethod == "CASH"){
		$sGroupKey = genGroupKey("cash", $iFamily, $fun_id, $dDate);
		foreach ($currencyDenomination2Name as $cdem_denominationID =>$cdem_denominationName)
		{
			$sSQL = "INSERT INTO pledge_denominations_pdem (pdem_plg_GroupKey, pdem_denominationID, pdem_denominationQuantity) 
			VALUES ('". $sGroupKey ."','".$cdem_denominationID."','".$_POST['currencyCount-'.$cdem_denominationID]."')";
		
			if (isset ($sSQL)) {
				RunQuery($sSQL);
				unset($sSQL);
			}
			//$currencyDenomination2Name[$cdem_denominationID] = $cdem_denominationName;
			//$currencyDenominationValue[$cdem_denominationID] = $cdem_denominationValue;
		}
	}
	
	
} // end if !$bErrorFlag
echo "out";

?>