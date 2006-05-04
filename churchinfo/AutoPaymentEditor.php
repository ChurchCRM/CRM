<?php
/*******************************************************************************
 *
 *  filename    : AutoPaymentEditor.php
 *  copyright   : Copyright 2001, 2002, 2003, 2004 Deane Barker, Chris Gebhardt, Michael Wilt
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

$linkBack = FilterInput($_GET["linkBack"]);
$iFamily = FilterInput($_GET["FamilyID"]);
$iAutID = FilterInput($_GET["AutID"]);

//Get Family name
if ($iFamily) {
	$sSQL = "SELECT * FROM family_fam where fam_ID = " . $iFamily;
	$rsFamily = RunQuery($sSQL);
	extract(mysql_fetch_array($rsFamily));
} else {
	$fam_Name = "TBD";
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

	// New automatic payment record
	if (strlen($iAutID) < 1)
	{
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
						$iFYID . "," .
						$nAmount . "," .
						$iInterval . "," .
						$iFund . "," .
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
		$bGetKeyBack = True;

	// Existing record (update)
	} else {
		$sSQL = "UPDATE autopayment_aut SET " .
						"aut_FamID	=	" . $iFamily . "," .
						"aut_EnableBankDraft	=" . 	$bEnableBankDraft . "," .
						"aut_EnableCreditCard	=" . 	$bEnableCreditCard . "," .
						"aut_NextPayDate	='" . $dNextPayDate . "'," .
						"aut_Amount	=" . 	$nAmount . "," .
						"aut_FYID	=" . 	$iFYID . "," .
						"aut_Interval	=" . 	$iInterval . "," .
						"aut_Fund	=" . 	$iFund . "," .
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
	}

	//Execute the SQL
	RunQuery($sSQL);

	if ($bGetKeyBack)
	{
		$sSQL = "SELECT MAX(aut_ID) AS iAutID FROM autopayment_aut";
		$rsAutID = RunQuery($sSQL);
		extract(mysql_fetch_array($rsAutID));
	}

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

} else {
	if ($iAutID) {
		$sSQL = "SELECT * FROM autopayment_aut WHERE aut_ID = " . $iAutID;
		$rsAutopayment = RunQuery($sSQL);
	}

	if ($iAutID && mysql_num_rows ($rsAutopayment) > 0) {
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
		$tExpMonth=$aut_ExpMonth;
		$tExpYear=$aut_ExpYear;
		$tBankName=$aut_BankName;
		$tRoute=$aut_Route;
		$tAccount=$aut_Account;
	} else {
		$dNextPayDate = date ("Y-m-d");
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

		// Default to the current fiscal year ID
		$FYID = CurrentFY ();
	}
}

require "Include/Header.php";

//Get Families for the drop-down
$sSQL = "SELECT * FROM family_fam ORDER BY fam_Name";
$rsFamilies = RunQuery($sSQL);

// Get the list of funds
$sSQL = "SELECT fun_ID,fun_Name,fun_Description,fun_Active FROM donationfund_fun";
if ($editorMode == 0) $sSQL .= " WHERE fun_Active = 'true'"; // New donations should show only active funds.
$rsFunds = RunQuery($sSQL);

?>


<form method="post" action="AutoPaymentEditor.php?<?php echo "AutID=" . $iAutID . "&FamilyID=" . $iFamily . "&linkBack=" . $linkBack; ?>" name="Canvas05Editor">

<table cellpadding="1" align="center">

	<tr>
		<td align="center">
			<input type="submit" class="icButton" value="<?php echo gettext("Save"); ?>" name="Submit">
			<input type="button" class="icButton" value="<?php echo gettext("Cancel"); ?>" name="Cancel" onclick="javascript:document.location='<?php if (strlen($linkBack) > 0) { echo $linkBack; } else {echo "Menu.php"; } ?>';">
		</td>
	</tr>

	<tr>
		<td>
		<table cellpadding="1" align="center">

			<tr>
				<td class="LabelColumn" <?php addToolTip("If a family member, select the appropriate family from the list. Otherwise, leave this as is."); ?>><?php echo gettext("Family:"); ?></td>
				<td class="TextColumn">
					<select name="Family" size="8">
						<option value="0" selected><?php echo gettext("Unassigned"); ?></option>
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
				<td class="LabelColumn"><?php echo gettext("Automatic payment type"); ?></td>
				<td class="TextColumn"><input type="radio" Name="EnableButton" value="1" <?php if ($bEnableBankDraft) echo " checked"; ?>>Bank Draft
				                       <input type="radio" Name="EnableButton" value="2" <?php if ($bEnableCreditCard) echo " checked"; ?>>Credit Card
											  <input type="radio" Name="EnableButton" value="3" <?php if ((!$bEnableBankDraft)&&(!$bEnableCreditCard)) echo " checked"; ?>>Disable</td>
			</tr>

			<tr>
				<td class="LabelColumn"<?php addToolTip("Format: YYYY-MM-DD<br>or enter the date by clicking on the calendar icon to the right."); ?>><?php echo gettext("Date:"); ?></td>
				<td class="TextColumn"><input type="text" name="NextPayDate" value="<?php echo $dNextPayDate; ?>" maxlength="10" id="NextPayDate" size="11">&nbsp;<input type="image" onclick="return showCalendar('NextPayDate', 'y-mm-dd');" src="Images/calendar.gif"> <span class="SmallText"><?php echo gettext("[format: YYYY-MM-DD]"); ?></span></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("Fiscal Year:"); ?></td>
				<td class="TextColumnWithBottomBorder">
					<?php PrintFYIDSelect ($iFYID, "FYID") ?>
				</td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("Payment amount");?></td>
				<td class="TextColumn"><input type="text" name="Amount" value="<?php echo $nAmount?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("Payment interval (months)");?></td>
				<td class="TextColumn"><input type="text" name="Interval" value="<?php echo $iInterval?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("Fund:"); ?></td>
				<td class="TextColumn">
					<select name="Fund">
					<option value="0"><?php echo gettext("None"); ?></option>
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
				<td class="LabelColumn"><?php echo gettext("First name");?></td>
				<td class="TextColumn"><input type="text" name="FirstName" value="<?php echo $tFirstName?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("Last name");?></td>
				<td class="TextColumn"><input type="text" name="LastName" value="<?php echo $tLastName?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("Address 1");?></td>
				<td class="TextColumn"><input type="text" name="Address1" value="<?php echo $tAddress1?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("Address 2");?></td>
				<td class="TextColumn"><input type="text" name="Address2" value="<?php echo $tAddress2?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("City");?></td>
				<td class="TextColumn"><input type="text" name="City" value="<?php echo $tCity?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("State");?></td>
				<td class="TextColumn"><input type="text" name="State" value="<?php echo $tState?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("Zip code");?></td>
				<td class="TextColumn"><input type="text" name="Zip" value="<?php echo $tZip?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("Country");?></td>
				<td class="TextColumn"><input type="text" name="Country" value="<?php echo $tCountry?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("Phone");?></td>
				<td class="TextColumn"><input type="text" name="Phone" value="<?php echo $tPhone?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("Email");?></td>
				<td class="TextColumn"><input type="text" name="Email" value="<?php echo $tEmail?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("Credit Card");?></td>
				<td class="TextColumn"><input type="text" name="CreditCard" value="<?php echo $tCreditCard?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("Expiration Month");?></td>
				<td class="TextColumn"><input type="text" name="ExpMonth" value="<?php echo $tExpMonth?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("Expiration Year");?></td>
				<td class="TextColumn"><input type="text" name="ExpYear" value="<?php echo $tExpYear?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("Bank Name");?></td>
				<td class="TextColumn"><input type="text" name="BankName" value="<?php echo $tBankName?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("Bank Route Number");?></td>
				<td class="TextColumn"><input type="text" name="Route" value="<?php echo $tRoute?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("Bank Account Number");?></td>
				<td class="TextColumn"><input type="text" name="Account" value="<?php echo $tAccount?>"></td>
			</tr>
		</table>
		</td>
	</form>
</table>

<?php
require "Include/Footer.php";
?>
