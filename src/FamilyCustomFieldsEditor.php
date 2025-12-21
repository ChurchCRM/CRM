<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\FamilyCustomMasterQuery;
use ChurchCRM\model\ChurchCRM\ListOption;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

// Security: user must be administrator to use this page
AuthenticationManager::redirectHomeIfNotAdmin();

$sPageTitle = gettext('Custom Family Fields Editor');

require_once __DIR__ . '/Include/Header.php'; ?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="fa fa-cogs"></i> <?= $sPageTitle ?>
                    </h4>
                </div>
                <div class="card-body">

<?php

$bNewNameError = false;
$bDuplicateNameError = false;
$bErrorFlag = false;
$aNameErrors = [];

// Does the user want to save changes to text fields?
if (isset($_POST['SaveChanges'])) {
    // Fill in the other needed custom field data arrays not gathered from the form submit
    $sSQL = 'SELECT * FROM family_custom_master ORDER BY fam_custom_Order';
    $rsCustomFields = RunQuery($sSQL);
    $numRows = mysqli_num_rows($rsCustomFields);

    for ($row = 1; $row <= $numRows; $row++) {
        $aRow = mysqli_fetch_array($rsCustomFields, MYSQLI_BOTH);
        extract($aRow);

        $aFieldFields[$row] = $fam_custom_Field;
        $aTypeFields[$row] = $type_ID;
        if (isset($fam_custom_Special)) {
            $aSpecialFields[$row] = $fam_custom_Special;
        } else {
            $aSpecialFields[$row] = 'NULL';
        }
    }

    for ($iFieldID = 1; $iFieldID <= $numRows; $iFieldID++) {
        $aNameFields[$iFieldID] = InputUtils::legacyFilterInput($_POST[$iFieldID . 'name']);

        if (strlen($aNameFields[$iFieldID]) === 0) {
            $aNameErrors[$iFieldID] = true;
            $bErrorFlag = true;
        } else {
            $aNameErrors[$iFieldID] = false;
        }

        $aFieldSecurity[$iFieldID] = InputUtils::legacyFilterInput($_POST[$iFieldID . 'FieldSec'], 'int');

        if (isset($_POST[$iFieldID . 'special'])) {
            $aSpecialFields[$iFieldID] = InputUtils::legacyFilterInput($_POST[$iFieldID . 'special'], 'int');

            if ($aSpecialFields[$iFieldID] == 0) {
                $aSpecialErrors[$iFieldID] = true;
                $bErrorFlag = true;
            } else {
                $aSpecialErrors[$iFieldID] = false;
            }
        }
    }

    // If no errors, then update.
    if (!$bErrorFlag) {
        for ($iFieldID = 1; $iFieldID <= $numRows; $iFieldID++) {
            // Update the type's name if it has changed from what was previously stored
            if ($aOldNameFields[$iFieldID] != $aNameFields[$iFieldID] || $aFieldSecurity[$iFieldID] != $aOldSecFields[$iFieldID]) {
                $sSQL = "UPDATE `family_custom_master`
                    SET `fam_custom_Name` = '" . $aNameFields[$iFieldID] . "',
                    `fam_custom_FieldSec` = '" . $aFieldSecurity[$iFieldID] . "'";

                if (isset($aSpecialFields[$iFieldID])) {
                    $sSQL .= ", `fam_custom_Special` = '" . $aSpecialFields[$iFieldID] . "'";
                }

                $sSQL .= " WHERE `fam_custom_Field` = '" . $aFieldFields[$iFieldID] . "'";
                RunQuery($sSQL);
            }
        }
    }
} else {
    // Check if we're adding a field
    if (isset($_POST['AddField'])) {
        $newFieldType = InputUtils::legacyFilterInput($_POST['newFieldType'], 'int');
        $newFieldName = InputUtils::legacyFilterInput($_POST['newFieldName']);
        $newFieldSec = InputUtils::legacyFilterInput($_POST['newFieldSec'], 'int');

        if (strlen($newFieldName) === 0) {
            $bNewNameError = true;
        } elseif (strlen($newFieldType) === 0 || $newFieldType < 1) {
            // This should never happen, but check anyhow.
            // $bNewTypeError = true;
        } else {
            $sSQL = 'SELECT fam_custom_Name FROM family_custom_master';
            $rsCustomNames = RunQuery($sSQL);
            while ($aRow = mysqli_fetch_array($rsCustomNames)) {
                if ($aRow[0] == $newFieldName) {
                    $bDuplicateNameError = true;
                    break;
                }
            }

            if (!$bDuplicateNameError) {
                global $cnInfoCentral;
                // Find the highest existing field number in the table to
                // determine the next free one.
                // This is essentially an auto-incrementing system where
                // deleted numbers are not re-used.
                $fields = mysqli_query($cnInfoCentral, 'SHOW COLUMNS FROM family_custom');
                $last = mysqli_num_rows($fields) - 1;
                // Set the new field number based on the highest existing.
                // Chop off the "c" at the beginning of the old one's name.
                // The "c#" naming scheme is necessary because MySQL 3.23
                // doesn't allow numeric-only field (table column) names.
                $fields = mysqli_query($cnInfoCentral, 'SELECT * FROM family_custom');
                $fieldInfo = mysqli_fetch_field_direct($fields, $last);
                $newFieldNum = (int) mb_substr($fieldInfo->name, 1) + 1;

                // If we're inserting a new custom-list type field,
                // create a new list and get its ID
                if ($newFieldType == 12) {
                    // Get the first available lst_ID for insertion.
                    // lst_ID 0-9 are reserved for permanent lists.
                    $sSQL = 'SELECT MAX(lst_ID) FROM list_lst';
                    $aTemp = mysqli_fetch_array(RunQuery($sSQL));
                    if ($aTemp[0] > 9) {
                        $newListID = $aTemp[0] + 1;
                    } else {
                        $newListID = 10;
                    }

                    // Insert into the lists table with an example option.
                    $listOption = new ListOption();
                    $listOption
                        ->setId($newListID)
                        ->setOptionId(1)
                        ->setOptionSequence(1)
                        ->setOptionName(gettext('Default Option'));
                    $listOption->save();

                    $newSpecial = "'$newListID'";
                } else {
                    $newSpecial = 'NULL';
                }

                // Insert into the master table
                $newOrderID = $last + 1;
                $sSQL = "INSERT INTO `family_custom_master`
                        (`fam_custom_Order` , `fam_custom_Field` , `fam_custom_Name` ,  `fam_custom_Special` , `fam_custom_FieldSec` , `type_ID`)
                        VALUES ('" . $newOrderID . "', 'c" . $newFieldNum . "', '" . $newFieldName . "', " . $newSpecial . ", '" . $newFieldSec . "', '" . $newFieldType . "');";
                RunQuery($sSQL);

                // Insert into the custom fields table
                $sSQL = 'ALTER TABLE `family_custom` ADD `c' . $newFieldNum . '` ';

                switch ($newFieldType) {
                    case 1:
                        $sSQL .= "ENUM('false', 'true')";
                        break;
                    case 2:
                        $sSQL .= 'DATE';
                        break;
                    case 3:
                        $sSQL .= 'VARCHAR(50)';
                        break;
                    case 4:
                        $sSQL .= 'VARCHAR(100)';
                        break;
                    case 5:
                        $sSQL .= 'TEXT';
                        break;
                    case 6:
                        $sSQL .= 'YEAR';
                        break;
                    case 7:
                        $sSQL .= "ENUM('winter', 'spring', 'summer', 'fall')";
                        break;
                    case 8:
                        $sSQL .= 'INT';
                        break;
                    case 9:
                        $sSQL .= 'MEDIUMINT(9)';
                        break;
                    case 10:
                        $sSQL .= 'DECIMAL(10,2)';
                        break;
                    case 11:
                        $sSQL .= 'VARCHAR(30)';
                        break;
                    case 12:
                        $sSQL .= 'TINYINT(4)';
                }

                $sSQL .= ' DEFAULT NULL ;';
                RunQuery($sSQL);

                $bNewNameError = false;
            }
        }
    }

    // Get data for the form as it now exists..
    $sSQL = 'SELECT * FROM family_custom_master ORDER BY fam_custom_Order';

    $rsCustomFields = RunQuery($sSQL);
    $numRows = mysqli_num_rows($rsCustomFields);

    // Create arrays of the fields.
    for ($row = 1; $row <= $numRows; $row++) {
        $aRow = mysqli_fetch_array($rsCustomFields, MYSQLI_BOTH);
        extract($aRow);

        $aNameFields[$row] = $fam_custom_Name;
        $aSpecialFields[$row] = $fam_custom_Special;
        $aFieldFields[$row] = $fam_custom_Field;
        $aTypeFields[$row] = $type_ID;
        $aFieldSecurity[$row] = $fam_custom_FieldSec;
        $aNameErrors[$row] = false;
    }
}

