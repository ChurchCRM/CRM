<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\UserConfig;
use ChurchCRM\model\ChurchCRM\UserConfigQuery;
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
        $id = (int)$id;
        // We can't update unless values already exist.
        $userConfig = UserConfigQuery::create()
            ->filterById($id)
            ->filterByPeronId($iPersonID)
            ->findOne();

        if ($userConfig === null) {
            // Row does not exist — clone defaults for this user
            $defaultConfig = UserConfigQuery::create()
                ->filterById($id)
                ->filterByPeronId(0)
                ->findOne();

            if ($defaultConfig) {
                $userConfig = new UserConfig();
                $userConfig
                    ->setPeronId($iPersonID)
                    ->setId($id)
                    ->setName($defaultConfig->getName())
                    ->setValue($defaultConfig->getValue())
                    ->setType($defaultConfig->getType())
                    ->setCat($defaultConfig->getCat())
                    ->setTooltip($defaultConfig->getTooltip())
                    ->setPermission($defaultConfig->getPermission());
                $userConfig->save();
            } else {
                echo '<BR> Error: Software BUG 3216';
                exit;
            }
        }

        // Enforce permission: non-admin users cannot save admin-only settings
        if (!AuthenticationManager::getCurrentUser()->isAdmin() && $userConfig->getPermission() !== 'TRUE') {
            next($type);
            continue;
        }

        // Save new setting
        $userConfig->setValue($value);
        $userConfig->save();
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
$configs = UserConfigQuery::create()
    ->filterByPeronId($iPersonID)
    ->orderById()
    ->find();
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
          foreach ($configs as $config) {
              $ucfg_id = $config->getId();
              $ucfg_name = $config->getName();
              $ucfg_value = $config->getValue();
              $ucfg_type = $config->getType();
              $ucfg_tooltip = $config->getTooltip();
              $ucfg_permission = $config->getPermission();
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
          } // end foreach
          ?>
          </tbody>
        </table>
      </div>
      <div class="d-flex gap-2 mt-3">
        <input type="submit" class="btn btn-primary" name="save" value="<?= gettext('Save Settings') ?>">
        <a href="<?= SystemURLs::getRootPath() ?>/v2/user/<?= $iPersonID ?>" class="btn btn-secondary"><?= gettext('Cancel') ?></a>
      </div>
    </form>
  </div>
</div>
<?php
require_once __DIR__ . '/Include/Footer.php';
