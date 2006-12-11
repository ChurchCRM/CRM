<?php
/*******************************************************************************
*
*  filename    : GroupList.php
*  website     : http://www.churchdb.org
*  copyright   : Copyright 2001, 2002 Deane Barker
*
*
*  Additional Contributors:
*  2006 Ed Davis
*
*
*  Copyright Contributors
*
*  ChurchInfo is free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  This file best viewed in a text editor with tabs stops set to 4 characters
*
******************************************************************************/
//Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

//Set the page title
$sPageTitle = gettext('Group Listing');

require 'Include/Header.php';

if ($_SESSION['bManageGroups']) 
{
	echo '<p align="center"><a href="GroupEditor.php">';
	echo gettext('Add a New Group') . '</a></p>';
}

//Get all group records
$sSQL = "SELECT * FROM group_grp LEFT JOIN list_lst "
      . "ON grp_Type = lst_OptionID "
      . "WHERE lst_ID='3' "
      . "ORDER BY lst_OptionSequence, grp_Name";

$rsGroups = RunQuery($sSQL);

echo '	<center><table cellpadding="4" cellspacing="0" width="70%">
		<tr class="TableHeader">
		<td>' . gettext('Name') . '</td>
		<td align="center">' . gettext('Members') . '</td>
		<td align="center">' . gettext('Type') . '</td>
		<td align="center">' . gettext('Add to Cart') . '</td>
		<td align="center">' . gettext('Remove from Cart') . '</td></tr>';

//Set the initial row color
$sRowClass = 'RowColorA';

//Loop through the person recordset
while ($aRow = mysql_fetch_array($rsGroups))
{
	extract($aRow);

	//Alternate the row color
	$sRowClass = AlternateRowStyle($sRowClass);

	//Get the count for this group
	$sSQL = "SELECT Count(*) AS iCount FROM person2group2role_p2g2r " .
			"WHERE p2g2r_grp_ID='$grp_ID'";
	$rsMemberCount = mysql_fetch_array(RunQuery($sSQL));
	extract($rsMemberCount);
		
	//Get the group's type name
	if ($grp_Type > 0)
	{
		$sSQL =	"SELECT lst_OptionName FROM list_lst WHERE " . 
				"lst_ID=3 AND lst_OptionID = " . $grp_Type;
		$rsGroupType = mysql_fetch_array(RunQuery($sSQL));
		$sGroupType = $rsGroupType[0];
	}
	else
		$sGroupType = gettext('Undefined');

		//Display the row

		echo '	<tr class="' .$sRowClass. '">
				<td><a href="GroupView.php?GroupID=' .$grp_ID. '">' .$grp_Name. '</a></td>
				<td align="center">' .$iCount. '</td>
				<td align="center">' .$sGroupType. '</td>
				<td align="center">'; // end echo

		$sSQL =	"SELECT p2g2r_per_ID FROM person2group2role_p2g2r " .
				"WHERE p2g2r_grp_ID='$grp_ID'";
		$rsGroupMembers = RunQuery($sSQL);

		$bNoneInCart = TRUE;
		$bAllInCart = TRUE;
		//Loop through the recordset
		while ($aPeople = mysql_fetch_array($rsGroupMembers))
		{
			extract($aPeople);

			if (!isset($_SESSION['aPeopleCart']))
				$bAllInCart = FALSE; // Cart does not exist.  This person is not in cart.
			elseif (!in_array($p2g2r_per_ID, $_SESSION['aPeopleCart'], false))
				$bAllInCart = FALSE; // This person is not in cart.
			elseif (in_array($p2g2r_per_ID, $_SESSION['aPeopleCart'], false))
				$bNoneInCart = FALSE; // This person is in the cart
		}

		if (!$bAllInCart)
		{
			// Add to cart ... screen should return to this location
			// after this group is added to cart
			echo '<a onclick="saveScrollCoordinates()" 
					href="GroupList.php?AddGroupToPeopleCart=' .$grp_ID. '">' .
					gettext('Add all') . '</a>';
		} else {
            echo '&nbsp;';
        }
    

		echo '</td><td align="center">';

		if (!$bNoneInCart)
		{
			// Add to cart ... screen should return to this location
			// after this group is removed from cart
			echo '	<a onclick="saveScrollCoordinates()"
					href="GroupList.php?RemoveGroupFromPeopleCart=' .$grp_ID. '">' .
					gettext('Remove all') . '</a>';
		} else {
            echo '&nbsp;';
        }

		echo '</td>';
	}

	//Close the table
	echo '</table></center>';

require 'Include/Footer.php';
?>
