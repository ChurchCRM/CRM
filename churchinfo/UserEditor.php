<?php
/*******************************************************************************
*
*  filename    : UserEditor.php
*  description : form for adding and editing users
*
*  http://www.churchdb.org/
*  Copyright 2001-2002 Phillip Hullquist, Deane Barker
* 
*  Updated 2005-03-19 by Everette L Mills: Updated to remove error that could be created
*  by use of duplicate usernames
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
*  This file best viewed in a text editor with tabs stops set to 4 characters.
*  Please configure your editor to use soft tabs (4 spaces for a tab) instead
*  of hard tab characters.
*
******************************************************************************/
// Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

// Security: User must be an Admin to access this page.
// Otherwise re-direct to the main menu.
if (!$_SESSION['bAdmin'])
{
	Redirect('Menu.php');
	exit;
}

// Get the PersonID out of either querystring or the form, depending and what we're doing
if (isset($_GET['PersonID'])) {
	$iPersonID = FilterInput($_GET['PersonID'],'int');
	$bNewUser = false;

} elseif (isset($_POST['PersonID'])) {
	$iPersonID = FilterInput($_POST['PersonID'],'int');
	$bNewUser = false;

} elseif (isset($_GET['NewPersonID'])) {
	$iPersonID = FilterInput($_GET['NewPersonID'],'int');
	$bNewUser = true;
}

if (isset($_GET['ErrorText'])){
	$sErrorText = FilterInput($_GET['ErrorText'],'string');
}else{
	$sErrorText = '';	
}

//Value to help determine correct return state on error
if (isset($_POST['NewUser'])){
	$NewUser = FilterInput($_POST['NewUser'],'string');
	}

