<?php
/*******************************************************************************
 *
 *  filename    : DepositSlipEditor.php
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

//Include the function library
require "Include/Config.php";
require "Include/Functions.php";

require "Include/MICRFunctions.php";

$linkBack = FilterInput($_GET["linkBack"]);
$iDepositSlipID = FilterInput($_GET["DepositSlipID"]);

// Security: User must have finance permission to use this form
if (!$_SESSION['bFinance'])
{
	Redirect("Menu.php");
	exit;
}

if ($iDepositSlipID) {
	// Get the current deposit slip
	$sSQL = "SELECT * from deposit_dep WHERE dep_ID = " . $iDepositSlipID;
	$rsDeposit = RunQuery($sSQL);
	extract(mysql_fetch_array($rsDeposit));
	// Set current deposit slip
	$_SESSION['iCurrentDeposit'] = $iDepositSlipID;
}

//Set the page title
if (! $iDepositSlipID)
	$sPageTitle = $dep_Type . " " . gettext("Deposit Slip Number: TBD");
else
	$sPageTitle = $dep_Type . " " . gettext("Deposit Slip Number: ") . $iDepositSlipID;

//Is this the second pass?
if (isset($_POST["DepositSlipSubmit"]))
{
	//Get all the variables from the request object and assign them locally
	$dDate = FilterInput($_POST["Date"]);
	$sComment = FilterInput($_POST["Comment"]);
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
			} else {
				//Send to the view of this DepositSlip
				Redirect("DepositSlipEditor.php?linkBack=" . $linkBack . "&DepositSlipID=" . $iDepositSlipID);
			}
		}
	}
} else if (isset($_POST["DepositSlipLoadAuthorized"])) {

	// Create all the payment records that have been authorized

	//Get all the variables from the request object and assign them locally
	$dDate = FilterInput($_POST["Date"]);
	$sComment = FilterInput($_POST["Comment"]);
	$bClosed = FilterInput($_POST["Closed"]);
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
		$amount = $aut_Amount;
		$FYID = $aut_FYID;
		$interval = $aut_Interval;
		$fund = $aut_Fund;
		$authDate = $aut_NextPayDate;

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
														plg_aut_ID)
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
														$aut_ID . ")";
			RunQuery ($sSQL);
		}
	}
} else if (isset($_POST["DepositSlipRunTransactions"])) {

	$dDate = FilterInput($_POST["Date"]);
	$sComment = FilterInput($_POST["Comment"]);
	$bClosed = FilterInput($_POST["Closed"]);
	$sDepositType = FilterInput($_POST["DepositType"]);
	if (! $bClosed)
		$bClosed = 0;

	// Process all the transactions

	//Get the payments for this deposit slip
	$sSQL = "SELECT plg_plgID,
                   plg_amount, 
	                plg_scanString,
						 plg_aut_Cleared,
						 plg_aut_ResultID,
						 a.aut_FirstName AS firstName,
						 a.aut_LastName AS lastName,
						 a.aut_Address1 AS address1,
						 a.aut_Address2 AS address2,
						 a.aut_City AS city,
						 a.aut_State AS state,
						 a.aut_Zip AS zip,
						 a.aut_Country AS country,
						 a.aut_Phone AS phone,
						 a.aut_Email AS email,
						 a.aut_CreditCard AS creditCard,
						 a.aut_ExpMonth AS expMonth,
						 a.aut_ExpYear AS expYear,
						 a.aut_BankName AS bankName,
						 a.aut_Route AS route,
						 a.aut_Account AS account,
						 a.aut_Serial AS serial,
						 a.aut_NextPayDate AS authDate,
						 a.aut_Interval AS aut_Interval,
						 a.aut_ID AS aut_ID
			 FROM pledge_plg
			 LEFT JOIN autopayment_aut a ON plg_aut_ID = a.aut_ID
			 LEFT JOIN donationfund_fun b ON plg_fundID = b.fun_ID
			 WHERE plg_depID = " . $iDepositSlipID . " ORDER BY pledge_plg.plg_date";
	$rsTransactions = RunQuery($sSQL);

	include ("Include/echophp.class");
	include ("Include/EchoConfig.php"); // Specific account information is in here

	$echoPHP = new EchoPHP;

	while ($aTransaction =mysql_fetch_array($rsTransactions))
	{
		extract($aTransaction);

		if ($plg_aut_Cleared) // If this one already cleared do not submit it again.
			continue;

		$echoPHP->set_EchoServer("https://wwws.echo-inc.com/scripts/INR200.EXE");
		$echoPHP->set_merchant_echo_id ($EchoAccount);
		$echoPHP->set_merchant_pin ($EchoPin);

		$echoPHP->set_grand_total($plg_amount);
		$echoPHP->set_billing_phone($phone);
		$echoPHP->set_billing_address1($address1);
		$echoPHP->set_billing_address2($address2);
		$echoPHP->set_billing_city($city);
		$echoPHP->set_billing_state($state);
		$echoPHP->set_billing_zip($zip);
		$echoPHP->set_billing_country($country);
		$echoPHP->set_billing_email($email);

		$echoPHP->set_billing_ip_address($REMOTE_ADDR);

		$echoPHP->set_order_type("S");
		$echoPHP->set_cnp_recurring("Y");

		if ($dep_Type == "CreditCard") {

			$echoPHP->set_billing_first_name($firstName);
			$echoPHP->set_billing_last_name($lastName);
			$echoPHP->set_cc_number($creditCard);
			$echoPHP->set_ccexp_month($expMonth);
			$echoPHP->set_ccexp_year($expYear);

			$echoPHP->set_transaction_type("EV");

			// $echoPHP->set_cnp_security(3333);  // The three-digit MasterCard (CVC2) or VISA (CVV2) or the four-digit Discover (CID) 
			// or AMEX card-not-present security code.

		} else {
			// check payment info if supplied...
			$echoPHP->set_ec_bank_name($bankName);
			$echoPHP->set_ec_first_name($firstName);
			$echoPHP->set_ec_last_name(lastName);
			$echoPHP->set_ec_address1($address1);
			$echoPHP->set_ec_address2($address2);
			$echoPHP->set_ec_city($city);
			$echoPHP->set_ec_state($state);
			$echoPHP->set_ec_zip($zip);
			$echoPHP->set_ec_rt($route);
			$echoPHP->set_ec_account($account);
			$echoPHP->set_ec_serial_number($serial);
			$echoPHP->set_ec_payee($EchoPayee);
			//$echoPHP->set_ec_id_state("");
			//$echoPHP->set_ec_id_number("");
			//$echoPHP->set_ec_id_type("");

			$echoPHP->set_transaction_type("DD");
		}

		$echoPHP->set_debug("F");  // set to T to turn on debugging

		$echoPHP->set_counter(1);

		$submitSuccess = $echoPHP->Submit();
		if ($submitSuccess)
			$submitSuccess = 1;
		else
			$submitSuccess = 0;
		
		if ($submitSuccess) {
			// Push the authorized transaction date forward by the interval
			$sSQL = "UPDATE autopayment_aut SET aut_NextPayDate=DATE_ADD('" . $authDate . "', INTERVAL " . $aut_Interval . " MONTH), aut_Serial=aut_Serial+1 WHERE aut_ID = " . $aut_ID . " AND aut_Amount = " . $plg_amount;
			RunQuery ($sSQL);
		}

		$sSQL = "UPDATE pledge_plg SET plg_aut_Cleared=" . $submitSuccess . " WHERE plg_plgID=" . $plg_plgID;
		RunQuery($sSQL);

		if ($plg_aut_ResultID) {
			// Already have a result record, update it.
			$sSQL = "UPDATE result_res SET " .
							"res_echotype1	='" . $echoPHP->echotype1	. "'," .
							"res_echotype2	='" . $echoPHP->echotype2	. "'," .
							"res_echotype3	='" . $echoPHP->echotype3	. "'," .
							"res_authorization	='" . $echoPHP->authorization	. "'," .
							"res_order_number	='" . $echoPHP->order_number	. "'," .
							"res_reference	='" . $echoPHP->reference	. "'," .
							"res_status	='" . $echoPHP->status	. "'," .
							"res_avs_result	='" . $echoPHP->avs_result	. "'," .
							"res_security_result	='" . $echoPHP->security_result	. "'," .
							"res_mac	='" . $echoPHP->mac	. "'," .
							"res_decline_code	='" . $echoPHP->decline_code	. "'," .
							"res_tran_date	='" . $echoPHP->tran_date	. "'," .
							"res_merchant_name	='" . $echoPHP->merchant_name	. "'," .
							"res_version	='" . $echoPHP->version	. "'," .
							"res_EchoServer	='" . $echoPHP->EchoServer	. "'" .
						" WHERE res_ID=" . $plg_aut_ResultID;
			RunQuery($sSQL);
		} else {
			// Need to make a new result record
			$sSQL = "INSERT INTO result_res (
							res_echotype1,
							res_echotype2,
							res_echotype3,
							res_authorization,
							res_order_number,
							res_reference,
							res_status,
							res_avs_result,
							res_security_result,
							res_mac,
							res_decline_code,
							res_tran_date,
							res_merchant_name,
							res_version,
							res_EchoServer)
						VALUES (" .
							"'" . $echoPHP->echotype1 . "'," .
							"'" . $echoPHP->echotype2 . "'," .
							"'" . $echoPHP->echotype3 . "'," .
							"'" . $echoPHP->authorization . "'," .
							"'" . $echoPHP->order_number . "'," .
							"'" . $echoPHP->reference . "'," .
							"'" . $echoPHP->status . "'," .
							"'" . $echoPHP->avs_result . "'," .
							"'" . $echoPHP->security_result . "'," .
							"'" . $echoPHP->mac . "'," .
							"'" . $echoPHP->decline_code . "'," .
							"'" . $echoPHP->tran_date . "'," .
							"'" . $echoPHP->merchant_name . "'," .
							"'" . $echoPHP->version . "'," .
							"'" . $echoPHP->EchoServer . "')";
			RunQuery($sSQL);

			// Now get the ID for the newly created record
			$sSQL = "SELECT MAX(res_ID) AS iResID FROM result_res";
			$rsLastEntry = RunQuery($sSQL);
			extract(mysql_fetch_array($rsLastEntry));
			$plg_aut_ResultID = $iResID;

			// Poke the ID of the new result record back into this pledge (payment) record
			$sSQL = "UPDATE pledge_plg SET plg_aut_ResultID=" . $plg_aut_ResultID . " WHERE plg_plgID=" . $plg_plgID;
			RunQuery($sSQL);
		}
	}

} else {

	//FirstPass
	//Are we editing or adding?
	if ($iDepositSlipID)
	{
		//Editing....
		//Get all the data on this record
																		
		$sSQL = "SELECT * FROM deposit_dep WHERE dep_ID = " . $iDepositSlipID;
		$rsDepositSlip = RunQuery($sSQL);
		extract(mysql_fetch_array($rsDepositSlip));

		$dDate = $dep_Date;
		$sComment = $dep_Comment;
		$bClosed = $dep_Closed;
	}
	else
	{
		//Adding....
		//Set defaults
	}
}

if ($iDepositSlipID) {
	//Get the payments for this deposit slip
	$sSQL = "SELECT plg_plgID, plg_date, plg_amount, plg_CheckNo, plg_method, plg_comment, plg_aut_Cleared,
	         a.fam_Name AS FamilyName, b.fun_Name as fundName, plg_NonDeductible
			 FROM pledge_plg 
			 LEFT JOIN family_fam a ON plg_FamID = a.fam_ID
			 LEFT JOIN donationfund_fun b ON plg_fundID = b.fun_ID
			 WHERE plg_depID = " . $iDepositSlipID . " AND plg_PledgeOrPayment='Payment' ORDER BY pledge_plg.plg_date";
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

<form method="post" action="DepositSlipEditor.php?<?php echo "linkBack=" . $linkBack . "&DepositSlipID=".$iDepositSlipID?>" name="DepositSlipEditor">

<table cellpadding="3" align="center">

	<tr>
		<td align="center">
		<input type="submit" class="icButton" value="<?php echo gettext("Save"); ?>" name="DepositSlipSubmit">
			<input type="button" class="icButton" value="<?php echo gettext("Cancel"); ?>" name="DepositSlipCancel" onclick="javascript:document.location='<?php if (strlen($linkBack) > 0) { echo $linkBack; } else {echo "Menu.php"; } ?>';">
			<?php
				if ($iDepositSlipID && !$dep_Closed)
					echo "<input type=button class=icButton value=\"".gettext("Add Payment")."\" name=AddPayment onclick=\"javascript:document.location='PledgeEditor.php?linkBack=DepositSlipEditor.php?DepositSlipID=$iDepositSlipID&PledgeOrPayment=Payment&CurrentDeposit=$iDepositSlipID';\">";
			?>
			<input type="button" class="icButton" value="<?php echo gettext("Deposit Slip Report"); ?>" name="DepositSlipGeneratePDF" onclick="javascript:document.location='Reports/PrintDeposit.php?BankSlip=<?php echo ($dep_Type == 'Bank')?>';">
			<input type="button" class="icButton" value="<?php echo gettext("More Reports"); ?>" name="DepositSlipGeneratePDF" onclick="javascript:document.location='FinancialReports.php';">

			<?php if ($dep_Type == 'BankDraft' || $dep_Type == 'CreditCard') { ?>
			<input type="submit" class="icButton" value="<?php echo gettext("Load Authorized Transactions"); ?>" name="DepositSlipLoadAuthorized">
			<input type="submit" class="icButton" value="<?php echo gettext("Run Transactions"); ?>" name="DepositSlipRunTransactions">
			<?php } ?>

		</td>
	</tr>

	<tr>
		<td>
		<table cellpadding="3">
			<tr>
				<td class="LabelColumn"><?php addToolTip("Format: YYYY-MM-DD<br>or enter the date by clicking on the calendar icon to the right."); ?><?php echo gettext("Date:"); ?></td>
				<td class="TextColumn"><input type="text" name="Date" value="<?php echo $dDate; ?>" maxlength="10" id="sel1" size="11">&nbsp;<input type="image" onclick="return showCalendar('sel1', 'y-mm-dd');" src="Images/calendar.gif"> <span class="SmallText"><?php echo gettext("[format: YYYY-MM-DD]"); ?></span><font color="red"><?php echo $sDateError ?></font></td>
			</tr>

			
			<?php
			if (!$iDepositSlipID) 
			{
				echo "<tr><td class=LabelColumn>".gettext("Deposit Type:")."</td>";
				if ($sDepositType == "BankDraft")
					$select3 = "Checked ";
				elseif ($sDepositType == "CreditCard")
					$select2 = "Checked ";
				else
					$select1 = "Checked ";
				echo "<td class=TextColumn><input type=radio name=DepositType id=DepositType value=\"Bank\" $select1>".gettext("Bank")." &nbsp; ";
				echo "<input type=radio name=DepositType id=DepositType value=\"CreditCard\" $select2>".gettext("Credit Card")." &nbsp; ";
				echo "<input type=radio name=DepositType id=DepositType value=\"BankDraft\" $select3>".gettext("Bank Draft")."</td></td>";
			}
			?>
			
			<tr>
				<td class="LabelColumn"><?php echo gettext("Comment:"); ?></td>
				<td class="TextColumn"><input type="text" name="Comment" id="Comment" value="<?php echo $sComment; ?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("Closed:"); ?></td>
				<td class="TextColumn"><input type="checkbox" name="Closed" value="1" <?php if ($bClosed) echo " checked";?>><?php echo gettext("Close deposit slip (remember to press Save)"); ?>
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
	if (!$despositTotal)
		$depositTotal = "0.00";
	echo "<b>\$$deposit_total - TOTAL AMOUNT </b> &nbsp; (Items: $totalItems)<br>";
	if ($totalCash)
		echo "<i><b>\$$totalCash - Total Cash </b> &nbsp; (Items: $totalCashItems)</i><br>";
	if ($totalChecks)
		echo "<i><b>\$$totalChecks - Total Checks</b> &nbsp; (Items: $totalCheckItems)</i><br>";
	echo "<br>";
?>
<b><?php echo gettext("Payments on this deposit slip:"); ?></b>
<br>

<table cellpadding="5" cellspacing="0" width="100%">

<tr class="TableHeader">
	<td><?php echo gettext("Family"); ?></td>
	<td><?php echo gettext("Date"); ?></td>
<?php if ($dep_Type == 'Bank') { ?>
	<td><?php echo gettext("Check #"); ?></td>
<?php } ?>
	<td><?php echo gettext("Fund"); ?></td>
	<td><?php echo gettext("Amount"); ?></td>
	<td><?php echo gettext("NonDeduct"); ?></td>
	<td><?php echo gettext("Method"); ?></td>
	<td><?php echo gettext("Comment"); ?></td>
<?php if ($dep_Type == 'BankDraft' || $dep_Type == 'CreditCard') { ?>
	<td><?php echo gettext("Cleared"); ?></td>
<?php } ?>
	<td><?php echo gettext("Edit"); ?></td>
	<td><?php echo gettext("Delete"); ?></td>
<?php if ($dep_Type == 'BankDraft' || $dep_Type == 'CreditCard') { ?>
	<td><?php echo gettext("Details"); ?></td>
<?php } ?>
</tr>

<?php


$tog = 0;

//Loop through all pledges
while ($aRow =mysql_fetch_array($rsPledges))
{
	$tog = (! $tog);

	$plg_date = "";
	$plg_CheckNo = "";
	$fundName = "";
	$plg_amount = "";
	$plg_method = "";
	$plg_comment = "";
	$plg_plgID = 0;

	extract($aRow);

	if ($tog)
		$sRowClass = "PaymentRowColorA";
	else
		$sRowClass = "PaymentRowColorB";
	?>

	<tr class="<?php echo $sRowClass ?>">
		<td>
			<?php echo $FamilyName ?>&nbsp;
		</td>
		<td>
			<?php echo $plg_date ?>&nbsp;
		</td>
<?php if ($dep_Type == 'Bank') { ?>
		<td>
			<?php echo $plg_CheckNo ?>&nbsp;
		</td>
<?php } ?>
		<td>
			<?php echo $fundName ?>&nbsp;
		</td>
		<td align=center>
			<?php echo $plg_amount ?>&nbsp;
		</td>
		<td align=center>
			<?php echo $plg_NonDeductible ?>&nbsp;
		</td>
		<td>
			<?php echo $plg_method; ?>&nbsp;
		</td>
		<td>
			<?php echo $plg_comment; ?>&nbsp;
		</td>
<?php if ($dep_Type == 'BankDraft' || $dep_Type == 'CreditCard') { ?>
		<td>
			<?php if ($plg_aut_Cleared) echo "Yes"; else echo "No"; ?>&nbsp;
		</td>
<?php } ?>
		<td>
			<a href="PledgeEditor.php?PledgeID=<?php echo $plg_plgID . "&linkBack=DepositSlipEditor.php?DepositSlipID=" . $iDepositSlipID;?>">Edit</a>
		</td>
		<td>
			<a href="PledgeDelete.php?PledgeID=<?php echo $plg_plgID . "&linkBack=DepositSlipEditor.php?DepositSlipID=" . $iDepositSlipID;?>">Delete</a>
		</td>
<?php if ($dep_Type == 'BankDraft' || $dep_Type == 'CreditCard') { ?>
		<td>
			<a href="PledgeDetails.php?PledgeID=<?php echo $plg_plgID . "&linkBack=DepositSlipEditor.php?DepositSlipID=" . $iDepositSlipID;?>">Details</a>
		</td>
<?php } ?>
	</tr>
<?php
} // while
?>

</table>

<?php
} // if (!$iDepositSlipID)
?>


<?php
require "Include/Footer.php";
?>
