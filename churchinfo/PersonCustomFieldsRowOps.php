<?php
/*******************************************************************************
 *
 *  filename    : PersonCustomFieldsRowOps.php
 *  last change : 2003-03-30
 *  website     : http://www.infocentral.org
 *  copyright   : Copyright 2003 Chris Gebhardt (http://www.openserve.org)
 *
 *  function    : Row operations for the person custom fields form
 *
 *  InfoCentral is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.

 ******************************************************************************/

require "Include/Config.php";
require "Include/Functions.php";

// Security: user must be administrator to use this page.
if (!$_SESSION['bAdmin'])
{
	Redirect("Menu.php");
	exit;
}

// Get the Group, Property, and Action from the querystring
$iOrderID = FilterInput($_GET["OrderID"],'int');
$sField = FilterInput($_GET["Field"]);
$sAction = $_GET["Action"];

switch ($sAction)
{
	// Move a field up:  Swap the custom_Order (ordering) of the selected row and the one above it
	case up:
		$sSQL = "UPDATE person_custom_master SET custom_Order = '" . $iOrderID . "' WHERE custom_Order = '" . ($iOrderID - 1) . "'";
		RunQuery($sSQL);
		$sSQL = "UPDATE person_custom_master SET custom_Order = '" . ($iOrderID - 1) . "' WHERE custom_Field = '" . $sField . "'";
		RunQuery($sSQL);
		break;

	// Move a field down:  Swap the custom_Order (ordering) of the selected row and the one below it
	case down:
		$sSQL = "UPDATE person_custom_master SET custom_Order = '" . $iOrderID . "' WHERE custom_Order = '" . ($iOrderID + 1) . "'";
		RunQuery($sSQL);
		$sSQL = "UPDATE person_custom_master SET custom_Order = '" . ($iOrderID + 1) . "' WHERE custom_Field = '" . $sField . "'";
		RunQuery($sSQL);
		break;

	// Delete a field from the form
	case delete:
		// Check if this field is a custom list type.  If so, the list needs to be deleted from list_lst.
		$sSQL = "SELECT type_ID,custom_Special FROM person_custom_master WHERE custom_Field = '" . $sField . "'";
		$rsTemp = RunQuery($sSQL);
		$aTemp = mysql_fetch_array($rsTemp);
		if ($aTemp[0] == 12)
		{
			$sSQL = "DELETE FROM list_lst WHERE lst_ID = $aTemp[1]";
			RunQuery($sSQL);
		}

		$sSQL = "ALTER TABLE `person_custom` DROP `" . $sField . "` ;";
		RunQuery($sSQL);

		$sSQL = "DELETE FROM person_custom_master WHERE custom_Field = '" . $sField . "'";
		RunQuery($sSQL);

		$sSQL = "SELECT * FROM person_custom_master";
		$rsPropList = RunQuery($sSQL);
		$numRows = mysql_num_rows($rsPropList);

		// Shift the remaining rows up by one, unless we've just deleted the only row
		if ($numRows != 0)
		{
			for ($reorderRow = $iOrderID+1; $reorderRow <= $numRows+1; $reorderRow++)
			{
				$sSQL = "UPDATE person_custom_master SET custom_Order = '" . ($reorderRow - 1) . "' WHERE custom_Order = '" . $reorderRow . "'";
				RunQuery($sSQL);
			}
		}
		break;

	// If no valid action was specified, abort and return to the GroupView
	default:
		Redirect("PersonCustomFieldsEditor.php");
		break;
}

// Reload the Form Editor page
Redirect("PersonCustomFieldsEditor.php");
exit;
?>
