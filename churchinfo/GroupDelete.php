<?php
/*******************************************************************************
 *
 *  filename    : GroupDelete.php
 *  last change : 2003-01-31
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

// Security: User must have Manage Groups permission
// Otherwise, re-direct them to the main menu.
if (!$_SESSION['bManageGroups'])
{
	Redirect("Menu.php");
	exit;
}

// Set the page title
$sPageTitle = gettext("Group Delete Confirmation");

// Get the PersonID from the querystring
$iGroupID = FilterInput($_GET["GroupID"],'int');

$sSQL = "SELECT grp_hasSpecialProps, grp_RoleListID FROM group_grp WHERE grp_ID =" . $iGroupID;
$rsTemp = RunQuery($sSQL);
$aTemp = mysql_fetch_array($rsTemp);
$hasSpecialProps = $aTemp[0];
$iRoleListID = $aTemp[1];

// Do we have deletion confirmation?
if (isset($_GET["Confirmed"]))
{
	//Delete all Members of this group
	$sSQL = "DELETE FROM person2group2role_p2g2r WHERE p2g2r_grp_ID = " . $iGroupID;
	RunQuery($sSQL);

	//Delete all Roles for this Group
	$sSQL = "DELETE FROM list_lst WHERE lst_ID = " . $iRoleListID;
	RunQuery($sSQL);

	// Remove group property data
	$sSQL = "SELECT pro_ID FROM property_pro WHERE pro_Class='g'";
	$rsProps = RunQuery($sSQL);

	while($aRow = mysql_fetch_row($rsProps)) {
		$sSQL = "DELETE FROM record2property_r2p WHERE r2p_pro_ID = " . $aRow[0] . " AND r2p_record_ID = " . $iGroupID;
		RunQuery($sSQL);
	}

	if ($hasSpecialProps == 'true')
	{
		// Drop the group-specific properties table and all references in the master index
		$sSQL = "DROP TABLE groupprop_" . $iGroupID;
		RunQuery($sSQL);

		$sSQL = "DELETE FROM groupprop_master WHERE grp_ID = " . $iGroupID;
		RunQuery($sSQL);
	}

	//Delete the Group
	$sSQL = "DELETE FROM group_grp WHERE grp_ID = " . $iGroupID;
	RunQuery($sSQL);

	//Redirect back to the family listing
	Redirect("GroupList.php");

}

//Get the group record in question
$sSQL = "SELECT * FROM group_grp WHERE grp_ID = " . $iGroupID;
$rsGroup = RunQuery($sSQL);
extract(mysql_fetch_array($rsGroup));

require "Include/Header.php";

?>

<p>
	<?php echo gettext("Please confirm deletion of this group record:"); ?>
</p>

<p class="ShadedBox">
	<?php echo $grp_Name; ?>
</p>

<p class="LargeError">
	<?php echo gettext("This will also delete all Roles and Group-Specific Property data associated with this Group record."); ?>
</p>

<p align="left">
	<a href="GroupDelete.php?Confirmed=Yes&GroupID=<?php echo $iGroupID ?>"><?php echo gettext("Yes, delete this record"); ?></a> <?php echo gettext("(this action cannot be undone)"); ?>
</p>
<p align="left">
	<a href="GroupList.php"><?php echo gettext("No, cancel this deletion"); ?></a>
</p>

<?php
require "Include/Footer.php";
?>
