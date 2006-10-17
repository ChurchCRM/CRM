<?php
/*******************************************************************************
*
*  filename    : SettingsUser.php
*  website     : http://www.churchdb.org
*  description : Default User Settings 
*                   File copied from SettingsGeneral.php with minor edits.
*                   Controls default settings for new users.
*
*  Contributors:
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
require "Include/Config.php";
require "Include/Functions.php";

// Security
if (!$_SESSION['bAdmin'])
{
	Redirect("Menu.php");
	exit;
}

// Save Settings
if ($_POST['save']){
	$new_value = $_POST['new_value'];
    $new_permission = $_POST['new_permission'];
	$type = $_POST['type'];
	ksort ($type);
	reset ($type);
	while ($current_type = current($type)) {
		$id = key($type);
		// Filter Input
		if ($current_type == 'text' || $current_type == "textarea")
			$value = FilterInput($new_value[$id]);
		elseif ($current_type == 'number')
			$value = FilterInput($new_value[$id],"float");
		elseif ($current_type == 'date')
			$value = FilterInput($new_value[$id],"date");
		elseif ($current_type == 'boolean'){
			if ($new_value[$id] != "1")
				$value = "";
			else
				$value = "1";
		}

        if ($new_permission[$id] != "TRUE")
            $permission="FALSE";
        else
            $permission="TRUE";


		// Save new setting
		$sSQL = "UPDATE userconfig_ucfg "
        .       "SET ucfg_value='$value', ucfg_permission='$permission' "
        .       "WHERE ucfg_id='$id' AND ucfg_per_id='0' ";
		$rsUpdate = RunQuery($sSQL);
		next($type);
	}
}

// Set the page title and include HTML header
$sPageTitle = gettext("Default User Settings");
require "Include/Header.php";

// Get settings
$sSQL = "SELECT * FROM userconfig_ucfg WHERE ucfg_per_id='0' ORDER BY ucfg_id";
$rsConfigs = RunQuery($sSQL);

// Table Headings
echo "<form method=post action=SettingsUser.php>";
echo "Set Permission True to give new users the ability to change their current value.<BR>";
echo "<table cellpadding=3 align=left>";
echo "<tr><td><h3>Permission</h3></td>
    <td><h3>". gettext("Variable name") . "</h3></td>
	<td><h3>Current Value</h3></td>
	<td><h3>Notes</h3></td></tr>";

$r = 1;
// List Individual Settings
while (list($ucfg_per_id, $ucfg_id, $ucfg_name, $ucfg_value, $ucfg_type, $ucfg_tooltip, $ucfg_permission) = mysql_fetch_row($rsConfigs)) {
	
	// Cancel, Save Buttons every 13 rows
	if ($r == 13) {
		echo "<tr><td>&nbsp;</td>
			<td><input type=submit class=icButton name=save value=\"" 
            . gettext("Save Settings") . "\">
			<input type=submit class=icButton name=cancel value=\"" 
            . gettext("Cancel") . "\">
			</td></tr>";
		$r = 1;
	}

	// Default Permissions
	if ($ucfg_permission=='TRUE'){
		$sel2 = "SELECTED ";
		$sel1 = "";
	} else {
		$sel1 = "SELECTED ";
		$sel2 = "";
	}	
	echo "<tr><td class=\"TextColumnWithBottomBorder\"><select name=\"new_permission[$ucfg_id]\">";
	echo "<option value=\"FALSE\" $sel1>False";
	echo "<option value=\"TRUE\" $sel2>True
                        </select></td>";

	
	// Variable Name & Type
	echo "<td class=\"LabelColumn\">$ucfg_name</td>";
	
	// Current Value
	if ($ucfg_type == 'text') {
		echo "<td class=\"TextColumnWithBottomBorder\">
            <input type=text size=\"30\" maxlength=\"255\" name=\"new_value[$ucfg_id]\"
            value=\"".htmlspecialchars($ucfg_value, ENT_QUOTES)."\"></td>";
	} elseif ($ucfg_type == 'textarea') {
		echo "<td class=\"TextColumnWithBottomBorder\">
			<textarea rows=\"4\" cols=\"30\" name=\"new_value[$ucfg_id]\">"
			.htmlspecialchars($ucfg_value, ENT_QUOTES)."</textarea></td>";
	} elseif ($ucfg_type == 'number' || $ucfg_type == 'date')	{
		echo "<td class=\"TextColumnWithBottomBorder\">
            <input type=text size=\"15\" maxlength=\"15\" name=\"new_value[$ucfg_id]\" 
            value=\"$ucfg_value\"></td>";
	} elseif ($ucfg_type == 'boolean') {
		if ($ucfg_value){
			$sel2 = "SELECTED ";
			$sel1 = "";
		} else {
			$sel1 = "SELECTED ";
			$sel2 = "";
		}	
		echo "<td class=\"TextColumnWithBottomBorder\">
                <select name=\"new_value[$ucfg_id]\">
                <option value=\"\" $sel1>False
                <option value=\"1\" $sel2>True
                </select></td>";
	}
		
	// Notes
	echo "<td><input type=hidden name=\"type[$ucfg_id]\" value=\"$ucfg_type\">
        $ucfg_tooltip</td></tr>";


	$r++;
}	 

// Cancel, Save Buttons
echo "<tr><td>&nbsp;</td>
	<td><input type=submit class=icButton name=save value=\"" . gettext("Save Settings") . "\">
	<input type=submit class=icButton name=cancel value=\"" . gettext("Cancel") . "\">
	</td></tr></table></form>";

require "Include/Footer.php";
?>
