<?php
/*******************************************************************************
 *
 *  filename    : PropertyUnassign.php
 *  last change : 2003-01-07
 *  website     : http://www.infocentral.org
 *  copyright   : Copyright 2001, 2002 Deane Barker
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

// Security: User must have Manage Groups or Edit Records permissions
// Otherwise, re-direct them to the main menu.
if (!$_SESSION['bManageGroups'] && !$_SESSION['bEditRecords'])
{
	Redirect("Menu.php");
	exit;
}

//Get the new property value from the post collection
$iPropertyID = FilterInput($_GET["PropertyID"],'int');

// Is there a PersonID in the querystring?
if (isset($_GET["PersonID"]) && $_SESSION['bEditRecords'])
{
	$iPersonID = FilterInput($_GET["PersonID"],'int');
	$iRecordID = $iPersonID;
	$sQuerystring = "?PersonID=" . $iPersonID;
	$sTypeName = "Person";
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
	$sTypeName = "Group";
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
	$sTypeName = "Family";
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
	exit;
}

//Do we have confirmation?
if (isset($_GET["Confirmed"]))
{
	$sSQL = "DELETE FROM record2property_r2p WHERE r2p_record_ID = " . $iRecordID . " AND r2p_pro_ID = " . $iPropertyID;
	RunQuery($sSQL);
	Redirect($sBackPage);
	exit;
}

//Get the name of the property
$sSQL = "SELECT pro_Name FROM property_pro WHERE pro_ID = " . $iPropertyID;
$rsProperty = RunQuery($sSQL);
$aRow = mysql_fetch_array($rsProperty);
$sPropertyName = $aRow["pro_Name"];

//Set the page title
$sPageTitle = $sTypeName . gettext(" Property Unassignment");

//Include the header
require "Include/Header.php";

?>

<?php echo gettext("Please confirm removal of this property from this") . " " . $sTypeName;?>:


<table cellpadding="4">
	<tr>
		<td align="right"><b><?php echo $sTypeName ?>:</b></td>
		<td><?php echo $sName; ?></td>
	</tr>
	<tr>
		<td align="right"><b><?php echo gettext("Unassigning:"); ?></b></td>
		<td><?php echo $sPropertyName ?></td>
	</tr>
</table>

<p>
	<a href="PropertyUnassign.php<?php echo $sQuerystring . "&PropertyID=" . $iPropertyID . "&Confirmed=Yes"; ?>"><?php echo gettext("Yes, unassign this Property"); ?></a>
</p>
<p>
	<a href="<?php echo $sBackPage; ?>"><?php echo gettext("No, retain this assignment"); ?></a>
</p>

<?php
require "Include/Footer.php";
?>
