<?php

/*******************************************************************************
*
*  filename    : SettingsUser.php
*  website     : https://churchcrm.io
*  description : Default User Settings
*                   File copied from SettingsGeneral.php with minor edits.
*                   Controls default settings for new users.
*
*  Contributors:
*  2006 Ed Davis

******************************************************************************/

// Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

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
        $sSQL = 'UPDATE userconfig_ucfg '
        . "SET ucfg_value='$value', ucfg_permission='$permission' "
        . "WHERE ucfg_id='$id' AND ucfg_per_id='0' ";
        $rsUpdate = RunQuery($sSQL);
        next($type);
    }
}

// Set the page title and include HTML header
$sPageTitle = gettext('Default User Settings');
require 'Include/Header.php';

// Get settings
$sSQL = "SELECT * FROM userconfig_ucfg WHERE ucfg_per_id='0' ORDER BY ucfg_id";
$rsConfigs = RunQuery($sSQL);
?>
<!-- Default box -->
<div class="card">
    <div class="card-header with-border">

    <form method=post action=SettingsUser.php'>
        <div class="callout callout-info"> <?= gettext('Set Permission True to give new users the ability to change their current value.<BR>'); ?></div>
        <div class="table-responsive">
        <table class='table table-responsive'>
        <tr>
            <th> <?= gettext('Permission') ?></th>
            <th><?= gettext('Variable name') ?></th>
            <th><?= gettext('Current Value') ?></th>
            <th><?=gettext('Notes') ?></th>
        </tr>
<?php
$r = 1;
// List Individual Settings
while (list($ucfg_per_id, $ucfg_id, $ucfg_name, $ucfg_value, $ucfg_type, $ucfg_tooltip, $ucfg_permission) = mysqli_fetch_row($rsConfigs)) {
    // Cancel, Save Buttons every 13 rows
    if ($r == 13) {
        echo '<tr><td>&nbsp;</td>
			<td><input type=submit class=btn name=save value="'
            . gettext('Save Settings') . '">
			<input type=submit class=btn name=cancel value="'
            . gettext('Cancel') . '">
			</td></tr>';
        $r = 1;
    }

    // Default Permissions
    if ($ucfg_permission == 'TRUE') {
        $sel2 = 'SELECTED ';
        $sel1 = '';
    } else {
        $sel1 = 'SELECTED ';
        $sel2 = '';
    }
    echo "<tr><td class=\"TextColumnWithBottomBorder\"><select name=\"new_permission[$ucfg_id]\">";
    echo "<option value=\"FALSE\" $sel1>" . gettext('False');
    echo "<option value=\"TRUE\" $sel2>" . gettext('True') . '
                        </select></td>';

    // Variable Name & Type
    echo "<td class=\"LabelColumn\">$ucfg_name</td>";

    // Current Value
    if ($ucfg_type == 'text') {
        echo "<td class=\"TextColumnWithBottomBorder\">
            <input type=text size=\"30\" maxlength=\"255\" name=\"new_value[$ucfg_id]\"
            value=\"" . htmlspecialchars($ucfg_value, ENT_QUOTES) . '"></td>';
    } elseif ($ucfg_type == 'textarea') {
        echo "<td class=\"TextColumnWithBottomBorder\">
			<textarea rows=\"4\" cols=\"30\" name=\"new_value[$ucfg_id]\">"
            . htmlspecialchars($ucfg_value, ENT_QUOTES) . '</textarea></td>';
    } elseif ($ucfg_type == 'number' || $ucfg_type == 'date') {
        echo "<td class=\"TextColumnWithBottomBorder\">
            <input type=text size=\"15\" maxlength=\"15\" name=\"new_value[$ucfg_id]\"
            value=\"$ucfg_value\"></td>";
    } elseif ($ucfg_type == 'boolean') {
        if ($ucfg_value) {
            $sel2 = 'SELECTED ';
            $sel1 = '';
        } else {
            $sel1 = 'SELECTED ';
            $sel2 = '';
        }
        echo "<td class=\"TextColumnWithBottomBorder\">
                <select name=\"new_value[$ucfg_id]\">
                <option value=\"\" $sel1>" . gettext('False') . "
                <option value=\"1\" $sel2>" . gettext('True') . '
                </select></td>';
    }

    // Notes
    echo "<td><input type=hidden name=\"type[$ucfg_id]\" value=\"$ucfg_type\">
        " . gettext($ucfg_tooltip) . '</td></tr>';

    $r++;
}

?>
<tr>
    <td colspan='3' class='text-center'>
        <input type=submit class='btn btn-primary' name=save value="<?=  gettext('Save Settings') ?> ">
        <input type=submit class=btn name=cancel value="<?= gettext('Cancel') ?>" onclick="javascript:document.location = 'v2/dashboard';">
    </td>
</tr>
</table>
        </div>
</form>

        </div>
</div>

<?php
require 'Include/Footer.php';
?>
