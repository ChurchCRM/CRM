<?php
/*******************************************************************************
 *
 *  filename    : PersonCustomFieldsEditor.php
 *  last change : 2003-03-28
 *  website     : http://www.infocentral.org
 *  copyright   : Copyright 2003 Chris Gebhardt (http://www.openserve.org)
 *
 *  function    : Editor for custom person fields
 *
 *  InfoCentral is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
******************************************************************************/

require "Include/Config.php";
require "Include/Functions.php";

// Security: user must be administrator to use this page
if (!$_SESSION['bAdmin'])
{
	Redirect("Menu.php");
	exit;
}

$sPageTitle = gettext("Custom Person Fields Editor");

require "Include/Header.php";

// Does the user want to save changes to text fields?
if (isset($_POST["SaveChanges"]))
{
	// Fill in the other needed custom field data arrays not gathered from the form submit
	$sSQL = "SELECT * FROM person_custom_master ORDER BY custom_Order";
	$rsCustomFields = RunQuery($sSQL);
	$numRows = mysql_num_rows($rsCustomFields);

	for ($row = 1; $row <= $numRows; $row++)
	{
		$aRow = mysql_fetch_array($rsCustomFields, MYSQL_BOTH);
		extract($aRow);

		$aFieldFields[$row] = $custom_Field;
		$aTypeFields[$row] = $type_ID;
		if (isset($custom_Special))
			$aSpecialFields[$row] = $custom_Special;
		else
			$aSpecialFields[$row] = "NULL";
	}

	for ($iFieldID = 1; $iFieldID <= $numRows; $iFieldID++ )
	{
		$aNameFields[$iFieldID] = FilterInput($_POST[$iFieldID . "name"]);

		if ( strlen($aNameFields[$iFieldID]) == 0 )
		{
			$aNameErrors[$iFieldID] = true;
			$bErrorFlag = true;
		}
		else
		{
			$aNameErrors[$iFieldID] = false;
		}

		$aSideFields[$iFieldID] = $_POST[$iFieldID . "side"];

		if (isset($_POST[$iFieldID . "special"]))
		{
			$aSpecialFields[$iFieldID] = FilterInput($_POST[$iFieldID . "special"],'int');

			if ( $aSpecialFields[$iFieldID] == 0 )
			{
				$aSpecialErrors[$iFieldID] = true;
				$bErrorFlag = true;
			}
			else
			{
				$aSpecialErrors[$iFieldID] = false;
			}
		}
	}

	// If no errors, then update.
	if (!$bErrorFlag)
	{
		for( $iFieldID=1; $iFieldID <= $numRows; $iFieldID++ )
		{
			if ($aSideFields[$iFieldID] == 0)
				$temp = 'left';
			else
				$temp = 'right';

			$sSQL = "UPDATE person_custom_master
					SET `custom_Name` = '" . $aNameFields[$iFieldID] . "',
						`custom_Special` = " . $aSpecialFields[$iFieldID] . ",
						`custom_Side` = '" . $temp . "'
					WHERE `custom_Field` = '" . $aFieldFields[$iFieldID] . "';";

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
		$newFieldSide = $_POST["newFieldSide"];

		if (strlen($newFieldName) == 0)
		{
			$bNewNameError = true;
		}
		elseif (strlen($newFieldType) == 0 || $newFieldType < 1)
		{
			// This should never happen, but check anyhow.
			// $bNewTypeError = true;
		}
		else
		{
			$sSQL = "SELECT custom_Name FROM person_custom_master";
			$rsCustomNames = RunQuery($sSQL);
			while($aRow = mysql_fetch_array($rsCustomNames))
			{
				if ($aRow[0] == $newFieldName) {
					$bDuplicateNameError = true;
					break;
				}
			}

			if (!$bDuplicateNameError)
			{
				// Find the highest existing field number in the table to determine the next free one.
				// This is essentially an auto-incrementing system where deleted numbers are not re-used.
				$fields = mysql_list_fields($sDATABASE, "person_custom", $cnInfoCentral);
				$last = mysql_num_fields($fields) - 1;

				// Set the new field number based on the highest existing.  Chop off the "c" at the beginning of the old one's name.
				// The "c#" naming scheme is necessary because MySQL 3.23 doesn't allow numeric-only field (table column) names.
				$newFieldNum = substr(mysql_field_name($fields, $last), 1) + 1;

				if ($newFieldSide == 0)
					$newFieldSide = 'left';
				else
					$newFieldSide = 'right';

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
					$sSQL = "INSERT INTO list_lst VALUES ($newListID, 1, 1,'". gettext("Default Option") . "')";
					RunQuery($sSQL);

					$newSpecial = "'$newListID'";
				}
				else
					$newSpecial = "NULL";

				// Insert into the master table
				$newOrderID = $last + 1;
				$sSQL = "INSERT INTO `person_custom_master`
						(`custom_Order` , `custom_Field` , `custom_Name` ,  `custom_Special` , `custom_Side` , `type_ID`)
						VALUES ('" . $newOrderID . "', 'c" . $newFieldNum . "', '" . $newFieldName . "', " . $newSpecial . ", '" . $newFieldSide . "', '" . $newFieldType . "');";
				RunQuery($sSQL);

				// Insert into the custom fields table
				$sSQL = "ALTER TABLE `person_custom` ADD `c" . $newFieldNum . "` ";

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
	$sSQL = "SELECT * FROM person_custom_master ORDER BY custom_Order";

	$rsCustomFields = RunQuery($sSQL);
	$numRows = mysql_num_rows($rsCustomFields);

	// Create arrays of the fields.
	for ($row = 1; $row <= $numRows; $row++)
	{
		$aRow = mysql_fetch_array($rsCustomFields, MYSQL_BOTH);
		extract($aRow);

		$aNameFields[$row] = $custom_Name;
		$aSpecialFields[$row] = $custom_Special;
		$aFieldFields[$row] = $custom_Field;
		$aTypeFields[$row] = $type_ID;
		$aSideFields[$row] = ($custom_Side == 'right');
	}
}

// Construct the form
?>

<script language="javascript">

function confirmDeleteField( Field, Row ) {
var answer = confirm (<?php echo "'" . gettext("Warning:  By deleting this field, you will irrevokably lose all person data assigned for this field!") . "'"; ?>)
if ( answer )
	window.location="PersonCustomFieldsRowOps.php?Field=" + Field + "&OrderID=" + Row + "&Action=delete";
	confirm ("Field Deleted");
}
</script>

<form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" name="PersonCustomFieldsEditor">

<table cellpadding="3" width="75%" align="center">

<?php
if ($numRows == 0)
{
?>
	<center><h2><?php echo gettext("No custom person fields have been added yet"); ?></h2>
	<input type="button" class="icButton" value="<?php echo gettext("Exit"); ?>" Name="Exit" onclick="javascript:document.location='Menu.php';">
	</center>
<?php
}
else
{
?>
	<tr><td colspan="6">
	<center><b><?php echo gettext("Warning: Arrow and delete buttons take effect immediately.  Field name changes will be lost if you do not 'Save Changes' before using an up, down, delete or 'add new' button!"); ?></b></center>
	</td></tr>

	<tr><td colspan="6">
	<?php
	if ( $bErrorFlag ) echo "<span class=\"LargeText\" style=\"color: red;\"><BR>" . gettext("Invalid fields or selections. Changes not saved! Please correct and try again!") . "</span>";
	?>
	</td></tr>

		<tr>
			<td colspan="6" align="center">
			<input type="submit" class="icButton" value="<?php echo gettext("Save Changes"); ?>" Name="SaveChanges">
			&nbsp;
			<input type="button" class="icButton" value="<?php echo gettext("Exit"); ?>" Name="Exit" onclick="javascript:document.location='Menu.php';">
			</td>
		</tr>

		<tr>
			<th></th>
			<th></th>
			<th><?php echo gettext("Type"); ?></th>
			<th><?php echo gettext("Name"); ?></th>
			<th><?php echo gettext("Special option"); ?></th>
			<th><?php echo gettext("Person-View Side"); ?></th>
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
					echo "<a href=\"PersonCustomFieldsRowOps.php?OrderID=$row&Field=" . $aFieldFields[$row] . "&Action=up\"><img src=\"Images/uparrow.gif\" border=\"0\"></a>";
				if ($row < $numRows)
					echo "<a href=\"PersonCustomFieldsRowOps.php?OrderID=$row&Field=" . $aFieldFields[$row] . "&Action=down\"><img src=\"Images/downarrow.gif\" border=\"0\"></a>";
				?>
				<input type="image" value="delete" Name="delete" onclick="confirmDeleteField(<?php echo "'" . $aFieldFields[$row] . "', '" . $row . "'"; ?>);" src="Images/x.gif">
			</td>

			<td class="TextColumn" style="font-size:80%;">
			<?php echo $aPropTypes[$aTypeFields[$row]];	?>
			</td>

			<td class="TextColumn" align="center"><input type="text" name="<?php echo $row . "name"; ?>" value="<?php echo htmlentities(stripslashes($aNameFields[$row])); ?>" size="35" maxlength="40">
				<?php
				if ( $aNameErrors[$row] )
					echo "<span style=\"color: red;\"><BR>" . gettext("You must enter a name.") . " </span>";
				?>
			</td>

			<td class="TextColumn" align="center">

			<?php
			if ($aTypeFields[$row] == 9)
			{
				echo "<select name=\"" . $row . "special\">";
					echo "<option value=\"0\" selected>Select a group</option>";

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
				echo "<a href=\"javascript:void(0)\" onClick=\"Newwin=window.open('OptionManager.php?mode=custom&ListID=$aSpecialFields[$row]','Newwin','toolbar=no,status=no,width=400,height=500')\">" . gettext("Edit List Options") . "</a>";
			else
				echo "&nbsp;";
			?>

			</td>
			<td class="TextColumn" align="center" nowrap>
				<input type="radio" Name="<?php echo $row . "side" ?>" value="0" <?php if (!$aSideFields[$row]) echo " checked" ?>><?php echo gettext("Left"); ?>
				<input type="radio" Name="<?php echo $row . "side" ?>" value="1" <?php if ($aSideFields[$row]) echo " checked" ?>><?php echo gettext("Right"); ?>
			</td>
		</tr>
	<?php } ?>

		<tr>
			<td colspan="6">
			<table width="100%">
				<tr>
					<td width="30%"></td>
					<td width="40%" align="center" valign="bottom">
						<input type="submit" class="icButton" <?php echo 'value="' . gettext("Save Changes") . '"'; ?> Name="SaveChanges">
						&nbsp;
						<input type="button" class="icButton" <?php echo 'value="' . gettext("Exit") . '"'; ?>" Name="Exit" onclick="javascript:document.location='Menu.php';">
					</td>
					<td width="30%"></td>
				</tr>
			</table>
			</td>
			<td>
		</tr>
<?php } ?>
		<tr><td colspan="6"><hr></td></tr>
		<tr>
			<td colspan="6">
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
						<input type="text" name="newFieldName" size="30" maxlength="40">
						<?php
							if ( $bNewNameError ) echo "<div><span style=\"color: red;\"><BR>" . gettext("You must enter a name") . "</span></div>";
							if ( $bDuplicateNameError ) echo "<div><span style=\"color: red;\"><BR>" . gettext("That field name already exists.") . "</span></div>";
						?>
						&nbsp;
					</td>
					<td valign="top" nowrap>
						<div><?php echo gettext("Side:"); ?></div>
						<input type="radio" name="newFieldSide" value="0" checked><?php echo gettext("Left"); ?>
						<input type="radio" name="newFieldSide" value="1"><?php echo gettext("Right"); ?>
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
