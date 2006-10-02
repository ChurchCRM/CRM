<?php
/*******************************************************************************
 *
 *  filename    : GroupPropsFormEditor.php
 *  last change : 2003-02-09
 *  website     : http://www.infocentral.org
 *  copyright   : Copyright 2003 Chris Gebhardt (http://www.openserve.org)
 *
 *  function    : Editor for group-specific properties form
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

// Get the Group from the querystring
$iGroupID = FilterInput($_GET["GroupID"],'int');

// Get the group information
$sSQL = "SELECT * FROM group_grp WHERE grp_ID = " . $iGroupID;
$rsGroupInfo = RunQuery($sSQL);
extract(mysql_fetch_array($rsGroupInfo));

// Abort if user tries to load with group having no special properties.
if ($grp_hasSpecialProps == 'false')
{
	Redirect("GroupView.php?GroupID=" . $iGroupID);
}

$sPageTitle = gettext("Group-Specific Properties Form Editor:") . " &nbsp&nbsp " . $grp_Name;

require "Include/Header.php";

// Does the user want to save changes to text fields?
if (isset($_POST["SaveChanges"]))
{

	// Fill in the other needed property data arrays not gathered from the form submit
	$sSQL = "SELECT prop_ID, prop_Field, type_ID, prop_Special, prop_PersonDisplay FROM groupprop_master WHERE grp_ID = " . $iGroupID . " ORDER BY prop_ID";
	$rsPropList = RunQuery($sSQL);
	$numRows = mysql_num_rows($rsPropList);

	for ($row = 1; $row <= $numRows; $row++)
	{
		$aRow = mysql_fetch_array($rsPropList, MYSQL_BOTH);
		extract($aRow);

		$aFieldFields[$row] = $prop_Field;
		$aTypeFields[$row] = $type_ID;
		$aSpecialFields[$row] = $prop_Special;
		if (isset($prop_Special))
			$aSpecialFields[$row] = $prop_Special;
		else
			$aSpecialFields[$row] = "NULL";
	}

	for( $iPropID = 1; $iPropID <= $numRows; $iPropID++ )
	{
		$aNameFields[$iPropID] = FilterInput($_POST[$iPropID . "name"]);

		if ( strlen($aNameFields[$iPropID]) == 0 )
		{
			$aNameErrors[$iPropID] = true;
			$bErrorFlag = true;
		}
		else
		{
			$aNameErrors[$iPropID] = false;
		}

		$aDescFields[$iPropID] = FilterInput($_POST[$iPropID . "desc"]);

		if (isset($_POST[$iPropID . "special"]))
		{
			$aSpecialFields[$iPropID] = FilterInput($_POST[$iPropID . "special"],'int');

			if ( $aSpecialFields[$iPropID] == 0 )
			{
				$aSpecialErrors[$iPropID] = true;
				$bErrorFlag = true;
			}
			else
			{
				$aSpecialErrors[$iPropID] = false;
			}
		}

		if (isset($_POST[$iPropID . "show"]))
			$aPersonDisplayFields[$iPropID] = true;
		else
			$aPersonDisplayFields[$iPropID] = false;
	}

	// If no errors, then update.
	if (!$bErrorFlag)
	{
		for( $iPropID=1; $iPropID <= $numRows; $iPropID++ )
		{
			if ($aPersonDisplayFields[$iPropID])
				$temp = 'true';
			else
				$temp = 'false';

			$sSQL = "UPDATE groupprop_master
					SET `prop_Name` = '" . $aNameFields[$iPropID] . "',
						`prop_Description` = '" . $aDescFields[$iPropID] . "',
						`prop_Special` = " . $aSpecialFields[$iPropID] . ",
						`prop_PersonDisplay` = '" . $temp . "'
					WHERE `grp_ID` = '" . $iGroupID . "' AND `prop_ID` = '" . $iPropID . "';";

			RunQuery($sSQL);
		}
	}
}

else
{
	// Check if we're adding a field
	if (isset($_POST["AddField"]))
	{
		$newFieldType = FilterInput($_POST["newFieldType"],'int');
		$newFieldName = FilterInput($_POST["newFieldName"]);
		$newFieldDesc = FilterInput($_POST["newFieldDesc"]);

		if (strlen($newFieldName) == 0)
		{
			$bNewNameError = true;
		}
		else
		{
			$sSQL = "SELECT prop_Name FROM groupprop_master WHERE grp_ID = " . $iGroupID;
			$rsPropNames = RunQuery($sSQL);
			while($aRow = mysql_fetch_array($rsPropNames))
			{
				if ($aRow[0] == $newFieldName) {
					$bDuplicateNameError = true;
					break;
				}
			}

			if (!$bDuplicateNameError)
			{
				// Get the new prop_ID (highest existing plus one)
				$sSQL = "SELECT prop_ID	FROM groupprop_master WHERE grp_ID = " . $iGroupID;
				$rsPropList = RunQuery($sSQL);
				$newRowNum = mysql_num_rows($rsPropList) + 1;

				// Find the highest existing field number in the group's table to determine the next free one.
				// This is essentially an auto-incrementing system where deleted numbers are not re-used.
				$tableName = "groupprop_" . $iGroupID;
				$fields = mysql_list_fields($sDATABASE, $tableName, $cnInfoCentral);
				$last = mysql_num_fields($fields) - 1;

				// Set the new field number based on the highest existing.  Chop off the "c" at the beginning of the old one's name.
				// The "c#" naming scheme is necessary because MySQL 3.23 doesn't allow numeric-only field (table column) names.
				$newFieldNum = substr(mysql_field_name($fields, $last), 1) + 1;

				// If we're inserting a new custom-list type field, create a new list and get its ID
				if ($newFieldType == 12)
				{
					// Get the first available lst_ID for insertion.  lst_ID 0-9 are reserved for permanent lists.
					$sSQL = "SELECT MAX(lst_ID) FROM list_lst";
					$aTemp = mysql_fetch_array(RunQuery($sSQL));
					if ($aTemp[0] > 9)
						$newListID = $aTemp[0] + 1;
					else
						$newListID = 10;

					// Insert into the lists table with an example option.
					$sSQL = "INSERT INTO list_lst VALUES ($newListID, 1, 1," . gettext("'Default Option'") . ")";
					RunQuery($sSQL);

					$newSpecial = "'$newListID'";
				}
				else
					$newSpecial = "NULL";

				// Insert into the master table
				$sSQL = "INSERT INTO `groupprop_master`
							( `grp_ID` , `prop_ID` , `prop_Field` , `prop_Name` , `prop_Description` , `type_ID` , `prop_Special` )
							VALUES ('" . $iGroupID . "', '" . $newRowNum . "', 'c" . $newFieldNum . "', '" . $newFieldName . "', '" . $newFieldDesc . "', '" . $newFieldType . "', $newSpecial);";
				RunQuery($sSQL);

				// Insert into the group-specific properties table
				$sSQL = "ALTER TABLE `groupprop_" . $iGroupID . "` ADD `c" . $newFieldNum . "` ";

				switch($newFieldType)
				{
				case 1:
					$sSQL .= "ENUM('false', 'true')";
					break;
				case 2:
					$sSQL .= "DATE";
					break;
				case 3:
					$sSQL .= "VARCHAR(50)";
					break;
				case 4:
					$sSQL .= "VARCHAR(100)";
					break;
				case 5:
					$sSQL .= "TEXT";
					break;
				case 6:
					$sSQL .= "YEAR";
					break;
				case 7:
					$sSQL .= "ENUM('winter', 'spring', 'summer', 'fall')";
					break;
				case 8:
					$sSQL .= "INT";
					break;
				case 9:
					$sSQL .= "MEDIUMINT(9)";
					break;
				case 10:
					$sSQL .= "DECIMAL(10,2)";
					break;
				case 11:
					$sSQL .= "VARCHAR(30)";
					break;
				case 12:
					$sSQL .= "TINYINT(4)";
				}

				$sSQL .= " DEFAULT NULL ;";
				RunQuery($sSQL);

				$bNewNameError = false;
			}
		}
	}

	// Get data for the form as it now exists..
	$sSQL = "SELECT * FROM groupprop_master WHERE grp_ID = " . $iGroupID . " ORDER BY prop_ID";

	$rsPropList = RunQuery($sSQL);
	$numRows = mysql_num_rows($rsPropList);

	// Create arrays of the properties.
	for ($row = 1; $row <= $numRows; $row++)
	{
		$aRow = mysql_fetch_array($rsPropList, MYSQL_BOTH);
		extract($aRow);

		// This is probably more clear than using a multi-dimensional array
		$aNameFields[$row] = $prop_Name;
		$aDescFields[$row] = $prop_Description;
		$aSpecialFields[$row] = $prop_Special;
		$aFieldFields[$row] = $prop_Field;
		$aTypeFields[$row] = $type_ID;
		$aPersonDisplayFields[$row] = ($prop_PersonDisplay == 'true');
	}
}

// Construct the form
?>

<script language="javascript">

function confirmDeleteField( Group, Prop, Field ) {
var answer = confirm (<?php echo '"' . gettext("Warning:  By deleting this field, you will irrevokably lose all group member data assigned for this field!") . '"'; ?>)
if ( answer )
	window.location="GroupPropsFormRowOps.php?GroupID=" + Group + "&PropID=" + Prop + "&Field=" + Field + "&Action=delete"
}
</script>

<form method="post" action="GroupPropsFormEditor.php?GroupID=<?php echo $iGroupID; ?>" name="GroupPropFormEditor">

<table cellpadding="3" width="100%">

<?php
if ($numRows == 0)
{
?>
	<center><h2><?php echo gettext("No properties have been added yet"); ?></h2>
	<input type="button" class="icButton" <?php echo 'value="' . gettext("Exit") . '"'; ?> Name="Exit" onclick="javascript:document.location='GroupView.php?GroupID=<?php echo $iGroupID; ?>';">
	</center>
<?php
}
else
{
?>
	<tr><td colspan="7">
	<center><b><?php echo gettext("Warning: Field changes will be lost if you do not 'Save Changes' before using an up, down, delete, or 'add new' button!"); ?></b></center>
	</td></tr>

	<tr><td colspan="7" align="center">
	<?php
	if ( $bErrorFlag ) echo "<span class=\"LargeText\" style=\"color: red;\">" . gettext("Invalid fields or selections. Changes not saved! Please correct and try again!") . "</span>";
	?>
	</td></tr>

		<tr>
			<td colspan="7" align="center">
			<input type="submit" class="icButton" <?php echo 'value="' . gettext("Save Changes") . '"'; ?> Name="SaveChanges">
			&nbsp;
			<input type="button" class="icButton" <?php echo 'value="' . gettext("Exit") . '"'; ?> Name="Exit" onclick="javascript:document.location='GroupView.php?GroupID=<?php echo $iGroupID; ?>';">
			</td>
		</tr>

		<tr>
			<th></th>
			<th></th>
			<th><?php echo gettext("Type"); ?></th>
			<th><?php echo gettext("Name"); ?></th>
			<th><?php echo gettext("Description"); ?></th>
			<th><?php echo gettext("Special option"); ?></th>
			<th><?php echo gettext("Show in"); ?><br><?php echo gettext("Person View"); ?></th>
		</tr>

	<?php

	for ($row=1; $row <= $numRows; $row++)
	{
		?>
		<tr>
			<td class="LabelColumn"><h2><b><?php echo $row ?></b></h2></td>
			<td class="TextColumn" width="5%" nowrap>
				<?php
				if ($row != 1)
					echo "<a href=\"GroupPropsFormRowOps.php?GroupID=$iGroupID&PropID=$row&Field=" . $aFieldFields[$row] . "&Action=up\"><img src=\"Images/uparrow.gif\" border=\"0\"></a>";
				if ($row < $numRows)
					echo "<a href=\"GroupPropsFormRowOps.php?GroupID=$iGroupID&PropID=$row&Field=" . $aFieldFields[$row] . "&Action=down\"><img src=\"Images/downarrow.gif\" border=\"0\"></a>";
				?>
				<input type="image" value="delete" Name="delete" onclick="confirmDeleteField(<?php echo $iGroupID . ", " . $row . ", '" . $aFieldFields[$row] . "'"; ?>);" src="Images/x.gif">
			</td>
			<td class="TextColumn" style="font-size:70%;">
			<?php echo $aPropTypes[$aTypeFields[$row]];	?>
			</td>

			<td class="TextColumn"><input type="text" name="<?php echo $row . "name"; ?>" value="<?php echo htmlentities(stripslashes($aNameFields[$row])); ?>" size="25" maxlength="40">
				<?php
				if ( $aNameErrors[$row] )
					echo "<span style=\"color: red;\"><BR>" . gettext("You must enter a name.") . " </span>";
				?>
			</td>

			<td class="TextColumn"><textarea name="<?php echo $row . "desc"; ?>" cols="30" rows="1" onKeyPress="LimitTextSize(this,60)"><?php echo htmlentities(stripslashes($aDescFields[$row])); ?></textarea></td>

			<td class="TextColumn">
			<?php

			if ($aTypeFields[$row] == 9)
			{
				echo "<select name=\"" . $row . "special\">";
					echo "<option value=\"0\" selected>" . gettext("Select a group") . "</option>";

				$sSQL = "SELECT grp_ID,grp_Name FROM group_grp ORDER BY grp_Name";

				$rsGroupList = RunQuery($sSQL);

				while ($aRow = mysql_fetch_array($rsGroupList))
				{
					extract($aRow);

					echo "<option value=\"" . $grp_ID . "\"";
					if ($aSpecialFields[$row] == $grp_ID) { echo " selected"; }
					echo ">" . $grp_Name;
				}

				echo "</select>";

				if ( $aSpecialErrors[$row] ) echo "<span style=\"color: red;\"><BR>" . gettext("You must select a group.") . "</span>";
			}
			elseif ($aTypeFields[$row] == 12)
				echo "<a href=\"javascript:void(0)\" onClick=\"Newwin=window.open('OptionManager.php?mode=groupcustom&ListID=$aSpecialFields[$row]','Newwin','toolbar=no,status=no,width=400,height=500')\">Edit List Options</a>";
			else { echo "&nbsp;"; }
			?></td>

			<td class="TextColumn">
				<input type="checkbox" Name="<?php echo $row . "show" ?>" value="1"	<?php if ($aPersonDisplayFields[$row]) echo " checked" ?>>
			</td>
		</tr>
	<?php } ?>

		<tr>
			<td colspan="7">
			<table width="100%">
				<tr>
					<td width="30%"></td>
					<td width="40%" align="center" valign="bottom">
						<input type="submit" class="icButton" <?php echo 'value="' . gettext("Save Changes") . '"'; ?> Name="SaveChanges">
						&nbsp;
						<input type="button" class="icButton" <?php echo 'value="' . gettext("Exit") . '"'; ?> Name="Exit" onclick="javascript:document.location='GroupView.php?GroupID=<?php echo $iGroupID; ?>';">
					</td>
					<td width="30%"></td>
				</tr>
			</table>
			</td>
			<td>
		</tr>
<?php } ?>
		<tr><td colspan="7"><hr></td></tr>
		<tr>
			<td colspan="7">
			<table width="100%">
				<tr>
					<td width="15%"></td>
					<td valign="top">
					<div><?php echo gettext("Type:"); ?></div>
					<?php
						echo "<select name=\"newFieldType\">";
						for ($iOptionID = 1; $iOptionID <= count($aPropTypes); $iOptionID++)
						{
							echo "<option value=\"" . $iOptionID . "\"";
							echo ">" . $aPropTypes[$iOptionID];
						}
						echo "</select>";
					?><BR>
					<a href="Help.php?page=Types"><?php echo gettext("Help on types.."); ?></a>
					</td>
					<td valign="top">
						<div><?php echo gettext("Name:"); ?></div>
						<input type="text" name="newFieldName" size="25" maxlength="40">
						<?php
						if ( $bNewNameError ) echo "<div><span style=\"color: red;\"><BR>" . gettext("You must enter a name.") . "</span></div>";
						if ( $bDuplicateNameError ) echo "<div><span style=\"color: red;\"><BR>" . gettext("That field name already exists.") . "</span></div>";
						?>
						&nbsp;
					</td>
					<td valign="top">
						<div><?php echo gettext("Description:"); ?></div>
						<input type="text" name="newFieldDesc" size="30" maxlength="60">
						&nbsp;
					</td>
					<td>
						<input type="submit" class="icButton" <?php echo 'value="' . gettext("Add New Field") . '"'; ?> Name="AddField">
					</td>
					<td width="15%"></td>
				</tr>
			</table>
			</td>
		</tr>

	</table>
	</form>

<?php require "Include/Footer.php"; ?>
