<?php
/*******************************************************************************
 *
 *  filename    : UserEditor.php
 *  last change : 2003-05-29
 *  description : form for adding and editing users
 *
 *  http://www.infocentral.org/
 *  Copyright 2001-2002 Phillip Hullquist, Deane Barker
 *
 *  InfoCentral is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

// Include the function library
require "Include/Config.php";
require "Include/Functions.php";

// Security: User must be an Admin to access this page.
// Otherwise, re-direct them to the main menu.
if (!$_SESSION['bAdmin'])
{
	Redirect("Menu.php");
	exit;
}

// Get the PersonID out of either querystring or the form, depending and what we're doing
if (isset($_GET["PersonID"])) {
	$iPersonID = FilterInput($_GET["PersonID"],'int');
	$bNewUser = false;

} elseif (isset($_POST["PersonID"])) {
	$iPersonID = FilterInput($_POST["PersonID"],'int');
	$bNewUser = false;

} elseif (isset($_GET["NewPersonID"])) {
	$iPersonID = FilterInput($_GET["NewPersonID"],'int');
	$bNewUser = true;
}

// Has the form been submitted?
if (isset($_POST["Submit"]) && $iPersonID > 0) {

	// Assign all variables locally
	$sAction = $_POST["Action"];

	$sUserName = FilterInput($_POST["UserName"]);
	if (isset($_POST["AddRecords"])) { $AddRecords = 1; } else { $AddRecords = 0; }
	if (isset($_POST["EditRecords"])) { $EditRecords = 1; } else { $EditRecords = 0; }
	if (isset($_POST["DeleteRecords"])) { $DeleteRecords = 1; } else { $DeleteRecords = 0; }
	if (isset($_POST["MenuOptions"])) { $MenuOptions = 1; } else { $MenuOptions = 0; }
	if (isset($_POST["ManageGroups"])) { $ManageGroups = 1; } else { $ManageGroups = 0; }
	if (isset($_POST["Finance"])) { $Finance = 1; } else { $Finance = 0; }
	if (isset($_POST["Notes"])) { $Notes = 1; } else { $Notes = 0; }
	if (isset($_POST["EditSelf"])) { $EditSelf = 1; } else { $EditSelf = 0; }
	if (isset($_POST["Canvasser"])) { $Canvasser = 1; } else { $Canvasser = 0; }

	// This setting will go un-used until InfoCentral 1.3
	// if (isset($_POST["Communication"])) { $Communication = 1; } else { $Communication = 0; }
	$Communication = 0;

	if (isset($_POST["Admin"])) { $Admin = 1; } else { $Admin = 0; }
	$Style = FilterInput($_POST["Style"]);

	// Initialize error flag
	$bErrorFlag = false;

	// Were there any errors?
	if (!$bErrorFlag) {

		// Write the SQL depending on whether we're adding or editing
		if ($sAction == "add") {
			$sSQL = "INSERT INTO user_usr (usr_per_ID, usr_Password, usr_NeedPasswordChange, usr_LastLogin, usr_AddRecords, usr_EditRecords, usr_DeleteRecords, usr_MenuOptions, usr_ManageGroups, usr_Finance, usr_Notes, usr_Communication, usr_Admin, usr_Style, usr_SearchLimit, usr_UserName, usr_EditSelf, usr_Canvasser) VALUES (" . $iPersonID . ",'" . md5(strtolower($sDefault_Pass)) . "',1,'" . date("Y-m-d H:i:s") . "', " . $AddRecords . ", " . $EditRecords . ", " . $DeleteRecords . ", " . $MenuOptions . ", " . $ManageGroups . ", " . $Finance . ", " . $Notes . ", " . $Communication . ", " . $Admin . ", '" . $Style . "', 10, \"" . $sUserName . "\"," . $EditSelf . "," . $Canvasser . ")";
		} else {
			$sSQL = "UPDATE user_usr SET usr_AddRecords = " . $AddRecords . ", usr_EditRecords = " . $EditRecords . ", usr_DeleteRecords = " . $DeleteRecords . ", usr_MenuOptions = " . $MenuOptions . ", usr_ManageGroups = " . $ManageGroups . ", usr_Finance = " . $Finance . ", usr_Notes = " . $Notes . ", usr_Communication = " . $Communication . ", usr_Admin = " . $Admin . ", usr_Style = \"" . $Style . "\", usr_UserName = \"" . $sUserName . "\", usr_EditSelf = " . $EditSelf . ", usr_Canvasser = " . $Canvasser . " WHERE usr_per_ID = " . $iPersonID;
		}

		// Execute the SQL
		RunQuery($sSQL);

		// Redirect back to the list
		Redirect("UserList.php");
	}

} else {

	// Do we know which person yet?
	if ($iPersonID > 0) {

		if (!$bNewUser) {
			// Get the data on this user
			$sSQL = "SELECT * FROM user_usr INNER JOIN person_per ON person_per.per_ID = user_usr.usr_per_ID WHERE usr_per_ID = " . $iPersonID;
			$rsUser = RunQuery($sSQL);
			$aUser = mysql_fetch_array($rsUser);
			extract($aUser);
			$sUser = $per_LastName . ", " . $per_FirstName;
			$sUserName = $usr_UserName;
			$sAction = "edit";
		} else {
			$sSQL = "SELECT per_LastName, per_FirstName FROM person_per WHERE per_ID = " . $iPersonID;
			$rsUser = RunQuery($sSQL);
			$aUser = mysql_fetch_array($rsUser);
			$sUser = $aUser['per_LastName'] . ", " . $aUser['per_FirstName'];
			$sUserName = $aUser['per_FirstName'] . $aUser['per_LastName'];
			$sAction = "add";
		}

	// New user without person selected yet
	} else {
		$sAction = "add";
		$bShowPersonSelect = true;

		$usr_AddRecords = 0;
		$usr_EditRecords = 0;
		$usr_DeleteRecords = 0;
		$usr_MenuOptions = 0;
		$usr_ManageGroups = 0;
		$usr_Finance = 0;
		$usr_Notes = 0;
		$usr_Communication = 0;
		$usr_Admin = 0;
		$usr_EditSelf = 0;
		$usr_Canvasser = 0;
		$sUserName = "";

		// Get all the people who are NOT currently users
		$sSQL = "SELECT * FROM person_per LEFT JOIN user_usr ON person_per.per_ID = user_usr.usr_per_ID WHERE usr_per_ID IS NULL ORDER BY per_LastName";
		$rsPeople = RunQuery($sSQL);
	}
}

// Style sheet (CSS) file selection options
function StyleSheetOptions($dirName,$currentStyle)
{
	$dir = @opendir($dirName) or die(gettext("Nope"));
	while($file = readdir($dir))
	{
		if (ereg("\.css",$file))
		{
			echo "<option value=\"$file\"";
			if ($file == $currentStyle)
				echo " selected";
			echo ">" . substr($file,0,-4) . "</option>";
		}
	}
	closedir($dir);
}

// Set the page title and include HTML header
$sPageTitle = gettext("User Editor");
require "Include/Header.php";

?>

<form method="post">
<input type="hidden" name="Action" value="<?php echo $sAction; ?>">
<table cellpadding="4" align="center">
<?php

// Are we adding?
if ($bShowPersonSelect) {
	//Yes, so display the people drop-down
?>
	<tr>
		<td class="LabelColumn"><?php echo gettext("Person to Make User:"); ?></td>
		<td class="TextColumn">
			<select name="PersonID" size="12">
	<?php
	// Loop through all the people
	while ($aRow =mysql_fetch_array($rsPeople)) {
		extract($aRow);
	?>
				<option value="<?php echo $per_ID; ?>"<?php if ($per_ID == $iPersonID) { echo " selected"; } ?>><?php echo $per_LastName . ", " .  $per_FirstName; ?></option>
	<?php }	?>
			</select>
		</td>
	</tr>

<?php
} else {
	// No, just display the user name

?>
	<input type="hidden" name="PersonID" value="<?php echo $iPersonID; ?>">

	<tr>
		<td class="LabelColumn"><?php echo gettext("User:"); ?></td>
		<td class="TextColumn"><?php echo $sUser; ?></td>
	</tr>
<?php
}
?>
	<tr>
		<td class="LabelColumn"><?php echo gettext("Login Name:"); ?></td>
		<td class="TextColumn"><input type="text" name="UserName" value="<?php echo $sUserName; ?>"></td>
	</tr>

	<tr>
		<td class="LabelColumn"><?php echo gettext("Add Records:"); ?></td>
		<td class="TextColumn"><input type="checkbox" name="AddRecords" value="1"<?php if ($usr_AddRecords) { echo " checked"; } ?>></td>
	</tr>

	<tr>
		<td class="LabelColumn"><?php echo gettext("Edit Records:"); ?></td>
		<td class="TextColumn"><input type="checkbox" name="EditRecords" value="1"<?php if ($usr_EditRecords) { echo " checked"; } ?>></td>
	</tr>

	<tr>
		<td class="LabelColumn"><?php echo gettext("Delete Records:"); ?></td>
		<td class="TextColumn"><input type="checkbox" name="DeleteRecords" value="1"<?php if ($usr_DeleteRecords) { echo " checked"; } ?>></td>
	</tr>

 	<tr>
		<td class="LabelColumn"><?php echo gettext("Manage Properties and Classifications:"); ?></td>
		<td class="TextColumn"><input type="checkbox" name="MenuOptions" value="1"<?php if ($usr_MenuOptions) { echo " checked"; } ?>></td>
	</tr>

	<tr>
		<td class="LabelColumn"><?php echo gettext("Manage Groups and Roles:"); ?></td>
		<td class="TextColumn"><input type="checkbox" name="ManageGroups" value="1"<?php if ($usr_ManageGroups) { echo " checked"; } ?>></td>
	</tr>

	<tr>
		<td class="LabelColumn"><?php echo gettext("Manage Donations and Finance:"); ?></td>
		<td class="TextColumn"><input type="checkbox" name="Finance" value="1"<?php if ($usr_Finance) { echo " checked"; } ?>></td>
	</tr>

	<tr>
		<td class="LabelColumn"><?php echo gettext("View, Add and Edit Notes:"); ?></td>
		<td class="TextColumn"><input type="checkbox" name="Notes" value="1"<?php if ($usr_Notes) { echo " checked"; } ?>></td>
	</tr>

<?php /*  removed until 1.3
	<tr>
		<td class="LabelColumn"><?php echo gettext("Send, Add and Edit Communications:"); ?></td>
		<td class="TextColumn"><input type="checkbox" name="Communication" value="1"<?php if ($usr_Communication) { echo " checked"; } ?>></td>
	</tr>  */ ?>


	<tr>
		<td class="LabelColumn"><?php echo gettext("Edit Self:"); ?></td>
		<td class="TextColumn"><input type="checkbox" name="EditSelf" value="1"<?php if ($usr_EditSelf) { echo " checked"; } ?>>&nbsp;<span class="SmallText"><?php echo gettext("(Edit own family only.)"); ?></span></td>
	</tr>

	<tr>
		<td class="LabelColumn"><?php echo gettext("Canvasser:"); ?></td>
		<td class="TextColumn"><input type="checkbox" name="Canvasser" value="1"<?php if ($usr_Canvasser) { echo " checked"; } ?>>&nbsp;<span class="SmallText"><?php echo gettext("(Canvass volunteer.)"); ?></span></td>
	</tr>

	<tr>
		<td class="LabelColumn"><?php echo gettext("Admin:"); ?></td>
		<td class="TextColumn"><input type="checkbox" name="Admin" value="1"<?php if ($usr_Admin) { echo " checked"; } ?>>&nbsp;<span class="SmallText"><?php echo gettext("(Grants all privileges.)"); ?></span></td>
	</tr>

	<tr>
		<td class="LabelColumn"><?php echo gettext("Style:"); ?></td>
		<td class="TextColumnWithBottomBorder"><select name="Style"><?php StyleSheetOptions('Include',$usr_Style); ?></select></td>
	</tr>

	<tr>
		<td colspan="2"><div align="center"><?php echo gettext("Note: Changes will not take effect until next logon."); ?></div></td>

	</tr>


	<tr>
		<td colspan="2" align="center">
		<input type="submit" class="icButton" <?php echo 'value="' . gettext("Save") . '"'; ?> name="Submit">&nbsp;<input type="button" class="icButton" name="Cancel" <?php echo 'value="' . gettext("Cancel") . '"'; ?> onclick="javascript:document.location='UserList.php';">
		</td>
	</tr>

</table>
</form>

<?php
require "Include/Footer.php";
?>
