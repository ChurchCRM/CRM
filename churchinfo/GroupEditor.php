<?php
/*******************************************************************************
 *
 *  filename    : GroupEditor.php
 *  last change : 2003-04-15
 *  website     : http://www.infocentral.org
 *  copyright   : Copyright 2001, 2002, 2003 Deane Barker, Chris Gebhardt
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
if (!$_SESSION['bManageGroups'])
{
	Redirect("Menu.php");
	exit;
}

//Set the page title
$sPageTitle = gettext("Group Editor");

//Get the GroupID from the querystring
$iGroupID = FilterInput($_GET["GroupID"],'int');

$bEmptyCart = ($_GET["EmptyCart"] == "yes") && count($_SESSION['aPeopleCart']) > 0;

//Is this the second pass?
if (isset($_POST["GroupSubmit"]))
{

	//Assign everything locally
	$sName = FilterInput($_POST["Name"]);
	$iGroupType = FilterInput($_POST["GroupType"],'int');
	$iDefaultRole = FilterInput($_POST["DefaultRole"],'int');
	$sDescription = FilterInput($_POST["Description"]);
	$bUseGroupProps = $_POST["UseGroupProps"];

	//Did they enter a Name?
	if (strlen($sName) < 1)
	{
		$bNameError = True;
		$bErrorFlag = True;

	}

	// If no errors, then let's update...
	if (!$bErrorFlag)
	{
		// Are we creating a new group?
		if (strlen($iGroupID) < 1)
		{
			//Get a new Role List ID
			$sSQL = "SELECT MAX(lst_ID) FROM list_lst";
			$aTemp = mysql_fetch_array(RunQuery($sSQL));
			if ($aTemp[0] > 9)
				$newListID = $aTemp[0] + 1;
			else
				$newListID = 10;

			if ($bUseGroupProps)
				$sUseProps = 'true';
			else
				$sUseProps = 'false';
			$sSQL = "INSERT INTO group_grp (grp_Name, grp_Type, grp_Description, grp_hasSpecialProps, grp_DefaultRole, grp_RoleListID) VALUES ('" . $sName . "', " . $iGroupType . ", '" . $sDescription . "', '" . $sUseProps . "', '1', " . $newListID . ")";

			$bGetKeyBack = True;
			$bCreateGroupProps = $bUseGroupProps;
		}
		else
		{
			$sSQLtest = "SELECT grp_hasSpecialProps FROM group_grp WHERE grp_ID = " . $iGroupID;
			$rstest = RunQuery($sSQLtest);
			$aRow = mysql_fetch_array($rstest);

			$bCreateGroupProps = ($aRow[0] == 'false') && $bUseGroupProps;
			$bDeleteGroupProps = ($aRow[0] == 'true') && !$bUseGroupProps;

			$sSQL = "UPDATE group_grp SET grp_Name='" . $sName . "', grp_Type='" . $iGroupType . "', grp_Description='" . $sDescription . "'";

			if ($bCreateGroupProps)
				$sSQL .= ", grp_hasSpecialProps = 'true'";

			if ($bDeleteGroupProps)
			{
				$sSQL .= ", grp_hasSpecialProps = 'false'";
				$sSQLp = "DROP TABLE groupprop_" . $iGroupID;
				RunQuery($sSQLp);

				// need to delete the master index stuff
				$sSQLp = "DELETE FROM groupprop_master WHERE grp_ID = " . $iGroupID;
				RunQuery($sSQLp);
			}

			$sSQL .= " WHERE grp_ID = " . $iGroupID;
			$bGetKeyBack = False;
		}

		// execute the SQL
		RunQuery($sSQL);

		//If the user added a new record, we need to key back to the route to the GroupView page
		if ($bGetKeyBack)
		{
			//Get the key back
			$iGroupID = mysql_insert_id($cnInfoCentral);

			$sSQL = "INSERT INTO list_lst VALUES ($newListID, 1, 1,'Member')";
			RunQuery($sSQL);
		}

		// Create a table for group-specific properties
		if ( $bCreateGroupProps )
		{
			$sSQLp = "CREATE TABLE groupprop_" . $iGroupID . " (
						per_ID mediumint(8) unsigned NOT NULL default '0',
						PRIMARY KEY  (per_ID),
  						UNIQUE KEY per_ID (per_ID)
						) TYPE=MyISAM;";
			RunQuery($sSQLp);

			// If this is an existing group, add rows in this table for each member
			if ( !$bGetKeyBack )
			{
				$sSQL = "SELECT per_ID FROM person_per INNER JOIN person2group2role_p2g2r ON per_ID = p2g2r_per_ID WHERE p2g2r_grp_ID = " . $iGroupID . " ORDER BY per_ID";
				$rsGroupMembers = RunQuery($sSQL);

				while ($aRow = mysql_fetch_array($rsGroupMembers))
				{
					$sSQLr = "INSERT INTO groupprop_" . $iGroupID . " ( `per_ID` ) VALUES ( '" . $aRow[0] . "' );";
					RunQuery($sSQLr);
				}
			}
		}

		if ($_POST["EmptyCart"] && count($_SESSION['aPeopleCart']) > 0)
		{
			while ($element = each($_SESSION['aPeopleCart'])) {
				AddToGroup($_SESSION['aPeopleCart'][$element[key]],$iGroupID,$iDefaultRole);
			}

			$sGlobalMessage = $iCount . " records(s) successfully added to selected Group.";

			Redirect("GroupEditor.php?GroupID=" . $iGroupID . "&Action=EmptyCart");
		}
		else
		{
			Redirect("GroupEditor.php?GroupID=$iGroupID");
		}
	}

}
else
{
	//FirstPass
	//Are we editing or adding?
	if (strlen($iGroupID) > 0)
	{
		//Editing....
		//Get the information on this familyAge Groups for the drop down
		$sSQL = "SELECT * FROM group_grp WHERE grp_ID = " . $iGroupID;
		$rsGroup = RunQuery($sSQL);
		$aRow = mysql_fetch_array($rsGroup);

		$iGroupID = $aRow["grp_ID"];
		$iGroupType = $aRow["grp_Type"];
		$iDefaultRole = $aRow["grp_DefaultRole"];
		$iRoleListID = $aRow["grp_RoleListID"];
		$sName = $aRow["grp_Name"];
		$sDescription = $aRow["grp_Description"];
		$bHasSpecialProps = ($aRow["grp_hasSpecialProps"] == 'true');
	}
}

// Get Group Types for the drop-down
$sSQL = "SELECT * FROM list_lst WHERE lst_ID = 3 ORDER BY lst_OptionSequence";
$rsGroupTypes = RunQuery($sSQL);

require "Include/Header.php";

?>

<script language="javascript">
bStatus = false;

function confirmDelete() {
	if (!bStatus) {
		bStatus = confirm(<?php echo "'" . gettext("Are you sure you want to remove the group-specific person properties?  All group member properties data will be lost!") . "'"; ?>);
		document.GroupEdit.UseGroupProps.checked = !bStatus;
	}
	else
		bStatus = false;
}
function confirmAdd() {
	if (!bStatus) {
		bStatus = confirm(<?php echo "'" . gettext("This will create a group-specific properties table for this group.  You should then add needed properties with the Group-Specific Properties Form Editor.") . "'"; ?>);
		document.GroupEdit.UseGroupProps.checked = bStatus;
	}
	else
		bStatus = false;
}
</script>


<table border="0" width="100%">
<tr>

<td width="40%" valign="top" align="center">
	<form name="GroupEdit" method="post" action="GroupEditor.php?GroupID=<?php echo $iGroupID ?>">
	<table cellpadding="3">
		<tr>
			<td class="LabelColumn"><b><?php echo gettext("Name:"); ?></b></td>
			<td class="TextColumn"><input type="text" Name="Name" value="<?php echo htmlentities(stripslashes($sName),ENT_NOQUOTES, "UTF-8"); ?>" size="40" maxlength="50">
			<?php if ($bNameError) echo "<br><font color=\"red\">" . gettext("You must enter a name.") . "</font>"; ?>
			</td>
		</tr>

		<tr>
			<td class="LabelColumn"><b><?php echo gettext("Description:"); ?></b></td>
			<td class="TextColumnWithBottomBorder"><textarea name="Description" cols="40" rows="5"><?php echo htmlentities(stripslashes($sDescription),ENT_NOQUOTES, "UTF-8"); ?></textarea></td>
		</tr>

		<tr>
			<td class="LabelColumn"><b><?php echo gettext("Type of Group:"); ?></b></td>
			<td class="TextColumnWithBottomBorder">
				<select name="GroupType">
					<option value="0"><?php echo gettext("Unassigned"); ?></option>
					<option value="0">-----------------------</option>
					<?php
					while ($aRow = mysql_fetch_array($rsGroupTypes))
					{
						extract($aRow);
						echo "<option value=\"" . $lst_OptionID . "\"";
						if ($iGroupType == $lst_OptionID)
							echo " selected";
						echo ">" . $lst_OptionName . "</option>";
					}
					?>
				</select>
			</td>
		</tr>

		<tr>
			<td class="LabelColumn"><b><?php echo gettext("Group-Specific<br>Properties:"); ?></b></td>
			<td class="TextColumnWithBottomBorder">
				<?php echo gettext("Use group-specific properties?"); ?>
				<?php
				if ($bHasSpecialProps)
				{
					echo "<input type=\"checkbox\" name=\"UseGroupProps\" value=\"1\" onChange=\"confirmDelete();\" checked><br><br>";
					echo "<a class=\"SmallText\" href=\"GroupPropsFormEditor.php?GroupID=$iGroupID\">" . gettext("Edit Group-Specific Properties Form") . "</a>";
				}
				else
					echo "<input type=\"checkbox\" name=\"UseGroupProps\" value=\"1\" onChange=\"confirmAdd();\">";
				?>
			</td>

		<tr>
			<td class="SmallShadedBox" colspan="2" align="center"><input type="checkbox" name="EmptyCart" value="1" <?php if ($bEmptyCart) { echo " checked"; } ?>>&nbsp;&nbsp;<b><?php echo gettext("Empty Cart to this Group?"); ?></b></td>
		</tr>

		<tr><td><br></td></tr>

		<tr>
			<td colspan="2" align="center">
				<input type="submit" class="icButton" <?php echo 'value="' . gettext("Save") . '"'; ?> Name="GroupSubmit">
				&nbsp;
				<input type="button" class="icButton" <?php echo 'value="' . gettext("Exit") . '"'; ?> Name="GroupCancel" onclick="javascript:document.location='<?php
				if (strlen($iGroupID) > 0)
					echo "GroupView.php?GroupID=$iGroupID';\">";
				else
					echo "GroupList.php';\">";
				?>
			</td>
		</tr>
	</table>
	</form>
</td>

<td align="center">

<?php
if (strlen($iGroupID) > 0)
{
	?>
	<b class="MediumLargeText"><?php echo gettext("Group Roles:"); ?></b><br><br>
	<iframe width="100%" height="400px" frameborder="0" align="left" marginheight="0" marginwidth="0"
	src="OptionManager.php?mode=grproles&ListID=<?php echo $iRoleListID; ?>"></iframe>
	<?php
}
else
{
	?><b class="MediumLargeText"><?php echo gettext("Initial Group Creation:  Group roles can be edited after the first save."); ?></b><br><br><?php
}
?>
</td>

</tr>
</table>

<?php
require "Include/Footer.php";
?>
