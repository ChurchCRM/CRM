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

	$dDate = FilterInput($_POST["Date"]);
	$sComment = FilterInput($_POST["Comment"]);
	if (array_key_exists ("Closed", $_POST))
		$bClosed = FilterInput($_POST["Closed"]);
	else
		$bClosed = false;
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
						 a.aut_CreditCardVanco AS creditcardvanco,
						 a.aut_ExpMonth AS expMonth,
						 a.aut_ExpYear AS expYear,
						 a.aut_BankName AS bankName,
						 a.aut_Route AS route,
						 a.aut_Account AS account,
						 a.aut_AccountVanco AS accountvanco,
						 a.aut_Serial AS serial,
						 a.aut_NextPayDate AS authDate,
						 a.aut_Interval AS aut_Interval,
						 a.aut_ID AS aut_ID
			 FROM pledge_plg
			 LEFT JOIN autopayment_aut a ON plg_aut_ID = a.aut_ID
			 LEFT JOIN donationfund_fun b ON plg_fundID = b.fun_ID
			 WHERE plg_depID = " . $iDepositSlipID . " ORDER BY pledge_plg.plg_date";
	$rsTransactions = RunQuery($sSQL);

	if ($sElectronicTransactionProcessor == "AuthorizeNet") {
    // This file is generated by Composer
    require_once dirname(__FILE__) . '/../vendor/autoload.php';
		include ("Include/AuthorizeNetConfig.php"); // Specific account information is in here
	}

	if ($sElectronicTransactionProcessor == "Vanco") {
		include "Include/vancowebservices.php";
		include "Include/VancoConfig.php";
	}

	while ($aTransaction =mysql_fetch_array($rsTransactions))
	{
		extract($aTransaction);

		if ($plg_aut_Cleared) // If this one already cleared do not submit it again.
			continue;

		if ($sElectronicTransactionProcessor == "AuthorizeNet") {
			$donation = new AuthorizeNetAIM;
			$donation->amount = "$plg_amount";
			$donation->first_name = $firstName;
			$donation->last_name = $lastName;
			$donation->address = $address1 . $address2;
			$donation->city = $city;
			$donation->state = $state;
			$donation->zip = $zip;
			$donation->country = $country;
			$donation->description = "UU Nashua Pledge";
			$donation->email = $email;
			$donation->phone = $phone;

			// not setting these
	//		$donation->allow_partial_auth
	//		$donation->auth_code
	//		$donation->authentication_indicator
	//		$donation->bank_aba_code
	//		$donation->bank_check_number
	//		$donation->card_code
	//		$donation->cardholder_authentication_value
	//		$donation->company
	//		$donation->cust_id
	//		$donation->customer_ip
	//		$donation->delim_char
	//		$donation->delim_data
	//		$donation->duplicate_window
	//		$donation->duty
	//		$donation->echeck_type
	//		$donation->email_customer
	//		$donation->encap_char
	//		$donation->fax
	//		$donation->footer_email_receipt
	//		$donation->freight
	//		$donation->header_email_receipt
	//		$donation->invoice_num
	//		$donation->line_item
	//		$donation->login
	//		$donation->method
	//		$donation->po_num
	//		$donation->recurring_billing
	//		$donation->relay_response
	//		$donation->ship_to_address
	//		$donation->ship_to_city
	//		$donation->ship_to_company
	//		$donation->ship_to_country
	//		$donation->ship_to_first_name
	//		$donation->ship_to_last_name
	//		$donation->ship_to_state
	//		$donation->ship_to_zip
	//		$donation->split_tender_id
	//		$donation->tax
	//		$donation->tax_exempt
	//		$donation->test_request
	//		$donation->tran_key
	//		$donation->trans_id
	//		$donation->type
	//		$donation->version

			if ($dep_Type == "CreditCard") {
				$donation->card_num = $creditCard;
				$donation->exp_date = $expMonth . "/" . $expYear;
			} else {
				// check payment info if supplied...

	// Use eCheck:
				$donation->bank_acct_name = $firstName . ' ' . $lastName;
				$donation->bank_acct_num = $account;
				$donation->bank_acct_type = 'CHECKING';
				$donation->bank_name = $bankName;

				$donation->setECheck(
				    $route,
				    $account,
				    'CHECKING',
				    $bankName,
				    $firstName . ' ' . $lastName,
				    'WEB'
				);
			}

			$response = $donation->authorizeAndCapture();
			if ($response->approved) {
			    $transaction_id = $response->transaction_id;
			}

			if ($response->approved) {
				// Push the authorized transaction date forward by the interval
				$sSQL = "UPDATE autopayment_aut SET aut_NextPayDate=DATE_ADD('" . $authDate . "', INTERVAL " . $aut_Interval . " MONTH) WHERE aut_ID = " . $aut_ID . " AND aut_Amount = " . $plg_amount;
				RunQuery ($sSQL);
				// Update the serial number in any case, even if this is not the scheduled payment
				$sSQL = "UPDATE autopayment_aut SET aut_Serial=aut_Serial+1 WHERE aut_ID = " . $aut_ID;
				RunQuery ($sSQL);
			}

			if (! ($response->approved))
				$response->approved = 0;

			$sSQL = "UPDATE pledge_plg SET plg_aut_Cleared=" . $response->approved . " WHERE plg_plgID=" . $plg_plgID;
			RunQuery($sSQL);

			if ($plg_aut_ResultID) {
				// Already have a result record, update it.
				$sSQL = "UPDATE result_res SET " .
								"res_echotype1	='" . $response->response_reason_code	. "'," .
								"res_echotype2	='" . $response->response_reason_text	. "'," .
								"res_echotype3	='" . $response->response_code	. "'," .
								"res_authorization	='" . $response->response_subcode	. "'," .
								"res_order_number	='" . $response->authorization_code	. "'," .
								"res_reference	='" . $response->avs_response	. "'," .
								"res_status	='" . $response->transaction_id	. "'" .
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
								res_status)
							VALUES (" .
								"'" . mysql_real_escape_string($response->response_reason_code) . "'," .
								"'" . mysql_real_escape_string($response->response_reason_text) . "'," .
								"'" . mysql_real_escape_string($response->response_code) . "'," .
								"'" . mysql_real_escape_string($response->response_subcode) . "'," .
								"'" . mysql_real_escape_string($response->authorization_code) . "'," .
								"'" . mysql_real_escape_string($response->avs_response) . "'," .
								"'" . mysql_real_escape_string($response->transaction_id) . "')";
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
		} else if ($sElectronicTransactionProcessor == "Vanco") {
			$customerid = "$aut_ID";  // This is an optional value that can be used to indicate a unique customer ID that is used in your system
			// put aut_ID into the $customerid field
			// Create object to preform API calls

			$workingobj = new VancoTools($VancoUserid, $VancoPassword, $VancoClientid, $VancoEnc_key, $VancoTest);
			// Call Login API to receive a session ID to be used in future API calls
			$sessionid = $workingobj->vancoLoginRequest();
			// Create content to be passed in the nvpvar variable for a TransparentRedirect API call
			$nvpvarcontent = $workingobj->vancoEFTTransparentRedirectNVPGenerator($VancoUrltoredirect,$customerid,"","NO");

			$paymentmethodref = "";
			if ($dep_Type == "CreditCard") {
				$paymentmethodref = $creditcardvanco;
			} else {
				$paymentmethodref = $accountvanco;
			}

			$addRet = $workingobj->vancoEFTAddCompleteTransactionRequest(
			    $sessionid, // $sessionid
			    $paymentmethodref,// $paymentmethodref
			    '0000-00-00',// $startdate
			    'O',// $frequencycode
			    $customerid,// $customerid
			    "",// $customerref
			    $firstName . " " . $lastName,// $name
			    $address1,// $address1
			    $address2,// $address2
			    $city,// $city
				$state,// $state
				$zip,// $czip
				$phone,// $phone
				"No",// $isdebitcardonly
				"",// $enddate
				"",// $transactiontypecode
				"",// $funddict
				$plg_amount);// $amount

			$retArr = array();
			parse_str($addRet, $retArr);

			$errListStr = "";
			if (array_key_exists ("errorlist", $retArr))
				$errListStr = $retArr["errorlist"];

			$bApproved = false;

			// transactionref=None&paymentmethodref=16610755&customerref=None&requestid=201411222041237455&errorlist=167
			if ($retArr["transactionref"]!="None" && $errListStr == "")
				$bApproved = true;

			$errStr = "";
			if ($errListStr != "") {
				$errList = explode (",", $errListStr);
				foreach ($errList as $oneErr) {
					$errStr .= $workingobj->errorString ($oneErr . "<br>\n");
				}
			}
			if ($errStr == "")
				$errStr = "Success: Transaction reference number " . $retArr["transactionref"] . "<br>";


			if ($bApproved) {
				// Push the authorized transaction date forward by the interval
				$sSQL = "UPDATE autopayment_aut SET aut_NextPayDate=DATE_ADD('" . $authDate . "', INTERVAL " . $aut_Interval . " MONTH) WHERE aut_ID = " . $aut_ID . " AND aut_Amount = " . $plg_amount;
				RunQuery ($sSQL);
				// Update the serial number in any case, even if this is not the scheduled payment
				$sSQL = "UPDATE autopayment_aut SET aut_Serial=aut_Serial+1 WHERE aut_ID = " . $aut_ID;
				RunQuery ($sSQL);
			}

			$sSQL = "UPDATE pledge_plg SET plg_aut_Cleared='" . $bApproved . "' WHERE plg_plgID=" . $plg_plgID;
			RunQuery($sSQL);

			if ($plg_aut_ResultID) {
				// Already have a result record, update it.

				$sSQL = "UPDATE result_res SET res_echotype2='" . mysql_real_escape_string($errStr)	. "' WHERE res_ID=" . $plg_aut_ResultID;
				RunQuery($sSQL);
			} else {
				// Need to make a new result record
				$sSQL = "INSERT INTO result_res (res_echotype2) VALUES ('" . mysql_real_escape_string($errStr) . "')";
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
	}

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
