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
use ChurchCRM\data\Countries;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\ConfigQuery;
use ChurchCRM\Config;

// Security
if (!$_SESSION['bAdmin']) {
  Redirect("Menu.php");
  exit;
}

// Set the page title and include HTML header
$sPageTitle = gettext("System Settings");

// Save Settings
if (isset ($_POST['save'])) {
  $new_value = $_POST['new_value'];
  $type = $_POST['type'];
  ksort($type);
  reset($type);
  
  $iHTMLHeaderRow = ConfigQuery::create()->filterByName("sHeader")->findOne()->getId();
  
  while ($current_type = current($type)) {
    $id = key($type);
    // Filter Input
    if ($id == $iHTMLHeaderRow)  // Special handling of header value so HTML doesn't get removed
      $value = FilterInput($new_value[$id], "htmltext");
    elseif ($current_type == 'text' || $current_type == "textarea" || $current_type == "country")
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

    SystemConfig::setValueById($id, $value);
    next($type);
  }
  $sGlobalMessage = gettext("Setting saved");
}

require "Include/Header.php";

// Get settings
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
            <?php foreach (SystemConfig::getConfigSteps() as $step => $stepName) { ?>
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
                $settings = ConfigQuery::create()->orderByCategory()->orderByOrder()->find();
                foreach($settings as  $setting) {
                if ($setting->getCategory() != $step) {
                $step = $setting->getCategory();
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
                  <td><?= $setting->getName() ?></td>
                  <input type=hidden name='type[<?= $setting->getId() ?>]' value='<?= $setting->getType() ?>'>
                  <td>
                    <!--  Current Value -->
                    <?php if ($setting->getName() == "sTimeZone") { ?>
                      <select name='new_value[<?= $setting->getId() ?>]' class="choiceSelectBox" style="width: 100%">
                        <?php
                        foreach (timezone_identifiers_list() as $timeZone) {
                          echo "<option value = '" . $timeZone . "'' " . ($setting->getValue() == $timeZone ? "selected" : "") . ">" . $timeZone . "</option>";
                        }
                        ?>
                      </select>
                    <?php } elseif ($setting->getType() == 'country') { ?>
                      <select name='new_value[<?= $setting->getId() ?>]' class="choiceSelectBox" style="width: 100%">
                        <?php
                        foreach (Countries::getNames() as $country) {
                          echo "<option value = '" . $country . "'' " . ($setting->getValue() == $country ? "selected" : "") . ">" . $country . "</option>";
                        }
                        ?>
                      </select>
                    <?php } elseif ($setting->getType() == 'choice') { ?>
                      <select name='new_value[<?= $setting->getId() ?>]' class="choiceSelectBox" style="width: 100%">
                        <?php
                        foreach (json_decode($setting->getData())->Choices as $choice) {
                          echo "<option value = " . $choice . " " . ($setting->getValue() == $choice ? "selected" : "") . ">" . $choice . "</option>";
                        }
                        ?>
                      </select>
                    <?php } elseif ($setting->getType() == 'text') { ?>
                      <input type=text size=40 maxlength=255 name='new_value[<?= $setting->getId() ?>]'
                             value='<?= htmlspecialchars($setting->getValue(), ENT_QUOTES) ?>' class="form-control">
                    <?php } elseif ($setting->getType() == 'textarea') { ?>
                      <textarea rows=4 cols=40 name='new_value[<?= $setting->getId() ?>]'
                                class="form-control"><?= htmlspecialchars($setting->getValue(), ENT_QUOTES) ?></textarea>
                    <?php } elseif ($setting->getType() == 'number' || $setting->getType() == 'date') { ?>
                      <input type=text size=40 maxlength=15 name='new_value[<?= $setting->getId() ?>]' value='<?= $setting->getValue() ?>'
                             class="form-control">
                    <?php } elseif ($setting->getType() == 'boolean') {
                      if ($setting->getValue()) {
                        $sel1 = "";
                        $sel2 = "SELECTED";
                      } else {
                        $sel1 = "SELECTED";
                        $sel2 = "";
                      } ?>
                      <select name='new_value[<?= $setting->getId() ?>]' class="choiceSelectBox" style="width: 100%">
                        <option value='' <?= $sel1 ?>><?= gettext("False")?>
                        <option value='1' <?= $sel2 ?>><?= gettext("True")?>
                      </select>
                    <?php } elseif ($setting->getType() == 'json') { ?>
                      <input type="hidden" name='new_value[<?= $setting->getId() ?>]' value='<?= $setting->getValue() ?>'>
                      <button class="btn-primary jsonSettingsEdit" id="set_value<?= $setting->getId() ?>"
                              data-cfgid="<?= $setting->getId() ?>"><?= gettext("Edit Settings")?>
                      </button>
                    <?php } ?>
                  </td>
                  <?php
                  // Default Value
                  $display_default = $setting->getDefault();
                  if ($setting->getType() == 'boolean') {
                    if ($setting->getDefault())
                      $display_default = "True";
                    else
                      $display_default = "False";
                  }
                  ?>
                  <td>
                    <?php if ($setting->getTooltip() != "") { ?>
                      <i class="fa fa-fw fa-question-circle" data-toggle="tooltip" title="<?= gettext($setting->getTooltip()) ?>"></i>
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
        <input type=submit class='btn btn-primary' name=save value="<?= gettext("Save Settings") ?>">
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
