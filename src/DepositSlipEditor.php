<?php
/*******************************************************************************
 *
 *  filename    : DepositSlipEditor.php
 *  last change : 2014-12-14
 *  website     : http://www.churchcrm.io
 *  copyright   : Copyright 2001, 2002, 2003-2014 Deane Barker, Chris Gebhardt, Michael Wilt
 *
 *  ChurchCRM is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

//Include the function library
require "Include/Config.php";
require "Include/Functions.php";
require "Include/MICRFunctions.php";

$linkBack = "";
$iDepositSlipID = 0;
$dep_Type = "";
$sDateError = "";
$sDepositType = "";
$sComment = "";
$bClosed = false;

if (array_key_exists ("linkBack", $_GET))
	$linkBack = FilterInput($_GET["linkBack"]);
if (array_key_exists ("DepositSlipID", $_GET))
	$iDepositSlipID = FilterInput($_GET["DepositSlipID"], 'int');

if ($iDepositSlipID) {
	// Get the current deposit slip
	$sSQL = "SELECT * from deposit_dep WHERE dep_ID = " . $iDepositSlipID;
	$rsDeposit = RunQuery($sSQL);
	extract(mysql_fetch_array($rsDeposit));
	// Set current deposit slip
	$_SESSION['iCurrentDeposit'] = $iDepositSlipID;

	// Set the session variable for default payment type so the new payment form will come up correctly
	if ($dep_Type == "Bank")
		$_SESSION['idefaultPaymentMethod'] = "CHECK";
	else if ($dep_Type == "CreditCard")
		$_SESSION['idefaultPaymentMethod'] = "CREDITCARD";
	else if ($dep_Type == "BankDraft")
		$_SESSION['idefaultPaymentMethod'] = "BANKDRAFT";
	else if ($dep_Type == "eGive")
		$_SESSION['idefaultPaymentMethod'] = "EGIVE";

	// Security: User must have finance permission or be the one who created this deposit
	if (! ($_SESSION['bFinance'] || $_SESSION['iUserID']==$dep_EnteredBy)) {
		Redirect("Menu.php");
		exit;
	}
}

//Set the page title
if (! $iDepositSlipID)
	$sPageTitle = $dep_Type . " " . gettext("Deposit Slip Number: TBD");
else
	$sPageTitle = $dep_Type . " " . gettext("Deposit Slip Number: ") . $iDepositSlipID;

//Is this the second pass?
if (isset($_POST["DepositSlipSubmit"])) {
	//Get all the variables from the request object and assign them locally
	$dDate = FilterInput($_POST["Date"]);
	$sComment = FilterInput($_POST["Comment"]);
	$bClosed = false;
	if (array_key_exists ("Closed", $_POST))
		$bClosed = FilterInput($_POST["Closed"]);
	$sDepositType = FilterInput($_POST["DepositType"]);

	if (! $bClosed)
		$bClosed = 0;

	//Initialize the error flag
	$bErrorFlag = false;

	// Validate Date
	if (strlen($dDate) > 0)
	{
		list($iYear, $iMonth, $iDay) = sscanf($dDate,"%04d-%02d-%02d");
		if ( !checkdate($iMonth,$iDay,$iYear) )
		{
			$sDateError = "<span style=\"color: red; \">" . gettext("Not a valid Date") . "</span>";
			$bErrorFlag = true;
		}
	}

	//If no errors, then let's update...
	if (!$bErrorFlag)
	{
		// New deposit slip
		if (! $iDepositSlipID)
		{
			$sSQL = "INSERT INTO deposit_dep (dep_Date, dep_Comment, dep_EnteredBy, dep_Closed, dep_Type)
			VALUES ('" . $dDate . "','" . $sComment . "'," . $_SESSION['iUserID'] . "," . $bClosed . ",'" . $sDepositType . "')";
			$bGetKeyBack = True;

		// Existing record (update)
		} else {
			$sSQL = "UPDATE deposit_dep SET dep_Date = '" . $dDate . "', dep_Comment = '" . $sComment . "', dep_EnteredBy = ". $_SESSION['iUserID'] . ", dep_Closed = " . $bClosed . " WHERE dep_ID = " . $iDepositSlipID . ";";
			$bGetKeyBack = false;

			if ($bClosed && ($dep_Type=='CreditCard' || $dep_Type == 'BankDraft')) {
				// Delete any failed transactions on this deposit slip now that it is closing
				$q = "DELETE FROM pledge_plg WHERE plg_depID = " . $iDepositSlipID . " AND plg_PledgeOrPayment=\"Payment\" AND plg_aut_Cleared=0" ;
				RunQuery($q);
			}
		}

		//Execute the SQL
		RunQuery($sSQL);

		// If this is a new deposit slip, get the key back
		if ($bGetKeyBack)
		{
			$sSQL = "SELECT MAX(dep_ID) AS iDepositSlipID FROM deposit_dep";
			$rsDepositSlipID = RunQuery($sSQL);
			extract(mysql_fetch_array($rsDepositSlipID));
			$_SESSION['iCurrentDeposit'] = $iDepositSlipID;
		}

		if (isset($_POST["DepositSlipSubmit"]))
		{
			if ($linkBack != "") {
				Redirect($linkBack);
			} else { //Send to the view of this DepositSlip
				Redirect("DepositSlipEditor.php?linkBack=" . $linkBack . "&DepositSlipID=" . $iDepositSlipID);
			}
		}
	}
} else if (isset($_POST["DepositSlipLoadAuthorized"])) {

	// Create all the payment records that have been authorized

	//Get all the variables from the request object and assign them locally
	$dDate = FilterInput($_POST["Date"]);
	$sComment = FilterInput($_POST["Comment"]);
	if (array_key_exists ("Closed", $_POST))
		$bClosed = FilterInput($_POST["Closed"]);
	else
		$bClosed = false;
	$sDepositType = FilterInput($_POST["DepositType"]);
	if (! $bClosed)
		$bClosed = 0;

	// Create any transactions that are authorized as of today
	if ($dep_Type == "CreditCard") {
		$enableStr = "aut_EnableCreditCard=1";
	} else {
		$enableStr = "aut_EnableBankDraft=1";
	}

	// Get all the families with authorized automatic transactions
	$sSQL = "SELECT * FROM autopayment_aut WHERE " . $enableStr . " AND aut_NextPayDate<='" . date('Y-m-d') . "'";

	$rsAuthorizedPayments = RunQuery($sSQL);

	while ($aAutoPayment =mysql_fetch_array($rsAuthorizedPayments))
	{
		extract($aAutoPayment);
		if ($dep_Type == "CreditCard") {
			$method = "CREDITCARD";
		} else {
			$method = "BANKDRAFT";
		}
		$dateToday = date ("Y-m-d");

		$amount = $aut_Amount;
		$FYID = $aut_FYID;
		$interval = $aut_Interval;
		$fund = $aut_Fund;
		$authDate = $aut_NextPayDate;
		$sGroupKey = genGroupKey($aut_ID, $aut_FamID, $fund, $dateToday);

		// Check for this automatic payment already loaded into this deposit slip
		$sSQL = "SELECT plg_plgID FROM pledge_plg WHERE plg_depID=" . $dep_ID . " AND plg_aut_ID=" . $aut_ID;
		$rsDupPayment = RunQuery ($sSQL);
		$dupCnt = mysql_num_rows ($rsDupPayment);

		if ($amount > 0.00 && $dupCnt == 0) {
			$sSQL = "INSERT INTO pledge_plg (plg_FamID,
											plg_FYID,
											plg_date,
											plg_amount,
											plg_method,
											plg_DateLastEdited,
											plg_EditedBy,
											plg_PledgeOrPayment,
											plg_fundID,
											plg_depID,
											plg_aut_ID,
											plg_CheckNo,
											plg_GroupKey)
								VALUES (" .
											$aut_FamID . "," .
											$FYID . "," .
											"'" . date ("Y-m-d") . "'," .
											$amount . "," .
											"'" . $method . "'," .
											"'" . date ("Y-m-d") . "'," .
											$_SESSION['iUserID'] . "," .
											"'Payment'," .
											$fund . "," .
											$dep_ID . "," .
											$aut_ID . "," .
											$aut_Serial . "," .
											"'" . $sGroupKey . "')";
			RunQuery ($sSQL);
		}
	}
} else if (isset($_POST["DepositSlipRunTransactions"])) {
    $deposit = \ChurchCRM\Base\DepositQuery::create()->findOneById($iDepositSlipID);
    $deposit->runTransactions();
	
} else {

	//FirstPass
	//Are we editing or adding?
	if ($iDepositSlipID)	{
		//Editing....
		//Get all the data on this record

		$sSQL = "SELECT * FROM deposit_dep WHERE dep_ID = " . $iDepositSlipID;
		$rsDepositSlip = RunQuery($sSQL);
		extract(mysql_fetch_array($rsDepositSlip));

		$dDate = $dep_Date;
		$sComment = $dep_Comment;
		$bClosed = $dep_Closed;
		$sDepositType = $dep_Type;
	} else {
		//Adding....
		//Set defaults
	}
}

if ($iDepositSlipID) {
	//Get the payments for this deposit slip
	$sSQL = "SELECT plg_plgID, plg_famID, plg_date, plg_FYID, plg_amount, plg_CheckNo, plg_method, plg_comment, plg_aut_Cleared,
	         a.fam_Name AS FamilyName, b.fun_Name as fundName, plg_NonDeductible, plg_GroupKey
			 FROM pledge_plg
			 LEFT JOIN family_fam a ON plg_FamID = a.fam_ID
			 LEFT JOIN donationfund_fun b ON plg_fundID = b.fun_ID
			 WHERE plg_depID = " . $iDepositSlipID . " AND plg_PledgeOrPayment='Payment' ORDER BY pledge_plg.plg_plgID, pledge_plg.plg_date";
	$rsPledges = RunQuery($sSQL);
} else {
	$rsPledges = 0;
	$dDate = date("Y-m-d");	// Set default date to today
}

// Set Current Deposit setting for user
if ($iDepositSlipID) {
	$_SESSION['iCurrentDeposit'] = $iDepositSlipID;		// Probably redundant
	$sSQL = "UPDATE user_usr SET usr_currentDeposit = '$iDepositSlipID' WHERE usr_per_id = \"".$_SESSION['iUserID']."\"";
	$rsUpdate = RunQuery($sSQL);
}

require "Include/Header.php";
?>


<form method="post" action="DepositSlipEditor.php?<?= "linkBack=" . $linkBack . "&DepositSlipID=".$iDepositSlipID ?>" name="DepositSlipEditor">

<table cellpadding="3" align="center">

	<tr>
		<td align="center">
			<input type="submit" class="btn" value="<?= gettext("Save") ?>" name="DepositSlipSubmit">
			<input type="button" class="btn" value="<?= gettext("Cancel") ?>" name="DepositSlipCancel" onclick="javascript:document.location='<?php if (strlen($linkBack) > 0) { echo $linkBack; } else {echo "Menu.php"; } ?>';">
			<?php 
      if (mysql_num_rows($rsPledges) > 0)
      {
      ?>
      <input type="button" class="btn" value="<?= gettext("Deposit Slip Report") ?>" name="DepositSlipGeneratePDF" onclick="javascript:window.open(window.CRM.root+'/api/deposits/<?= $iDepositSlipID ?>/pdf');">
      <input type="button" class="btn" value="<?= gettext("More Reports") ?>" name="DepositSlipGeneratePDF" onclick="javascript:document.location='FinancialReports.php';">
      <?php
      }
			if ($iDepositSlipID && $sDepositType && !$dep_Closed) {
				if ($sDepositType == "eGive") {
					echo "<input type=button class=btn value=\"".gettext("Import eGive")."\" name=ImporteGive onclick=\"javascript:document.location='eGive.php?DepositSlipID=$iDepositSlipID&linkBack=DepositSlipEditor.php?DepositSlipID=$iDepositSlipID&PledgeOrPayment=Payment&CurrentDeposit=$iDepositSlipID';\">";
				} else {
					echo "<input type=button class=btn value=\"".gettext("Add Payment")."\" name=AddPayment onclick=\"javascript:document.location='PledgeEditor.php?CurrentDeposit=$iDepositSlipID&PledgeOrPayment=Payment&linkBack=DepositSlipEditor.php?DepositSlipID=$iDepositSlipID&PledgeOrPayment=Payment&CurrentDeposit=$iDepositSlipID';\">";
				} ?>

				<?php if ($dep_Type == 'BankDraft' || $dep_Type == 'CreditCard') { ?>
					<input type="submit" class="btn" value="<?= gettext("Load Authorized Transactions") ?>" name="DepositSlipLoadAuthorized">
    					<input type="submit" class="btn" value="<?= gettext("Run Transactions") ?>" name="DepositSlipRunTransactions">
			    	<?php } ?>
		    <?php } ?>
		</td>
	</tr>

	<tr>
		<td>
		<table cellpadding="3">
			<tr>
				<td class="LabelColumn"><?= gettext("Date:") ?></td>
				<td class="TextColumn"><input type="text" name="Date" value="<?= $dDate ?>" maxlength="10" id="DepositDate" size="11"><font color="red"><?php echo $sDateError ?></font></td>
			</tr>


			<?php
			if (!$iDepositSlipID || !$sDepositType)
			{
				$selectOther = "";
				$selectCreditCard = "";
				$selectBankDraft = "";
				$selecteGive = "";

				echo "<tr><td class=LabelColumn>".gettext("Deposit Type:")."</td>";
				if ($sDepositType == "BankDraft")
					$selectBankDraft = "Checked ";
				elseif ($sDepositType == "CreditCard")
					$selectCreditCard = "Checked ";
				elseif ($sDepositType == "eGive")
					$selecteGive = "Checked ";
				else
					$selectOther = "Checked ";
				echo "<td class=TextColumn><input type=radio name=DepositType id=DepositType value=\"Bank\" $selectOther>".gettext("Bank")." &nbsp; ";
				echo "<input type=radio name=DepositType id=DepositType value=\"CreditCard\" $selectCreditCard>".gettext("Credit Card")." &nbsp; ";
				echo "<input type=radio name=DepositType id=DepositType value=\"BankDraft\" $selectBankDraft>".gettext("Bank Draft")." &nbsp; ";
				echo "<input type=radio name=DepositType id=DepositType value=\"eGive\" $selecteGive>".gettext("eGive")."</td></td>";
			} else {
				echo "<input type=hidden name=DepositType id=DepositType value=\"$sDepositType\"></td></td>";
			}
			?>

			<tr>
				<td class="LabelColumn"><?= gettext("Comment:") ?></td>
				<td class="TextColumn"><input type="text" size=40 name="Comment" id="Comment" value="<?= $sComment ?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?= gettext("Closed:") ?></td>
				<td class="TextColumn"><input type="checkbox" name="Closed" value="1" <?php if ($bClosed) echo " checked";?>><?= gettext("Close deposit slip (remember to press Save)") ?>
<?php
				if ($dep_Type == 'BankDraft' || $dep_Type == 'CreditCard') {
					echo "<p>" . gettext("Important note: failed transactions will be deleted permanantly when the deposit slip is closed.") . "</p>";
				}
?>
			</tr>

		</table>
		</td>
	</form>
</table>

<br>
<?php if ($iDepositSlipID) {

	// Get deposit totals
	$sSQL = "SELECT SUM(plg_amount) FROM pledge_plg WHERE plg_depID = '$iDepositSlipID' AND plg_PledgeOrPayment = 'Payment'";
	$rsDepositTotal = RunQuery($sSQL);
	list ($deposit_total) = mysql_fetch_row($rsDepositTotal);
	$sSQL = "SELECT SUM(plg_amount) FROM pledge_plg WHERE plg_depID = '$iDepositSlipID' AND plg_PledgeOrPayment = 'Payment' AND plg_method = 'CASH'";
	$rsDepositTotal = RunQuery($sSQL);
	list ($totalCash) = mysql_fetch_row($rsDepositTotal);
	$sSQL = "SELECT SUM(plg_amount) FROM pledge_plg WHERE plg_depID = '$iDepositSlipID' AND plg_PledgeOrPayment = 'Payment' AND plg_method = 'CHECK'";
	$rsDepositTotal = RunQuery($sSQL);
	list ($totalChecks) = mysql_fetch_row($rsDepositTotal);
	$sSQL = "SELECT COUNT(plg_plgID) AS deposit_total FROM pledge_plg WHERE plg_depID = '$iDepositSlipID' AND plg_PledgeOrPayment = 'Payment'";
	$rsDepositTotal = RunQuery($sSQL);
	list ($totalItems) = mysql_fetch_row($rsDepositTotal);
	if (!$totalItems)
		$totalItems = "0";
	$sSQL = "SELECT COUNT(plg_plgID) AS deposit_total FROM pledge_plg WHERE plg_depID = '$iDepositSlipID' AND plg_PledgeOrPayment = 'Payment' AND plg_method = 'CASH'";
	$rsDepositTotal = RunQuery($sSQL);
	list ($totalCashItems) = mysql_fetch_row($rsDepositTotal);
	if (!$totalCashItems)
		$totalCashItems = "0";
	$sSQL = "SELECT COUNT(plg_plgID) AS deposit_total FROM pledge_plg WHERE plg_depID = '$iDepositSlipID' AND plg_PledgeOrPayment = 'Payment' AND plg_method = 'CHECK'";
	$rsDepositTotal = RunQuery($sSQL);
	list ($totalCheckItems) = mysql_fetch_row($rsDepositTotal);
	if (!$totalCheckItems)
		$totalCheckItems = "0";
	echo "<b>\$$deposit_total - ".gettext("Total Amount")." </b> &nbsp; (".gettext("Items").": $totalItems)<br>";
	if ($totalCash)
		echo "<i><b>\$$totalCash - ".gettext("Total Cash")." </b> &nbsp; ".gettext("Items").": $totalCashItems)</i><br>";
	if ($totalChecks)
		echo "<i><b>\$$totalChecks - ".gettext("Total Checks")."</b> &nbsp; ".gettext("Items").": $totalCheckItems)</i><br>";
	echo "<br>";
?>
<b><?= gettext("Payments on this deposit slip:") ?></b>
<br>

<table cellpadding="5" cellspacing="0" width="100%">

<tr class="TableHeader">
	<td><?= gettext("Family") ?></td>
	<td><?= gettext("Date") ?></td>
	<td><?= gettext("Fiscal Year") ?></td>
<?php if ($dep_Type == 'Bank') { ?>
	<td><?= gettext("Check #") ?></td>
<?php } ?>
	<td><?= gettext("Fund") ?></td>
	<td><?= gettext("Amount") ?></td>
	<td><?= gettext("NonDeduct") ?></td>
	<td><?= gettext("Method") ?></td>
	<td><?= gettext("Comment") ?></td>
<?php if ($dep_Type == 'BankDraft' || $dep_Type == 'CreditCard') { ?>
	<td><?= gettext("Cleared") ?></td>
<?php } ?>
	<?php if ($dep_Closed) { ?>
	<td><?= gettext("View Detail") ?></td>
	<?php } else { ?>
	<td><?= gettext("Edit") ?></td>
	<td><?= gettext("Delete") ?></td>
	<?php } ?>
<?php if ($dep_Type == 'BankDraft' || $dep_Type == 'CreditCard') { ?>
	<td><?= gettext("Details") ?></td>
<?php } ?>
</tr>

<?php

// Loop through all gifts
// there can be multiple 'check' records that contain data for a single deposit, due to allowing a single payment to be split to several pledge funds.  We need to therefore collect information from those 'like' records and create a single deposit line from those multiple records.  We'll create a unique key that contains the family name and check number as a map key and build the record into vertical bar separated records.
// but because we're doing this only for checks, we need to then save the data into a unique 'payment' hash and later unpack the now combined check info into that payment hash.  (there's probably a better way to do this, but this should work for now).

$depositOrder = 1;
$depositHashOrder2Key = array ();
$depositArray = array ();
$depositHash = array ();

while ($aRow = mysql_fetch_array($rsPledges)) {
	extract($aRow);

	if (array_key_exists($plg_GroupKey, $depositHash)) {
		// add/tweak fields so existing key'ed record contains information of new record

		// we could coherency check checkNo, famID, and date, but we won't since I don't know how we'd surface the error
		list($e_plg_CheckNo, $e_plg_famID, $e_plg_date, $e_plg_FYID, $e_plg_amount, $e_fundName, $e_plg_comment, $e_plg_aut_Cleared, $e_plg_NonDeductible) = explode("|", $depositHash[$plg_GroupKey]);

		unset($depositHash[$plg_GroupKey]);

		$n_fundName = $e_fundName . "," . $fundName;
		$n_plg_comment = $e_plg_comment . "," . $plg_comment;
		$n_amount = $e_plg_amount + $plg_amount;
		$n_plg_NonDeductible = $e_plg_NonDeductible + $plg_NonDeductible;

		$depositHash[$plg_GroupKey] = $plg_CheckNo . "|" .  $plg_famID . "|" . $plg_date . "|" . $plg_FYID . "|" . $n_amount . "|" . $n_fundName . "|" . $n_plg_comment . "|" . $plg_aut_Cleared . "|" . $n_plg_NonDeductible . "|" . $plg_plgID . "|" . $plg_method . "|" . $FamilyName;

	} else {
		$depositArray[$depositOrder] = 0;
		$depositHashOrder2Key[$depositOrder] = $plg_GroupKey;
		++$depositOrder;
		$depositHash[$plg_GroupKey] = $plg_CheckNo . "|" .  $plg_famID . "|" . $plg_date . "|" . $plg_FYID . "|" . $plg_amount . "|" . $fundName . "|" . $plg_comment . "|" . $plg_aut_Cleared . "|" . $plg_NonDeductible . "|" . $plg_plgID . "|" . $plg_method . "|" . $FamilyName;
	}
}

if (count ($depositHashOrder2Key)) {
	foreach ($depositHashOrder2Key as $order => $key) {
		$depositArray[$order] = $key . "%" . $depositHash[$key];
	}
}

$tog = 0;
if (count ($depositArray)) {
foreach ($depositArray as $order => $value) {
	// key is: method-specific-id, plg_famID, plg_funID, plg_data

	list($plg_GroupKey, $data) = explode("%", $value);
	list($plg_CheckNo, $plg_famID, $plg_date, $plg_FYID, $plg_amount, $fundName, $plg_comment, $plg_aut_Cleared, $plg_NonDeductible, $plg_plgID, $plg_method, $FamilyName) = explode("|", $data);

	$tog = (! $tog);

	if ($tog)
		$sRowClass = "PaymentRowColorA";
	else
		$sRowClass = "PaymentRowColorB";
	?>

	<tr class="<?= $sRowClass ?>">
		<td>
			<?= $FamilyName ?>&nbsp;
		</td>
		<td>
			<?= $plg_date ?>&nbsp;
		</td>
		<td>
			<?= MakeFYString ($plg_FYID) ?>&nbsp;
		</td>
<?php if ($dep_Type == 'Bank') { ?>
		<td>
			<?= $plg_CheckNo ?>&nbsp;
		</td>
<?php } ?>
		<td>
			<?= $fundName ?>&nbsp;
		</td>
		<td align=center>
			<?= $plg_amount ?>&nbsp;
		</td>
		<td align=center>
			<?= $plg_NonDeductible ?>&nbsp;
		</td>
		<td>
			<?= $plg_method ?>&nbsp;
		</td>
		<td>
			<?= $plg_comment ?>&nbsp;
		</td>
<?php if ($dep_Type == 'BankDraft' || $dep_Type == 'CreditCard') { ?>
		<td>
			<?php if ($plg_aut_Cleared) echo "Yes"; else echo "No"; ?>&nbsp;
		</td>
<?php } ?>
		<?php if ($dep_Closed) { ?>
		<td>
			<a href="PledgeEditor.php?GroupKey=<?= $plg_GroupKey . "&linkBack=DepositSlipEditor.php?DepositSlipID=" . $iDepositSlipID ?>">View</a>
		</td>
		<?php } else { ?>
		<td>
			<a href="PledgeEditor.php?GroupKey=<?= $plg_GroupKey . "&linkBack=DepositSlipEditor.php?DepositSlipID=" . $iDepositSlipID ?>">Edit</a>
		</td>
		<td>
			<a href="PledgeDelete.php?GroupKey=<?= $plg_GroupKey . "&linkBack=DepositSlipEditor.php?DepositSlipID=" . $iDepositSlipID ?>">Delete</a>
		</td>
		<?php } ?>
<?php if ($dep_Type == 'BankDraft' || $dep_Type == 'CreditCard') { ?>
		<td>
			<a href="PledgeDetails.php?PledgeID=<?= $plg_plgID . "&linkBack=DepositSlipEditor.php?DepositSlipID=" . $iDepositSlipID ?>">Details</a>
		</td>
<?php } ?>
	</tr>
<?php
}
} // while
?>

</table>

<?php
}
?>
<script>
$("#DepositDate").datepicker({format:'yyyy-mm-dd'});
</script>
<?php require "Include/Footer.php" ?>
