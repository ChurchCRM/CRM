<?php
/*******************************************************************************
 *
 *  filename    : GroupPropsFormRowOps.php
 *  last change : 2003-02-02
 *  website     : http://www.infocentral.org
 *  copyright   : Copyright 2003 Chris Gebhardt (http://www.openserve.org)
 *
 *  function    : Row operations for the group-specific properties form
 *
 *  InfoCentral is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.

 ******************************************************************************/

require "Include/Config.php";
require "Include/Functions.php";

// Security: user must be allowed to edit records to use this page.
if (!$_SESSION['bManageGroups'])
{
	Redirect("Menu.php");
	exit;
}

// Get the Group, Property, and Action from the querystring
$iGroupID = FilterInput($_GET["GroupID"],'int');
$iPropID = FilterInput($_GET["PropID"],'int');
$sField = FilterInput($_GET["Field"]);
$sAction = $_GET["Action"];

// Get the group information
$sSQL = "SELECT * FROM group_grp WHERE grp_ID = " . $iGroupID;
$rsGroupInfo = RunQuery($sSQL);
extract(mysql_fetch_array($rsGroupInfo));

// Abort if user tries to load with group having no special properties.
if ($grp_hasSpecialProps == 'false')
{
	Redirect("GroupView.php?GroupID=" . $iGroupID);
}

switch ($sAction)
{
	// Move a field up:  Swap the prop_ID (ordering) of the selected row and the one above it
	case up:
		$sSQL = "UPDATE groupprop_master SET prop_ID = '" . $iPropID . "' WHERE grp_ID = '" . $iGroupID . "' AND prop_ID = '" . ($iPropID - 1) . "'";
		RunQuery($sSQL);
		$sSQL = "UPDATE groupprop_master SET prop_ID = '" . ($iPropID - 1) . "' WHERE grp_ID = '" . $iGroupID . "' AND prop_Field = '" . $sField . "'";
		RunQuery($sSQL);
		break;

	// Move a field down:  Swap the prop_ID (ordering) of the selected row and the one below it
	case down:
		$sSQL = "UPDATE groupprop_master SET prop_ID = '" . $iPropID . "' WHERE grp_ID = '" . $iGroupID . "' AND prop_ID = '" . ($iPropID + 1) . "'";
		RunQuery($sSQL);
		$sSQL = "UPDATE groupprop_master SET prop_ID = '" . ($iPropID + 1) . "' WHERE grp_ID = '" . $iGroupID . "' AND prop_Field = '" . $sField . "'";
		RunQuery($sSQL);
		break;

	// Delete a field from the form
	case delete:
		// Check if this field is a custom list type.  If so, the list needs to be deleted from list_lst.
		$sSQL = "SELECT type_ID,prop_Special FROM groupprop_master WHERE grp_ID = '" . $iGroupID . "' AND prop_Field = '" . $sField . "'";
		$rsTemp = RunQuery($sSQL);
		$aTemp = mysql_fetch_array($rsTemp);
		if ($aTemp[0] == 12)
		{
			$sSQL = "DELETE FROM list_lst WHERE lst_ID = $aTemp[1]";
			RunQuery($sSQL);
		}

		$sSQL = "ALTER TABLE `groupprop_" . $iGroupID . "` DROP `" . $sField . "` ;";
		RunQuery($sSQL);

		$sSQL = "DELETE FROM groupprop_master WHERE grp_ID = '" . $iGroupID . "' AND prop_ID = '" . $iPropID . "'";
		RunQuery($sSQL);

		$sSQL = "SELECT *	FROM groupprop_master WHERE grp_ID = " . $iGroupID;
		$rsPropList = RunQuery($sSQL);
		$numRows = mysql_num_rows($rsPropList);

		// Shift the remaining rows up by one, unless we've just deleted the only row
		if ($numRows != 0)
		{
			for ($reorderRow = $iPropID+1; $reorderRow <= $numRows+1; $reorderRow++)
			{
				$sSQL = "UPDATE groupprop_master SET prop_ID = '" . ($reorderRow - 1) . "' WHERE grp_ID = '" . $iGroupID . "' AND prop_ID = '" . $reorderRow . "'";
				RunQuery($sSQL);
			}
		}
		break;

	// If no valid action was specified, abort and return to the GroupView
	default:
		Redirect("GroupView.php?GroupID=" . $iGroupID);
		break;
}

// Reload the Form Editor page
Redirect("GroupPropsFormEditor.php?GroupID=" . $iGroupID);
exit;
?>
