<?php
/*******************************************************************************
 *
 *  filename    : SettingsGeneral.php
 *  last change : 2013-02-19
 *  description : form to modify general global configuration settings
 *
 *  ChurchCRM is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

// Include the function library
require "Include/Config.php";
require "Include/Functions.php";
require "Include/TranslateMenuOptions.php";

// Security
if (!$_SESSION['bAdmin'])
{
	Redirect("Menu.php");
	exit;
}
if (isset ($_POST['cancel'])){
  Redirect("SystemSettings.php");
}

$sSQL = "SELECT * FROM config_cfg ORDER BY cfg_id";
if (isset ($_GET["Cat"])) {
  $scfgCategory = FilterInput($_GET["Cat"], 'string');
  $sSQL = "SELECT * FROM config_cfg WHERE cfg_category='" . $scfgCategory . "' ORDER BY cfg_order";
}

$rsConfigs = RunQuery($sSQL);
$iRowCount=0;
while ($aRow = mysql_fetch_array($rsConfigs)) {
    $iRowCount++;
    extract($aRow);
    if ($cfg_name == "sHeader") {
        $iHTMLHeaderRow=$iRowCount;
    }
}

// Save Settings
if (isset ($_POST['save'])){
	$new_value = $_POST['new_value'];
	$type = $_POST['type'];
	ksort ($type);
	reset ($type);
	while ($current_type = current($type)) {
		$id = key($type);
		// Filter Input
		if ($id == $iHTMLHeaderRow)	// Special handling of header value so HTML doesn't get removed
			$value = html_entity_decode($new_value[$id]);
		elseif ($current_type == 'text' || $current_type == "textarea")
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
		
		// If changing the locale, translate the menu options
		if ($id == 39 && $value != $sLanguage) {
			$sLanguage = $value;
			if (!(stripos(php_uname('s'), "windows") === false)) {
			        $sLang_Code = $lang_map_windows[strtolower($sLanguage)];
			} else {
			        $sLang_Code = $sLanguage;
			}
			putenv("LANG=$sLang_Code");
			setlocale(LC_ALL, $sLang_Code);

			TranslateMenuOptions ();
		}
		
		// Save new setting
		$sSQL = "UPDATE config_cfg SET cfg_value='$value' WHERE cfg_id='$id'";
		$rsUpdate = RunQuery($sSQL);
		next($type);
	}
    $sGlobalMessage ="Setting saved";
}

// Set the page title and include HTML header
$sPageTitle = gettext("General Configuration Settings") ." ". $_GET["Cat"];
require "Include/Header.php";

// Get settings
$rsConfigs = RunQuery($sSQL);
?>
<div class="box box-body">

<form method=post action=EditSettings.php>
<table class="table">
<tr>
    <th><?= gettext("Variable name") ?></th>
    <th>Current Value</th>
    <th>Default Value</th>
    <th>Notes</th>
</tr>

<?php
$r = 1;
// List Individual Settings
while (list($cfg_id, $cfg_name, $cfg_value, $cfg_type, $cfg_default, $cfg_tooltip, $cfg_section) = mysql_fetch_row($rsConfigs)) {
	
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
?>
			<tr>
				<td>&nbsp;</td>
				<td>
					<input type=submit class='btn btn-primary' name=save value='<?= gettext("Save Settings") ?>'>
					<input type=submit class=btn name=cancel value='<?= gettext("Cancel") ?>'>
				</td>
			</tr>
		</table>
		</form>
		</div>
<?php require "Include/Footer.php"; ?>
