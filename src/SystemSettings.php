<?php
/*******************************************************************************
 *
 *  filename    : SystemSettings.php
 *  description : setup de systema settings
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

use ChurchCRM\dto\LocaleInfo;

// Security
if (!$_SESSION['bAdmin']) {
  Redirect("Menu.php");
  exit;
}

// Set the page title and include HTML header
$sPageTitle = gettext("System Settings");

$steps = array(
  "Step1" => gettext("Church Information"),
  "Step2" => gettext("User setup"),
  "Step3" => gettext("Email Setup"),
  "Step4" => gettext("Member Setup"),
  "Step5" => gettext("System Settings"),
  "Step6" => gettext("Map Settings"),
  "Step7" => gettext("Report Settings"),
  "Step9" => gettext("Localization"),
  "Step8" => gettext("Other Settings")
);


$sSQL = "SELECT * FROM config_cfg ORDER BY cfg_category, cfg_order";
$rsConfigs = RunQuery($sSQL);
$iRowCount = 0;
while ($aRow = mysql_fetch_array($rsConfigs)) {
  $iRowCount++;
  extract($aRow);
  if ($cfg_name == "sHeader") {
    $iHTMLHeaderRow = $iRowCount;
  }
}

// Save Settings
if (isset ($_POST['save'])) {
  $new_value = $_POST['new_value'];
  $type = $_POST['type'];
  ksort($type);
  reset($type);
  while ($current_type = current($type)) {
    $id = key($type);
    // Filter Input
    if ($id == $iHTMLHeaderRow)  // Special handling of header value so HTML doesn't get removed
      $value = html_entity_decode($new_value[$id]);
    elseif ($current_type == 'text' || $current_type == "textarea")
      $value = FilterInput($new_value[$id]);
    elseif ($current_type == 'number')
      $value = FilterInput($new_value[$id], "float");
    elseif ($current_type == 'date')
      $value = FilterInput($new_value[$id], "date");
    elseif ($current_type == 'json')
      $value = $new_value[$id];
    elseif ($current_type == 'choice')
      $value = FilterInput($new_value[$id]);
    elseif ($current_type == 'boolean') {
      if ($new_value[$id] != "1")
        $value = "";
      else
        $value = "1";
    }

    // If changing the locale, translate the menu options
    if ($id == 39 && $value != $localeInfo->getLocale()) {
      $localeInfo = new LocaleInfo($value);
      setlocale(LC_ALL, $localeInfo->getLocale());
      $aLocaleInfo = $localeInfo->getLocaleInfo();
    }

    if ($id == 65 && !(in_array($value, timezone_identifiers_list()))) {
      $value = date_default_timezone_get();
    }

    // Save new setting
    $sSQL = "UPDATE config_cfg SET cfg_value='$value' WHERE cfg_id='$id'";
    $rsUpdate = RunQuery($sSQL);
    next($type);
  }
  $sGlobalMessage = gettext("Setting saved");
}

require "Include/Header.php";

// Get settings
$sSQL = "SELECT * FROM config_cfg ORDER BY cfg_category, cfg_order";
$rsConfigs = RunQuery($sSQL);
?>

<div id="JSONSettingsModal" class="modal fade" role="dialog">
  <div class="modal-dialog">
    <!-- Modal content-->
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title"><?= gettext("Edit JSON Settings") ?></h4>
      </div>
      <div class="modal-body" id="JSONSettingsDiv">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary jsonSettingsClose">Save</button>
        <button type="button" class="btn btn-primary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-lg-12">
    <div class="box box-body">
      <form method=post action=SystemSettings.php>
        <div class="nav-tabs-custom">
          <ul class="nav nav-tabs">
            <?php foreach ($steps as $step => $stepName) { ?>
              <li class="<?php if ($step == "Step1") echo "active" ?>"><a href="#<?= $step ?>" data-toggle="tab"
                                                                          aria-expanded="false"><?= $stepName ?></a>
              </li>
            <?php } ?>
          </ul>
          <div class="tab-content">
            <div class="tab-pane active" id="Step1">
              <table class="table table-striped">
                <tr>
                  <th width="150px"><?= gettext("Variable name") ?></th>
                  <th width="400px"><?= gettext("Value")?></th>
                  <th><?= gettext("Default Value")?></th>
                </tr>
                <?php
                $r = 1;
                $step = "Step" . $r;
                // List Individual Settings
                while (list($cfg_id, $cfg_name, $cfg_value, $cfg_type, $cfg_default, $cfg_tooltip, $cfg_section, $cfg_category, $cfg_order, $cfg_data) = mysql_fetch_row($rsConfigs)) {
                if ($cfg_category != $step) {
                $step = $cfg_category;
                ?>
              </table>
            </div>
            <div class="tab-pane" id="<?= $step ?>">
              <table class="table table-striped">
                <tr>
                  <th width="150px"><?= gettext("Variable name") ?></th>
                  <th width="400px"><?= gettext("Current Value") ?></th>
                  <th><?= gettext("Default Value") ?></th>
                </tr>
                <?php } ?>
                <tr>
                  <td><?= $cfg_name ?></td>
                  <input type=hidden name='type[<?= $cfg_id ?>]' value='<?= $cfg_type ?>'>
                  <td>
                    <!--  Current Value -->
                    <?php if ($cfg_name == "sTimeZone") { ?>
                      <select name='new_value[<?= $cfg_id ?>]' class="choiceSelectBox" style="width: 100%">
                        <?php
                        foreach (timezone_identifiers_list() as $timeZone) {
                          echo "<option value = " . $timeZone . " " . ($cfg_value == $timeZone ? "selected" : "") . ">" . $timeZone . "</option>";
                        }
                        ?>
                      </select>
                    <?php } elseif ($cfg_type == 'choice') { ?>
                      <select name='new_value[<?= $cfg_id ?>]' class="choiceSelectBox" style="width: 100%">
                        <?php
                        foreach (json_decode($cfg_data)->Choices as $choice) {
                          echo "<option value = " . $choice . " " . ($cfg_value == $choice ? "selected" : "") . ">" . $choice . "</option>";
                        }
                        ?>
                      </select>
                    <?php } elseif ($cfg_type == 'text') { ?>
                      <input type=text size=40 maxlength=255 name='new_value[<?= $cfg_id ?>]'
                             value='<?= htmlspecialchars($cfg_value, ENT_QUOTES) ?>' class="form-control">
                    <?php } elseif ($cfg_type == 'textarea') { ?>
                      <textarea rows=4 cols=40 name='new_value[<?= $cfg_id ?>]'
                                class="form-control"><?= htmlspecialchars($cfg_value, ENT_QUOTES) ?></textarea>
                    <?php } elseif ($cfg_type == 'number' || $cfg_type == 'date') { ?>
                      <input type=text size=40 maxlength=15 name='new_value[<?= $cfg_id ?>]' value='<?= $cfg_value ?>'
                             class="form-control">
                    <?php } elseif ($cfg_type == 'boolean') {
                      if ($cfg_value) {
                        $sel1 = "";
                        $sel2 = "SELECTED";
                      } else {
                        $sel1 = "SELECTED";
                        $sel2 = "";
                      } ?>
                      <select name='new_value[<?= $cfg_id ?>]' class="choiceSelectBox" style="width: 100%">
                        <option value='' <?= $sel1 ?>><?= gettext("False")?>
                        <option value='1' <?= $sel2 ?>><?= gettext("True")?>
                      </select>
                    <?php } elseif ($cfg_type == 'json') { ?>
                      <input type="hidden" name='new_value[<?= $cfg_id ?>]' value='<?= $cfg_value ?>'>
                      <button class="btn-primary jsonSettingsEdit" id="set_value<?= $cfg_id ?>"
                              data-cfgid="<?= $cfg_id ?>"><?= gettext("Edit Settings")?>
                      </button>
                    <?php } ?>
                  </td>
                  <?php
                  // Default Value
                  $display_default = $cfg_default;
                  if ($cfg_type == 'boolean') {
                    if ($cfg_default)
                      $display_default = "True";
                    else
                      $display_default = "False";
                  }
                  ?>
                  <td>
                    <?php if ($cfg_tooltip != "") { ?>
                      <i class="fa fa-fw fa-question-circle" data-toggle="tooltip" title="<?= gettext($cfg_tooltip) ?>"></i>
                    <?php } ?>
                    <?= $display_default ?>
                  </td>
                </tr>
                <?php $r++; ?>
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

<script>
  $(document).ready(function () {
    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
      var target = $(e.target).attr("href") // activated tab
      $(target + " .choiceSelectBox").select2({width: 'resolve'});
    });
    $(".choiceSelectBox").select2({width: 'resolve'});
  });
</script>
<script src="skin/js/SystemSettings.js" type="text/javascript"></script>


<?php require "Include/Footer.php" ?>
