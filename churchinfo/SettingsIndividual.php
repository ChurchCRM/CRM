<?php
/*******************************************************************************
*
*  filename    : SettingsIndividual.php
*  website     : http://www.churchdb.org
*  description : Page where users can modify their own settings 
*                   File copied from SettingsUser.php with minor edits.
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

$iPersonID=$_SESSION['iUserID'];

// Save Settings
if (isset($_POST['save'])){
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
                .       "$ucfg_permission, ' ')";
                $rsResult = RunQuery($sSQL);

            } else {
                echo "<BR> Error: Software BUG 3216";
                exit;
            }
        }

		// Save new setting
		$sSQL = "UPDATE userconfig_ucfg "
        .       "SET ucfg_value='$value' "
        .       "WHERE ucfg_id=$id AND ucfg_per_id=$iPersonID ";
		$rsUpdate = RunQuery($sSQL);
		next($type);
	}
}

// Set the page title and include HTML header
$sPageTitle = gettext("My User Settings");
require "Include/Header.php";

// Get settings
$sSQL = "SELECT * FROM userconfig_ucfg WHERE ucfg_per_id=".$iPersonID
.       " ORDER BY ucfg_id";
$rsConfigs = RunQuery($sSQL);

// Table Headings
echo "<form method=post action=SettingsIndividual.php>";
echo "<table cellpadding=3 align=left>";
echo "<tr><td><h3>". gettext("Variable name") . "</h3></td>
	<td><h3>Current Value</h3></td>
	<td><h3>Notes</h3></td></tr>";

$r = 1;
// List Individual Settings
while (list($ucfg_per_id, $ucfg_id, $ucfg_name, $ucfg_value, $ucfg_type, $ucfg_tooltip, $ucfg_permission) = mysql_fetch_row($rsConfigs)) {

    if(!(($ucfg_permission == 'TRUE') || $_SESSION['bAdmin']))
        break; // Don't show rows that can't be changed

	// Cancel, Save Buttons every 13 rows
	if ($r == 13) {
		echo "<tr><td>&nbsp;</td>
			<td><input type=submit class=icButton name=save value='" . gettext("Save Settings") . "'>
			<input type=submit class=icButton name=cancel value='" . gettext("Cancel") . "'>
			</td></tr>";
		$r = 1;
	}

	
	// Variable Name & Type
	echo '<tr><td class=LabelColumn>'.$ucfg_name;
	echo '<input type=hidden name="type['.$ucfg_id.']" value="'.$ucfg_type.'"></td>';
	
	// Current Value
	if ($ucfg_type == 'text') {
		echo "<td class=TextColumnWithBottomBorder>
			<input type=text size=30 maxlength=255 name='new_value[$ucfg_id]'
			value='".htmlspecialchars($ucfg_value, ENT_QUOTES)."'></td>";
	} elseif ($ucfg_type == 'textarea') {
		echo "<td class=TextColumnWithBottomBorder>
			<textarea rows=4 cols=30 name='new_value[$ucfg_id]'>"
			.htmlspecialchars($ucfg_value, ENT_QUOTES)."</textarea></td>";
	} elseif ($ucfg_type == 'number' || $ucfg_type == 'date')	{
		echo "<td class=TextColumnWithBottomBorder><input type=text size=15 maxlength=15 name="
			."'new_value[$ucfg_id]' value='$ucfg_value'></td>";
	} elseif ($ucfg_type == 'boolean') {
		if ($ucfg_value){
			$sel2 = "SELECTED";
			$sel1 = "";
		} else {
			$sel1 = "SELECTED";
			$sel2 = "";
		}	
		echo "<td class=TextColumnWithBottomBorder><select name=\"new_value[$ucfg_id]\">";
		echo "<option value='' $sel1>False";
		echo "<option value='1' $sel2>True";
		echo "</select></td>";
	}
		
	// Notes
	echo "<td>$ucfg_tooltip</td>	</tr>";
	$r++;
}	 

// Cancel, Save Buttons
echo "<tr><td>&nbsp;</td>
	<td><input type=submit class=icButton name=save value='" . gettext("Save Settings") . "'>
	<input type=submit class=icButton name=cancel value='" . gettext("Cancel") . "'>
	</td></tr></table></form>";

require "Include/Footer.php";
?>
