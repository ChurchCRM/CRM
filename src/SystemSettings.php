<?php
/*******************************************************************************
 *
 *  filename    : CSVExport.php
 *  description : options for creating csv file
 *
 *  http://www.churchcrm.io/
 *  Copyright 2001-2002 Phillip Hullquist, Deane Barker
 *
 *  LICENSE:
 *  (C) Free Software Foundation, Inc.
 *
 *  ChurchCRM is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful, but
 *  WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 *  General Public License for mote details.
 *
 *  http://www.gnu.org/licenses
 *
 ******************************************************************************/

// Include the function library
require "Include/Config.php";
require "Include/Functions.php";
require "Include/TranslateMenuOptions.php";

// Security
if (!$_SESSION['bAdmin']) {
  Redirect("Menu.php");
  exit;
}

// Set the page title and include HTML header
$sPageTitle = gettext("System Settings");

$steps = array(
  "Step1" => "Church Information",
  "Step2" => "User setup",
  "Step3" => "Email Setup",
  "Step4" => "Member Setup",
  "Step5" => "System Settings",
  "Step6" => "Map Settings",
  "Step7" => "Report Settings",
  "Step8" => "Other Settings",
);



$sSQL = "SELECT * FROM config_cfg ORDER BY cfg_category, cfg_order";
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

require "Include/Header.php";

// Get settings
$sSQL = "SELECT * FROM config_cfg ORDER BY cfg_category, cfg_order";
$rsConfigs = RunQuery($sSQL);


?>

<div class="row">
  <div class="col-lg-12">
    <div class="box box-body">
      <form method=post action=SystemSettings.php>

      <div class="nav-tabs-custom">
        <ul class="nav nav-tabs">
          <?php foreach ($steps as $step => $stepName) { ?>
            <li class="<?php if ($step == "Step1") echo "active" ?>"><a href="#<?= $step ?>" data-toggle="tab" aria-expanded="false"><?= $stepName ?></a></li>
          <?php } ?>
        </ul>
        <div class="tab-content">
          <div class="tab-pane active" id="Step1">
            <table class="table">
            <tr>
              <th><?= gettext("Variable name") ?></th>
              <th>Current Value</th>
              <th>Default Value</th>
              <th>Notes</th>
            </tr>
          <?php
          $r = 1;
          $step = "Step".$r;
          // List Individual Settings
          while (list($cfg_id, $cfg_name, $cfg_value, $cfg_type, $cfg_default, $cfg_tooltip, $cfg_section, $cfg_category) = mysql_fetch_row($rsConfigs)) {
            if ($cfg_category != $step) {
          $step = $cfg_category;
          ?>
              </table>
            </div>
            <div class="tab-pane" id="<?= $step?>">
              <table class="table">
              <tr>
                <th><?= gettext("Variable name") ?></th>
                <th>Current Value</th>
                <th>Default Value</th>
                <th>Notes</th>
              </tr>
            <?php  } ?>


                  <?php  // Variable Name & Type
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

                  ?>
                </tr>
                <?php } ?>
              </table>

            </div>

        </div>

      </div>

      <input type=submit class='btn btn-primary' name=save value='<?= gettext("Save Settings") ?>'>
    </div>
    </form>
  </div>
</div>


<?php require "Include/Footer.php" ?>
