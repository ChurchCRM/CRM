<?php
/*******************************************************************************
 *
 *  filename    : PropertyAssign.php
 *  last change : 2003-06-04
 *  description : property assign
 *
 *  http://www.infocentral.org/
 *  Copyright 2001-2003 Phillip Hullquist, Deane Barker, Chris Gebhardt
 *
 *  InfoCentral is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

// Include the function library
require "Include/Config.php";
require "Include/Functions.php";

// Security: User must have Manage Groups or Edit Records permissions
// Otherwise, re-direct them to the main menu.
if (!$_SESSION['bManageGroups'] && !$_SESSION['bEditRecords'])
{
	Redirect("Menu.php");
	exit;
}

// Get the new property value from the request
if (isset($_POST["PropertyID"])) {
	$iPropertyID = FilterInput($_POST["PropertyID"],'int');
	$sAction = "add";
} else {
	$iPropertyID = FilterInput($_GET["PropertyID"],'int');
	$sAction = "edit";
}

// Is there a PersonID in the querystring?
if (isset($_GET["PersonID"]) && $_SESSION['bEditRecords']) {

	$iPersonID = FilterInput($_GET["PersonID"],'int');
	$iRecordID = $iPersonID;
	$sQuerystring = "?PersonID=" . $iPersonID;
	$sTypeName = gettext("Person");
	$sBackPage = "PersonView.php?PersonID=" . $iPersonID;

	// Get the name of the person
	$sSQL = "SELECT per_FirstName, per_LastName FROM person_per WHERE per_ID = " . $iPersonID;
	$rsName = RunQuery($sSQL);
	$aRow = mysql_fetch_array($rsName);
	$sName = $aRow["per_LastName"] . ", " . $aRow["per_FirstName"];
}

// Is there a GroupID in the querystring?
elseif (isset($_GET["GroupID"]) && $_SESSION['bManageGroups'])
{
	$iGroupID = FilterInput($_GET["GroupID"],'int');
	$iRecordID = $iGroupID;
	$sQuerystring = "?GroupID=" . $iGroupID;
	$sTypeName = gettext("Group");
	$sBackPage = "GroupView.php?GroupID=" . $iGroupID;

	// Get the name of the group
	$sSQL = "SELECT grp_Name FROM group_grp WHERE grp_ID = " . $iGroupID;
	$rsName = RunQuery($sSQL);
	$aRow = mysql_fetch_array($rsName);
	$sName = $aRow["grp_Name"];
}

// Is there a FamilyID in the querystring?
elseif (isset($_GET["FamilyID"]) && $_SESSION['bEditRecords'])
{
	$iFamilyID = FilterInput($_GET["FamilyID"],'int');
	$iRecordID = $iFamilyID;
	$sQuerystring = "?FamilyID=" . $iFamilyID;
	$sTypeName = gettext("Family");
	$sBackPage = "FamilyView.php?FamilyID=" . $iFamilyID;

	// Get the name of the family
	$sSQL = "SELECT fam_Name FROM family_fam WHERE fam_ID = " . $iFamilyID;
	$rsName = RunQuery($sSQL);
	$aRow = mysql_fetch_array($rsName);
	$sName = $aRow["fam_Name"];
}

// Somebody tried to call the script with no options
else
{
	Redirect("Menu.php");
}

// If no property, return to previous page
if (!$iPropertyID)
	Redirect ("$sBackPage");

function UpdateProperty($iRecordID,$sValue,$iPropertyID,$sAction)
{
	global $cnInfoCentral;

	if ($sAction == "add")
	{
		// Make sure this property isn't already assigned
		$sSQL = "SELECT * FROM record2property_r2p WHERE r2p_record_ID = $iRecordID AND r2p_pro_ID = $iPropertyID";
		$rsExistingTest = RunQuery($sSQL);

		if (mysql_num_rows($rsExistingTest) == 0)
		{
			$sSQL = "INSERT INTO record2property_r2p (r2p_record_ID,r2p_pro_ID,r2p_Value) VALUES ($iRecordID,$iPropertyID,'$sValue')";
			RunQuery($sSQL);
		}
	} else {
		$sSQL = "UPDATE record2property_r2p SET r2p_Value = '$sValue' WHERE r2p_record_ID = $iRecordID AND r2p_pro_ID = $iPropertyID";
		RunQuery($sSQL);
	}
}

// Was the form submitted?
if (isset($_POST["SecondPass"]))
{
	// Get the action (this will overwrite the value set at the top of the page, which is fine)
	$sAction = $_POST["Action"];

	// Get the value
	$sValue = FilterInput($_POST["Value"]);

	// Update the property
	UpdateProperty($iRecordID, $sValue, $iPropertyID, $sAction);

	// Set the Global Message
	$_SESSION['sGlobalMessage'] = gettext("Property successfully assigned.");

	// Back to the PersonView
	Redirect($sBackPage);
}

// Get the name of the property
$sSQL = "SELECT pro_Name, pro_Prompt FROM property_pro WHERE pro_ID = " . $iPropertyID;
$rsProperty = RunQuery($sSQL);
$aRow = mysql_fetch_array($rsProperty);
$sPropertyName = $aRow["pro_Name"];
$sPrompt = $aRow["pro_Prompt"];

// If there's no prompt, then just do the insert
if (strlen($sPrompt) == 0) {

	UpdateProperty($iRecordID, "", $iPropertyID, $sAction);

	// Set the Global Message
	$_SESSION['sGlobalMessage'] = gettext("Property successfully assigned.");

	// Back to the PersonView
	Redirect($sBackPage);

}

// If we're editing, get the value
if ($sAction == "edit") {
	$sSQL = "SELECT r2p_Value FROM record2property_r2p WHERE r2p_pro_ID = " . $iPropertyID . " AND r2p_record_ID = " . $iRecordID;
	$rsValue = RunQuery($sSQL);
	$aRow = mysql_fetch_array($rsValue);
	$sValue = $aRow["r2p_Value"];
}

// Set the page title and include HTML header
$sPageTitle = $sTypeName . gettext(" Property Assignment");
require "Include/Header.php";
?>

<form method="post" action="<?php echo $_SERVER['PHP_SELF'] . $sQuerystring . "&PropertyID=" . $iPropertyID; ?>">
<input type="hidden" name="SecondPass" value="True">
<input type="hidden" name="Action" value="<?php echo $sAction; ?>">

<table cellpadding="4">
	<tr>
		<td align="right"><b><?php echo $sTypeName ?>:</b></td>
		<td><?php echo $sName; ?></td>
	</tr>
	<tr>
		<td align="right"><b><?php echo gettext("Assigning:"); ?></b></td>
		<td><?php echo $sPropertyName ?></td>
<?php if (strlen($sPrompt)) { ?>
		<tr>
			<td align="right" valign="top"><b><?php echo gettext("Value:"); ?></b></td>
			<td><?php echo $sPrompt; ?><br><textarea name="Value" cols="60" rows="10"><?php echo $sValue ?></textarea></td>
		</tr>
<?php } ?>
</table>

<p align="center"><input type="submit" class="icButton" <?php echo 'value="'; if (sAction == "add") { echo gettext("Assign"); } else { echo gettext("Update"); } echo '"'; ?> name="Submit"></p>

</form>

<?php
require "Include/Footer.php";
?>
