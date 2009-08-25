<?php
/*******************************************************************************
 *
 *  filename    : PledgeEditor.php
 *  last change : 2004-6-12
 *  website     : http://www.infocentral.org
 *  copyright   : Copyright 2001, 2002, 2003 Deane Barker, Chris Gebhardt, Michael Wilt
 *
 *  InfoCentral is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

global $bChecksPerDepositForm;

//Include the function library
require "Include/Config.php";
require "Include/Functions.php";

require "Include/MICRFunctions.php";

$PledgeOrPayment = FilterInput($_GET["PledgeOrPayment"]);

$dDate = $_SESSION['idefaultDate'];
if (!$dDate) {
  $dDate = FilterInput($_POST["Date"]);
}

if (!$dDate) {
  $dDate = date ("Y-m-d");
}

$_SESSION['idefaultDate'] = $dDate;

$iCheckNo = FilterInput($_POST["CheckNo"], 'int');

// set from saved session default, or from prior input, or default by calcuation
$iFYID =  $_SESSION['idefaultFY'];

if (!$iFYID) {
	$iFYID = FilterInput($_POST["FYID"], 'int');
}

if (!$iFYID) {
	$iFYID = CurrentFY();
}

$iSchedule = FilterInput($_POST["Schedule"]);
if ($iSchedule=='') {
	$iSchedule='Once';
}

if (!$PledgeOrPayment) {
	$PledgeOrPayment = FilterInput($_POST["PledgeOrPayment"],'int');
}

$iCurrentDeposit = FilterInput($_GET["CurrentDeposit"]);

if ($PledgeOrPayment == 'Pledge') { // Don't assign the deposit slip if this is a pledge
	$iCurrentDeposit = 0;
} else { // its a deposit
	if ($iCurrentDeposit) {
		$_SESSION['iCurrentDeposit'] = $iCurrentDeposit;
	} else {
		$iCurrentDeposit = $_SESSION['iCurrentDeposit'];
	}

	// Get the current deposit slip data
	if ($iCurrentDeposit) {
		// this query was for '*' but I don't see where anything other than dep_Closed is used...  so only get that for now
		$sSQL = "SELECT dep_Closed from deposit_dep WHERE dep_ID = " . $iCurrentDeposit;
		$rsDeposit = RunQuery($sSQL);
		extract(mysql_fetch_array($rsDeposit));
		if ($dep_Closed) // If the current deposit slip is closed, force creation of a new one.
			$iCurrentDeposit = 0;
	}
}

$iMethod = FilterInput($_POST["Method"]);
if (!$iMethod) {
	if ($iCurrentDeposit) {
		$sSQL = "SELECT plg_method from pledge_plg where plg_depID=\"" . $iCurrentDeposit . "\" ORDER by plg_plgID DESC LIMIT 1";
		$rsMethod = RunQuery($sSQL);
		$num = mysql_num_rows($rsMethod);
		if ($num) {	// set iMethod to last record's setting
			extract(mysql_fetch_array($rsMethod));  
			$iMethod = $plg_method;
		} else {
			$iMethod = "CHECK";
		}
	} else {
		$iMethod = "CHECK";
	}
}

$_SESSION['idefaultPaymentMethod'] = $iMethod;

if ($iMethod == "CASH" or $iMethod == "CHECK")
	$dep_Type = "Bank";
elseif ($iMethod == "CREDITCARD")
	$dep_Type = "CreditCard";
elseif ($iMethod == "BANKDRAFT")
	$dep_Type = "BankDraft";

// Set parms passed into local variables
$linkBack = FilterInput($_GET["linkBack"]);

if (strlen($PledgeOrPayment) < 1) {
	if (ereg("Deposit", $linkBack)) {
		$PledgeOrPayment = 'Payment';
	} else {
		$PledgeOrPayment = 'Pledge'; // default to Pledge rather than payment
	}
}

// Get the list of funds
$sSQL = "SELECT fun_ID,fun_Name,fun_Description,fun_Active FROM donationfund_fun";
if ($PledgeOrPayment == 'Pledge') {
	$sSQL .= " WHERE fun_Active = 'true'"; // New donations should show only active funds.
	// $sSQL .= " ORDER BY fun_Name";  take this out so that ordering is however they were added/entered rather than on fund name
}

$rsFunds = RunQuery($sSQL);
mysql_data_seek($rsFunds,0);
while ($row = mysql_fetch_array($rsFunds)) {
	$fun_id = $row["fun_ID"];
	$fundId2Name[$fun_id] = $row["fun_Name"];
	if (!isset($defaultFund)) {
		$defaultFund = $row["fun_Name"];
	}
	$fundIdActive[$fun_id] = $row["fun_Active"];
} // end while

$iPledgeID = FilterInput($_GET["PledgeID"],'int'); // this will only be set if someone pressed the 'edit' button on the Pledge or Deposit line

$iFamily = FilterInput($_GET["FamilyID"],'int');
if (!$iFamily) {
	$iFamily = FilterInput($_POST["FamilyID"],'int');
}

$iEnvelope = FilterInput($_POST["Envelope"], 'int');
if (!$iEnvelope and $iFamily) {
	$sSQL = "SELECT fam_Envelope FROM family_fam WHERE fam_ID=\"" . $iFamily . "\";";
	$rsEnv = RunQuery($sSQL);
	extract(mysql_fetch_array($rsEnv));
	if ($fam_Envelope) {
		$iEnvelope = $fam_Envelope;
	}
}

if ($PledgeOrPayment == 'Pledge' and $iFamily) {
	$sSQL = "SELECT plg_plgID, plg_fundID from pledge_plg where plg_famID=\"" . $iFamily . "\" AND plg_PledgeOrPayment=\"" .  	$PledgeOrPayment . "\";";
	$rsPlgIDs = RunQuery($sSQL);
	while ($row = mysql_fetch_array($rsPlgIDs)) {
		$plgID = $row["plg_plgID"];
		$fundID = $row["plg_fundID"];
		$fund2PlgIds[$fundID] = $plgID;
	} // end while
} elseif ($iPledgeID) { // handles the case where PledgeID is set.  Need to get all the family records for that payment so we can prime the data for editing
	$sSQL = "SELECT plg_famID, plg_CheckNo from pledge_plg where plg_plgID=\"" . $iPledgeID . "\";";
	$rsFam = RunQuery($sSQL);
	extract(mysql_fetch_array($rsFam));
	$iFamily = $plg_famID;
	$iCheckNo = $plg_CheckNo;
	
	$sSQL = "SELECT plg_plgID, plg_fundID from pledge_plg where plg_famID=\"" . $iFamily . "\" AND plg_PledgeOrPayment=\"" . $PledgeOrPayment . "\";";
	
	// AND plg_CheckNo=\"" . $iCheckNo . "\";";

	$rsPlgIDs = RunQuery($sSQL);
	while ($row = mysql_fetch_array($rsPlgIDs)) {
		$plgID = $row["plg_plgID"];
		$fundID = $row["plg_fundID"];
		$fund2PlgIds[$fundID] = $plgID;
	} // end while
} // end if $iPledgeID

if ($pledgeOrPayment == 'Payment') {
	$bEnableNonDeductible = 1; // this could/should be a config parm?  regardless, having a non-deductible amount for a pledge doesn't seem possible
}

//Set the page title
if ($PledgeOrPayment == 'Pledge') {
	$sPageTitle = gettext("Pledge Editor");
} elseif ($iCurrentDeposit) {
	$sPageTitle = gettext("Payment Editor: ") . $dep_Type . gettext(" Deposit Slip #") . $iCurrentDeposit . " ($dep_Date)";

	// form assumed by Reports/PrintDeposit.php. 
	$checksFit = $bChecksPerDepositForm;

	$sSQL = "SELECT plg_plgID, plg_checkNo, plg_method from pledge_plg where plg_depID=" . $iCurrentDeposit;
	$rsChecksThisDep = RunQuery ($sSQL);
	$depositCount = 0;
	while ($aRow = mysql_fetch_array($rsChecksThisDep)) {
		extract($aRow);
		if ($plg_method=='CHECK') {
			if ($checkHash and array_key_exists($plg_checkNo, $checkHash)) {
				next;
			} else {
				$checkHash[$plg_checkNo] = $plg_plgID;
				++$depositCount;
			}
		} else {
			++$depsitCount;
		}
	}

	//$checkCount = mysql_num_rows ($rsChecksThisDep);
	$roomForDeposits = $checksFit - $depositCount;
	if ($roomForDeposits <= 0)
		$sPageTitle .= "<font color=red>";
	$sPageTitle .= " (" . $roomForDeposits . gettext (" more entries will fit.") . ")";
	if ($roomForDeposits <= 0)
		$sPageTitle .= "</font>";
} else { // not a plege and a current deposit hasn't been created yet
	if ($iPledgeID) {
		$sPageTitle = gettext("Payment Editor - Modify Existing Payment");
	} else {
		$sPageTitle = gettext("Payment Editor - New Deposit Slip Will Be Created");
	}
} // end if $PledgeOrPayment

if ($dep_Closed && $iPledgeID && $PledgeOrPayment == 'Payment') {
	$sPageTitle .= " &nbsp; <font color=red>Deposit closed</font>";
}			

// Security: User must have Finance permission to use this form.
// Clean error handling: (such as somebody typing an incorrect URL ?PersonID= manually)
if (! $_SESSION['bFinance']) {
	Redirect("Menu.php");
	exit;
}

if ($bUseScannedChecks) { // Instantiate the MICR class
   $micrObj = new MICRReader();
}

if (isset($_POST["PledgeSubmit"]) or isset($_POST["PledgeSubmitAndAdd"])) {
	//echo "top of loop\n";
	//Initialize the error flag
	$bErrorFlag = false;

	// make sure at least one fund has a non-zero numer
	$nonZeroFundAmountEntered = 0;
	foreach ($fundId2Name as $fun_id => $fun_name) {
		//$fun_active = $fundActive[$fun_id];
		$nAmount[$fun_name] = FilterInput($_POST[$fun_name . "_Amount"]);
		$sComment[$fun_name] = FilterInput($_POST[$fun_name . "_Comment"]);
		if ($nAmount[$fun_name] > 0) {
			++$nonZeroFundAmountEntered;
		}

		if ($bEnableNonDeductible) {
			$nNonDeductible[$fun_name] = FilterInput($_POST[$fun_name . "_NonDeductible"]);
			//Validate the NonDeductible Amount
			if ($nNonDeductible[$fun_name] > $nAmount[$fun_name]) { //Validate the NonDeductible Amount
				$sNonDeductibleError[$fun_name] = gettext("NonDeductible amount can't be greater than total amount.");
			$bErrorFlag = true;
			}
		}
	} // end foreach

	if (!$nonZeroFundAmountEntered) {
		$sAmountError[$fun_name] = gettext("At least one fund must have a non-zero amount.");
		$bErrorFlag = true;
	}

	$tScanString = FilterInput($_POST["ScanInput"]);
	$iAutID = FilterInput($_POST["AutoPay"]);
	//$iEnvelope = FilterInput($_POST["Envelope"], 'int');

	if ($iAutID=="") 
		$iAutID=0; 

	if ($PledgeOrPayment=='Payment' and !$iCheckNo and $iMethod == "CHECK") {
		$sCheckNoError = "<span style=\"color: red; \">" . gettext("Must specify non-zero check number") . "</span>";
		$bErrorFlag = true;
	}

	// Validate Date
	if (strlen($dDate) > 0) {
		list($iYear, $iMonth, $iDay) = sscanf($dDate,"%04d-%02d-%02d");
		if ( !checkdate($iMonth,$iDay,$iYear) ) {
			$sDateError = "<span style=\"color: red; \">" . gettext("Not a valid Date") . "</span>";
			$bErrorFlag = true;
		}
	}

	//If no errors, then let's update...
	if (!$bErrorFlag) {
		// Create new Deposit Slip
		if ((!$iCurrentDeposit) and $PledgeOrPayment=='Payment') {					
			$sSQL = "INSERT INTO deposit_dep (dep_Date, dep_Comment, dep_EnteredBy, dep_Type)
				         VALUES ('" . date("Y-m-d") . "','Automatically created because current slip was closed'," . $_SESSION['iUserID'] . ",'$dep_Type')";
			RunQuery($sSQL);
			$sSQL = "SELECT MAX(dep_ID) AS iDepositSlipID FROM deposit_dep";
			$rsDepositSlipID = RunQuery($sSQL);
			extract(mysql_fetch_array($rsDepositSlipID));
			$_SESSION['iCurrentDeposit'] = $iDepositSlipID;
			$iCurrentDeposit = $iDepositSlipID;
			$dep_Date = date("Y-m-d");
		}
		// Only set PledgeOrPayment when the record is first created
		// loop through all funds and create non-zero amount pledge records
		foreach ($fundId2Name as $fun_id => $fun_name) {
			if (!$iCheckNo) { $iCheckNo = 0; }

			if ($fund2PlgIds and array_key_exists($fun_id, $fund2PlgIds)) {
				if ($nAmount[$fun_name] > 0) {
					$sSQL = "UPDATE pledge_plg SET plg_FYID = '" . $iFYID . "',plg_date = '" . $dDate . "', plg_amount = '" . $nAmount[$fun_name] . "', plg_schedule = '" . $iSchedule . "', plg_method = '" . $iMethod . "', plg_comment = '" . $sComment[$fun_name] . "'";
					$sSQL .= ", plg_DateLastEdited = '" . date("YmdHis") . "', plg_EditedBy = " . $_SESSION['iUserID'] . ", plg_CheckNo = \"" . $iCheckNo . "\", plg_scanString = \"" . $tScanString . "\", plg_aut_ID=\"" . $iAutID . "\", plg_NonDeductible=\"" . $nNonDeductible[$fun_name] . "\" WHERE plg_plgID = \"" . $fund2PlgIds[$fun_id] . "\" AND plg_famID = \"" . $iFamily . "\"";
				} else { // delete that record
					$sSQL = "DELETE FROM pledge_plg WHERE plg_plgID = \"" . $fund2PlgIds[$fun_id] . "\" AND plg_famID = \"" . $iFamily . "\"";
				}
			} elseif ($nAmount[$fun_name] > 0) {
				if ($iMethod <> "CHECK") {
					$iCheckNo = "NULL";
				}
				$sSQL = "INSERT INTO pledge_plg (plg_famID, plg_FYID, plg_date, plg_amount, plg_schedule, plg_method, plg_comment, plg_DateLastEdited, plg_EditedBy, plg_PledgeOrPayment, plg_fundID, plg_depID, plg_CheckNo, plg_scanString, plg_aut_ID, plg_NonDeductible)
			VALUES ('" . $iFamily . "','" . $iFYID . "','" . $dDate . "','" . $nAmount[$fun_name] . "','" . $iSchedule . "','" . $iMethod  . "','" . $sComment[$fun_name] . "'";
				$sSQL .= ",'" . date("YmdHis") . "'," . $_SESSION['iUserID'] . ",'" . $PledgeOrPayment . "'," . $fun_id . "," . $iCurrentDeposit . "," . $iCheckNo . ",\"" . $tScanString . "\",\"" . $iAutID  . "\",\"" . $nNonDeductible[$fun_name] . "\")";
				$bGetKeyBack = True;
			}

			RunQuery($sSQL);
			// If this is a new pledge or deposit, get the key back
			if ($bGetKeyBack) {
				$sSQL = "SELECT MAX(plg_plgID) AS iPledgeID FROM pledge_plg";
				$rsPledgeID = RunQuery($sSQL);
				extract(mysql_fetch_array($rsPledgeID));
			}
		} // end foreach of $fundId2Name
	
		if (isset($_POST["PledgeSubmit"])) {
			// Check for redirection to another page after saving information: (ie. PledgeEditor.php?previousPage=prev.php?a=1;b=2;c=3)
			if ($linkBack != "") {
				Redirect($linkBack);
			} else {
				//Send to the view of this pledge
				Redirect("PledgeEditor.php?PledgeOrPayment=" . $PledgeOrPayment . "&PledgeID=" . $iPledgeID . "&linkBack=", $linkBack);
			}
		} elseif (isset($_POST["PledgeSubmitAndAdd"])) {
			//Reload to editor to add another record
			Redirect("PledgeEditor.php?CurrentDeposit=$iCurrentDeposit&PledgeOrPayment=" . $PledgeOrPayment . "&linkBack=", $linkBack);
		}
	} // end if !$bErrorFlag
} elseif (isset($_POST["MatchFamily"]) or isset($_POST["MatchEnvelope"]) or isset($_POST["SetDefaultCheck"]) or isset($_POST["TotalAmount"])) {

	//$iCheckNo = 0;
	// Take care of match-family first- select the family based on the scanned check
	if ($bUseScannedChecks and isset($_POST["MatchFamily"])) {
		$tScanString = FilterInput($_POST["ScanInput"]);

		$routeAndAccount = $micrObj->FindRouteAndAccount ($tScanString); // use routing and account number for matching

    		if ($routeAndAccount) {
		   $sSQL = "SELECT fam_ID FROM family_fam WHERE fam_scanCheck REGEXP \"" . $routeAndAccount . "\"";
		   $rsFam = RunQuery($sSQL);
		   extract(mysql_fetch_array($rsFam));
		   $iFamily = $fam_ID;

		   $iCheckNo = $micrObj->FindCheckNo ($tScanString);
      		} else {
		   $iFamily = FilterInput($_POST["FamilyID"],'int');
		   $iCheckNo = FilterInput($_POST["CheckNo"], 'int');
    		}
	} elseif (isset($_POST["MatchEnvelope"])) {
		// Match envelope is similar to match check- use the envelope number to choose a family
		
		$iEnvelope = FilterInput($_POST["Envelope"], 'int');
		if ($iEnvelope and strlen($iEnvelope) > 0) {
			$sSQL = "SELECT fam_ID FROM family_fam WHERE fam_Envelope=" . $iEnvelope;
			$rsFam = RunQuery($sSQL);
			$numRows = mysql_num_rows($rsFam);
			if ($numRows) {
				extract(mysql_fetch_array($rsFam));
				$iFamily = $fam_ID;
			}
		}
	} elseif (isset($_POST["TotalAmount"])) {
		$iTotalAmount = FilterInput($_POST["TotalAmount"]);
		$sSQL = "SELECT plg_fundID, plg_amount from pledge_plg where plg_famID=\"" . $iFamily . "\" AND plg_PledgeOrPayment=\"Pledge\" AND plg_FYID=\"" . $iFYID . "\";";
//echo "sSQL: " . $sSQL . "\n";
		$rsPledge = RunQuery($sSQL);
		$totalPledgeAmount = 0;
		while ($row = mysql_fetch_array($rsPledge)) {
			$fundID = $row["plg_fundID"];
			$plgAmount = $row["plg_amount"];
//echo "plgAmount: " . $plgAmount . "\n";
			$fundName = 	$fundId2Name[$fundID];
			$fundName2Pledge[$fundName] = $plgAmount;
			$totalPledgeAmount = $totalPledgeAmount + $plgAmount;
		} // end while
		if ($fundName2Pledge) {
			// division rounding can cause total of calculations to not equal total.  Keep track of running total, and asssign any rounding error to 'default' fund
			$calcTotal = 0;
			$calcOtherFunds = 0;
			foreach ($fundName2Pledge as $fundName => $plgAmount) {
				$calcAmount = number_format($iTotalAmount * ($plgAmount / $totalPledgeAmount), 2);
//echo "calcAmount: " . $fundName . " " . $calcAmount . "\n";
				$nAmount[$fundName] = $calcAmount;
				if ($fundName <> $defaultFund) {
					$calcOtherFunds += $calcAmount;
				}
				$calcTotal =+ $calcAmount;
			}
			if ($calcTotal <> $iTotalAmount) {
				$nAmount[$defaultFund] = number_format($iTotalAmount - $calcOtherFunds, 2);
			}
		} else {
			$nAmount[$defaultFund] = $iTotalAmount;
		}
	} else {
		$iFamily = FilterInput($_POST["FamilyID"]);
		$iCheckNo = FilterInput($_POST["CheckNo"], 'int');
	} // end if bUseScannedChecks

	// Handle special buttons at the bottom of the form.
	if (isset($_POST["SetDefaultCheck"])) {
		$tScanString = FilterInput($_POST["ScanInput"]);
		$iFamily = FilterInput($_POST["FamilyID"],'int');
		$sSQL = "UPDATE family_fam SET fam_scanCheck=\"" . $tScanString . "\" WHERE fam_ID = " . $iFamily;
		RunQuery($sSQL);
	}
} else { // First time into screen
	if ($fund2PlgIds) { // pledge records exist so pull data from the ones that exist
		$sSQL = "SELECT * FROM pledge_plg WHERE plg_famID=\"" . $iFamily . "\" AND plg_PledgeOrPayment=\"" . $PledgeOrPayment . "\" AND plg_FYID=\"" . $iFYID . "\";";

		$rsPledge = RunQuery($sSQL);
		while ($aRow = mysql_fetch_array($rsPledge)) {
			extract($aRow);
			//$iFYID = $plg_FYID;
			$dDate = $plg_date;
			//$iCheckNo = $plg_CheckNo;
			$iSchedule = $plg_schedule;
			$iMethod = $plg_method;
			$tScanString = $plg_scanString;
	      	//$PledgeOrPayment = $plg_PledgeOrPayment;
		  	$iAutID = $plg_aut_ID;
			$fun_id = $plg_fundID;
			$fun_name = 	$fundId2Name[$fun_id];
			$nAmount[$fun_name] = $plg_amount;
			$sComment[$fun_name] = $plg_comment;
			if ($bEnableNonDeductible) {
				$nNonDeductible[$fun_name] = $plg_NonDeductible;
			}
		}
	} else { // adding
		//Set defaults

		if ($dep_Type == "CreditCard") {
			$iMethod = "CREDITCARD";
		} elseif ($dep_Type == "BankDraft") {
			$iMethod = "BANKDRAFT";
		}
		$iAutID = 0;
	}
} // end FirstPass

// Set Current Deposit setting for user
if ($iCurrentDeposit) {
	$sSQL = "UPDATE user_usr SET usr_currentDeposit = '$iCurrentDeposit' WHERE usr_per_id = \"".$_SESSION['iUserID']."\"";
	$rsUpdate = RunQuery($sSQL);
}

//Get Families for the drop-down
$sSQL = "SELECT fam_ID, fam_Name, fam_Address1, fam_City, fam_State FROM family_fam ORDER BY fam_Name";
$rsFamilies = RunQuery($sSQL);

require "Include/Header.php";

?>
<form method="post" action="PledgeEditor.php?<?php echo "CurrentDeposit=" . $iCurrentDeposit . "&PledgeID=" . $iPledgeID . "&PledgeOrPayment=" . $PledgeOrPayment. "&linkBack=" . $linkBack; ?>" name="PledgeEditor">

<input type="hidden" name="FamilyID" id="FamilyID" value="<?php echo $iFamily; ?>">
<input type="hidden" name="PledgeOrPayment" id="PledgeOrPayment" value="<?php echo $PledgeOrPayment; ?>">

<table cellpadding="3" align="center">
	<tr>
		<td align="center">
			<input type="submit" class="icButton" value="<?php echo gettext("Save"); ?>" name="PledgeSubmit">
			<?php if ($_SESSION['bAddRecords']) { echo "<input type=\"submit\" class=\"icButton\" value=\"" . gettext("Save and Add") . "\" name=\"PledgeSubmitAndAdd\">"; } ?>
			<input type="button" class="icButton" value="<?php echo gettext("Cancel"); ?>" name="PledgeCancel" onclick="javascript:document.location='<?php if (strlen($linkBack) > 0) { echo $linkBack; } else {echo "Menu.php"; } ?>';">
		</td>
	</tr>

	<tr>
		<td>
		<table border="0" width="100%" cellspacing="0" cellpadding="4">
		<td width="50%" valign="top" align="left">
		<table cellpadding="3">
			<?php if ($dep_Type == 'Bank' and $bUseDonationEnvelopes) {?>
			<tr>
				<td class="PaymentLabelColumn"><?php echo gettext("Envelope number:"); ?></td>
				<td class="TextColumn"><input type="text" name="Envelope" id="Envelope" value="<?php echo $iEnvelope; ?>"></td>
				<td><input type="submit" class="icButton" value="<?php echo gettext("Find family->"); ?>" name="MatchEnvelope"></td>
			</tr>
			<?php } ?>
			<tr>
				<?php if ($PledgeOrPayment=='Pledge') { ?>
					<td <?php if ($PledgeOrPayment=='Pledge') echo "class=\"LabelColumn\">"; else echo "class=\"PaymentLabelColumn\">"; ?><?php echo gettext("Payment Schedule:"); ?></td>
					<td class="TextColumnWithBottomBorder">
						<select name="Schedule">
							<option value="0"><?php echo gettext("Select Schedule"); ?></option>
							<option value="Weekly" <?php if ($iSchedule == "Weekly") { echo "selected"; } ?>><?php echo gettext("Weekly"); ?></option>
							<option value="Monthly" <?php if ($iSchedule == "Monthly") { echo "selected"; } ?>><?php echo gettext("Monthly"); ?></option>
							<option value="Quarterly" <?php if ($iSchedule == "Quarterly") { echo "selected"; } ?>><?php echo gettext("Quarterly"); ?></option>
							<option value="Once" <?php if ($iSchedule == "Once") { echo "selected"; } ?>><?php echo gettext("Once"); ?></option>
							<option value="Other" <?php if ($iSchedule == "Other") { echo "selected"; } ?>><?php echo gettext("Other"); ?></option>
						</select>
					</td>
				<?php }?>

			</tr>
			<tr>
				<td <?php if ($PledgeOrPayment=='Pledge') echo "class=\"LabelColumn\">"; else echo "class=\"PaymentLabelColumn\">"; ?><?php echo gettext("Payment Method:"); ?></td>
				<td class="TextColumnWithBottomBorder">
					<select name="Method">
						<?php if ($PledgeOrPayment=='Pledge' or $dep_Type == "Bank" or !$iCurrentDeposit) { ?>
						<option value="CHECK" <?php if ($iMethod == "CHECK") { echo "selected"; } ?>><?php echo gettext("CHECK"); 						?></option>
						<option value="CASH" <?php if ($iMethod == "CASH") { echo "selected"; } ?>><?php echo gettext("CASH"); 						?></option>
						<?php } ?>
						<?php if ($PledgeOrPayment=='Pledge' or $dep_Type == "CreditCard" or !$iCurrentDeposit) { ?>
						<option value="CREDITCARD" <?php if ($iMethod == "CREDITCARD") { echo "selected"; } ?>><?php echo 						gettext("Credit Card"); ?></option>
						<?php } ?>
						<?php if ($PledgeOrPayment=='Pledge' or $dep_Type == "BankDraft" or !$iCurrentDeposit) { ?>
						<option value="BANKDRAFT" <?php if ($iMethod == "BANKDRAFT") { echo "selected"; } ?>><?php echo 						gettext("Bank Draft"); ?></option>
						<?php } ?>
					</select>
				</td>
			</tr>

			<tr>
				<td <?php if ($PledgeOrPayment=='Pledge') echo "class=\"LabelColumn\">"; else echo "class=\"PaymentLabelColumn\">"; ?><?php echo gettext("Fiscal Year:"); ?></td>
				<td class="TextColumnWithBottomBorder">
					<?php PrintFYIDSelect ($iFYID, "FYID") ?>
				</td>
			</tr>
		<tr> <?php if ($PledgeOrPayment=='Payment') { ?>
			<td width="100%" valign="top" align="left" class="PaymentLabelColumn"><?php echo gettext("Total Amount:"); ?></td>
			<td class="TextColumn"><input type="text" name="TotalAmount" id="TotalAmount" value="<?php echo $iTotalAmount; ?>"></td>
			<td><input type="submit" class="icButton" value="<?php echo gettext("Split to Funds by pledge"); ?>" name="SplitTotal"></td>
		<?php } ?>
		</tr>

		</table>
		</td>
		<td width="50%" valign="top" align="center">
		<table cellpadding="3">
			<tr>
				<td <?php if ($PledgeOrPayment=='Pledge') echo "class=\"LabelColumn\">"; else echo "class=\"PaymentLabelColumn\">"; ?><?php addToolTip("Select the pledging family from the list."); ?><?php echo gettext("FamilyID:"); ?></td>
				<td class="TextColumn">
					<select name="FamilyID">
						<option value="0" selected><?php echo gettext("Unassigned"); ?></option>
						<?php
						// Build Criteria for Head of Household
						if (!$sDirRoleHead)
							$sDirRoleHead = "1";
						$head_criteria = " per_fmr_ID = " . $sDirRoleHead;
						// If more than one role assigned to Head of Household, add OR
						$head_criteria = str_replace(",", " OR per_fmr_ID = ", $head_criteria);
						// Add Spouse to criteria
						if (intval($sDirRoleSpouse) > 0)
							$head_criteria .= " OR per_fmr_ID = $sDirRoleSpouse";
						// Build array of Head of Households and Spouses with fam_ID as the key
						$sSQL = "SELECT per_FirstName, per_fam_ID FROM person_per WHERE per_fam_ID > 0 AND (" . $head_criteria . ") ORDER BY per_fam_ID";
						$rs_head = RunQuery($sSQL);
						$aHead = "";
						while (list ($head_firstname, $head_famid) = mysql_fetch_row($rs_head)) {
							if ($head_firstname && $aHead[$head_famid])
								$aHead[$head_famid] .= " & " . $head_firstname;
							elseif ($head_firstname)
								$aHead[$head_famid] = $head_firstname;
						}
						
						while ($aRow = mysql_fetch_array($rsFamilies)) {
							extract($aRow);
							echo "<option value=\"" . $fam_ID . "\"";
							if ($iFamily == $fam_ID) { echo " selected"; }
							echo ">" . $fam_Name;
							if ($aHead[$fam_ID])
								echo ", " . $aHead[$fam_ID];
							echo " " . FormatAddressLine($fam_Address1, $fam_City, $fam_State);
						}
						?>

					</select>
				</td>
			</tr>

			<?php if ($PledgeOrPayment=='Payment' and $dep_Type == 'Bank') {?>
				<tr>
					<td class="PaymentLabelColumn"><?php echo gettext("Check number:"); ?></td>
					<td class="TextColumn"><input type="text" name="CheckNo" id="CheckNo" value="<?php echo $iCheckNo; ?>"><font color="red"><?php echo $sCheckNoError ?></font></td>
				</tr>
			<?php } ?>



			<tr>
				<td <?php if ($PledgeOrPayment=='Pledge') echo "class=\"LabelColumn\""; else echo "class=\"PaymentLabelColumn\""; ?><?php addToolTip("Format: YYYY-MM-DD<br>or enter the date by clicking on the calendar icon to the right."); ?>><?php echo gettext("Date:"); ?></td>
<?php	if (!$dDate)	$dDate = $dep_Date ?>
	
				<td class="TextColumn"><input type="text" name="Date" value="<?php echo $dDate; ?>" maxlength="10" id="sel1" size="11">&nbsp;<input type="image" onclick="return showCalendar('sel1', 'y-mm-dd');" src="Images/calendar.gif"> <span class="SmallText"><?php echo gettext("[format: YYYY-MM-DD]"); ?></span><font color="red"><?php echo $sDateError ?></font></td>
			</tr>


			<td width="100%" valign="top" align="left">

			<?php if ($bUseScannedChecks and ($dep_Type == 'Bank' or $PledgeOrPayment=='Pledge')) {?>
			<tr>
				<td <?php  if ($PledgeOrPayment=='Pledge') echo "class=\"LabelColumn\">"; else echo "class=\"PaymentLabelColumn\">";echo gettext("Scan check");?></td>
				<td><textarea name="ScanInput" rows="2" cols="90"><?php echo $tScanString?></textarea></td>
			</tr>
			<?php } ?>

<?php
			if (($dep_Type == 'CreditCard') or ($dep_Type == 'BankDraft')) {
?>
			<tr>
				<td <?php  if ($PledgeOrPayment=='Pledge') echo "class=\"LabelColumn\">"; else echo "class=\"PaymentLabelColumn\">";echo gettext("Choose online payment method");?></td>
				<td class="TextColumnWithBottomBorder">
					<select name="AutoPay">
<?php
					echo "<option value=0";
					if ($iAutID == 0)
						echo " selected";
					echo ">" . gettext ("Select online payment record") . "</option>\n";
					$sSQLTmp = "SELECT aut_ID, aut_CreditCard, aut_BankName, aut_Route, aut_Account FROM autopayment_aut WHERE aut_FamID=" . $iFamily;
					$rsFindAut = RunQuery($sSQLTmp);
					while ($aRow = mysql_fetch_array($rsFindAut))
					{
						extract($aRow);
						if ($aut_CreditCard <> "") {
							$showStr = gettext ("Credit card ...") . substr ($aut_CreditCard, strlen ($aut_CreditCard) - 4, 4);
						} else {
							$showStr = gettext ("Bank account ") . $aut_BankName . " " . $aut_Route . " " . $aut_Account;
						}
						echo "<option value=" . $aut_ID;
						if ($iAutID == $aut_ID)
							echo " selected";
						echo ">" . $showStr . "</option>\n";
					}
?>
					</select>
				</td>
			</tr>
			<?php } ?>
		</td>
		</tr>
		</table>
		</td>

	<tr>
		<td align="center">
		<?php if ($dep_type == 'Bank' and $bUseScannedChecks) { ?>
			<input type="submit" class="icButton" value="<?php echo gettext("find familiy from check account #"); ?>" name="MatchFamily">
			<input type="submit" class="icButton" value="<?php echo gettext("Set default check account number for family"); ?>" name="SetDefaultCheck">
        <?php } ?>
		</td>
	</tr>

		</table>
		</td>

	<tr>
		<td width="100%" valign="top" align="left">
		<table cellpadding="3">

			<tr>

				<td <?php if ($PledgeOrPayment=='Pledge') echo "class=\"LabelColumn\">"; else echo "class=\"PaymentLabelColumn\">"; ?><?php echo gettext("Fund Name"); ?></td>
				<td <?php if ($PledgeOrPayment=='Pledge') echo "class=\"LabelColumn\">"; else echo "class=\"PaymentLabelColumn\">"; ?><?php echo gettext("Amount"); ?></td>

				<?php if ($bEnableNonDeductible) {?>
					<td <?php if ($PledgeOrPayment=='Pledge') echo "class=\"LabelColumn\">"; else echo "class=\"PaymentLabelColumn\">"; ?><?php echo gettext("Non-deductible amount"); ?></td>
				<?php }?>

				<td <?php if ($PledgeOrPayment=='Pledge') echo "class=\"LabelColumn\">"; else echo "class=\"PaymentLabelColumn\">"; ?><?php echo gettext("Comment"); ?></td>
             </tr>

			<?php foreach ($fundId2Name as $fun_name) {
				echo "<tr>";
				echo "<td class=\"TextColumn\"><b>" . $fun_name . "</b></td>";
				echo "<td class=\"TextColumn\"><input type=\"text\" name=\"" . $fun_name . "_Amount\" id=\"" . $fun_name . "_Amount\" value=\"" . $nAmount[$fun_name] . "\"><br><font color=\"red\">" . $sAmountError[$fun_name] . "</font></td>";
				if ($bEnableNonDeductible) {
					echo "<td class=\"TextColumn\"><input type=\"text\" name=\"" . $fun_name . "_NonDeductible\" id=\"" . $fun_name . "_Amount\" value=\"" . $nNonDeductible[$fun_name] . "\"><br><font color=\"red\">" . $sAmountError[$fun_name] . "</font></td>";
				}
				echo "<td class=\"TextColumn\"><input type=\"text\" name=\"" . $fun_name . "_Comment\" id=\"" . $fun_name . "_Comment\" value=\"" . $sComment[$fun_name] . "\"></td>";
				echo "</tr>";
			}
			?>
		</td>
		</table>
		</tr>

</table>
</form>

<?php
require "Include/Footer.php";
?>
