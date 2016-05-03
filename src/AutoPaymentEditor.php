<?php
/*******************************************************************************
 *
 *  filename    : AutoPaymentEditor.php
 *  copyright   : Copyright 2001, 2002, 2003, 2004 - 2014 Deane Barker, Chris Gebhardt, Michael Wilt
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

$linkBack = FilterInput($_GET["linkBack"]);
$iFamily = FilterInput($_GET["FamilyID"], 'int');
$iAutID = FilterInput($_GET["AutID"], 'int');

//Get Family name
if ($iFamily) {
	$sSQL = "SELECT * FROM family_fam where fam_ID = " . $iFamily;
	$rsFamily = RunQuery($sSQL);
	extract(mysql_fetch_array($rsFamily));
} else {
	$fam_Name = "TBD";
}

if ($iAutID <= 0) {  // Need to create the record so there is a place to store the Vanco payment handle
	$dNextPayDate = date ("Y-m-d");
	$tFirstName="";
	$tLastName="";
	$tAddress1=$fam_Address1;
	$tAddress2=$fam_Address2;
	$tCity=$fam_City;
	$tState=$fam_State;
	$tZip=$fam_Zip;
	$tCountry=$fam_Country;
	$tPhone=$fam_HomePhone;
	$tEmail=$fam_Email;
	$iInterval = 1;
	$iFund = 1;

	$bEnableBankDraft=0;
	$bEnableCreditCard=0;

	// Default to the current fiscal year ID
	$FYID = CurrentFY ();
	$iFYID = $FYID;

	$tCreditCard="";
	$tCreditCardVanco="";
	$tExpMonth="";
	$tExpYear="";
	$tBankName="";
	$tRoute="";
	$tAccount="";
	$tAccountVanco="";

	$nAmount = 0;

	$sSQL = "INSERT INTO autopayment_aut (
	           aut_FamID,
				  aut_EnableBankDraft,
				  aut_EnableCreditCard,
				  aut_NextPayDate,
				  aut_FYID,
				  aut_Amount,
				  aut_Interval,
				  aut_Fund,
				  aut_FirstName,
				  aut_LastName,
				  aut_Address1,
				  aut_Address2,
				  aut_City,
				  aut_State,
				  aut_Zip,
				  aut_Country,
				  aut_Phone,
				  aut_Email,
				  aut_CreditCard,
				  aut_ExpMonth,
				  aut_ExpYear,
				  aut_BankName,
				  aut_Route,
				  aut_Account,
				  aut_Serial,
				  aut_DateLastEdited,
				  aut_EditedBy)
			   VALUES (" .
					$iFamily . "," .
					$bEnableBankDraft . "," .
					$bEnableCreditCard . "," .
					"'" . $dNextPayDate . "'," .
					"'" . $iFYID . "'," .
					"'" . $nAmount . "'," .
					"'" . $iInterval . "'," .
					"'" . $iFund . "'," .
					"'" . $tFirstName . "'," .
					"'" . $tLastName . "'," .
					"'" . $tAddress1 . "'," .
					"'" . $tAddress2 . "'," .
					"'" . $tCity . "'," .
					"'" . $tState . "'," .
					"'" . $tZip . "'," .
					"'" . $tCountry . "'," .
					"'" . $tPhone . "'," .
					"'" . $tEmail . "'," .
					"'" . $tCreditCard . "'," .
					"'" . $tExpMonth . "'," .
					"'" . $tExpYear . "'," .
					"'" . $tBankName . "'," .
					"'" . $tRoute . "'," .
					"'" . $tAccount . "'," .
					"'" . 1 . "'," .
					"'" . date ("YmdHis") . "'," .
					$_SESSION['iUserID'] .
					")";
	RunQuery($sSQL);

	$sSQL = "SELECT MAX(aut_ID) AS iAutID FROM autopayment_aut";
	$rsAutID = RunQuery($sSQL);
	extract(mysql_fetch_array($rsAutID));
}

$sPageTitle = gettext("Automatic payment configuration for the " . $fam_Name . " family");

//Is this the second pass?
if (isset($_POST["Submit"]))
{
	$iFamily  = FilterInput ($_POST["Family"]);

	$enableCode = FilterInput ($_POST["EnableButton"]);
	$bEnableBankDraft = ($enableCode == 1);
	if (! $bEnableBankDraft)
		$bEnableBankDraft = 0;
	$bEnableCreditCard = ($enableCode == 2);
	if (! $bEnableCreditCard)
		$bEnableCreditCard = 0;

	$dNextPayDate = FilterInput ($_POST["NextPayDate"]);
	$nAmount = FilterInput ($_POST["Amount"]);
	if (! $nAmount)
		$nAmount = 0;

	$iFYID = FilterInput ($_POST["FYID"]);

	$iInterval = FilterInput ($_POST["Interval"],'int');
	$iFund = FilterInput ($_POST["Fund"],'int');

	$tFirstName = FilterInput ($_POST["FirstName"]);
	$tLastName = FilterInput ($_POST["LastName"]);

	$tAddress1 = FilterInput ($_POST["Address1"]);
	$tAddress2 = FilterInput ($_POST["Address2"]);
	$tCity = FilterInput ($_POST["City"]);
	$tState = FilterInput ($_POST["State"]);
	$tZip = FilterInput ($_POST["Zip"]);
	$tCountry = FilterInput ($_POST["Country"]);
	$tPhone = FilterInput ($_POST["Phone"]);
	$tEmail = FilterInput ($_POST["Email"]);

	$tCreditCard = FilterInput ($_POST["CreditCard"]);
	$tExpMonth = FilterInput ($_POST["ExpMonth"]);
	$tExpYear = FilterInput ($_POST["ExpYear"]);

	$tBankName = FilterInput ($_POST["BankName"]);
	$tRoute = FilterInput ($_POST["Route"]);
	$tAccount = FilterInput ($_POST["Account"]);

	$sSQL = "UPDATE autopayment_aut SET " .
					"aut_FamID	=	" . $iFamily . "," .
					"aut_EnableBankDraft	=" . 	$bEnableBankDraft . "," .
					"aut_EnableCreditCard	=" . 	$bEnableCreditCard . "," .
					"aut_NextPayDate	='" . $dNextPayDate . "'," .
					"aut_Amount	='" . 	$nAmount . "'," .
					"aut_FYID	='" . 	$iFYID . "'," .
					"aut_Interval	='" . 	$iInterval . "'," .
					"aut_Fund	='" . 	$iFund . "'," .
					"aut_FirstName	='" . $tFirstName . "'," .
					"aut_LastName	='" . $tLastName . "'," .
					"aut_Address1	='" . $tAddress1 . "'," .
					"aut_Address2	='" . $tAddress2 . "'," .
					"aut_City	='" . $tCity . "'," .
					"aut_State	='" . $tState . "'," .
					"aut_Zip	='" . $tZip . "'," .
					"aut_Country	='" . $tCountry . "'," .
					"aut_Phone	='" . $tPhone . "'," .
					"aut_Email	='" . $tEmail . "'," .
					"aut_CreditCard	='" . $tCreditCard . "'," .
					"aut_ExpMonth	='" . $tExpMonth . "'," .
					"aut_ExpYear	='" . $tExpYear . "'," .
					"aut_BankName	='" . $tBankName . "'," .
					"aut_Route	='" . $tRoute . "'," .
					"aut_Account	='" . $tAccount . "'," .
					"aut_DateLastEdited	='" . date ("YmdHis") . "'," .
					"aut_EditedBy	=" . 	$_SESSION['iUserID'] .
				" WHERE aut_ID = " . $iAutID;
	RunQuery($sSQL);

	if (isset($_POST["Submit"]))
	{
		// Check for redirection to another page after saving information: (ie. PledgeEditor.php?previousPage=prev.php?a=1;b=2;c=3)
		if ($linkBack != "") {
			Redirect($linkBack);
		} else {
			//Send to the view of this pledge
			Redirect("AutoPaymentEditor.php?AutID=" . $iAutID . "&FamilyID=" . $iFamily . "&linkBack=", $linkBack);
		}
	}

} else { // not submitting, just get ready to build the page
	$sSQL = "SELECT * FROM autopayment_aut WHERE aut_ID = " . $iAutID;
	$rsAutopayment = RunQuery($sSQL);
	extract(mysql_fetch_array($rsAutopayment));

	$iFamily=$aut_FamID;
	$bEnableBankDraft=$aut_EnableBankDraft;
	$bEnableCreditCard=$aut_EnableCreditCard;
	$dNextPayDate=$aut_NextPayDate;
	$iFYID = $aut_FYID;
	$nAmount=$aut_Amount;
	$iInterval=$aut_Interval;
	$iFund=$aut_Fund;
	$tFirstName=$aut_FirstName;
	$tLastName=$aut_LastName;
	$tAddress1=$aut_Address1;
	$tAddress2=$aut_Address2;
	$tCity=$aut_City;
	$tState=$aut_State;
	$tZip=$aut_Zip;
	$tCountry=$aut_Country;
	$tPhone=$aut_Phone;
	$tEmail=$aut_Email;
	$tCreditCard=$aut_CreditCard;
	$tCreditCardVanco=$aut_CreditCardVanco;
	$tExpMonth=$aut_ExpMonth;
	$tExpYear=$aut_ExpYear;
	$tBankName=$aut_BankName;
	$tRoute=$aut_Route;
	$tAccount=$aut_Account;
	$tAccountVanco=$aut_AccountVanco;
}

require "Include/Header.php";

//Get Families for the drop-down
$sSQL = "SELECT * FROM family_fam ORDER BY fam_Name";
$rsFamilies = RunQuery($sSQL);

// Get the list of funds
$sSQL = "SELECT fun_ID,fun_Name,fun_Description,fun_Active FROM donationfund_fun WHERE fun_Active = 'true'";
$rsFunds = RunQuery($sSQL);

if ($sElectronicTransactionProcessor == "Vanco") {
	include "Include/vancowebservices.php";
	include "Include/VancoConfig.php";
	$customerid = "$iAutID"; // This is an optional value that can be used to indicate a unique customer ID that is used in your system
	// put aut_ID into the $customerid field
	// Create object to preform API calls

	$workingobj = new VancoTools($VancoUserid, $VancoPassword, $VancoClientid, $VancoEnc_key, $VancoTest);
	// Call Login API to receive a session ID to be used in future API calls
	$sessionid = $workingobj->vancoLoginRequest();
	// Create content to be passed in the nvpvar variable for a TransparentRedirect API call
	$nvpvarcontent = $workingobj->vancoEFTTransparentRedirectNVPGenerator(RedirectURL("CatchCreatePayment.php"),$customerid,"","NO");
}
?>

<?php
if ($sElectronicTransactionProcessor == "Vanco") {
?>

<script>

function VancoErrorString (errNo)
{
	switch (errNo) {
		case 10: return "Invalid UserID/password combination";
		case 11: return "Session expired";
		case 25: return "All default address fields are required";
		case 32: return "Name is required";
		case 33: return "Unknown bank/bankpk";
		case 34: return "Valid PaymentType is required";
		case 35: return "Valid Routing Number Is Required";
		case 63: return "Invalid StartDate";
		case 65: return "Specified fund reference is not valid.";
		case 66: return "Invalid End Date";
		case 67: return "Transaction must have at least one transaction fund.";
		case 68: return "User is Inactive";
		case 69: return "Expiration Date Invalid";
		case 70: return "Account Type must be C, S' for ACH and must be blank for Credit Card";
		case 71: return "Class Code must be PPD, CCD, TEL, WEB, RCK or blank.";
		case 72: return "Missing Client Data: Client ID";
		case 73: return "Missing Customer Data: Customer ID or Name or Last Name & First Name";
		case 74: return "PaymentMethod is required.";
		case 76: return "Transaction Type is required";
		case 77: return "Missing Credit Card Data: Card # or Expiration Date";
		case 78: return "Missing ACH Data: Routing # or Account #";
		case 79: return "Missing Transaction Data: Amount or Start Date";
		case 80: return "Account Number has invalid characters in it";
		case 81: return "Account Number has too many characters in it";
		case 82: return "Customer name required";
		case 83: return "Customer ID has not been set";
		case 86: return "NextSettlement does not fall in today's processing dates";
		case 87: return "Invalid FrequencyPK";
		case 88: return "Processed yesterday";
		case 89: return "Duplicate Transaction (matches another with PaymentMethod and NextSettlement)";
		case 91: return "Dollar amount for transaction is over the allowed limit";
		case 92: return "Invalid client reference occurred. - Transaction WILL NOT process";
		case 94: return "Customer ID already exists for this client";
		case 95: return "Payment Method is missing Account Number";
		case 101: return "Dollar Amount for transaction cannot be negative";
		case 102: return "Updated transaction's dollar amount violates amount limit";
		case 105: return "PaymentMethod Date not valid yet.";
		case 125: return "Email Address is required.";
		case 127: return "User Is Not Proofed";
		case 134: return "User does not have access to specified client.";
		case 157: return "Client ID is required";
		case 158: return "Specified Client is invalid";
		case 159: return "Customer ID required";
		case 160: return "Customer ID is already in use";
		case 161: return "Customer name required";
		case 162: return "Invalid Date Format";
		case 163: return "Transaction Type is required";
		case 164: return "Transaction Type is invalid";
		case 165: return "Fund required";
		case 166: return "Customer Required";
		case 167: return "Payment Method Not Found";
		case 168: return "Amount Required";
		case 169: return "Amount Exceeds Limit. Set up manually.";
		case 170: return "Start Date Required";
		case 171: return "Invalid Start Date";
		case 172: return "End Date earlier than Start Date";
		case 173: return "Cannot Prenote a Credit Card";
		case 174: return "Cannot Prenote processed account";
		case 175: return "Transaction pending for Prenote account";
		case 176: return "Invalid Account Type";
		case 177: return "Account Number Required";
		case 178: return "Invalid Routing Number";
		case 179: return "Client doesn't accept Credit Card Transactions";
		case 180: return "Client is in test mode for Credit Cards";
		case 181: return "Client is cancelled for Credit Cards";
		case 182: return "Name on Credit Card is Required";
		case 183: return "Invalid Expiration Date";
		case 184: return "Complete Billing Address is Required";
		case 195: return "Transaction Cannot Be Deleted";
		case 196: return "Recurring Telephone Entry Transaction NOT Allowed";
		case 198: return "Invalid State";
		case 199: return "Start Date Is Later Than Expiration date";
		case 201: return "Frequency Required";
		case 202: return "Account Cannot Be Deleted, Active Transaction Exists";
		case 203: return "Client Does Not Accept ACH Transactions";
		case 204: return "Duplicate Transaction";
		case 210: return "Recurring Credits NOT Allowed";
		case 211: return "ONHold/Cancelled Customer";
		case 217: return "End Date Cannot Be Earlier Than The Last Settlement Date";
		case 218: return "Fund ID Cannot Be W, P, T, or C";
		case 223: return "Customer ID not on file";
		case 224: return "Credit Card Credits NOT Allowed - Must Be Refunded";
		case 231: return "Customer Not Found For Client";
		case 232: return "Invalid Account Number";
		case 233: return "Invalid Country Code";
		case 234: return "Transactions Are Not Allow From This Country";
		case 242: return "Valid State Required";
		case 251: return "Transactionref Required";
		case 284: return "User Has Been Deleted";
		case 286: return "Client not set up for International Credit Card Processing";
		case 296: return "Client Is Cancelled";
		case 328: return "Credit Pending - Cancel Date cannot be earlier than Today";
		case 329: return "Credit Pending - Account cannot be placed on hold until Tomorrow";
		case 341: return "Cancel Date Cannot be Greater Than Today";
		case 344: return "Phone Number Must be 10 Digits Long";
		case 365: return "Invalid Email Address";
		case 378: return "Invalid Loginkey";
		case 379: return "Requesttype Unavailable";
		case 380: return "Invalid Sessionid";
		case 381: return "Invalid Clientid for Session";
		case 383: return "Internal Handler Error. Contact Vanco Services.";
		case 384: return "Invalid Requestid";
		case 385: return "Duplicate Requestid";
		case 390: return "Requesttype Not Authorized For User";
		case 391: return "Requesttype Not Authorized For Client";
		case 392: return "Invalid Value Format";
		case 393: return "Blocked IP";
		case 395: return "Transactions cannot be processed on Weekends";
		case 404: return "Invalid Date";
		case 410: return "Credits Cannot Be WEB or TEL";
		case 420: return "Transaction Not Found";
		case 431: return "Client Does Not Accept International Credit Cards";
		case 432: return "Can not process credit card";
		case 434: return "Credit Card Processor Error";
		case 445: return "Cancel Date Cannot Be Prior to the Last Settlement Date";
		case 446: return "End Date Cannot Be In The Past";
		case 447: return "Masked Account";
		case 469: return "Card Number Not Allowed";
		case 474: return "MasterCard Not Accepted";
		case 475: return "Visa Not Accepted";
		case 476: return "American Express Not Accepted";
		case 477: return "Discover Not Accepted";
		case 478: return "Invalid Account Number";
		case 489: return "Customer ID Exceeds 15 Characters";
		case 490: return "Too Many Results, Please Narrow Search";
		case 495: return "Field Contains Invalid Characters";
		case 496: return "Field contains Too Many Characters";
		case 497: return "Invalid Zip Code";
		case 498: return "Invalid City";
		case 499: return "Invalid Canadian Postal Code";
		case 500: return "Invalid Canadian Province";
		case 506: return "User Not Found";
		case 511: return "Amount Exceeds Limit";
		case 512: return "Client Not Set Up For Credit Card Processing";
		case 515: return "Transaction Already Refunded";
		case 516: return "Can Not Refund a Refund";
		case 517: return "Invalid Customer";
		case 518: return "Invalid Payment Method";
		case 519: return "Client Only Accepts Debit Cards";
		case 520: return "Transaction Max for Account Number Reached";
		case 521: return "Thirty Day Max for Client Reached";
		case 523: return "Invalid Login Request";
		case 527: return "Change in account/routing# or type";
		case 535: return "SSN Required";
		case 549: return "CVV2 Number is Required";
		case 550: return "Invalid Client ID";
		case 556: return "Invalid Banking Information";
		case 569: return "Please Contact This Organization for Assistance with Processing This Transaction";
		case 570: return "City Required";
		case 571: return "Zip Code Required";
		case 572: return "Canadian Provence Required";
		case 573: return "Canadian Postal Code Required";
		case 574: return "Country Code Required";
		case 578: return "Unable to Read Card Information. Please Click 'Click to Swipe' Button and Try Again.";
		case 610: return "Invalid Banking Information. Previous Notification of Change Received for this Account";
		case 629: return "Invalid CVV2";
		case 641: return "Fund ID Not Found";
		case 642: return "Request Amount Exceeds Total Transaction Amount";
		case 643: return "Phone Extension Required";
		case 645: return "Invalid Zip Code";
		case 652: return "Invalid SSN";
		case 653: return "SSN Required";
		case 657: return "Billing State Required";
		case 659: return "Phone Number Required";
		case 663: return "Version Not Supported";
		case 665: return "Invalid Billing Address";
		case 666: return "Customer Not On Hold";
		case 667: return "Account number for fund is invalid";
		case 678: return "Password Expired";
		case 687: return "Fund Name is currently in use. Please choose another name. If you would like to use this Fund Name, go to the other fund and change the Fund Name to something different.";
		case 688: return "Fund ID is currently in use. Please choose another number. If you would like to use this Fund ID, go to the other fund and change the Fund ID to something different.";
		case 705: return "Please Limit Your Date Range To 30 Days";
		case 706: return "Last Digits of Account Number Required";
		case 721: return "MS Transaction Amount Cannot Be Greater Than $50,000.";
		case 725: return "User ID is for Web Services Only";
		case 730: return "Start Date Required";
		case 734: return "Date Range Cannot Be Greater Than One Year";
		case 764: return "Start Date Cannot Occur In The Past";
		case 800: return "The CustomerID Does Not Match The Given CustomerRef";
		case 801: return "Default Payment Method Not Found";
		case 838: return "Transaction Cannot Be Processed. Please contact your organization.";
		case 842: return "Invalid Pin";
		case 844: return "Phone Number Must be 10 Digits Long";
		case 850: return "Invalid Authentication Signature";
		case 857: return "Fund Name Can Not Be Greater Than 30 Characters";
		case 858: return "Fund ID Can Not Be Greater Than 20 Characters";
		case 859: return "Customer Is Unproofed";
		case 862: return "Invalid Start Date";
		case 956: return "Amount Must Be Greater Than $0.00";
		case 960: return "Date of Birth Required";
		case 963: return "Missing Field";
		case 973: return "No match found for these credentials.";
		case 974: return "Recurring Return Fee Not Allowed";
		case 992: return "No Transaction Returned Within the Past 45 Days";
		case 993: return "Return Fee Must Be Collected Within 45 Days";
		case 994: return "Return Fee Is Greater Than the Return Fee Allowed";
		case 1005: return "Phone Extension Must Be All Digits";
		case 1008: return "We are sorry. This organization does not accept online credit card transactions. Please try again using a debit card.";
		case 1047: return "Invalid nvpvar variables";
		case 1054: return "Invalid. Debit Card Only";
		case 1067: return "Invalid Original Request ID";
		case 1070: return "Transaction Cannot Be Voided";
		case 1073: return "Transaction Processed More Than 25 Minutes Ago";
		case 1127: return "Declined - Tran Not Permitted";
		case 1128: return "Unable To Process, Please Try Again";
	}
}

function CreatePaymentMethod()
{
    var accountType = "CC";
	if (document.getElementById("EnableBankDraft").checked)
		accountType = "C";

	var accountNum = "";
	if (document.getElementById("EnableBankDraft").checked)
		accountNum = Account.value;
	if (document.getElementById("EnableCreditCard").checked)
		accountNum = CreditCard.value;

    $.ajax({
        type: "POST",
        url: "<?php if ($VancoTest) echo "https://www.vancodev.com/cgi-bin/wsnvptest.vps";
                               else echo "https://www.vancoservices.com/cgi-bin/wsnvp.vps"; ?>",
        data: { "sessionid":"<?= $sessionid ?>",
    	        "nvpvar":"<?= $nvpvarcontent ?>",
    	        "newcustomer":"true",
    	        "accounttype":accountType,
    	        "accountnumber":accountNum,
    	        "routingnumber":Route.value,
    	        "expmonth": ExpMonth.value,
    	        "expyear": ExpYear.value,
    	        "email": Email.value,
    	        "name":FirstName.value + " " + LastName.value,
    	        "billingaddr1":Address1.value,
    	        "billingcity":City.value,
                "billingstate":State.value,
                "billingzip":Zip.value,
                "name_on_card":FirstName.value + " " + LastName.value
        },
        dataType: 'jsonp',
        async: true,
        traditional: false,
        success: function (vancodata) {
        	var gotPaymentRef = vancodata["paymentmethodref"];// replace the private account# with the Vanco payment method reference
        	var errorList = vancodata["errorlist"];
            $.ajax({
                type: "POST",
                url: "<?= $VancoUrltoredirect ?>",
                data: vancodata,
                dataType: 'json',
                async: true,
                traditional: false,
                success: function (postbackdata) {
                	if (gotPaymentRef > 0) {
        	        	if (document.getElementById("EnableBankDraft").checked) {
            	        	accountVal = document.getElementById("Account").value;
        	            	document.getElementById("Account").value = "*****" + accountVal.substr (accountVal.length-4,4);
     		           		document.getElementById ("AccountVanco").value = gotPaymentRef;
        	        	} else if (document.getElementById("EnableCreditCard").checked) {
            	        	ccVal = document.getElementById("CreditCard").value;
        	            	document.getElementById("CreditCard").value = "************" + ccVal.substr(ccVal.length-4,4) ;
                    		document.getElementById ("CreditCardVanco").value = gotPaymentRef;
        	        	}
                	} else {
                    	errorArr = errorList.split(',');
                    	errorStr = "";
                    	for (var i = 0; i < errorArr.length; i++)
                        	errorStr += "Error " + errorArr[i] + ": " + VancoErrorString(Number(errorArr[i])) + "\n";
                		alert (errorStr);
                		window.location = "<?= RedirectURL ("AutoPaymentEditor.php")."?AutID=$iAutID&FamilyID=$aut_FamID$&linkBack=$linkBack" ?>";
                	}
                },
                error: function (jqXHR, textStatus, errorThrown, nashuadata) {
                    alert("ErrorThrown calling back to register payment method: " + errorThrown);
                    alert("Error calling back to register payment method: " + textStatus);
                    alert("Data returned calling back to register payment method: " + JSON.stringify(postbackdata));
                }
            });
        },
        error: function (jqXHR, textStatus, errorThrown, vancodata) {
            alert("Error calling Vanco: " + errorThrown);
        }
    });
}
</script>
<?php
}
?>

<form method="post" action="AutoPaymentEditor.php?<?= "AutID=" . $iAutID . "&FamilyID=" . $iFamily . "&linkBack=" . $linkBack ?>" name="AutoPaymentEditor">

<table cellpadding="1" align="center">

	<tr>
		<td align="center">
			<input type="submit" class="btn" value="<?= gettext("Save") ?>" name="Submit">
			<input type="button" class="btn" value="<?= gettext("Cancel") ?>" name="Cancel" onclick="javascript:document.location='<?php if (strlen($linkBack) > 0) { echo $linkBack; } else {echo "Menu.php"; } ?>';">
		</td>
	</tr>

	<tr>
		<td>
		<table cellpadding="1" align="center">

			<tr>
				<td class="LabelColumn"><?= gettext("Family:") ?></td>
				<td class="TextColumn">
					<select name="Family" size="8">
						<option value="0" selected><?= gettext("Unassigned") ?></option>
						<option value="0">-----------------------</option>

						<?php
						while ($aRow = mysql_fetch_array($rsFamilies))
						{
							extract($aRow);

							echo "<option value=\"" . $fam_ID . "\"";
							if ($iFamily == $fam_ID) { echo " selected"; }
							echo ">" . $fam_Name . "&nbsp;" . FormatAddressLine($fam_Address1, $fam_City, $fam_State);
						}
						?>

					</select>
				</td>
			</tr>

			<tr>
				<td class="LabelColumn"><?= gettext("Automatic payment type") ?></td>
				<td class="TextColumn"><input type="radio" Name="EnableButton" value="1" id="EnableBankDraft"<?php if ($bEnableBankDraft) echo " checked"; ?>>Bank Draft
				                       <input type="radio" Name="EnableButton" value="2" id="EnableCreditCard" <?php if ($bEnableCreditCard) echo " checked"; ?>>Credit Card
											  <input type="radio" Name="EnableButton" value="3"  id="Disable" <?php if ((!$bEnableBankDraft)&&(!$bEnableCreditCard)) echo " checked"; ?>>Disable</td>
			</tr>

			<tr>
				<td class="LabelColumn"><?= gettext("Date:") ?></td>
				<td class="TextColumn"><input type="text" name="NextPayDate" value="<?= $dNextPayDate ?>" maxlength="10" id="NextPayDate" size="11" class="form-control pull-right active"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?= gettext("Fiscal Year:") ?></td>
				<td class="TextColumnWithBottomBorder">
					<?php PrintFYIDSelect ($iFYID, "FYID") ?>
				</td>
			</tr>

			<tr>
				<td class="LabelColumn"><?= gettext("Payment amount") ?></td>
				<td class="TextColumn"><input type="text" name="Amount" value="<?= $nAmount ?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?= gettext("Payment interval (months)") ?></td>
				<td class="TextColumn"><input type="text" name="Interval" value="<?= $iInterval ?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?= gettext("Fund:") ?></td>
				<td class="TextColumn">
					<select name="Fund">
					<option value="0"><?= gettext("None") ?></option>
					<?php
					mysql_data_seek($rsFunds,0);
					while ($row = mysql_fetch_array($rsFunds))
					{
						$fun_id = $row["fun_ID"];
						$fun_name = $row["fun_Name"];
						$fun_active = $row["fun_Active"];
						echo "<option value=\"$fun_id\" " ;
						if ($iFund == $fun_id)
							echo "selected" ;
						echo ">$fun_name";
						if ($fun_active != 'true') echo " (" . gettext("inactive") . ")";
						echo "</option>" ;
					}
					?>
					</select>
				</td>
			</tr>

			<tr>
				<td class="LabelColumn"><?= gettext("First name") ?></td>
				<td class="TextColumn"><input type="text" id="FirstName" name="FirstName" value="<?= $tFirstName ?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?= gettext("Last name") ?></td>
				<td class="TextColumn"><input type="text" id="LastName" name="LastName" value="<?= $tLastName ?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?= gettext("Address 1") ?></td>
				<td class="TextColumn"><input type="text" id="Address1" name="Address1" value="<?= $tAddress1 ?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?= gettext("Address 2") ?></td>
				<td class="TextColumn"><input type="text" id="Address2" name="Address2" value="<?= $tAddress2 ?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?= gettext("City") ?></td>
				<td class="TextColumn"><input type="text" id="City" name="City" value="<?= $tCity ?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?= gettext("State") ?></td>
				<td class="TextColumn"><input type="text" id="State" name="State" value="<?= $tState ?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?= gettext("Zip code") ?></td>
				<td class="TextColumn"><input type="text" id="Zip" name="Zip" value="<?= $tZip ?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?= gettext("Country") ?></td>
				<td class="TextColumn"><input type="text" id="Country" name="Country" value="<?= $tCountry ?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?= gettext("Phone") ?></td>
				<td class="TextColumn"><input type="text" id="Phone" name="Phone" value="<?= $tPhone ?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?= gettext("Email") ?></td>
				<td class="TextColumn"><input type="text" id="Email" name="Email" value="<?= $tEmail ?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?= gettext("Credit Card") ?></td>
				<td class="TextColumn"><input type="text" id="CreditCard" name="CreditCard" value="<?= $tCreditCard ?>"></td>
			</tr>
<?php
if ($sElectronicTransactionProcessor == "Vanco") {
?>
			<tr>
				<td class="LabelColumn"><?= gettext("Vanco Credit Card Method") ?></td>
				<td class="TextColumn"><input type="text" id="CreditCardVanco" name="CreditCardVanco" value="<?= $tCreditCardVanco ?>" readonly></td>
			</tr>
<?php
}
?>

			<tr>
				<td class="LabelColumn"><?= gettext("Expiration Month") ?></td>
				<td class="TextColumn"><input type="text" id="ExpMonth" name="ExpMonth" value="<?= $tExpMonth ?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?= gettext("Expiration Year") ?></td>
				<td class="TextColumn"><input type="text" id="ExpYear" name="ExpYear" value="<?= $tExpYear ?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?= gettext("Bank Name") ?></td>
				<td class="TextColumn"><input type="text" id="BankName" name="BankName" value="<?= $tBankName ?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?= gettext("Bank Route Number") ?></td>
				<td class="TextColumn"><input type="text" id="Route" name="Route" value="<?= $tRoute ?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?= gettext("Bank Account Number") ?></td>
				<td class="TextColumn"><input type="text" id="Account" name="Account" value="<?= $tAccount ?>"></td>
			</tr>
<?php
if ($sElectronicTransactionProcessor == "Vanco") {
?>
			<tr>
				<td class="LabelColumn"><?= gettext("Vanco Bank Account Method") ?></td>
				<td class="TextColumn"><input type="text" id="AccountVanco" name="AccountVanco" value="<?= $tAccountVanco ?>" readonly></td>
			</tr>
<?php
	}
?>

<?php
	if ($sElectronicTransactionProcessor == "Vanco") {
?>
			<tr>
				<td>
<?php
	if ($iAutID > 0) {
?>
		<input type="button" id="PressToCreatePaymentMethod" value="Store Private Data at Vanco" onclick="CreatePaymentMethod();" />
<?php
	} else {
?>
		<b>Save this record to enable storing private data at Vanco</b>
<?php
	}
?>
				</td>
			</tr>
<?php
	}
?>
		</table>
		</td>
		</tr>
</table>
</form>
<script>
$("#NextPayDate").datepicker({format:'yyyy-mm-dd'});
</script>
<?php require "Include/Footer.php" ?>
