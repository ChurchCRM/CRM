<?php
/*******************************************************************************
 *
 *  filename    : SelectDelete
 *  last change : 2003-01-07
 *  website     : http://www.infocentral.org
 *  copyright   : Copyright 2001-2003 Deane Barker, Lewis Franklin
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

// Security: User must have Delete records permission
// Otherwise, re-direct them to the main menu.
if (!$_SESSION['bDeleteRecords'])
{
	Redirect("Menu.php");
	exit;
}


if (!empty($_GET["FamilyID"])) $iFamilyID = FilterInput($_GET["FamilyID"],'int');
if (!empty($_GET["PersonID"])) $iPersonID = FilterInput($_GET["PersonID"],'int');
if (!empty($_GET["mode"])) $sMode = $_GET["mode"];

//Set the Page Title
if($sMode == 'person')
	$sPageTitle = gettext("Person Delete Confirmation");
else
	$sPageTitle = gettext("Family Delete Confirmation");

function DeletePerson($iPersonID)
{
	// Remove person from all groups they belonged to
	$sSQL = "SELECT p2g2r_grp_ID FROM person2group2role_p2g2r WHERE p2g2r_per_ID = " . $iPersonID;
	$rsAssignedGroups = RunQuery($sSQL);
	while ($aRow = mysql_fetch_array($rsAssignedGroups))
	{
		extract($aRow);
		RemoveFromGroup($iPersonID, $p2g2r_grp_ID);
	}

	// Remove custom field data
	$sSQL = "DELETE FROM person_custom WHERE per_ID = " . $iPersonID;
	RunQuery($sSQL);

	// Remove note data
	$sSQL = "DELETE FROM note_nte WHERE nte_per_ID = " . $iPersonID;
	RunQuery($sSQL);

	// Delete the Person record
	$sSQL = "DELETE FROM person_per WHERE per_ID = " . $iPersonID;
	RunQuery($sSQL);

	// Remove person property data
	$sSQL = "SELECT pro_ID FROM property_pro WHERE pro_Class='p'";
	$rsProps = RunQuery($sSQL);

	while($aRow = mysql_fetch_row($rsProps)) {
		$sSQL = "DELETE FROM record2property_r2p WHERE r2p_pro_ID = " . $aRow[0] . " AND r2p_record_ID = " . $iPersonID;
		RunQuery($sSQL);
	}

	// Delete any User record
	// $sSQL = "DELETE FROM user_usr WHERE usr_per_ID = " . $iPersonID;
	// RunQuery($sSQL);

	// Make sure person was not in the cart
	RemoveFromPeopleCart($iPersonID);
}

//Do we have deletion confirmation?
if (isset($_GET["Confirmed"]))
{
	if ($sMode == 'person')
	{
		// Make sure this person is not a user
		$sSQL = "SELECT '' FROM user_usr WHERE usr_per_ID = " . $iPersonID;
		$rsUser = RunQuery($sSQL);
		$bIsUser = (mysql_num_rows($rsUser) > 0);

		if (!$bIsUser)
		{
			DeletePerson($iPersonID);

			// Redirect back to the list
			Redirect("SelectList.php?mode=person");
		}
	}
	else
	{
		// Delete all associated Notes associated with this Family record
		$sSQL = "DELETE FROM note_nte WHERE nte_fam_ID = " . $iFamilyID;
		RunQuery($sSQL);

		// Remove family property data
		$sSQL = "SELECT pro_ID FROM property_pro WHERE pro_Class='f'";
		$rsProps = RunQuery($sSQL);

		while($aRow = mysql_fetch_row($rsProps)) {
			$sSQL = "DELETE FROM record2property_r2p WHERE r2p_pro_ID = " . $aRow[0] . " AND r2p_record_ID = " . $iFamilyID;
			RunQuery($sSQL);
		}

		if (isset($_GET["Members"]))
		{
			// Delete all persons that were in this family
			$sSQL = "SELECT per_ID FROM person_per WHERE per_fam_ID = " . $iFamilyID;
			$rsPersons = RunQuery($sSQL);
			while($aRow = mysql_fetch_row($rsPersons))
			{
				DeletePerson($aRow[0]);
			}
		}
		else
		{
			// Reset previous members' family ID to 0 (undefined)
			$sSQL = "UPDATE person_per SET per_fam_ID = 0 WHERE per_fam_ID = " . $iFamilyID;
			RunQuery($sSQL);
		}

		// Delete the specified Family record
		$sSQL = "DELETE FROM family_fam WHERE fam_ID = " . $iFamilyID;
		RunQuery($sSQL);

		// Redirect back to the family listing
		Redirect("SelectList.php?mode=family");
	}
}

if($sMode == 'person')
{
	// Get the data on this person
	$sSQL = "SELECT per_FirstName, per_LastName FROM person_per WHERE per_ID = " . $iPersonID;
	$rsPerson = RunQuery($sSQL);
	extract(mysql_fetch_array($rsPerson));

	// See if this person is a user
	$sSQL = "SELECT '' FROM user_usr WHERE usr_per_ID = " . $iPersonID;
	$rsUser = RunQuery($sSQL);
	$bIsUser = (mysql_num_rows($rsUser) > 0);
}
else
{
	//Get the family record in question
	$sSQL = "SELECT * FROM family_fam WHERE fam_ID = " . $iFamilyID;
	$rsFamily = RunQuery($sSQL);
	extract(mysql_fetch_array($rsFamily));
}

require "Include/Header.php";

if($sMode == 'person')
{

	if ($bIsUser) {
		echo "<p class=\"LargeText\">" . gettext("Sorry, this person is a user.  An administrator must remove their user status before they may be deleted from the database.") . "<br><br>";
		echo "<a href=\"PersonView.php?PersonID=" . $iPersonID . ">" . gettext("Return to Person View") . "</a></p>";
	}
	else
	{
		echo "<p>" . gettext("Please confirm deletion of:") . "</p>";
		echo "<p class=\"ShadedBox\">" . $per_FirstName . " " . $per_LastName . "</p>";
		echo "<BR>";
		echo "<p><h3><a href=\"SelectDelete.php?mode=person&PersonID=" . $iPersonID . "&Confirmed=Yes\">" . gettext("Yes, delete this record") . "</a>" . gettext(" (This action CANNOT be undone!)") . "</h3></p>";
		echo "<p><h2><a href=\"PersonView.php?PersonID=" . $iPersonID . "\">" . gettext("No, cancel this deletion") . "</a></h2></p>";
	}
}
else
{
	echo "<p>" . gettext("Please confirm deletion of this family record:") . "</p>";
	echo "<p>" . gettext("Note: This will also delete all Notes associated with this Family record.") . "</p>";
	echo "<div class=\"ShadedBox\">";
	echo "<div class=\"LightShadedBox\"><strong>" . gettext("Family Name:") . "</strong></div>";
	echo "&nbsp;" . $fam_Name;
	echo "</div>";
	echo "<p class=\"MediumText\"><a href=\"SelectDelete.php?Confirmed=Yes&FamilyID=" . $iFamilyID . "\">" . gettext("Delete Family Record ONLY") . "</a>" . gettext(" (this action cannot be undone)") . "</p>";
	echo "<div class=\"ShadedBox\">";
	echo "<div class=\"LightShadedBox\"><strong>" . gettext("Family Members:") . "</strong></div>";
	//List Family Members
	$sSQL = "SELECT * FROM person_per WHERE per_fam_ID = " . $iFamilyID;
	$rsPerson = RunQuery($sSQL);
	while($aRow = mysql_fetch_array($rsPerson)) {
		extract($aRow);
		echo "&nbsp;" . $per_FirstName . " " . $per_LastName . "<br>";
		RunQuery($sSQL);
	}
	echo "</div>";
	echo "<p class=\"MediumText\"><a href=\"SelectDelete.php?Confirmed=Yes&Members=Yes&FamilyID=" . $iFamilyID . "\">" . gettext("Delete Family Record AND Family Members") . "</a>" . gettext(" (this action cannot be undone)") . "</p>";
	echo "<br><p class=\"LargeText\"><a href=\"SelectList.php\">" . gettext("No, cancel this deletion</a>") . "</p>";
}
require "Include/Footer.php";
?>
