<?php

/*******************************************************************************
 *
 *  filename    : SystemSettings.php
 *  description : setup de systema settings
 *
 *  https://churchcrm.io/
 *  Copyright 2001-2002 Phillip Hullquist, Deane Barker
 *
 ******************************************************************************/

// Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Bootstrapper;
use ChurchCRM\dto\LocaleInfo;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

// Security
if (!AuthenticationManager::getCurrentUser()->isAdmin()) {
    RedirectUtils::redirect('Menu.php');
    exit;
}

// Set the page title and include HTML header
$sPageTitle = gettext('System Settings');

// Save Settings
if (isset($_POST['save'])) {
    $new_value = $_POST['new_value'];
    $type = $_POST['type'];
    ksort($type);
    reset($type);

    $iHTMLHeaderRow = SystemConfig::getConfigItem('sHeader')->getId();

    while ($current_type = current($type)) {
        $id = key($type);
        // Filter Input
        if ($id == $iHTMLHeaderRow) {  // Special handling of header value so HTML doesn't get removed
            $value = InputUtils::filterHTML($new_value[$id]);
        } elseif ($current_type == 'text' || $current_type == 'textarea' || $current_type == 'password') {
            $value = InputUtils::filterString($new_value[$id]);
        } elseif ($current_type == 'number') {
            $value = InputUtils::filterFloat($new_value[$id]);
        } elseif ($current_type == 'date') {
            $value = InputUtils::filterDate($new_value[$id]);
        } elseif ($current_type == 'json') {
            $value = $new_value[$id];
        } elseif ($current_type == 'choice') {
            $value = InputUtils::filterString($new_value[$id]);
        } elseif ($current_type == 'ajax') {
            $value = InputUtils::filterString($new_value[$id]);
        } elseif ($current_type == 'boolean') {
            if ($new_value[$id] != '1') {
                $value = '';
            } else {
                $value = '1';
            }
        }

        // If changing the locale, translate the menu options
        if ($id == 39 && $value != Bootstrapper::getCurrentLocale()->getLocale()) {
            $localeInfo = new LocaleInfo($value, AuthenticationManager::getCurrentUser()->getSetting("ui.locale"));
            setlocale(LC_ALL, $localeInfo->getLocale(), $localeInfo->getLocale() . '.UTF-8', $localeInfo->getLocale() . '.utf8');
            $aLocaleInfo = $localeInfo->getLocaleInfo();
        }

        if ($id == 65 && !(in_array($value, timezone_identifiers_list()))) {
            $value = date_default_timezone_get();
        }

        SystemConfig::setValueById($id, $value);
        next($type);
    }
    RedirectUtils::redirect("SystemSettings.php?saved=true");
}

if (isset($_GET['saved'])) {
    $sGlobalMessage = gettext('Setting saved');
}

require 'Include/Header.php';

// Get settings
?>

<div id="JSONSettingsModal" class="modal fade" role="dialog">
  <div class="modal-dialog">
    <!-- Modal content-->
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title"><?= gettext('Edit JSON Settings') ?></h4>
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

