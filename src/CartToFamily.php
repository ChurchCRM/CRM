<?php
/*******************************************************************************
 *
 *  filename    : CartToFamily.php
 *  last change : 2003-10-09
 *  description : Add cart records to a family
 *
 *  http://www.churchcrm.io/
 *  Copyright 2003 Chris Gebhardt
 *
 *  ChurchCRM is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

// Include the function library
require "Include/Config.php";
require "Include/Functions.php";
require "Include/PersonFunctions.php";

// Security: User must have add records permission
if (!$_SESSION['bAddRecords'])
{
	Redirect("Menu.php");
	exit;
}

// Was the form submitted?
if (isset($_POST["Submit"]) && count($_SESSION['aPeopleCart']) > 0) {

	// Get the FamilyID
	$iFamilyID = FilterInput($_POST["FamilyID"],'int');

	// Are we creating a new family
	if ($iFamilyID == 0)
	{
		$sFamilyName = FilterInput($_POST["FamilyName"]);

		$dWeddingDate = FilterInput($_POST["WeddingDate"]);
		if ( strlen($dWeddingDate) > 0 )
			$dWeddingDate = "\"" . $dWeddingDate . "\"";
		else
			$dWeddingDate = "NULL";

		$iPersonAddress = FilterInput($_POST["PersonAddress"]);

		if ($iPersonAddress != 0)
		{
			$sSQL = "SELECT * FROM person_per WHERE per_ID = " . $iPersonAddress;
			$rsPerson = RunQuery($sSQL);
			extract(mysql_fetch_array($rsPerson));
		}

		SelectWhichAddress($sAddress1, $sAddress2, FilterInput($_POST["Address1"]), FilterInput($_POST["Address2"]), $per_Address1, $per_Address2, false);
		$sCity = SelectWhichInfo(FilterInput($_POST["City"]),$per_City);
		$sZip	= SelectWhichInfo(FilterInput($_POST["Zip"]),$per_Zip);
		$sCountry = SelectWhichInfo(FilterInput($_POST["Country"]),$per_Country);

		if ($sCountry == "United States" || $sCountry == "Canada")
			$sState = FilterInput($_POST["State"]);
		else
			$sState = FilterInput($_POST["StateTextbox"]);
		$sState = SelectWhichInfo($sState,$per_State);

		// Get and format any phone data from the form.
		$sHomePhone = FilterInput($_POST["HomePhone"]);
		$sWorkPhone = FilterInput($_POST["WorkPhone"]);
		$sCellPhone = FilterInput($_POST["CellPhone"]);
		if (!isset($_POST["NoFormat_HomePhone"])) $sHomePhone = CollapsePhoneNumber($sHomePhone,$sCountry);
		if (!isset($_POST["NoFormat_WorkPhone"])) $sWorkPhone = CollapsePhoneNumber($sWorkPhone,$sCountry);
		if (!isset($_POST["NoFormat_CellPhone"])) $sCellPhone = CollapsePhoneNumber($sCellPhone,$sCountry);

		$sHomePhone = SelectWhichInfo($sHomePhone,$per_HomePhone);
		$sWorkPhone = SelectWhichInfo($sWorkPhone,$per_WorkPhone);
		$sCellPhone = SelectWhichInfo($sCellPhone,$per_CellPhone);
		$sEmail = SelectWhichInfo(FilterInput($_POST["Email"]),$per_Email);

		if (strlen($sFamilyName) == 0) {
			$sError = "<p class=\"callout callout-warning\" align=\"center\" style=\"color:red;\">" . gettext("No family name entered!") . "</p>";
			$bError = true;
		}
		else
		{
			$sSQL = "INSERT INTO family_fam (fam_Name, fam_Address1, fam_Address2, fam_City, fam_State, fam_Zip, fam_Country, fam_HomePhone, fam_WorkPhone, fam_CellPhone, fam_Email, fam_WeddingDate, fam_DateEntered, fam_EnteredBy) VALUES ('" . $sFamilyName . "','" . $sAddress1 . "','" . $sAddress2 . "','" . $sCity . "','" . $sState . "','" . $sZip . "','" . $sCountry . "','" . $sHomePhone . "','" . $sWorkPhone . "','" . $sCellPhone . "','" . $sEmail . "'," . $dWeddingDate . ",'" . date("YmdHis") . "'," . $_SESSION['iUserID'] . ")";
			RunQuery($sSQL);

			//Get the key back
			$sSQL = "SELECT MAX(fam_ID) AS iFamilyID FROM family_fam";
			$rsLastEntry = RunQuery($sSQL);
			extract(mysql_fetch_array($rsLastEntry));
		}
	}

	if (!$bError)
	{
		// Loop through the cart array
		$iCount = 0;
		while ($element = each($_SESSION['aPeopleCart']))
		{
			$iPersonID = $_SESSION['aPeopleCart'][$element[key]];
			$sSQL = "SELECT per_fam_ID FROM person_per WHERE per_ID = " . $iPersonID;
			$rsPerson = RunQuery($sSQL);
			extract(mysql_fetch_array($rsPerson));

			// Make sure they are not already in a family
			if ($per_fam_ID == 0)
			{
				$iFamilyRoleID = FilterInput($_POST["role" . $iPersonID],"int");

				$sSQL = "UPDATE person_per SET per_fam_ID = " . $iFamilyID . ", per_fmr_ID = " . $iFamilyRoleID . " WHERE per_ID = " . $iPersonID;
				RunQuery($sSQL);
				$iCount++;
			}
		}

		$sGlobalMessage = $iCount . " records(s) successfully added to selected Family.";

		Redirect("FamilyView.php?FamilyID=" . $iFamilyID . "&Action=EmptyCart");
	}
}


// Set the page title and include HTML header
$sPageTitle = gettext("Add Cart to Family");
require "Include/Header.php";

echo $sError;
?>
<div class="box">
    <form method="post">
<?php
if (count($_SESSION['aPeopleCart']) > 0)
{

	// Get all the families
	$sSQL = "SELECT fam_Name, fam_ID FROM family_fam ORDER BY fam_Name";
	$rsFamilies = RunQuery($sSQL);

	// Get the family roles
	$sSQL = "SELECT * FROM list_lst WHERE lst_ID = 2 ORDER BY lst_OptionSequence";
	$rsFamilyRoles = RunQuery($sSQL);

	$sRoleOptionsHTML = "";
	while($aRow = mysql_fetch_array($rsFamilyRoles))
	{
		extract($aRow);
		$sRoleOptionsHTML .= "<option value=\"" . $lst_OptionID . "\">" . $lst_OptionName . "</option>";
	}

	$sSQL = "SELECT per_Title, per_FirstName, per_MiddleName, per_LastName, per_Suffix, per_fam_ID, per_ID
			FROM person_per WHERE per_ID IN (" . ConvertCartToString($_SESSION['aPeopleCart']) . ")
			ORDER BY per_LastName";
	$rsCartItems = RunQuery($sSQL);

	echo "<table class='table'>";
	echo "<tr>";
	echo "<td>&nbsp;</td>";
	echo "<td><b>" . gettext("Name") . "</b></td>";
	echo "<td align=\"center\"><b>" . gettext("Assign Role") . "</b></td>";

	$count = 1;
	while ($aRow = mysql_fetch_array($rsCartItems))
	{
		$sRowClass = AlternateRowStyle($sRowClass);

		extract($aRow);

		echo "<tr class=\"" . $sRowClass . "\">";
		echo "<td align=\"center\">" . $count++ . "</td>";
		echo "<td><img src='". $personService->getPhoto($per_ID). "' class='direct-chat-img'> &nbsp <a href=\"PersonView.php?PersonID=" . $per_ID . "\">" . FormatFullName($per_Title, $per_FirstName, $per_MiddleName, $per_LastName, $per_Suffix, 1) . "</a></td>";

		echo "<td align=\"center\">";
		if ($per_fam_ID == 0)
			echo "<select name=\"role" . $per_ID . "\">" . $sRoleOptionsHTML . "</select>";
		else
			echo gettext("Already in a family");
		echo "</td>";
		echo "</tr>";
	}

	echo "</table>";
?>
    </div>
    <div class="box">
<table align="center" class="table table-responsive">
	<tr>
		<td class="LabelColumn"><?= gettext("Add to Family:") ?></td>
		<td class="TextColumn">
			<?php
			// Create the family select drop-down
			echo "<select name=\"FamilyID\">";
			echo "<option value=\"0\">" . gettext("Create new family") . "</option>";
			while ($aRow = mysql_fetch_array($rsFamilies)) {
				extract($aRow);
				echo "<option value=\"" . $fam_ID . "\">" . $fam_Name . "</option>";
			}
			echo "</select>";
			?>
		</td>
	</tr>

	<tr>
		<td></td>
		<td><p class="MediumLargeText"><?= gettext("If adding a new family, enter data below.") ?></p></td>
	</tr>


	<tr>
		<td class="LabelColumn"><?= gettext("Family Name:") ?></td>
		<td class="TextColumnWithBottomBorder"><input type="text" Name="FamilyName" value="<?= $sName ?>" maxlength="48"><font color="red"><?= $sNameError ?></font></td>
	</tr>

	<tr>
		<td class="LabelColumn"><?= gettext("Wedding Date:") ?></td>
		<td class="TextColumnWithBottomBorder"><input type="text" Name="WeddingDate" value="<?= $dWeddingDate ?>" maxlength="10" id="sel1" size="15"  class="form-control pull-right active"><font color="red"><?php echo "<BR>" . $sWeddingDateError ?></font></td>
	</tr>

	<tr>
		<td class="LabelColumn"><?= gettext("Use address/contact data from:") ?></td>
		<td class="TextColumn">
			<?php
			echo "<select name=\"PersonAddress\">";
			echo "<option value=\"0\">" . gettext("Only the new data below") . "</option>";

			mysql_data_seek($rsCartItems,0);
			while ($aRow = mysql_fetch_array($rsCartItems)) {
				extract($aRow);
				if ($per_fam_ID == 0)
					echo "<option value=\"" . $per_ID . "\">" . $per_FirstName . ' ' . $per_LastName . "</option>";
			}

			echo "</select>";
			?>
		</td>
	</tr>

	<tr>
		<td class="LabelColumn"><?= gettext("Address1:") ?></td>
		<td class="TextColumn"><input type="text" Name="Address1" value="<?= $sAddress1 ?>" size="50" maxlength="250"></td>
	</tr>

	<tr>
		<td class="LabelColumn"><?= gettext("Address2:") ?></td>
		<td class="TextColumn"><input type="text" Name="Address2" value="<?= $sAddress2 ?>" size="50" maxlength="250"></td>
	</tr>

	<tr>
		<td class="LabelColumn"><?= gettext("City:") ?></td>
		<td class="TextColumn"><input type="text" Name="City" value="<?= $sCity ?>" maxlength="50"></td>
	</tr>

	<tr>
		<td class="LabelColumn"><?= gettext("State:") ?></td>
		<td class="TextColumn">
			<?php require "Include/StateDropDown.php"; ?>
			OR
			<input type="text" name="StateTextbox" value="<?php if ($sCountry != "United States" && $sCountry != "Canada") echo $sState ?>" size="20" maxlength="30">
			<BR><?= gettext("(Use the textbox for countries other than US and Canada)") ?>
		</td>
	</tr>

	<tr>
		<td class="LabelColumn">
			<?php
			if($sCountry == "Canada")
			  echo gettext("Postal Code:");
			else
			  echo gettext("Zip:");
			?>
		</td>
		<td class="TextColumn">
			<input type="text" Name="Zip" value="<?= $sZip ?>" maxlength="10" size="8">
		</td>

	</tr>

	<tr>
		<td class="LabelColumn"><?= gettext("Country:") ?></td>
		<td class="TextColumnWithBottomBorder">
			<?php require "Include/CountryDropDown.php" ?>
		</td>
	</tr>

	<tr>
		<td>&nbsp;</td>
	</tr>

	<tr>
		<td class="LabelColumn"><?= gettext("Home Phone:") ?></td>
		<td class="TextColumn">
			<input type="text" Name="HomePhone" value="<?= $sHomePhone ?>" size="30" maxlength="30">
			<input type="checkbox" name="NoFormat_HomePhone" value="1" <?php if ($bNoFormat_HomePhone) echo " checked";?>><?= gettext("Do not auto-format") ?>
		</td>
	</tr>

	<tr>
		<td class="LabelColumn"><?= gettext("Work Phone:") ?></td>
		<td class="TextColumn">
			<input type="text" name="WorkPhone" value="<?php echo $sWorkPhone ?>" size="30" maxlength="30">
			<input type="checkbox" name="NoFormat_WorkPhone" value="1" <?php if ($bNoFormat_WorkPhone) echo " checked";?>><?= gettext("Do not auto-format") ?>
		</td>
	</tr>

	<tr>
		<td class="LabelColumn"><?= gettext("Mobile Phone:") ?></td>
		<td class="TextColumn">
			<input type="text" name="CellPhone" value="<?php echo $sCellPhone ?>" size="30" maxlength="30">
			<input type="checkbox" name="NoFormat_CellPhone" value="1" <?php if ($bNoFormat_CellPhone) echo " checked";?>><?= gettext("Do not auto-format") ?>
		</td>
	</tr>

	<tr>
		<td class="LabelColumn"><?= gettext("Email:") ?></td>
		<td class="TextColumnWithBottomBorder"><input type="text" Name="Email" value="<?= $sEmail ?>" size="30" maxlength="50"></td>
	</tr>

</table>

<p align="center">
<BR>
<input type="submit" class="btn" name="Submit" value="<?= gettext("Add to Family") ?>">
<BR><BR>
</p>
</form>
<?php
}
else
	echo "<p align=\"center\" class='callout callout-warning'>" . gettext("Your cart is empty!") . "</p>";
?>
</div>
<script>
$("#sel1").datepicker({format:'yyyy-mm-dd'});
</script>
<?php require "Include/Footer.php"; ?>