// Has the form been submitted?
if (isset($_POST['save']) && $iPersonID > 0) {

	// Assign all variables locally
	$sAction = $_POST['Action'];

	$defaultFY = CurrentFY ();
	$sUserName = FilterInput($_POST['UserName']);
	
	 if (strlen($sUserName) < 3) 
	{	
		if($NewUser == false)
		{
		//Report error for current user creation
		Redirect('UserEditor.php?PersonID='.$iPersonID.'&ErrorText=Login must be a least 3 characters!');
		
		}else{
		//Report error for new user creation			
		Redirect('UserEditor.php?NewPersonID='.$PersonID.'&ErrorText=Login must be a least 3 characters!');
		}
		
		
	}else{
	if (isset($_POST['AddRecords'])) { $AddRecords = 1; } else { $AddRecords = 0; }
	if (isset($_POST['EditRecords'])) { $EditRecords = 1; } else { $EditRecords = 0; }
	if (isset($_POST['DeleteRecords'])) { $DeleteRecords = 1; } else { $DeleteRecords = 0; }
	if (isset($_POST['MenuOptions'])) { $MenuOptions = 1; } else { $MenuOptions = 0; }
	if (isset($_POST['ManageGroups'])) { $ManageGroups = 1; } else { $ManageGroups = 0; }
	if (isset($_POST['Finance'])) { $Finance = 1; } else { $Finance = 0; }
	if (isset($_POST['Notes'])) { $Notes = 1; } else { $Notes = 0; }
	if (isset($_POST['EditSelf'])) { $EditSelf = 1; } else { $EditSelf = 0; }
	if (isset($_POST['Canvasser'])) { $Canvasser = 1; } else { $Canvasser = 0; }

	$Communication = 0;

	if (isset($_POST['Admin'])) { $Admin = 1; } else { $Admin = 0; }
	$Style = FilterInput($_POST['Style']);

	// Initialize error flag
	$bErrorFlag = false;

	// Were there any errors?
	if (!$bErrorFlag) {
		
		//Check for duplicate user names
  	    $unSQL = "Select Count(*) as dup from user_usr where usr_UserName like '".
                 $sUserName."' and usr_per_ID not like '".$iPersonID."'";
  	 
  	   // Execute the SQL
  	   $unQueryResult = RunQuery($unSQL);
  	   //Extract Value, if greater than 0 for add error, greater than 1 for add then error
  	   $unQueryResultSet = mysql_fetch_array($unQueryResult);
  	   $undupCount = $unQueryResultSet['dup'];

		// Write the SQL depending on whether we're adding or editing
		if ($sAction == 'add'){
									
							if ($undupCount == 0) {
								$sSQL = "INSERT INTO user_usr (usr_per_ID, usr_Password, usr_NeedPasswordChange, usr_LastLogin, usr_AddRecords, usr_EditRecords, usr_DeleteRecords, usr_MenuOptions, usr_ManageGroups, usr_Finance, usr_Notes, usr_Communication, usr_Admin, usr_Style, usr_SearchLimit, usr_defaultFY, usr_UserName, usr_EditSelf, usr_Canvasser) VALUES (" . $iPersonID . ",'" . md5(strtolower($sDefault_Pass)) . "',1,'" . date("Y-m-d H:i:s") . "', " . $AddRecords . ", " . $EditRecords . ", " . $DeleteRecords . ", " . $MenuOptions . ", " . $ManageGroups . ", " . $Finance . ", " . $Notes . ", " . $Communication . ", " . $Admin . ", '" . $Style . "', 10," . $defaultFY . ",\"" . $sUserName . "\"," . $EditSelf . "," . $Canvasser . ")";
							// Execute the SQL
							RunQuery($sSQL);

							}else{
							// Set the error text for duplicate when new user
							Redirect('UserEditor.php?NewPersonID='.$PersonID.'&ErrorText=Login already in use, please select a different login!');
							}
		} else{
		
		if ($undupCount == 0) {
					$sSQL =  "UPDATE user_usr SET usr_AddRecords = " . $AddRecords . ", usr_EditRecords = " . $EditRecords . ", usr_DeleteRecords = " . $DeleteRecords . ", usr_MenuOptions = " . $MenuOptions . ", usr_ManageGroups = " . $ManageGroups . ", usr_Finance = " . $Finance . ", usr_Notes = " . $Notes . ", usr_Communication = " . $Communication . ", usr_Admin = " . $Admin . ", usr_Style = \"" . $Style . "\", usr_UserName = \"" . $sUserName . "\", usr_EditSelf = " . $EditSelf . " WHERE usr_per_ID = " . $iPersonID;
					// Execute the SQL
					RunQuery($sSQL);

				}else{
					// Set the error text for duplicate when currently existing
					Redirect('UserEditor.php?PersonID='.$iPersonID.'&ErrorText=Login already in use, please select a different login!');
				} 
	
			}
		}
	}	         	  
}else{

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
			$sAction = 'edit';
		} else {
			$sSQL = "SELECT per_LastName, per_FirstName FROM person_per WHERE per_ID = " . $iPersonID;
			$rsUser = RunQuery($sSQL);
			$aUser = mysql_fetch_array($rsUser);
			$sUser = $aUser['per_LastName'] . ', ' . $aUser['per_FirstName'];
			$sUserName = $aUser['per_FirstName'] . $aUser['per_LastName'];
			$sAction = 'add';
			$vNewUser = 'true';
		}

	// New user without person selected yet
	} else {
		$sAction = 'add';
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
		$usr_EditSelf = 1;
		$usr_Canvasser = 0;
		$sUserName = '';
		$vNewUser = 'true';

		// Get all the people who are NOT currently users
		$sSQL = "SELECT * FROM person_per LEFT JOIN user_usr ON person_per.per_ID = user_usr.usr_per_ID WHERE usr_per_ID IS NULL ORDER BY per_LastName";
		$rsPeople = RunQuery($sSQL);
	}
}

// Style sheet (CSS) file selection options
function StyleSheetOptions($dirName,$currentStyle)
{
	$dir = @opendir($dirName);
	if ($dir) {
		while($file = readdir($dir))
		{
			if (ereg("\.css",$file))
			{
				echo "<option value=\"$file\"";
				if ($file == $currentStyle)
					echo " selected";
				echo '>' . substr($file,0,-4) . '</option>';
			}
		}
		closedir($dir);
	} else {
		foreach ( array ('Style-blue.css', 'Style-green.css', 'Style-purple.css', 'Style-red.css', 'Style.css') as $stylename ) {
			echo '<option value="'.$stylename.'"';
			if ($stylename == $currentStyle)
				echo ' selected';
			echo '>' . substr($stylename,0,-4) . '</option>';
		}
	}
}



