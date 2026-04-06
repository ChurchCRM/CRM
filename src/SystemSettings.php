<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Bootstrapper;
use ChurchCRM\dto\LocaleInfo;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\view\PageHeader;

// Security
AuthenticationManager::redirectHomeIfNotAdmin();

$sPageTitle = gettext('System Settings');
$sPageSubtitle = gettext('Configure global system settings and preferences');

// Save Settings
if (isset($_POST['save'])) {
    $new_value = $_POST['new_value'];
    $type = $_POST['type'];
    ksort($type);
    reset($type);

    while ($current_type = current($type)) {
        $id = key($type);
        // Filter Input based on type
        if ($current_type == 'text' || $current_type == 'textarea' || $current_type == 'password') {
            $value = InputUtils::sanitizeText($new_value[$id]);
        } elseif ($current_type == 'number') {
            $value = InputUtils::filterFloat($new_value[$id]);
        } elseif ($current_type == 'date') {
            $value = InputUtils::filterDate($new_value[$id]);
        } elseif ($current_type == 'json') {
            $raw = $new_value[$id];
            json_decode($raw);
            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $raw;
            } else {
                $_SESSION['sGlobalMessage'] = gettext('Invalid JSON value — setting was not saved. Please correct it and try again.');
                $_SESSION['sGlobalMessageClass'] = 'danger';
                RedirectUtils::redirect('SystemSettings.php');
                exit;
            }
        } elseif ($current_type == 'choice') {
            $value = InputUtils::sanitizeText($new_value[$id]);
        } elseif ($current_type == 'ajax') {
            $value = InputUtils::sanitizeText($new_value[$id]);
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

        // For password fields, only update if a new value is provided (non-empty)
        if ($current_type == 'password' && empty($value)) {
            // Skip update - preserve existing password
            next($type);
            continue;
        }

        SystemConfig::setValueById($id, $value);
        next($type);
    }
    $_SESSION['sGlobalMessage'] = gettext('Setting saved');
    $_SESSION['sGlobalMessageClass'] = 'success';
    RedirectUtils::redirect("SystemSettings.php");
}

if (isset($_SESSION['sGlobalMessage'])) {
    $sGlobalMessage = $_SESSION['sGlobalMessage'];
    $sGlobalMessageClass = $_SESSION['sGlobalMessageClass'] ?? 'success';
    unset($_SESSION['sGlobalMessage']);
    unset($_SESSION['sGlobalMessageClass']);
}

$aBreadcrumbs = PageHeader::breadcrumbs([
    [gettext('Admin'), '/admin/'],
    [gettext('System Settings')],
]);
require_once __DIR__ . '/Include/Header.php';

// Build a stable, valid CSS ID from a category name
function categoryId(string $category): string {
    return 'cat-' . preg_replace('/[^a-zA-Z0-9]+/', '-', strtolower($category));
}
?>

<!-- JSON Settings Modal -->
<div id="JSONSettingsModal" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        <h4 class="modal-title"><?= gettext('Edit JSON Settings') ?></h4>
      </div>
      <div class="modal-body" id="JSONSettingsDiv"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= gettext('Close') ?></button>
        <button type="button" class="btn btn-primary jsonSettingsClose"><?= gettext('Save') ?></button>
      </div>
    </div>
  </div>
</div>

