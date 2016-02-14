<?php
/*******************************************************************************
 *
 *  filename    : OptionsManager.php
 *  last change : 2003-04-16
 *  website     : http://www.churchcrm.io
 *  copyright   : Copyright 2003 Chris Gebhardt
 *
 *  OptionName : Interface for editing simple selection options such as those
 *              : used for Family Roles, Classifications, and Group Types
 *
 *  ChurchCRM is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

//Include the function library
require "Include/Config.php";
require "Include/Functions.php";

$mode = trim($_GET["mode"]);

// Check security for the mode selected.
switch ($mode) {
	case 'famroles':
	case 'classes':
		if (!$_SESSION['bMenuOptions'])
		{
			Redirect("Menu.php");
			exit;
		}
		break;

	case 'grptypes':
	case 'grproles':
	case 'groupcustom':
		if (!$_SESSION['bManageGroups'])
		{
			Redirect("Menu.php");
			exit;
		}
		break;

	case 'custom':
	case 'famcustom':
	case 'securitygrp':
		if (!$_SESSION['bAdmin'])
		{
			Redirect("Menu.php");
			exit;
		}
		break;

	default:
		Redirect("Menu.php");
		break;
}

// Select the proper settings for the editor mode
switch ($mode) {
	case 'famroles':
		$adj = gettext("Family");
		$noun = gettext("Role");
		$listID = 2;
		$embedded = false;
		break;
	case 'classes':
		$adj = gettext("Person");
		$noun = gettext("Classification");
		$listID = 1;
		$embedded = false;
		break;
	case 'grptypes':
		$adj = gettext("Group");
		$noun = gettext("Type");
		$listID = 3;
		$embedded = false;
		break;
	case 'securitygrp':
		$adj = gettext("Security");
		$noun = gettext("Group");
		$listID = 5;
		$embedded = false;
		break;
	case 'grproles':
		$adj = gettext("Group Member");
		$noun = gettext("Role");
		$listID = FilterInput($_GET["ListID"],'int');
		$embedded = true;

		$sSQL = "SELECT grp_DefaultRole FROM group_grp WHERE grp_RoleListID = " . $listID;
		$rsTemp = RunQuery($sSQL);

		// Validate that this list ID is really for a group roles list. (for security)
		if (mysql_num_rows($rsTemp) == 0) {
			Redirect("Menu.php");
			break;
		}

		$aTemp = mysql_fetch_array($rsTemp);
		$iDefaultRole = $aTemp[0];

		break;
	case 'custom':
		$adj = gettext("Person Custom List");
		$noun = gettext("Option");
		$listID = FilterInput($_GET["ListID"],'int');
		$embedded = true;

		$sSQL = "SELECT '' FROM person_custom_master WHERE type_ID = 12 AND custom_Special = " . $listID;
		$rsTemp = RunQuery($sSQL);

		// Validate that this is a valid person-custom field custom list
		if (mysql_num_rows($rsTemp) == 0) {
			Redirect("Menu.php");
			break;
		}

		break;
	case 'groupcustom':
		$adj = gettext("Custom List");
		$noun = gettext("Option");
		$listID = FilterInput($_GET["ListID"],'int');
		$embedded = true;

		$sSQL = "SELECT '' FROM groupprop_master WHERE type_ID = 12 AND prop_Special = " . $listID;
		$rsTemp = RunQuery($sSQL);

		// Validate that this is a valid group-specific-property field custom list
		if (mysql_num_rows($rsTemp) == 0) {
			Redirect("Menu.php");
			break;
		}

		break;
	case 'famcustom':
		$adj = gettext("Family Custom List");
		$noun = gettext("Option");
		$listID = FilterInput($_GET["ListID"],'int');
		$embedded = true;

		$sSQL = "SELECT '' FROM family_custom_master WHERE type_ID = 12 AND fam_custom_Special = " . $listID;
		$rsTemp = RunQuery($sSQL);

		// Validate that this is a valid family_custom field custom list
		if (mysql_num_rows($rsTemp) == 0) {
			Redirect("Menu.php");
			break;
		}

		break;
	default:
		Redirect("Menu.php");
		break;
}

$iNewNameError = 0;

// Check if we're adding a field
if (isset($_POST["AddField"]))
{
	$newFieldName = FilterInput($_POST["newFieldName"]);

	if (strlen($newFieldName) == 0)
	{
		$iNewNameError = 1;
	}
	else
	{
		// Check for a duplicate option name
		$sSQL = "SELECT '' FROM list_lst WHERE lst_ID = $listID AND lst_OptionName = '" . $newFieldName . "'";
		$rsCount = RunQuery($sSQL);
		if (mysql_num_rows($rsCount) > 0)
		{
			$iNewNameError = 2;
		}
		else
		{
			// Get count of the options
			$sSQL = "SELECT '' FROM list_lst WHERE lst_ID = $listID";
			$rsTemp = RunQuery($sSQL);
			$numRows = mysql_num_rows($rsTemp);
			$newOptionSequence = $numRows + 1;

			// Get the new OptionID
			$sSQL = "SELECT MAX(lst_OptionID) FROM list_lst WHERE lst_ID = $listID";
			$rsTemp = RunQuery($sSQL);
			$aTemp = mysql_fetch_array($rsTemp);
			$newOptionID = $aTemp[0] + 1;

			// Insert into the appropriate options table
			$sSQL = "INSERT INTO list_lst (lst_ID, lst_OptionID, lst_OptionName, lst_OptionSequence)
					VALUES (" . $listID . "," . $newOptionID . ",'" . $newFieldName . "'," . $newOptionSequence . ")";

			RunQuery($sSQL);
			$iNewNameError = 0;
		}
	}
}

$bErrorFlag = false;
$bDuplicateFound = false;

// Get the original list of options..
//ADDITION - get Sequence Also
$sSQL = "SELECT lst_OptionName, lst_OptionID, lst_OptionSequence FROM list_lst WHERE lst_ID=$listID ORDER BY lst_OptionSequence";
$rsList = RunQuery($sSQL);
$numRows = mysql_num_rows($rsList);

$aNameErrors = array();
for ($row = 1; $row <= $numRows; $row++)
	$aNameErrors[$row] = 0;
	
if (isset($_POST["SaveChanges"]))
{
	for ($row = 1; $row <= $numRows; $row++)
	{
		$aRow = mysql_fetch_array($rsList, MYSQL_BOTH);
		$aOldNameFields[$row] = $aRow["lst_OptionName"];
		$aIDs[$row] =  $aRow["lst_OptionID"];

		//addition save off sequence also
		$aSeqs[$row] = $aRow['lst_OptionSequence'];

		$aNameFields[$row] = FilterInput($_POST[$row . "name"]);
	}

	for ($row = 1; $row <= $numRows; $row++)
	{
		if ( strlen($aNameFields[$row]) == 0 )
		{
			$aNameErrors[$row] = 1;
			$bErrorFlag = true;
		}
		elseif ($row < $numRows)
		{
			$aNameErrors[$row] = 0;
			for ($rowcmp = $row + 1; $rowcmp <= $numRows; $rowcmp++)
			{
				if ($aNameFields[$row] == $aNameFields[$rowcmp])
				{
					$bErrorFlag = true;
					$bDuplicateFound = true;
					$aNameErrors[$row] = 2;
					break;
				}
			}
		}
		else
		{
			$aNameErrors[$row] = 0;
		}
	}

	// If no errors, then update.
	if (!$bErrorFlag)
	{
		for( $row=1; $row <= $numRows; $row++ )
		{
			// Update the type's name if it has changed from what was previously stored
			if ($aOldNameFields[$row] != $aNameFields[$row])
			{
				$sSQL = "UPDATE list_lst SET `lst_OptionName` = '" . $aNameFields[$row] . "' WHERE `lst_ID` = '$listID' AND `lst_OptionSequence` = '" . $row . "'";
				RunQuery($sSQL);
			}
		}
	}
}

// Get data for the form as it now exists..

$sSQL = "SELECT lst_OptionName, lst_OptionID, lst_OptionSequence FROM list_lst WHERE lst_ID = $listID ORDER BY lst_OptionSequence";
$rsRows = RunQuery($sSQL);
$numRows = mysql_num_rows($rsRows);

// Create arrays of the option names and IDs
for ($row = 1; $row <= $numRows; $row++)
{
	$aRow = mysql_fetch_array($rsRows, MYSQL_BOTH);

	if (!$bErrorFlag)
		$aNameFields[$row] = $aRow["lst_OptionName"];

	$aIDs[$row] = $aRow["lst_OptionID"];
	//addition save off sequence also
	$aSeqs[$row] = $aRow['lst_OptionSequence'];
}

//Set the starting row color
$sRowClass = "RowColorA";

// Use a minimal page header if this form is going to be used within a frame
if ($embedded)
	include "Include/Header-Minimal.php";
else
{
	$sPageTitle = $adj . ' ' . $noun . "s Editor:";
	include "Include/Header.php";
}

?>
<div class="box">
	<div class="box-body">
<form method="post" action="OptionManager.php?<?= "mode=$mode&ListID=$listID" ?>" name="OptionManager">

<div class="callout callout-warning"><?= gettext("Warning: Removing will reset all assignments for all persons with the assignment!") ?></div>

<?php

if ( $bErrorFlag )
{
	echo "<span class=\"MediumLargeText\" style=\"color: red;\">";
	if ($bDuplicateFound) echo "<br>" . gettext("Error: Duplicate") . " " . $adj . " " . $noun . gettext("s are not allowed.");
	echo "<br>" . gettext("Invalid fields or selections. Changes not saved! Please correct and try again!") . "</span><br><br>";
}
?>

<br>
<table cellpadding="3" width="30%" align="center">

<?php
for ($row=1; $row <= $numRows; $row++)
{
	?>
	<tr align="center">
		<td class="LabelColumn">
			<b>
			<?php
			if ($mode == "grproles" && $aIDs[$row] == $iDefaultRole)
				echo gettext("Default") . " ";
			echo $row;
			?>
			</b>
		</td>

		<td class="TextColumn" nowrap>

			<?php
			if ($row != 1)
				echo "<a href=\"OptionManagerRowOps.php?mode=$mode&Order=$aSeqs[$row]&ListID=$listID&ID=" . $aIDs[$row] . "&Action=up\"><img src=\"Images/uparrow.gif\" border=\"0\"></a>";
			if ($row < $numRows)
				echo "<a href=\"OptionManagerRowOps.php?mode=$mode&Order=$aSeqs[$row]&ListID=$listID&ID=" . $aIDs[$row] . "&Action=down\"><img src=\"Images/downarrow.gif\" border=\"0\"></a>";
			if ($numRows > 0)
				echo "<a href=\"OptionManagerRowOps.php?mode=$mode&Order=$aSeqs[$row]&ListID=$listID&ID=" . $aIDs[$row] . "&Action=delete\"><img src=\"Images/x.gif\" border=\"0\"></a>";
			?>
		</td>
		<td class="TextColumn">
			<span class="SmallText">
				<input class="form-control input-small" type="text" name="<?= $row . "name" ?>" value="<?= htmlentities(stripslashes($aNameFields[$row]),ENT_NOQUOTES, "UTF-8") ?>" size="30" maxlength="40">
			</span>
			<?php

			if ( $aNameErrors[$row] == 1 )
				echo "<span style=\"color: red;\"><BR>" . gettext("You must enter a name.") . " </span>";
			elseif ( $aNameErrors[$row] == 2 )
				echo "<span style=\"color: red;\"><BR>" . gettext("Duplicate name found.") . " </span>";
			?>
		</td>
		<?php
		if ($mode == "grproles")
			echo "<td class=\"TextColumn\"><input class=\"form-control input-small\" type=\"button\" class=\"btn\" value=\"" . gettext("Make Default") . "\" Name=\"default\" onclick=\"javascript:document.location='OptionManagerRowOps.php?mode=" . $mode . "&ListID=" . $listID . "&ID=" . $aIDs[$row] . "&Action=makedefault';\" ></td>";
		?>

	</tr>
<?php } ?>

</table>
	<input type="submit" class="btn btn-primary" value="<?= gettext("Save Changes") ?>" Name="SaveChanges">


	<?php if ($mode == 'groupcustom' || $mode == 'custom' || $mode == 'famcustom') { ?>
		<input type="button" class="btn" value="<?= gettext("Exit") ?>" Name="Exit" onclick="javascript:window.close();">
	<?php } elseif ($mode != "grproles") { ?>
		<input type="button" class="btn" value="<?= gettext("Exit") ?>" Name="Exit" onclick="javascript:document.location='<?php
		echo "Menu.php";
		?>';">
	<?php } ?>
	</div>
</div>

<div class="box box-primary">
	<div class="box-body">
New <?= $noun . " " . gettext("Name:") ?>&nbsp;
<span class="SmallText">
	<input class="form-control input-small" type="text" name="newFieldName" size="30" maxlength="40">
</span>
<p>  </p>
<input type="submit" class="btn" value="<?= gettext("Add New") . ' ' . $adj . ' ' . $noun ?>" Name="AddField">
<?php
	if ($iNewNameError > 0)
	{
		echo "<div><span style=\"color: red;\"><BR>";
		if ( $iNewNameError == 1 )
			echo gettext("Error: You must enter a name.");
		else
			echo gettext("Error: A ") . $noun . gettext(" by that name already exists.");
		echo "</span></div>";
	}
?>
</center>
</form>
	</div>
</div>
<?php
if ($embedded)
	echo "</body></html>";
else
	include "Include/Footer.php";
?>
