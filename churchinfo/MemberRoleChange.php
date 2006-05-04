<?php
/*******************************************************************************
 *
 *  filename    : MemberRoleChange.php
 *  last change : 2003-04-03
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

// Security: User must have Manage Groups & Roles permission
if (!$_SESSION['bManageGroups'])
{
	Redirect("Menu.php");
	exit;
}

//Set the page title
$sPageTitle = gettext("Member Role Change");

//Get the GroupID from the querystring
$iGroupID = FilterInput($_GET["GroupID"],'int');

//Get the PersonID from the querystring
$iPersonID = FilterInput($_GET["PersonID"],'int');

//Get the return location flag from the querystring
$iReturn = $_GET["Return"];

//Was the form submitted?
if (isset($_POST["Submit"]))
{

	//Get the new role
	$iNewRole = FilterInput($_POST["NewRole"]);

	//Update the database
	$sSQL = "UPDATE person2group2role_p2g2r SET p2g2r_rle_ID = " . $iNewRole . " WHERE p2g2r_per_ID = $iPersonID AND p2g2r_grp_ID = $iGroupID";
	RunQuery($sSQL);

	//Reroute back to the proper location
	if($iReturn)
		Redirect("GroupView.php?GroupID=" . $iGroupID);
	else
		Redirect("PersonView.php?PersonID=" .$iPersonID);
}

//Get their current role
$sSQL = "SELECT per_FirstName, per_LastName, grp_Name, grp_RoleListID, lst_OptionName AS sRoleName, p2g2r_rle_ID AS iRoleID
		FROM person_per
		LEFT JOIN person2group2role_p2g2r ON p2g2r_per_ID = per_ID 
		LEFT JOIN group_grp ON p2g2r_grp_ID = grp_ID
		LEFT JOIN list_lst ON lst_ID = grp_RoleListID
		WHERE per_ID = $iPersonID AND grp_ID = $iGroupID";
$rsCurrentRole = mysql_fetch_array(RunQuery($sSQL));
extract($rsCurrentRole);

//Get all the possible roles
$sSQL = "SELECT * FROM list_lst WHERE lst_ID = $grp_RoleListID ORDER BY lst_OptionSequence";
$rsAllRoles = RunQuery($sSQL);

//Include the header
require "Include/Header.php"

?>

<form method="post" action="MemberRoleChange.php?GroupID=<?php echo $iGroupID ?>&PersonID=<?php echo $iPersonID ?>&Return=<?php echo $iReturn ?>">

<table cellpadding="4">
	<tr>
		<td align="right"><b><?php echo gettext("Group Name:"); ?></b></td>
		<td><?php echo $grp_Name ?></td>
	</tr>
	<tr>
		<td align="right"><b><?php echo gettext("Member's Name:"); ?></b></td>
		<td><?php echo $per_LastName . ", " . $per_FirstName ?></td>
	</tr>
	<tr>
		<td align="right"><b><?php echo gettext("Current Role:"); ?></b></td>
		<td><?php echo $sRoleName ?></td>
	</tr>
	<tr>
		<td align="right"><b><?php echo gettext("New Role:"); ?></b></td>
		<td>
			<select name="NewRole">
				<?php

				//Loop through all the possible roles
				while ($aRow = mysql_fetch_array($rsAllRoles))
				{
					extract($aRow);

					//If this is the current role, select it
					if ($iRoleID == $lst_OptionID)
					{
						$sSelected = "selected";
					}
					else
					{
						$sSelected = "";
					}
					//Write the <option> tag
					echo "<option value=\"" . $lst_OptionID . "\" " . $sSelected . ">" . $lst_OptionName . "</option>";
				}
				?>
			</select>
		</td>
	</tr>
	<tr>
		<td colspan="2" align="center">
			<input type="submit" class="icButton" name="Submit" value="<?php echo gettext("Update"); ?>">
			<?php
				if ($iReturn)
					echo "&nbsp;&nbsp;<input type=\"button\" class=\"icButton\" name=\"Cancel\" value=\"" . gettext("Cancel") . "\" onclick=\"document.location='GroupView.php?GroupID=" . $iGroupID . "';\">";
				else
					echo "&nbsp;&nbsp;<input type=\"button\" class=\"icButton\" name=\"Cancel\" value=\"" . gettext("Cancel") . "\" onclick=\"document.location='PersonView.php?PersonID=" . $iPersonID . "';\">";
			?>
		</td>
	</tr>
</table>
</form>
<?php
require "Include/Footer.php";
?>
