<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\UserConfigQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\view\PageHeader;

// Security
AuthenticationManager::redirectHomeIfNotAdmin();

// Save Settings
if (isset($_POST['save'])) {
    $new_value = $_POST['new_value'];
    $new_permission = $_POST['new_permission'];
    $type = $_POST['type'];
    ksort($type);
    reset($type);
    while ($current_type = current($type)) {
        $id = key($type);
        // Filter Input
        if ($current_type == 'text' || $current_type == 'textarea') {
            $value = InputUtils::legacyFilterInput($new_value[$id]);
        } elseif ($current_type == 'number') {
            $value = InputUtils::legacyFilterInput($new_value[$id], 'float');
        } elseif ($current_type == 'date') {
            $value = InputUtils::legacyFilterInput($new_value[$id], 'date');
        } elseif ($current_type == 'boolean') {
            if ($new_value[$id] != '1') {
                $value = '';
            } else {
                $value = '1';
            }
        }

        if ($new_permission[$id] != 'TRUE') {
            $permission = 'FALSE';
        } else {
            $permission = 'TRUE';
        }

        // Save new setting
        $userConfig = UserConfigQuery::create()
            ->filterById((int)$id)
            ->filterByPeronId(0)
            ->findOne();
        if ($userConfig !== null) {
            $userConfig->setValue($value);
            $userConfig->setPermission($permission);
            $userConfig->save();
        }
        next($type);
    }
}

$sPageTitle = gettext('Default User Settings');
$sPageSubtitle = gettext('Set default preferences for new user accounts');
$aBreadcrumbs = PageHeader::breadcrumbs([
    [gettext('Admin'), '/admin/'],
    [gettext('Default User Settings')],
]);
require_once __DIR__ . '/Include/Header.php';

// Get settings
$configs = UserConfigQuery::create()
    ->filterByPeronId(0)
    ->orderById()
    ->find();
?>
<div class="card">
    <div class="card-body">
        <div class="alert alert-info"><?= gettext('Set Permission True to give new users the ability to change their current value.') ?></div>
        <form method="post" action="SettingsUser.php">
            <div class="table-responsive">
                <table class="table table-vcenter">
                    <thead>
                        <tr>
                            <th><?= gettext('Permission') ?></th>
                            <th><?= gettext('Variable name') ?></th>
                            <th><?= gettext('Current Value') ?></th>
                            <th><?= gettext('Notes') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    foreach ($configs as $config) {
                        $ucfg_id = $config->getId();
                        $ucfg_name = $config->getName();
                        $ucfg_value = $config->getValue();
                        $ucfg_type = $config->getType();
                        $ucfg_tooltip = $config->getTooltip();
                        $ucfg_permission = $config->getPermission();

                        $sel1 = $sel2 = '';
                        if ($ucfg_permission == 'TRUE') {
                            $sel2 = 'selected';
                        } else {
                            $sel1 = 'selected';
                        }
                        ?>
                        <tr>
                            <td>
                                <select name="new_permission[<?= $ucfg_id ?>]" class="form-select form-select-sm">
                                    <option value="FALSE" <?= $sel1 ?>><?= gettext('False') ?></option>
                                    <option value="TRUE" <?= $sel2 ?>><?= gettext('True') ?></option>
                                </select>
                            </td>
                            <td class="text-secondary"><?= InputUtils::escapeHTML($ucfg_name) ?></td>
                            <td>
                                <?php
                                if ($ucfg_type == 'text') {
                                    echo '<input type="text" class="form-control form-control-sm" maxlength="255" name="new_value[' . $ucfg_id . ']" value="' . InputUtils::escapeAttribute($ucfg_value) . '">';
                                } elseif ($ucfg_type == 'textarea') {
                                    echo '<textarea rows="3" class="form-control form-control-sm" name="new_value[' . $ucfg_id . ']">' . InputUtils::escapeHTML($ucfg_value) . '</textarea>';
                                } elseif ($ucfg_type == 'number' || $ucfg_type == 'date') {
                                    echo '<input type="text" class="form-control form-control-sm" maxlength="15" name="new_value[' . $ucfg_id . ']" value="' . InputUtils::escapeAttribute($ucfg_value) . '">';
                                } elseif ($ucfg_type == 'boolean') {
                                    if ($ucfg_value) {
                                        $bSel2 = 'selected';
                                        $bSel1 = '';
                                    } else {
                                        $bSel1 = 'selected';
                                        $bSel2 = '';
                                    }
                                    echo '<select name="new_value[' . $ucfg_id . ']" class="form-select form-select-sm">';
                                    echo '<option value="" ' . $bSel1 . '>' . gettext('False') . '</option>';
                                    echo '<option value="1" ' . $bSel2 . '>' . gettext('True') . '</option>';
                                    echo '</select>';
                                }
                                echo '<input type="hidden" name="type[' . $ucfg_id . ']" value="' . InputUtils::escapeAttribute($ucfg_type) . '">';
                                ?>
                            </td>
                            <td class="text-secondary small"><?= gettext($ucfg_tooltip) ?></td>
                        </tr>
                        <?php
                    }
                    ?>
                    </tbody>
                </table>
            </div>
            <div class="d-flex gap-2 mt-3">
                <input type="submit" class="btn btn-primary" name="save" value="<?= gettext('Save Settings') ?>">
                <a href="<?= SystemURLs::getRootPath() ?>/admin/system/users" class="btn btn-secondary"><?= gettext('Cancel') ?></a>
            </div>
        </form>
    </div>
</div>
<?php
require_once __DIR__ . '/Include/Footer.php';