// Prepare Security Group list
    $sSQL = 'SELECT * FROM list_lst WHERE lst_ID = 5 ORDER BY lst_OptionSequence';
    $rsSecurityGrp = RunQuery($sSQL);

    $aSecurityGrp = [];
while ($aRow = mysqli_fetch_array($rsSecurityGrp)) {
    $aSecurityGrp[] = $aRow;
    extract($aRow);
    $aSecurityType[$lst_OptionID] = $lst_OptionName;
}

function GetSecurityList($aSecGrp, $fld_name, $currOpt = 'bAll')
{
    $sOptList = '<select name="' . $fld_name . '">';
    $grp_Count = count($aSecGrp);

    for ($i = 0; $i < $grp_Count; $i++) {
        $aAryRow = $aSecGrp[$i];
        $sOptList .= '<option value="' . $aAryRow['lst_OptionID'] . '"';
        if ($aAryRow['lst_OptionName'] == $currOpt) {
            $sOptList .= ' selected';
        }
        $sOptList .= '>' . $aAryRow['lst_OptionName'] . "</option>\n";
    }
    $sOptList .= '</select>';

    return $sOptList;
}

// Construct the form
?>

<script nonce="<?= SystemURLs::getCSPNonce() ?>" >
function confirmDeleteField( Field ) {
    var answer = confirm (<?= "'" . gettext('Warning:  By deleting this field, you will irrevokably lose all family data assigned for this field!') . "'" ?>)
    if ( answer )
    {
        window.location="FamilyCustomFieldsRowOps.php?Field=" + Field +"&Action=delete";
        return true;
    }
    event.preventDefault ? event.preventDefault() : event.returnValue = false;
    return false;
}
</script>
<div class="alert alert-warning mb-4">
        <i class="fa fa-exclamation-triangle"></i>
        <?= gettext("Warning: Arrow and delete buttons take effect immediately. Field name changes will be lost if you do not 'Save Changes' before using an up, down, delete or 'add new' button!") ?>