// Save Settings
if (isset($_POST['save']) && ($iPersonID > 0)){
	$new_value = $_POST['new_value'];
    $new_permission = $_POST['new_permission'];
	$type = $_POST['type'];
	ksort ($type);
	reset ($type);
	while ($current_type = current($type)) {
		$id = key($type);
		// Filter Input
		if ($current_type == 'text' || $current_type == 'textarea')
			$value = FilterInput($new_value[$id]);
		elseif ($current_type == 'number')
			$value = FilterInput($new_value[$id],'float');
		elseif ($current_type == 'date')
			$value = FilterInput($new_value[$id],'date');
		elseif ($current_type == 'boolean'){
			if ($new_value[$id] != '1')
				$value = '';
			else
				$value = '1';
        }

        if ($new_permission[$id] != 'TRUE')
            $permission='FALSE';
        else
            $permission='TRUE';

        // We can't update unless values already exist.
        $sSQL = "SELECT * FROM userconfig_ucfg "
        .       "WHERE ucfg_id=$id AND ucfg_per_id=$iPersonID ";
        $bRowExists=TRUE;
        $iNumRows=mysql_num_rows(RunQuery($sSQL));
        if($iNumRows==0)
            $bRowExists=FALSE;

        if(!$bRowExists) { // If Row does not exist then insert default values.
                           // Defaults will be replaced in the following Update
            $sSQL = "SELECT * FROM userconfig_ucfg "
            .       "WHERE ucfg_id=$id AND ucfg_per_id=0 ";
            $rsDefault = RunQuery($sSQL);
            $aDefaultRow = mysql_fetch_row($rsDefault);
            if($aDefaultRow) {
                list($ucfg_per_id, $ucfg_id, $ucfg_name, $ucfg_value, $ucfg_type, 
                    $ucfg_tooltip, $ucfg_permission) = $aDefaultRow;

                $sSQL = "INSERT INTO userconfig_ucfg VALUES ($iPersonID, $id, "
                .       "'$ucfg_name', '$ucfg_value', '$ucfg_type', '$ucfg_tooltip', "
                .       "'$ucfg_permission')";
                $rsResult = RunQuery($sSQL);

            } else {
                echo '<br> Error on line '.__LINE__.' of file '.__FILE__;
                exit;
            }
        }

		// Save new setting
		$sSQL = "UPDATE userconfig_ucfg "
        .       "SET ucfg_value='$value', ucfg_permission='$permission' "
        .       "WHERE ucfg_id='$id' AND ucfg_per_id=$iPersonID ";
		$rsUpdate = RunQuery($sSQL);
		next($type);
	}

    Redirect('UserList.php');
    exit;
}

// Set the page title and include HTML header
$sPageTitle = gettext('User Editor');
require 'Include/Header.php';

?>

<form method="post" action="UserEditor.php">
<input type="hidden" name="Action" value="<?php echo $sAction; ?>">
<input type="hidden" name="NewUser" value="<?php echo $vNewUser; ?>">
<?php

