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

//Include the function library
require "Include/Config.php";
require "Include/Functions.php";

require "Include/MICRFunctions.php";

//Get the PersonID out of the querystring
$iPledgeID = FilterInput($_GET["PledgeID"],'int');
$linkBack = FilterInput($_GET["linkBack"]);
$FamIDIn = FilterInput($_GET["FamilyID"]);
$PledgeOrPayment = FilterInput($_GET["PledgeOrPayment"]);

// Get the list of funds
$sSQL = "SELECT fun_ID,fun_Name,fun_Description,fun_Active FROM donationfund_fun";
if ($editorMode == 0) $sSQL .= " WHERE fun_Active = 'true'"; // New donations should show only active funds.
$rsFunds = RunQuery($sSQL);

//Set the page title
if ($PledgeOrPayment == 'Pledge')
	$sPageTitle = gettext("Pledge Editor");
else
	$sPageTitle = gettext("Payment Editor");

// Security: User must have Add or Edit Records permission to use this form in those manners
// Clean error handling: (such as somebody typing an incorrect URL ?PersonID= manually)
if (strlen($iPledgeID) > 0)
{
	if (!$_SESSION['bEditRecords'])
	{
		Redirect("Menu.php");
		exit;
	}
	$sSQL = "SELECT '' FROM pledge_plg WHERE plg_plgID = " . $iPledgeID;
	if (mysql_num_rows(RunQuery($sSQL)) == 0)
	{
		Redirect("Menu.php");
		exit;
	}
}
elseif (!$_SESSION['bAddRecords'])
{
	Redirect("Menu.php");
	exit;
}

// Instantiate the MICR class
$micrObj = new MICRReader();