<form name="SystemSettingsForm" method=post action=SystemSettings.php>
<div class="row">
  <div class="col-2 col-sm-2">
    <div class="nav flex-column nav-tabs h-100" id="vert-tabs-tab" role="tablist" aria-orientation="vertical">
      <?php foreach (SystemConfig::getCategories() as $category => $settings) {
            $navItemId = str_replace(" ", '', $category);
            $shouldBeSelected = false;
            if ($category == 'Church Information') {
                $shouldBeSelected = true;
            } ?>
        <a class="nav-link <?= $shouldBeSelected ? "active" : "" ?>" id="<?= $navItemId ?>-tab" data-toggle="pill" href="#<?= $navItemId ?>" role="tab" aria-controls="vert-tabs-profile" aria-selected="<?= $shouldBeSelected ?>"><?= gettext($category) ?></a>
            <?php
      } ?>
      <hr>
      <input type='submit' class='btn btn-sm btn-primary' name='save' id='save' value="<?= gettext('Save Settings') ?>">
    </div>

  </div>
  <div class="col-7 col-sm-9">
    <div class="tab-content" id="vert-tabs-tabContent">
      <?php foreach (SystemConfig::getCategories() as $category => $settings) {
            $navItemId = str_replace(" ", '', $category);
            $shouldBeSelected = false;
            if ($category == 'Church Information') {
                $shouldBeSelected = true;
            } ?>
        <div class="tab-pane fade <?= $shouldBeSelected ? "show active" : "" ?>" id="<?= $navItemId ?>" role="tabpanel" aria-labelledby="<?= $navItemId ?>-tab">
          <div class="table-responsive">
            <table class="table table-striped">
              <tr>
                <th width="150px"><?= gettext('Variable name') ?></th>
                <th width="400px"><?= gettext('Value') ?></th>
                <th><?= gettext('Default Value') ?></th>
              </tr>
              <?php
                foreach ($settings as $settingName) {
                    $setting = SystemConfig::getConfigItem($settingName)
                    ?>
                <tr>
                  <td><?= $setting->getName() ?></td>
                  <input type=hidden name='type[<?= $setting->getId() ?>]' value='<?= $setting->getType() ?>'>
                  <td>
                    <!--  Current Value -->
                    <?php
                    if ($setting->getType() == 'choice') {
                        ?>
                      <select name='new_value[<?= $setting->getId() ?>]' class="choiceSelectBox" style="width: 100%">
                        <?php
                        foreach (json_decode($setting->getData())->Choices as $choice) {
                            if (strpos($choice, ":") === false) {
                                $text = $choice;
                                $value = $choice;
                            } else {
                                $keyValue = explode(":", $choice);
                                $value = $keyValue[1];
                                $text = $keyValue[0] . ' [' . $value . ']';
                            }
                            echo '<option value = "' . $value . '" ' . ($setting->getValue() == $value ? 'selected' : '') . '>' . $text . '</option>';
                        } ?>
                      </select>
                        <?php
                    } elseif ($setting->getType() == 'text') {
                        ?>
                      <input type=text size=40 maxlength=255 name='new_value[<?= $setting->getId() ?>]' value='<?= htmlspecialchars($setting->getValue(), ENT_QUOTES) ?>' class="form-control">
                        <?php
                    } elseif ($setting->getType() == 'password') {
                        ?>
                      <input type=password size=40 maxlength=255 name='new_value[<?= $setting->getId() ?>]' value='<?= htmlspecialchars($setting->getValue(), ENT_QUOTES) ?>' class="form-control">
                        <?php
                    } elseif ($setting->getType() == 'textarea') {
                        ?>
                      <textarea rows=4 cols=40 name='new_value[<?= $setting->getId() ?>]' class="form-control"><?= htmlspecialchars($setting->getValue(), ENT_QUOTES) ?></textarea>
                        <?php
                    } elseif ($setting->getType() == 'number' || $setting->getType() == 'date') {
                        ?>
                      <input type=text size=40 maxlength=15 name='new_value[<?= $setting->getId() ?>]' value='<?= $setting->getValue() ?>' class="form-control">
                        <?php
                    } elseif ($setting->getType() == 'boolean') {
                        if ($setting->getValue()) {
                            $sel1 = '';
                            $sel2 = 'SELECTED';
                        } else {
                            $sel1 = 'SELECTED';
                            $sel2 = '';
                        } ?>
                      <select name='new_value[<?= $setting->getId() ?>]' class="choiceSelectBox" style="width: 100%">
                        <option value='' <?= $sel1 ?>><?= gettext('False') ?>
                        <option value='1' <?= $sel2 ?>><?= gettext('True') ?>
                      </select>
                        <?php
                    } elseif ($setting->getType() == 'json') {
                        ?>
                      <input type="hidden" name='new_value[<?= $setting->getId() ?>]' value='<?= $setting->getValue() ?>'>
                      <button class="btn-primary jsonSettingsEdit" id="set_value<?= $setting->getId() ?>" data-cfgid="<?= $setting->getId() ?>"><?= gettext('Edit Settings') ?>
                      </button>
                        <?php
                    } elseif ($setting->getType() == 'ajax') {
                        ?>
                      <select id='ajax-<?= $setting->getId() ?>' name='new_value[<?= $setting->getId() ?>]' data-url="<?= $setting->getData() ?>" data-value="<?= $setting->getValue() ?>" class="choiceSelectBox" style="width: 100%">
                        <option value=''><?= gettext('Unassigned') ?>
                      </select>
                        <?php
                    } else {
                        echo gettext("Unknown Type") . " " . $setting->getType();
                    } ?>
                  </td>
                    <?php
                    // Default Value
                    $display_default = $setting->getDefault();
                    if ($setting->getType() == 'boolean') {
                        if ($setting->getDefault()) {
                            $display_default = 'True';
                        } else {
                            $display_default = 'False';
                        }
                    } ?>
                  <td>
                    <?php if (!empty($setting->getTooltip())) {
                        ?>
                      <a class="setting-tip" data-tip="<?= $setting->getTooltip() ?>"><i class="fa fa-fw fa-question-circle"></i></a>
                        <?php
                    }
                    if (!empty($setting->getUrl())) {
                        ?>
                      <a href="<?= $setting->getUrl() ?>" target="_blank"><i class="fa fa-fw fa-link"></i></a>
                        <?php
                    } ?>
                    <?= $display_default ?>
                  </td>
                </tr>
                    <?php
                } ?>
            </table>
          </div>
        </div>
            <?php
      }
        ?>
    </div>
  </div>
</div>

</form>


<script nonce="<?= SystemURLs::getCSPNonce() ?>">
  $(document).ready(function() {
    $('a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
      var target = $(e.target).attr("href") // activated tab
      $(target + " .choiceSelectBox").select2({
        width: 'resolve'
      });
    });
    $(".choiceSelectBox").select2({
      width: 'resolve'
    });

    <?php
    foreach (SystemConfig::getCategories() as $category => $settings) {
        foreach ($settings as $settingName) {
            $setting = SystemConfig::getConfigItem($settingName);
            if ($setting->getType() == 'ajax') {
                ?>
          updateDropDrownFromAjax($('#ajax-<?= $setting->getId() ?>'));
                <?php
            }
        }
    } ?>
  });
</script>
<script src="skin/js/SystemSettings.js"></script>


<?php require 'Include/Footer.php' ?>