// Are we adding?
if ($bShowPersonSelect) {
	//Yes, so display the people drop-down
?>
    <table cellpadding="4" align="center">
	<tr>
		<td class="LabelColumn"><?php echo gettext('Person to Make User:'); ?></td>
		<td class="TextColumn">
			<select name="PersonID" size="12">
	<?php
	// Loop through all the people
	while ($aRow =mysql_fetch_array($rsPeople)) {
		extract($aRow);
	?>
				<option value="<?php echo $per_ID; ?>"<?php if ($per_ID == $iPersonID) { echo ' selected'; } ?>><?php echo $per_LastName . ', ' .  $per_FirstName; ?></option>
	<?php }	?>
			</select>
		</td>
	</tr>

<?php
} else {
	// No, just display the user name

?>
	<input type="hidden" name="PersonID" value="<?php echo $iPersonID; ?>">
    <table cellpadding="4" align="center">
	<tr>
		<td class="LabelColumn"><?php echo gettext('User:'); ?></td>
		<td class="TextColumn"><?php echo $sUser; ?></td>
	</tr>
<?php
}
?>
	 <?php if (isset($sErrorText) <> '') { ?>
  	<tr>
  	    <td align="center" colspan="2">
  	    <span style="color:red;" id="PasswordError"><?php echo $sErrorText; ?></span>
  		</td>
    </tr><?php } ?>
	<tr>
		<td class="LabelColumn"><?php echo gettext('Login Name:'); ?></td>
		<td class="TextColumn"><input type="text" name="UserName" value="<?php echo $sUserName; ?>"></td>
	</tr>

	<tr>
		<td class="LabelColumn"><?php echo gettext('Add Records:'); ?></td>
		<td class="TextColumn"><input type="checkbox" name="AddRecords" value="1"<?php if ($usr_AddRecords) { echo " checked"; } ?>></td>
	</tr>

	<tr>
		<td class="LabelColumn"><?php echo gettext('Edit Records:'); ?></td>
		<td class="TextColumn"><input type="checkbox" name="EditRecords" value="1"<?php if ($usr_EditRecords) { echo " checked"; } ?>></td>
	</tr>

	<tr>
		<td class="LabelColumn"><?php echo gettext('Delete Records:'); ?></td>
		<td class="TextColumn"><input type="checkbox" name="DeleteRecords" value="1"<?php if ($usr_DeleteRecords) { echo ' checked'; } ?>></td>
	</tr>

 	<tr>
		<td class="LabelColumn"><?php echo gettext('Manage Properties and Classifications:'); ?></td>
		<td class="TextColumn"><input type="checkbox" name="MenuOptions" value="1"<?php if ($usr_MenuOptions) { echo ' checked'; } ?>></td>
	</tr>

	<tr>
		<td class="LabelColumn"><?php echo gettext('Manage Groups and Roles:'); ?></td>
		<td class="TextColumn"><input type="checkbox" name="ManageGroups" value="1"<?php if ($usr_ManageGroups) { echo ' checked'; } ?>></td>
	</tr>

	<tr>
		<td class="LabelColumn"><?php echo gettext('Manage Donations and Finance:'); ?></td>
		<td class="TextColumn"><input type="checkbox" name="Finance" value="1"<?php if ($usr_Finance) { echo ' checked'; } ?>></td>
	</tr>

	<tr>
		<td class="LabelColumn"><?php echo gettext('View, Add and Edit Notes:'); ?></td>
		<td class="TextColumn"><input type="checkbox" name="Notes" value="1"<?php if ($usr_Notes) { echo ' checked'; } ?>></td>
	</tr>

<?php /*  removed until 1.3
	<tr>
		<td class="LabelColumn"><?php echo gettext("Send, Add and Edit Communications:"); ?></td>
		<td class="TextColumn"><input type="checkbox" name="Communication" value="1"<?php if ($usr_Communication) { echo " checked"; } ?>></td>
	</tr>  */ ?>


	<tr>
		<td class="LabelColumn"><?php echo gettext('Edit Self:'); ?></td>
		<td class="TextColumn"><input type="checkbox" name="EditSelf" value="1"<?php if ($usr_EditSelf) { echo ' checked'; } ?>>&nbsp;<span class="SmallText"><?php echo gettext('(Edit own family only.)'); ?></span></td>
	</tr>

	<tr>
		<td class="LabelColumn"><?php echo gettext('Canvasser:'); ?></td>
		<td class="TextColumn"><input type="checkbox" name="Canvasser" value="1"<?php if ($usr_Canvasser) { echo ' checked'; } ?>>&nbsp;<span class="SmallText"><?php echo gettext('(Canvass volunteer.)'); ?></span></td>
	</tr>

	<tr>
		<td class="LabelColumn"><?php echo gettext('Admin:'); ?></td>
		<td class="TextColumn"><input type="checkbox" name="Admin" value="1"<?php if ($usr_Admin) { echo ' checked'; } ?>>&nbsp;<span class="SmallText"><?php echo gettext('(Grants all privileges.)'); ?></span></td>
	</tr>

	<tr>
		<td class="LabelColumn"><?php echo gettext('Style:'); ?></td>
		<td class="TextColumnWithBottomBorder"><select name="Style"><?php StyleSheetOptions('Include',$usr_Style); ?></select></td>
	</tr>

	<tr>
		<td colspan="2"><div align="center"><?php echo gettext('Note: Changes will not take effect until next logon.'); ?></div></td>

	</tr>


	<tr>
		<td colspan="2" align="center">
		<input type="submit" class="icButton" <?php echo 'value="' . gettext('Save') . '"'; ?> name="save">&nbsp;<input type="button" class="icButton" name="Cancel" <?php echo 'value="' . gettext('Cancel') . '"'; ?> onclick="javascript:document.location='UserList.php';">
		</td>
	</tr>

</table>

<?php

// Table Headings
echo '<br><br>';
echo 'Set Permission True to give this user the ability to change their current value.<br>';
echo '<table cellpadding="3" align="left">';
echo "\n".'<tr><td><h3>Permission</h3></td>
    <td><h3>'. gettext("Variable name") . '</h3></td>
	<td><h3>Current Value</h3></td>
	<td><h3>Notes</h3></td></tr>';