//Is this the second pass?
if (isset($_POST["PledgeSubmit"]) || isset($_POST["PledgeSubmitAndAdd"]) || isset($_POST["MatchFamily"]) ||
    isset($_POST["SetDefaultCheck"]) || isset($_POST["SetDefaultCreditCard"]))
{
	$iFamily = 0;
	$iCheckNo = 0;
	// Take care of match-family first- select the family based on the scanned check
	if (isset($_POST["MatchFamily"])) {
		$tScanString = FilterInput($_POST["ScanInput"]);

		$routeAndAccount = $micrObj->FindRouteAndAccount ($tScanString); // use routing and account number for matching

      if ($routeAndAccount) {
		   $sSQL = "SELECT fam_ID FROM family_fam WHERE fam_scanCheck REGEXP \"" . $routeAndAccount . "\"";
		   $rsFam = RunQuery($sSQL);
		   extract(mysql_fetch_array($rsFam));
		   $iFamily = $fam_ID;

		   $iCheckNo = $micrObj->FindCheckNo ($tScanString);
      } else {
		   $iFamily = FilterInput($_POST["Family"],'int');
		   $iCheckNo = FilterInput($_POST["CheckNo"], 'int');
      }
	} else {
		$iFamily = FilterInput($_POST["Family"],'int');
		$iCheckNo = FilterInput($_POST["CheckNo"], 'int');
	}
	// Handle special buttons at the bottom of the form.
	if (isset($_POST["SetDefaultCheck"])) {
		$tScanString = FilterInput($_POST["ScanInput"]);
		$iFamily = FilterInput($_POST["Family"],'int');
		$sSQL = "UPDATE family_fam SET fam_scanCheck=\"" . $tScanString . "\" WHERE fam_ID = " . $iFamily;
		RunQuery($sSQL);
	}
	if (isset($_POST["SetDefaultCreditCard"])) {
		$tScanString = FilterInput($_POST["ScanInput"]);
		$iFamily = FilterInput($_POST["Family"],'int');
		$sSQL = "UPDATE family_fam SET fam_scanCredit=\"" . $tScanString . "\" WHERE fam_ID = " . $iFamily;
		RunQuery($sSQL);
	}

	//Get all the variables from the request object and assign them locally
	$iFYID = FilterInput($_POST["FYID"], 'int');
	$dDate = FilterInput($_POST["Date"]);
	$nAmount = FilterInput($_POST["Amount"]);
	$iSchedule = FilterInput($_POST["Schedule"]);
	$iMethod = FilterInput($_POST["Method"]);
	$sComment = FilterInput($_POST["Comment"]);
	$iFundID = FilterInput($_POST["FundID"],'int');
	$tScanString = FilterInput($_POST["ScanInput"]);

	if (! $iCheckNo)
		$iCheckNo = "NULL";

	$_SESSION['idefaultFY'] = $iFYID; // Remember default fiscal year

	//Initialize the error flag
	$bErrorFlag = false;

	//Validate the Amount
	if (strlen($nAmount) < 1)
	{
		$sAmountError = gettext("You must enter an Amount.");
		$bErrorFlag = true;
	}

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
		// New Person (add)
		if (strlen($iPledgeID) < 1)
		{
			// when creating a payment record the current deposit slip ID
			$depIDString = "NULL";
			if ($_SESSION['iCurrentDeposit'] && $PledgeOrPayment=='Payment')
				$depIDString = $_SESSION['iCurrentDeposit'];

			// Only set PledgeOrPayment when the record is first created
			$sSQL = "INSERT INTO pledge_plg (plg_FamID, plg_FYID, plg_date, plg_amount, plg_schedule, plg_method, plg_comment, plg_DateLastEdited, plg_EditedBy, plg_PledgeOrPayment, plg_fundID, plg_depID, plg_CheckNo, plg_scanString)
			VALUES ('" . $iFamily . "','" . $iFYID . "','" . $dDate . "','" . $nAmount . "','" . $iSchedule . "','" . $iMethod  . "','" . $sComment . "'";
			$sSQL .= ",'" . date("YmdHis") . "'," . $_SESSION['iUserID'] . ",'" . $PledgeOrPayment . "'," . $iFundID . "," . $depIDString . "," . $iCheckNo . ",\"" . $tScanString . "\")";
			$bGetKeyBack = True;

		// Existing record (update)
		} else {
			$sSQL = "UPDATE pledge_plg SET plg_FamID = '" . $iFamily . "',plg_FYID = '" . $iFYID . "',plg_date = '" . $dDate . "', plg_amount = '" . $nAmount . "', plg_schedule = '" . $iSchedule . "', plg_method = '" . $iMethod . "', plg_comment = '" . $sComment . "'";
			$sSQL .= ", plg_DateLastEdited = '" . date("YmdHis") . "', plg_EditedBy = " . $_SESSION['iUserID'] . ", plg_fundID = " . $iFundID . ", plg_CheckNo = " . $iCheckNo . ", plg_scanString = \"" . $tScanString . "\" WHERE plg_plgID = " . $iPledgeID;

			$bGetKeyBack = false;
		}

		//Execute the SQL
		RunQuery($sSQL);

		// If this is a new person, get the key back
		if ($bGetKeyBack)
		{
			$sSQL = "SELECT MAX(plg_plgID) AS iPledgeID FROM pledge_plg";
			$rsPledgeID = RunQuery($sSQL);
			extract(mysql_fetch_array($rsPledgeID));
		}

		if (isset($_POST["PledgeSubmit"]))
		{
			// Check for redirection to another page after saving information: (ie. PledgeEditor.php?previousPage=prev.php?a=1;b=2;c=3)
			if ($linkBack != "") {
				Redirect($linkBack);
			} else {
				//Send to the view of this pledge
				Redirect("PledgeEditor.php?PledgeOrPayment=" . $PledgeOrPayment . "&PledgeID=" . $iPledgeID . "&linkBack=", $linkBack);
			}
		}
		else if (isset($_POST["PledgeSubmitAndAdd"]))
		{
			//Reload to editor to add another record
			Redirect("PledgeEditor.php?PledgeOrPayment=" . $PledgeOrPayment . "&linkBack=", $linkBack);
		}

	}

} else {

	//FirstPass
	//Are we editing or adding?
	if (strlen($iPledgeID) > 0)
	{
		//Editing....
		//Get all the data on this record

		$sSQL = "SELECT * FROM pledge_plg LEFT JOIN family_fam ON plg_famID = fam_ID WHERE plg_plgID = " . $iPledgeID;
		$rsPledge = RunQuery($sSQL);
		extract(mysql_fetch_array($rsPledge));

		$iFYID = $plg_FYID;
		$dDate = $plg_date;
		$nAmount = $plg_amount;
		$iCheckNo = $plg_CheckNo;
		$iFundID = $plg_fundID;
		$iSchedule = $plg_schedule;
		$iMethod = $plg_method;
		$sComment = $plg_comment;
		$iFamily = $plg_FamID;
		$tScanString = $plg_scanString;
      $PledgeOrPayment = $plg_PledgeOrPayment;
	}
	else
	{
		//Adding....
		//Set defaults
		$iFamily = $FamIDIn; // Will be set only if they pressed the "add pledge" link in the family view
		$iFYID = $_SESSION['idefaultFY'];
	}
}

//Get Families for the drop-down
$sSQL = "SELECT * FROM family_fam ORDER BY fam_Name";
$rsFamilies = RunQuery($sSQL);

require "Include/Header.php";

?>

