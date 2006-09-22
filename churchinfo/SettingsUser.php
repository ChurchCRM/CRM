<?php
/*******************************************************************************
*
*  filename    : SettingsUser.php
*  website     : http://www.churchdb.org
*  description : Default User Settings 
*                   File copied from SettingsGeneral.php with minor edits.
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
		// Save new setting
		$sSQL = "UPDATE config_cfg SET cfg_value='$value' WHERE cfg_id='$id'";
		$rsUpdate = RunQuery($sSQL);
		next($type);
	}
}

// Set the page title and include HTML header
$sPageTitle = gettext("Default User Settings");
require "Include/Header.php";

// Get settings
$sSQL = "SELECT * FROM config_cfg WHERE cfg_section='UserDefaults' ORDER BY cfg_id";
$rsConfigs = RunQuery($sSQL);

// Table Headings
echo "<form method=post action=SettingsUser.php>";
echo "<table cellpadding=3 align=left>";
echo "<tr><td><h3>". gettext("Variable name") . "</h3></td>
	<td><h3>Current Value</h3></td>
	<td><h3>Default Value</h3></td>
	<td><h3>Notes</h3></td></tr>";

$r = 1;
// List Individual Settings
while (list($cfg_id, $cfg_name, $cfg_value, $cfg_type, $cfg_default, $cfg_tooltip, $cfg_section) = mysql_fetch_row($rsConfigs)) {
	
	// Cancel, Save Buttons every 13 rows
	if ($r == 13) {
		echo "<tr><td>&nbsp;</td>
			<td><input type=submit class=icButton name=save value='" . gettext("Save Settings") . "'>
			<input type=submit class=icButton name=cancel value='" . gettext("Cancel") . "'>
			</td></tr>";
		$r = 1;
	}
	
	// Variable Name & Type
	echo "<tr><td class=LabelColumn>$cfg_name</td>";
	echo "<input type=hidden name='type[$cfg_id]' value='$cfg_type'>";
	
	// Current Value
	if ($cfg_type == 'text') {
		echo "<td class=TextColumnWithBottomBorder>
			<input type=text size=30 maxlength=255 name='new_value[$cfg_id]'
			value='".htmlspecialchars($cfg_value, ENT_QUOTES)."'></td>";
	} elseif ($cfg_type == 'textarea') {
		echo "<td class=TextColumnWithBottomBorder>
			<textarea rows=4 cols=30 name='new_value[$cfg_id]'>"
			.htmlspecialchars($cfg_value, ENT_QUOTES)."</textarea></td>";
	} elseif ($cfg_type == 'number' || $cfg_type == 'date')	{
		echo "<td class=TextColumnWithBottomBorder><input type=text size=15 maxlength=15 name="
			."'new_value[$cfg_id]' value='$cfg_value'></td>";
	} elseif ($cfg_type == 'boolean') {
		if ($cfg_value){
			$sel2 = "SELECTED";
			$sel1 = "";
		} else {
			$sel1 = "SELECTED";
			$sel2 = "";
		}	
		echo "<td class=TextColumnWithBottomBorder><select name='new_value[$cfg_id]'>";
		echo "<option value='' $sel1>False";
		echo "<option value='1' $sel2>True";
		echo "</select></td>";
	}
	
	// Default Value
	if ($cfg_type == 'number' || $cfg_type == 'date' || $cfg_type == 'text' || $cfg_type == 'textarea') {
		$display_default = "";
		// Add line breaks every 25 characters
		for ($i=0; $i<=strlen($cfg_default)-1; $i=$i+25){
			if ($i > 0)
				$display_default .= "<br>";
			$display_default .= substr($cfg_default,$i,25);
		}
		echo "<td class=TextColumnWithBottomBorder><i>$display_default</i></td>";
	} elseif ($cfg_type == 'boolean'){
		if ($cfg_default)
			echo "<td class=TextColumnWithBottomBorder><i>True</i></td>";
		else
			echo "<td class=TextColumnWithBottomBorder><i>False</i></td>";
	}
	
	// Notes
	echo "<td>$cfg_tooltip</td>	</tr>";
	$r++;
}	 

// Cancel, Save Buttons
echo "<tr><td>&nbsp;</td>
	<td><input type=submit class=icButton name=save value='" . gettext("Save Settings") . "'>
	<input type=submit class=icButton name=cancel value='" . gettext("Cancel") . "'>
	</td></tr></table></form>";

require "Include/Footer.php";
?>
