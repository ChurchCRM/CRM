<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\UserConfig;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\view\PageHeader;

$iPersonID = AuthenticationManager::getCurrentUser()->getId();

// Save Settings
if (isset($_POST['save'])) {
    $new_value = $_POST['new_value'];
    $type = $_POST['type'];
    ksort($type);
    reset($type);
    while ($current_type = current($type)) {
        $id = key($type);
        // Filter Input
        if ($current_type === 'text' || $current_type === 'textarea') {
            $value = InputUtils::legacyFilterInput($new_value[$id]);
        } elseif ($current_type === 'number') {
            $value = InputUtils::legacyFilterInput($new_value[$id], 'float');
        } elseif ($current_type === 'date') {
            $value = InputUtils::legacyFilterInput($new_value[$id], 'date');
        } elseif ($current_type === 'boolean') {
            if ($new_value[$id] !== '1') {
                $value = '';
            } else {
                $value = '1';
            }
        }
        // We can't update unless values already exist.
        $sSQL = 'SELECT * FROM userconfig_ucfg '
            ."WHERE ucfg_id=$id AND ucfg_per_id=$iPersonID";
        $bRowExists = true;
        $iNumRows = mysqli_num_rows(RunQuery($sSQL));
        if ($iNumRows === 0) {
            $bRowExists = false;
        }

        if (!$bRowExists) { // If Row does not exist then insert default values.
            // Defaults will be replaced in the following Update
            $sSQL = 'SELECT * FROM userconfig_ucfg '
                ."WHERE ucfg_id=$id AND ucfg_per_id=0";
            $rsDefault = RunQuery($sSQL);
            $aDefaultRow = mysqli_fetch_row($rsDefault);
            if ($aDefaultRow) {
                list(
                    $ucfg_per_id, $ucfg_id, $ucfg_name, $ucfg_value, $ucfg_type,
                    $ucfg_tooltip, $ucfg_permission
                ) = $aDefaultRow;

                $userConfig = new UserConfig();
                $userConfig
                    ->setPeronId($iPersonID)
                    ->setId($id)
                    ->setName($ucfg_name)
                    ->setValue($ucfg_value)
                    ->setType($ucfg_type)
                    ->setTooltip($ucfg_tooltip)
                    ->setPermission($ucfg_permission);
                $userConfig->save();
            } else {
                echo '<BR> Error: Software BUG 3216';
                exit;
            }
        }

        // Save new setting
        $sSQL = 'UPDATE userconfig_ucfg '
            ."SET ucfg_value='$value'"
            ."WHERE ucfg_id=$id AND ucfg_per_id=$iPersonID";
        $rsUpdate = RunQuery($sSQL);
        next($type);
    }

    RedirectUtils::redirect('SettingsIndividual.php'); // to reflect the tooltip change, we have to refresh the page
}

$sPageTitle = gettext('My User Settings');
$sPageSubtitle = gettext('Manage your personal preferences and account settings');
$aBreadcrumbs = PageHeader::breadcrumbs([
    [gettext('My Settings')],
]);
require_once __DIR__ . '/Include/Header.php';

// Get settings
$sSQL = 'SELECT * FROM userconfig_ucfg WHERE ucfg_per_id=' . $iPersonID
    . ' ORDER BY ucfg_id';
$rsConfigs = RunQuery($sSQL);
?>
<div class="card">
  <div class="card-body">
    <form method="post" action="SettingsIndividual.php">
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead>
            <tr>
              <th><?= gettext('Variable name') ?></th>
              <th><?= gettext('Current Value') ?></th>
              <th><?= gettext('Notes') ?></th>
            </tr>
          </thead>
          <tbody>
          <?php
          $r = 1;
          while (list($ucfg_per_id, $ucfg_id, $ucfg_name, $ucfg_value, $ucfg_type, $ucfg_tooltip, $ucfg_permission) = mysqli_fetch_row($rsConfigs)) {
              if (!($ucfg_permission === 'TRUE' || AuthenticationManager::getCurrentUser()->isAdmin())) {
                  continue;
              }

              // Variable Name & Type
              echo '<tr>';
              echo '<td>' . InputUtils::escapeHTML($ucfg_name);
              echo '<input type="hidden" name="type[' . (int)$ucfg_id . ']" value="' . InputUtils::escapeAttribute($ucfg_type) . '"></td>';

              // Current Value
              if ($ucfg_type === 'text') {
                  echo '<td><input type="text" class="form-control" maxlength="255" name="new_value[' . (int)$ucfg_id . ']" value="' . InputUtils::escapeHTML($ucfg_value) . '"></td>';
              } elseif ($ucfg_type === 'textarea') {
                  echo '<td><textarea class="form-control" rows="4" name="new_value[' . (int)$ucfg_id . ']">' . InputUtils::escapeHTML($ucfg_value) . '</textarea></td>';
              } elseif ($ucfg_type === 'number' || $ucfg_type === 'date') {
                  echo '<td><input type="text" class="form-control" maxlength="15" name="new_value[' . (int)$ucfg_id . ']" value="' . InputUtils::escapeAttribute($ucfg_value) . '"></td>';
              } elseif ($ucfg_type === 'boolean') {
                  $sel1 = $ucfg_value ? '' : 'selected';
                  $sel2 = $ucfg_value ? 'selected' : '';
                  echo '<td><select class="form-select" name="new_value[' . (int)$ucfg_id . ']">';
                  echo '<option value="" ' . $sel1 . '>' . gettext('False') . '</option>';
                  echo '<option value="1" ' . $sel2 . '>' . gettext('True') . '</option>';
                  echo '</select></td>';
              }

              // Notes
              echo '<td>' . gettext($ucfg_tooltip) . '</td></tr>';
              $r++;
          }
          ?>
          </tbody>
        </table>
      </div>
      <div class="d-flex gap-2 mt-3">
        <input type="submit" class="btn btn-primary" name="save" value="<?= gettext('Save Settings') ?>">
        <input type="submit" class="btn btn-secondary" name="cancel" value="<?= gettext('Cancel') ?>">
      </div>
    </form>
  </div>
</div>
<?php
require_once __DIR__ . '/Include/Footer.php';