<form method="post" action="<?php echo $_SERVER['PHP_SELF'] . "?PledgeID=" . $iPledgeID . "&PledgeOrPayment=" . $PledgeOrPayment. "&linkBack=" . $linkBack; ?>" name="PledgeEditor">

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
		<table cellpadding="3">
			<tr>
				<td <?php if ($PledgeOrPayment=='Pledge') echo "class=\"LabelColumn\">"; else echo "class=\"PaymentLabelColumn\">"; ?><?php addToolTip("Select the pledging family from the list."); ?><?php echo gettext("Family:"); ?></td>
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
				<td <?php if ($PledgeOrPayment=='Pledge') echo "class=\"LabelColumn\">"; else echo "class=\"PaymentLabelColumn\">"; ?><?php echo gettext("Fiscal Year:"); ?></td>
				<td class="TextColumnWithBottomBorder">
					<select name="FYID">
						<option value="0"><?php echo gettext("Select Fiscal Year"); ?></option>
						<option value="1" <?php if ($iFYID == 1) { echo "selected"; } ?>><?php echo gettext("1996/97"); ?></option>
						<option value="2" <?php if ($iFYID == 2) { echo "selected"; } ?>><?php echo gettext("1997/98"); ?></option>
						<option value="3" <?php if ($iFYID == 3) { echo "selected"; } ?>><?php echo gettext("1998/99"); ?></option>
						<option value="4" <?php if ($iFYID == 4) { echo "selected"; } ?>><?php echo gettext("1999/00"); ?></option>
						<option value="5" <?php if ($iFYID == 5) { echo "selected"; } ?>><?php echo gettext("2000/01"); ?></option>
						<option value="6" <?php if ($iFYID == 6) { echo "selected"; } ?>><?php echo gettext("2001/02"); ?></option>
						<option value="7" <?php if ($iFYID == 7) { echo "selected"; } ?>><?php echo gettext("2002/03"); ?></option>
						<option value="8" <?php if ($iFYID == 8) { echo "selected"; } ?>><?php echo gettext("2003/04"); ?></option>
						<option value="9" <?php if ($iFYID == 9) { echo "selected"; } ?>><?php echo gettext("2004/05"); ?></option>
						<option value="10" <?php if ($iFYID == 10) { echo "selected"; } ?>><?php echo gettext("2005/06"); ?></option>
						<option value="11" <?php if ($iFYID == 11) { echo "selected"; } ?>><?php echo gettext("2006/07"); ?></option>
						<option value="12" <?php if ($iFYID == 12) { echo "selected"; } ?>><?php echo gettext("2007/08"); ?></option>
						<option value="13" <?php if ($iFYID == 13) { echo "selected"; } ?>><?php echo gettext("2008/09"); ?></option>
						<option value="14" <?php if ($iFYID == 14) { echo "selected"; } ?>><?php echo gettext("2009/10"); ?></option>
						<option value="15" <?php if ($iFYID == 15) { echo "selected"; } ?>><?php echo gettext("2010/11"); ?></option>
						<option value="16" <?php if ($iFYID == 16) { echo "selected"; } ?>><?php echo gettext("2011/12"); ?></option>
						<option value="17" <?php if ($iFYID == 17) { echo "selected"; } ?>><?php echo gettext("2012/13"); ?></option>
						<option value="18" <?php if ($iFYID == 18) { echo "selected"; } ?>><?php echo gettext("2013/14"); ?></option>
					</select>
				</td>
			</tr>

			<tr>
				<td <?php if ($PledgeOrPayment=='Pledge') echo "class=\"LabelColumn\""; else echo "class=\"PaymentLabelColumn\""; ?><?php addToolTip("Format: YYYY-MM-DD<br>or enter the date by clicking on the calendar icon to the right."); ?>><?php echo gettext("Date:"); ?></td>
				<td class="TextColumn"><input type="text" name="Date" value="<?php echo $dDate; ?>" maxlength="10" id="sel1" size="11">&nbsp;<input type="image" onclick="return showCalendar('sel1', 'y-mm-dd');" src="Images/calendar.gif"> <span class="SmallText"><?php echo gettext("[format: YYYY-MM-DD]"); ?></span><font color="red"><?php echo $sDateError ?></font></td>
			</tr>

			<tr>
				<td <?php if ($PledgeOrPayment=='Pledge') echo "class=\"LabelColumn\">"; else echo "class=\"PaymentLabelColumn\">"; ?><?php echo gettext("Fund:"); ?></td>
				<td class="TextColumnWithBottomBorder">
					<select name="FundID">
					<option value="0"><?php echo gettext("None"); ?></option>
					<?php
					mysql_data_seek($rsFunds,0);
					while ($row = mysql_fetch_array($rsFunds))
					{
						$fun_id = $row["fun_ID"];
						$fun_name = $row["fun_Name"];
						$fun_active = $row["fun_Active"];
						echo "<option value=\"$fun_id\" " ;
						if ($iFundID == $fun_id)
							echo "selected" ;
						echo ">$fun_name";
						if ($fun_active != 'true') echo " (" . gettext("inactive") . ")";
						echo "</option>" ;
					}
					?>
					</select>
				</td>
			</tr>

			<?php if ($PledgeOrPayment!='Pledge') {?>
				<tr>
					<td class="PaymentLabelColumn"><?php echo gettext("Check number:"); ?></td>
					<td class="TextColumn"><input type="text" name="CheckNo" id="CheckNo" value="<?php echo $iCheckNo; ?>"></td>
				</tr>
			<?php } ?>

			<tr>
				<td <?php if ($PledgeOrPayment=='Pledge') echo "class=\"LabelColumn\">"; else echo "class=\"PaymentLabelColumn\">"; ?><?php echo gettext("Amount:"); ?></td>
				<td class="TextColumn"><input type="text" name="Amount" id="Amount" value="<?php echo $nAmount; ?>"><br><font color="red"><?php echo $sAmountError ?></font></td>
			</tr>

			<?php if ($PledgeOrPayment=='Pledge') {?>
				<tr>
					<td <?php if ($PledgeOrPayment=='Pledge') echo "class=\"LabelColumn\">"; else echo "class=\"PaymentLabelColumn\">"; ?><?php echo gettext("Payment Schedule:"); ?></td>
					<td class="TextColumnWithBottomBorder">
						<select name="Schedule">
							<option value="0"><?php echo gettext("Select Schedule"); ?></option>
							<option value="Monthly" <?php if ($iSchedule == "Monthly") { echo "selected"; } ?>><?php echo gettext("Monthly"); ?></option>
							<option value="Quarterly" <?php if ($iSchedule == "Quarterly") { echo "selected"; } ?>><?php echo gettext("Quarterly"); ?></option>
							<option value="Once" <?php if ($iSchedule == "Once") { echo "selected"; } ?>><?php echo gettext("Once"); ?></option>
							<option value="Other" <?php if ($iSchedule == "Other") { echo "selected"; } ?>><?php echo gettext("Other"); ?></option>
						</select>
					</td>
				</tr>
			<?php } ?>

			<tr>
				<td <?php if ($PledgeOrPayment=='Pledge') echo "class=\"LabelColumn\">"; else echo "class=\"PaymentLabelColumn\">"; ?><?php echo gettext("Payment Method:"); ?></td>
				<td class="TextColumnWithBottomBorder">
					<select name="Method">
						<option value="0"><?php echo gettext("Select Method"); ?></option>
						<option value="CREDITCARD" <?php if ($iMethod == "CREDITCARD") { echo "selected"; } ?>><?php echo gettext("Credit Card"); ?></option>
						<option value="CHECK" <?php if ($iMethod == "CHECK") { echo "selected"; } ?>><?php echo gettext("Check"); ?></option>
						<option value="CASH" <?php if ($iMethod == "CASH") { echo "selected"; } ?>><?php echo gettext("Cash"); ?></option>
						<option value="OTHER" <?php if ($iMethod == "OTHER") { echo "selected"; } ?>><?php echo gettext("Other"); ?></option>
					</select>
				</td>
			</tr>

			<tr>
				<td <?php if ($PledgeOrPayment=='Pledge') echo "class=\"LabelColumn\">"; else echo "class=\"PaymentLabelColumn\">"; ?><?php echo gettext("Comment:"); ?></td>
				<td class="TextColumn"><input type="text" name="Comment" id="Comment" value="<?php echo $sComment; ?>"></td>
			</tr>

			<tr>
				<td <?php  if ($PledgeOrPayment=='Pledge') echo "class=\"LabelColumn\">"; else echo "class=\"PaymentLabelColumn\">";echo gettext("Scan check or credit card:");?></td>
				<td><textarea name="ScanInput" rows="3" cols="90"><?php echo $tScanString?></textarea></td>
			</tr>
	
		</table>
		</td>

	<tr>
		<td align="center">
			<input type="submit" class="icButton" value="<?php echo gettext("Match family to check"); ?>" name="MatchFamily">
			<input type="submit" class="icButton" value="<?php echo gettext("Set default check for family"); ?>" name="SetDefaultCheck">
			<input type="submit" class="icButton" value="<?php echo gettext("Set default credit card for family"); ?>" name="SetDefaultCreditCard">
		</td>
	</tr>

	</form>
</table>

<?php
require "Include/Footer.php";
?>