<form name="SystemSettingsForm" method="post" action="SystemSettings.php">
<div class="row g-4">

  <!-- Left: vertical nav pills -->
  <div class="col-md-3 col-lg-2">
    <div class="card">
      <div class="card-body p-2">
        <div class="nav flex-column nav-pills" id="settings-nav" role="tablist" aria-orientation="vertical">
          <?php
          $first = true;
          foreach (SystemConfig::getCategories() as $category => $settings) {
              $tabId = categoryId($category);
              ?>
          <a class="nav-link <?= $first ? 'active' : '' ?>"
             id="<?= $tabId ?>-tab"
             data-bs-toggle="pill"
             href="#<?= $tabId ?>"
             role="tab"
             aria-controls="<?= $tabId ?>"
             aria-selected="<?= $first ? 'true' : 'false' ?>">
            <?= gettext($category) ?>
          </a>
          <?php
              $first = false;
          } ?>
        </div>
        <hr class="my-2">
        <div class="d-grid">
          <input type="submit" class="btn btn-primary btn-sm" name="save" id="save"
                 data-save-scope="all"
                 value="<?= gettext('Save Settings') ?>">
        </div>
      </div>
    </div>
  </div>

  <!-- Right: tab content -->
  <div class="col-md-9 col-lg-10">
    <div class="tab-content" id="settings-tab-content">
      <?php
      $first = true;
      foreach (SystemConfig::getCategories() as $category => $settings) {
          $tabId = categoryId($category);
          ?>
      <div class="tab-pane fade <?= $first ? 'show active' : '' ?>"
           id="<?= $tabId ?>"
           role="tabpanel"
           aria-labelledby="<?= $tabId ?>-tab">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title"><?= gettext($category) ?></h3>
          </div>
          <div class="table-responsive">
            <table class="table table-vcenter card-table">
              <thead>
                <tr>
                  <th style="width:200px"><?= gettext('Variable name') ?></th>
                  <th style="width:380px"><?= gettext('Value') ?></th>
                  <th><?= gettext('Default / Notes') ?></th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($settings as $settingName) :
                  $setting = SystemConfig::getConfigItem($settingName); ?>
              <tr>
                <td class="text-secondary">
                  <?= InputUtils::escapeHTML($setting->getName()) ?>
                  <input type="hidden" name="type[<?= $setting->getId() ?>]" value="<?= InputUtils::escapeAttribute($setting->getType()) ?>">
                </td>
                <td>
                  <?php if ($setting->getType() === 'choice') : ?>
                    <select name="new_value[<?= $setting->getId() ?>]" class="form-select choiceSelectBox"
                            data-setting-name="<?= InputUtils::escapeAttribute($setting->getName()) ?>"
                            data-initial-value="<?= InputUtils::escapeAttribute($setting->getValue() ?? '') ?>">
                      <?php foreach (json_decode($setting->getData())->Choices as $choice) :
                          if (strpos($choice, ':') === false) {
                              $choiceText = $choice;
                              $choiceValue = $choice;
                          } else {
                              $keyValue = explode(':', $choice);
                              $choiceValue = $keyValue[1];
                              $choiceText = $keyValue[0] . ' [' . $choiceValue . ']';
                          } ?>
                        <option value="<?= InputUtils::escapeAttribute($choiceValue) ?>" <?= $setting->getValue() == $choiceValue ? 'selected' : '' ?>><?= InputUtils::escapeHTML($choiceText) ?></option>
                      <?php endforeach; ?>
                    </select>

                  <?php elseif ($setting->getType() === 'text') : ?>
                    <input type="text" maxlength="255" class="form-control" name="new_value[<?= $setting->getId() ?>]" value="<?= InputUtils::escapeAttribute($setting->getValue()) ?>"
                           data-setting-name="<?= InputUtils::escapeAttribute($setting->getName()) ?>"
                           data-initial-value="<?= InputUtils::escapeAttribute($setting->getValue() ?? '') ?>">

                  <?php elseif ($setting->getType() === 'password') : ?>
                    <input type="password" maxlength="255" class="form-control" name="new_value[<?= $setting->getId() ?>]" placeholder="<?= gettext('Leave blank to keep existing password') ?>"
                           data-setting-name="<?= InputUtils::escapeAttribute($setting->getName()) ?>">

                  <?php elseif ($setting->getType() === 'textarea') : ?>
                    <textarea rows="4" class="form-control" name="new_value[<?= $setting->getId() ?>]"
                              data-setting-name="<?= InputUtils::escapeAttribute($setting->getName()) ?>"
                              data-initial-value="<?= InputUtils::escapeAttribute($setting->getValue() ?? '') ?>"><?= InputUtils::escapeHTML($setting->getValue()) ?></textarea>

                  <?php elseif ($setting->getType() === 'number' || $setting->getType() === 'date') : ?>
                    <input type="text" maxlength="15" class="form-control" name="new_value[<?= $setting->getId() ?>]" value="<?= InputUtils::escapeAttribute($setting->getValue()) ?>"
                           data-setting-name="<?= InputUtils::escapeAttribute($setting->getName()) ?>"
                           data-initial-value="<?= InputUtils::escapeAttribute($setting->getValue() ?? '') ?>">

                  <?php elseif ($setting->getType() === 'boolean') : ?>
                    <select name="new_value[<?= $setting->getId() ?>]" class="form-select choiceSelectBox"
                            data-setting-name="<?= InputUtils::escapeAttribute($setting->getName()) ?>"
                            data-initial-value="<?= $setting->getValue() ? '1' : '0' ?>">
                      <option value="0" <?= !$setting->getValue() ? 'selected' : '' ?>><?= gettext('False') ?></option>
                      <option value="1" <?= $setting->getValue() ? 'selected' : '' ?>><?= gettext('True') ?></option>
                    </select>

                  <?php elseif ($setting->getType() === 'json') : ?>
                    <input type="hidden" name="new_value[<?= $setting->getId() ?>]" value="<?= InputUtils::escapeAttribute($setting->getValue()) ?>"
                           data-setting-name="<?= InputUtils::escapeAttribute($setting->getName()) ?>"
                           data-initial-value="<?= InputUtils::escapeAttribute($setting->getValue() ?? '') ?>">
                    <button type="button" class="btn btn-outline-primary btn-sm jsonSettingsEdit"
                            id="set_value<?= $setting->getId() ?>"
                            data-cfgid="<?= $setting->getId() ?>">
                      <i class="fa-solid fa-pen-to-square me-1"></i><?= gettext('Edit Settings') ?>
                    </button>

                  <?php elseif ($setting->getType() === 'ajax') : ?>
                    <select id="ajax-<?= $setting->getId() ?>"
                            name="new_value[<?= $setting->getId() ?>]"
                            data-url="<?= InputUtils::escapeAttribute($setting->getData()) ?>"
                            data-value="<?= InputUtils::escapeAttribute($setting->getValue()) ?>"
                            class="form-select choiceSelectBox"
                            data-setting-name="<?= InputUtils::escapeAttribute($setting->getName()) ?>"
                            data-initial-value="<?= InputUtils::escapeAttribute($setting->getValue() ?? '') ?>">
                      <option value=""><?= gettext('Unassigned') ?></option>
                    </select>

                  <?php else : ?>
                    <span class="text-danger"><?= gettext('Unknown Type') ?>: <?= InputUtils::escapeHTML($setting->getType()) ?></span>
                  <?php endif; ?>
                </td>
                <td class="text-secondary small">
                  <?php
                  if (!empty($setting->getTooltip())) : ?>
                    <a class="setting-tip me-1" data-tip="<?= InputUtils::escapeAttribute($setting->getTooltip()) ?>">
                      <i class="fa-solid fa-circle-question"></i>
                    </a>
                  <?php endif;
                  if (!empty($setting->getUrl())) : ?>
                    <a href="<?= InputUtils::escapeAttribute($setting->getUrl()) ?>" target="_blank" class="me-1">
                      <i class="fa-solid fa-link"></i>
                    </a>
                  <?php endif;
                  // Do not display password defaults for security reasons (GHSA-p98h-5xcj-5c6x)
                  if ($setting->getType() !== 'password') :
                      $display_default = $setting->getDefault();
                      if ($setting->getType() === 'boolean') {
                          $display_default = $setting->getDefault() ? 'True' : 'False';
                      }
                      echo InputUtils::escapeHTML($display_default);
                  endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="card-footer text-end">
            <input type="submit" class="btn btn-primary" name="save"
                   data-save-scope="section"
                   value="<?= gettext('Save Settings') ?>">
          </div>
        </div>
      </div>
      <?php
          $first = false;
      } ?>
    </div>
  </div>

</div>
</form>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
  $(document).ready(function() {
    // Initialise TomSelect for the active tab immediately
    $('.tab-pane.active .choiceSelectBox').each(function () {
      if (!this.tomselect) new TomSelect(this, { dropdownParent: 'body' });
    });

    // Initialise TomSelect when switching tabs
    $('a[data-bs-toggle="pill"]').on('shown.bs.tab', function(e) {
      var target = $(e.target).attr('href');
      $(target + ' .choiceSelectBox').each(function () {
        if (!this.tomselect) new TomSelect(this, { dropdownParent: 'body' });
      });
    });

    <?php
    foreach (SystemConfig::getCategories() as $category => $settings) {
        foreach ($settings as $settingName) {
            $setting = SystemConfig::getConfigItem($settingName);
            if ($setting->getType() === 'ajax') { ?>
      updateDropDrownFromAjax($('#ajax-<?= $setting->getId() ?>'));
    <?php   }
        }
    } ?>
  });
</script>
<script src="<?= SystemURLs::assetVersioned('/skin/js/SystemSettings.js') ?>"></script>
<?php
require_once __DIR__ . '/Include/Footer.php';