</div>
<form method="post" action="FamilyCustomFieldsEditor.php" name="FamilyCustomFieldsEditor">
    <div class="table-responsive mb-4">
<table class="table table-hover">

<?php
if ($numRows == 0) {
    ?>
    <div class="alert alert-info text-center">
        <i class="fa fa-info-circle"></i>
        <h5><?= gettext('No custom Family fields have been added yet') ?></h5>
    </div>
    <?php
} else {
    ?>
    <?php
    if ($bErrorFlag) {
        echo '<div class="alert alert-danger mb-4"><i class="fa fa-times-circle"></i> ' . gettext('Invalid fields or selections. Changes not saved! Please correct and try again!') . '</div>';
    } ?>
    <thead class="table-light">
        <tr>
            <th><?= gettext('Type') ?></th>
            <th><?= gettext('Name') ?></th>
            <th><?= gettext('Special Option') ?></th>
            <th><?= gettext('Security Option') ?></th>
            <th class="text-center" style="width: 120px;"><?= gettext('Actions') ?></th>
        </tr>
    </thead>
    <tbody>
    <?php

    for ($row = 1; $row <= $numRows; $row++) {
        ?>
        <tr>
            <td>
                <?= htmlspecialchars($aPropTypes[$aTypeFields[$row]], ENT_QUOTES, 'UTF-8') ?>
            </td>
            <td>
                <input class="form-control form-control-sm" type="text" name="<?= $row . 'name' ?>" value="<?= htmlspecialchars(stripslashes($aNameFields[$row]), ENT_QUOTES, 'UTF-8') ?>" maxlength="40">
                <?php
                if ($aNameErrors[$row]) {
                    echo '<div class="text-danger small mt-1"><i class="fa fa-times-circle"></i> ' . gettext('You must enter a name') . '</div>';
                } ?>
            </td>
            <td>
            <?php
            if ($aTypeFields[$row] == 9) {
                echo '<select class="form-select form-select-sm" name="' . $row . 'special">';
                echo '<option value="0">Select a group</option>';

                $sSQL = 'SELECT grp_ID,grp_Name FROM group_grp ORDER BY grp_Name';
                $rsGroupList = RunQuery($sSQL);

                while ($aRow = mysqli_fetch_array($rsGroupList)) {
                    extract($aRow);

                    echo '<option value="' . $grp_ID . '"';
                    if ($aSpecialFields[$row] == $grp_ID) {
                        echo ' selected';
                    }
                    echo '>' . htmlspecialchars($grp_Name, ENT_QUOTES, 'UTF-8');
                }

                echo '</select>';
                if (isset($aSpecialErrors[$row]) && $aSpecialErrors[$row]) {
                    echo '<div class="text-danger small mt-1"><i class="fa fa-times-circle"></i> ' . gettext('You must select a group.') . '</div>';
                }
            } elseif ($aTypeFields[$row] == 12) {
                // TLH 6-23-07 Added scrollbars to the popup so long lists can be edited.
                echo '<a href="javascript:void(0)" class="btn btn-sm btn-outline-secondary" onclick="Newwin=window.open(\'OptionManager.php?mode=famcustom&ListID=' . $aSpecialFields[$row] . '\',\'Newwin\',\'toolbar=no,status=no,width=400,height=500,scrollbars=1\')">' . gettext('Edit List Options') . '</a>';
            } else {
                echo '&ndash;';
            } ?>
            </td>
            <td>
                <select class="form-select form-select-sm" name="<?= $row . 'FieldSec' ?>">
                    <?php
                    foreach ($aSecurityGrp as $secGroup) {
                        $selected = ($aFieldSecurity[$row] == $secGroup['lst_OptionID']) ? ' selected' : '';
                        echo '<option value="' . (int)$secGroup['lst_OptionID'] . '"' . $selected . '>' . htmlspecialchars($secGroup['lst_OptionName'], ENT_QUOTES, 'UTF-8') . '</option>';
                    }
                    ?>
                </select>
            </td>
            <td class="text-center">
                <div class="btn-group btn-group-sm" role="group">
                <?php
                    if ($row > 1) {
                        echo '<a href="FamilyCustomFieldsRowOps.php?OrderID=' . $row . '&Field=' . urlencode($aFieldFields[$row]) . '&Action=up" class="btn btn-outline-secondary" title="' . gettext('Move Up') . '"><i class="fa fa-arrow-up"></i></a>';
                    }
                    if ($row < $numRows) {
                        echo '<a href="FamilyCustomFieldsRowOps.php?OrderID=' . $row . '&Field=' . urlencode($aFieldFields[$row]) . '&Action=down" class="btn btn-outline-secondary" title="' . gettext('Move Down') . '"><i class="fa fa-arrow-down"></i></a>';
                    }
                    ?>
                    <button type="button" class="btn btn-outline-danger" onclick="return confirmDeleteField('<?= htmlspecialchars($aFieldFields[$row], ENT_QUOTES, 'UTF-8') ?>');" title="<?= gettext('Delete') ?>">
                        <i class="fa fa-trash"></i>
                    </button>
                </div>
            </td>

        </tr>
        <?php
    } ?>
                    </tbody>
                </table>
            </div>

            <div class="d-flex gap-2 mb-4">
                <button type="submit" class="btn btn-primary" name="SaveChanges">
                    <i class="fa fa-save"></i> <?= gettext('Save Changes') ?>
                </button>
            </div>

        <?php
} ?>

        <hr class="my-4">

        <div class="card bg-light">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    <i class="fa fa-plus"></i> <?= gettext('Add New Field') ?>
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="newFieldType" class="form-label"><?= gettext('Type') ?><span class="text-danger">*</span></label>
                        <select class="form-select" id="newFieldType" name="newFieldType">
                            <option value=""><?= gettext('Select Type') ?></option>
                            <?php
                            for ($iOptionID = 1; $iOptionID <= count($aPropTypes); $iOptionID++) {
                                echo '<option value="' . $iOptionID . '">' . htmlspecialchars($aPropTypes[$iOptionID], ENT_QUOTES, 'UTF-8') . '</option>';
                            }
                            ?>
                        </select>
                        <small class="d-block mt-2">
                            <a href="<?= SystemURLs::getSupportURL() ?>" target="_blank"><?= gettext('Help on types...') ?></a>
                        </small>
                    </div>

                    <div class="col-md-3">
                        <label for="newFieldName" class="form-label"><?= gettext('Name') ?><span class="text-danger">*</span></label>
                        <input class="form-control" type="text" id="newFieldName" name="newFieldName" maxlength="40" placeholder="<?= gettext('Field name') ?>" required>
                        <?php
                        if ($bNewNameError) {
                            echo '<div class="text-danger small mt-1"><i class="fa fa-times-circle"></i> ' . gettext('You must enter a name') . '</div>';
                        }
                        if ($bDuplicateNameError) {
                            echo '<div class="text-danger small mt-1"><i class="fa fa-times-circle"></i> ' . gettext('That field name already exists.') . '</div>';
                        }
                        ?>
                    </div>

                    <div class="col-md-3">
                        <label for="newFieldSec" class="form-label"><?= gettext('Security Option') ?><span class="text-danger">*</span></label>
                        <select class="form-select" id="newFieldSec" name="newFieldSec" required>
                            <option value=""><?= gettext('Select Security') ?></option>
                            <?php
                            foreach ($aSecurityGrp as $secGroup) {
                                echo '<option value="' . (int)$secGroup['lst_OptionID'] . '">' . htmlspecialchars($secGroup['lst_OptionName'], ENT_QUOTES, 'UTF-8') . '</option>';
                            }
                            ?>
                        </select>
                    </div>

                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-success w-100" name="AddField">
                            <i class="fa fa-plus"></i> <?= gettext('Add New Field') ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
require_once __DIR__ . '/Include/Footer.php';
