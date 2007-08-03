<?php
/*******************************************************************************
 *
 *  filename    : OptionManagerRowOps.php
 *  last change : 2003-04-09
 *  website     : http://www.infocentral.org
 *  copyright   : Copyright 2003 Chris Gebhardt (http://www.openserve.org)
 *
 *  function    : Row operations for the option manager
 *
 *  InfoCentral is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.

 ******************************************************************************/

require "Include/Config.php";
require "Include/Functions.php";

if (!$_SESSION['bAdmin'])
{
	Redirect("Menu.php");
}

// Get the Order, ID, Mode, and Action from the querystring
$iOrder = FilterInput($_GET["Order"],'int');  // the option Sequence
$sAction = $_GET["Action"];
$iID = FilterInput($_GET["ID"],'int');  // the option ID
$menu = trim($_GET["menu"]);

switch ($sAction)
{
	// Move a field up:  Swap the OptionSequence (ordering) of the selected row and the one above it
	case up:
		$sSQL = "UPDATE menuconfig_mcf SET sortorder = " . $iOrder . " WHERE parent = '$menu' AND sortorder = " . ($iOrder - 1);
		RunQuery($sSQL);
		$sSQL = "UPDATE menuconfig_mcf SET sortorder = " . ($iOrder - 1) . " WHERE parent = '$menu' AND mid = " . $iID ;
		RunQuery($sSQL);
		break;

	// Move a field down:  Swap the OptionSequence (ordering) of the selected row and the one below it
	case down:
		$sSQL = "UPDATE menuconfig_mcf SET sortorder = " . $iOrder . " WHERE parent = '$menu' AND sortorder = " . ($iOrder + 1) ;
		RunQuery($sSQL);
		$sSQL = "UPDATE menuconfig_mcf SET sortorder = " . ($iOrder + 1) . " WHERE parent = '$menu' AND mid = " . $iID ;
		RunQuery($sSQL);
		break;

	// Delete a field from the form
	case delete:
		$sSQL = "SELECT '' FROM menuconfig_mcf WHERE parent = '$menu'";
		$rsPropList = RunQuery($sSQL);
		$numRows = mysql_num_rows($rsPropList);

		// Make sure we never delete the only option
		if ($numRows > 1)
		{
			$sSQL = "DELETE FROM menuconfig_mcf WHERE parent ='$listID' AND sortorder = " . $iOrder . " AND mid = ". $iID ;
			RunQuery($sSQL);

			// Shift the remaining rows up by one
			for ($reorderRow = $iOrder+1; $reorderRow <= $numRows+1; $reorderRow++)
			{
				$sSQL = "UPDATE menuconfig_mcf SET sortorder = " . ($reorderRow - 1) . " WHERE parent = '$menu' AND sortorder = " . $reorderRow ;
				RunQuery($sSQL);
			}
		}
		break;

	// If no valid action was specified, abort
	default:
		Redirect("Menu.php");
		break;
}

// Reload the option manager page
Redirect("MenuManager.php?menu=$menu");
exit;
?>