//First get default settings, then overwrite with settings from this user 

// Get default settings
$sSQL = "SELECT * FROM userconfig_ucfg WHERE ucfg_per_id='0' ORDER BY ucfg_id";
$rsDefault = RunQuery($sSQL);
$r = 1;
// List Default Settings
while ($aDefaultRow = mysql_fetch_row($rsDefault)) {
    list($ucfg_per_id, $ucfg_id, $ucfg_name, $ucfg_value, $ucfg_type, 
        $ucfg_tooltip, $ucfg_permission) = $aDefaultRow;

    // Overwrite with user settings if they already exist
    $sSQL = "SELECT * FROM userconfig_ucfg WHERE ucfg_per_id='$usr_per_ID' "
    .       "AND ucfg_id='$ucfg_id' ";
    $rsUser = RunQuery($sSQL);
    while ($aUserRow = mysql_fetch_row($rsUser)) {
            list($ucfg_per_id, $ucfg_id, $ucfg_name, $ucfg_value, $ucfg_type, 
                $ucfg_tooltip, $ucfg_permission) = $aUserRow;
    }
	
	// Cancel, Save Buttons every 13 rows
	if ($r == 13) {
		echo "\n".'<tr><td>&nbsp;</td>
			<td><input type="submit" class="icButton" name="save" value="'.gettext('Save Settings').'">
			<input type="submit" class="icButton" name="cancel" value="' . gettext('Cancel') . '">
			</td></tr>';
		$r = 1;
	}

	// Default Permissions
	if ($ucfg_permission=='TRUE'){
		$sel2 = 'SELECTED';
		$sel1 = '';
	} else {
		$sel1 = 'SELECTED';
		$sel2 = '';
	}	
    echo "\n<tr>";
	echo "<td class=\"TextColumnWithBottomBorder\"><select name=\"new_permission[$ucfg_id]\">";
	echo "<option value=\"FALSE\" $sel1>False";
	echo "<option value=\"TRUE\" $sel2>True";
	echo '</select></td>';
	
	// Variable Name & Type
	echo "<td class=\"LabelColumn\">$ucfg_name</td>";
	
	// Current Value
	if ($ucfg_type == 'text') {
		echo "<td class=\"TextColumnWithBottomBorder\">
			<input type=\"text\" size=\"30\" maxlength=\"255\" name=\"new_value[$ucfg_id]\"
			value=\"".htmlspecialchars($ucfg_value, ENT_QUOTES)."\"></td>";
	} elseif ($ucfg_type == 'textarea') {
		echo "<td class=\"TextColumnWithBottomBorder\">
			<textarea rows=\"4\" cols=\"30\" name=\"new_value[$ucfg_id]\">"
			.htmlspecialchars($ucfg_value, ENT_QUOTES)."</textarea></td>";
	} elseif ($ucfg_type == 'number' || $ucfg_type == 'date')	{
		echo "<td class=\"TextColumnWithBottomBorder\"><input type=\"text\" size=\"15\""
        . " maxlength=\"15\" name=\"new_value[$ucfg_id]\" value=\"$ucfg_value\"></td>";
	} elseif ($ucfg_type == 'boolean') {
		if ($ucfg_value){
			$sel2 = 'SELECTED';
			$sel1 = '';
		} else {
			$sel1 = 'SELECTED';
			$sel2 = '';
		}	
		echo "<td class=\"TextColumnWithBottomBorder\"><select name=\"new_value[$ucfg_id]\">";
		echo "<option value=\"\" $sel1>False";
		echo "<option value=\"1\" $sel2>True";
		echo '</select></td>';
	}
		
	// Notes
	echo "<td><input type=\"hidden\" name=\"type[$ucfg_id]\" value=\"$ucfg_type\">
            $ucfg_tooltip</td></tr>";

	$r++;
}	 

// Cancel, Save Buttons
echo "\n<tr><td>&nbsp;</td>
	<td><input type=\"submit\" class=\"icButton\" name=\"save\" value=\"" 
    . gettext("Save Settings") . "\">
	<input type=\"submit\" class=\"icButton\" name=\"cancel\" value=\"" . gettext("Cancel") . "\">
	</td></tr></table></form>";


require 'Include/Footer.php';
?>
