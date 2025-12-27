<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\ListOption;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

// Security: user must be allowed to edit records to use this page.
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isManageGroupsEnabled(), 'ManageGroups');

// Get the Group from the querystring
$iGroupID = InputUtils::legacyFilterInput($_GET['GroupID'], 'int');

// Get the group information
$sSQL = 'SELECT * FROM group_grp WHERE grp_ID = ' . $iGroupID;
$rsGroupInfo = RunQuery($sSQL);
extract(mysqli_fetch_array($rsGroupInfo));

// Abort if user tries to load with group having no special properties.
if ($grp_hasSpecialProps == false) {
    RedirectUtils::redirect('GroupView.php?GroupID=' . $iGroupID);
}

$sPageTitle = gettext('Group-Specific Properties Form Editor') . ':' . '  ' . $grp_Name;

require_once __DIR__ . '/Include/Header.php'; ?>

<div class="card card-body">
    <script nonce="<?= SystemURLs::getCSPNonce() ?>">
        function confirmDeleteField(fieldName, propId, fieldId) {
            var msg = <?= json_encode(gettext('Are you sure you want to delete')) ?> + ' "' + fieldName + '"?';
            msg += '<br><br><strong>' + <?= json_encode(gettext('Warning:')) ?> + '</strong> ';
            msg += <?= json_encode(gettext('By deleting this field, you will irrevocably lose all group member data assigned for this field!')) ?>;
            bootbox.confirm({
                title: <?= json_encode(gettext('Delete Confirmation')) ?>,
                message: msg,
                buttons: {
                    cancel: { label: <?= json_encode(gettext('Cancel')) ?>, className: 'btn-secondary' },
                    confirm: { label: <?= json_encode(gettext('Delete')) ?>, className: 'btn-danger' }
                },
                callback: function(result) {
                    if (result) {
                        window.location.href = 'GroupPropsFormRowOps.php?GroupID=<?= $iGroupID ?>&PropID=' + propId + '&Field=' + fieldId + '&Action=delete';
                    }
                }
            });
            return false;
        }
    </script>

    <?php
    $bErrorFlag = false;
    $aNameErrors = [];
    $bNewNameError = false;
    $bDuplicateNameError = false;

    // Does the user want to save changes to text fields?
    if (isset($_POST['SaveChanges'])) {
        // Fill in the other needed property data arrays not gathered from the form submit
        $sSQL = 'SELECT prop_ID, prop_Field, type_ID, prop_Special, prop_PersonDisplay FROM groupprop_master WHERE grp_ID = ' . $iGroupID . ' ORDER BY prop_ID';
        $rsPropList = RunQuery($sSQL);
        $numRows = mysqli_num_rows($rsPropList);

        for ($row = 1; $row <= $numRows; $row++) {
            $aRow = mysqli_fetch_array($rsPropList, MYSQLI_BOTH);
            extract($aRow);

            $aFieldFields[$row] = $prop_Field;
            $aTypeFields[$row] = $type_ID;
            $aSpecialFields[$row] = $prop_Special;
            if (isset($prop_Special)) {
                $aSpecialFields[$row] = $prop_Special;
            } else {
                $aSpecialFields[$row] = 'NULL';
            }
        }

        for ($iPropID = 1; $iPropID <= $numRows; $iPropID++) {
            $aNameFields[$iPropID] = InputUtils::legacyFilterInput($_POST[$iPropID . 'name']);

            if (strlen($aNameFields[$iPropID]) === 0) {
                $aNameErrors[$iPropID] = true;
                $bErrorFlag = true;
            } else {
                $aNameErrors[$iPropID] = false;
            }

            $aDescFields[$iPropID] = InputUtils::legacyFilterInput($_POST[$iPropID . 'desc']);

            if (isset($_POST[$iPropID . 'special'])) {
                $aSpecialFields[$iPropID] = InputUtils::legacyFilterInput($_POST[$iPropID . 'special'], 'int');

                if ($aSpecialFields[$iPropID] == 0) {
                    $aSpecialErrors[$iPropID] = true;
                    $bErrorFlag = true;
                } else {
                    $aSpecialErrors[$iPropID] = false;
                }
            }

            if (isset($_POST[$iPropID . 'show'])) {
                $aPersonDisplayFields[$iPropID] = true;
            } else {
                $aPersonDisplayFields[$iPropID] = false;
            }
        }

        // If no errors, then update.
        if (!$bErrorFlag) {
            for ($iPropID = 1; $iPropID <= $numRows; $iPropID++) {
                if ($aPersonDisplayFields[$iPropID]) {
                    $temp = 'true';
                } else {
                    $temp = 'false';
                }

                $sSQL = "UPDATE groupprop_master
                    SET `prop_Name` = '" . $aNameFields[$iPropID] . "',
                        `prop_Description` = '" . $aDescFields[$iPropID] . "',
                        `prop_Special` = " . $aSpecialFields[$iPropID] . ",
                        `prop_PersonDisplay` = '" . $temp . "'
                    WHERE `grp_ID` = '" . $iGroupID . "' AND `prop_ID` = '" . $iPropID . "';";

                RunQuery($sSQL);
            }
        }
    } else {
        // Check if we're adding a field
        if (isset($_POST['AddField'])) {
            $newFieldType = InputUtils::legacyFilterInput($_POST['newFieldType'], 'int');
            $newFieldName = InputUtils::legacyFilterInput($_POST['newFieldName']);
            $newFieldDesc = InputUtils::legacyFilterInput($_POST['newFieldDesc']);

            if (strlen($newFieldName) === 0) {
                $bNewNameError = true;
            } else {
                $sSQL = 'SELECT prop_Name FROM groupprop_master WHERE grp_ID = ' . $iGroupID;
                $rsPropNames = RunQuery($sSQL);
                while ($aRow = mysqli_fetch_array($rsPropNames)) {
                    if ($aRow[0] == $newFieldName) {
                        $bDuplicateNameError = true;
                        break;
                    }
                }

                if (!$bDuplicateNameError) {
                    // Get the new prop_ID (highest existing plus one)
                    $sSQL = 'SELECT prop_ID    FROM groupprop_master WHERE grp_ID = ' . $iGroupID;
                    $rsPropList = RunQuery($sSQL);
                    $newRowNum = mysqli_num_rows($rsPropList) + 1;

                    // Find the highest existing field number in the group's table to determine the next free one.
                    // This is essentially an auto-incrementing system where deleted numbers are not re-used.
                    $tableName = 'groupprop_' . $iGroupID;

                    $fields = mysqli_query($cnInfoCentral, 'SELECT * FROM ' . $tableName);
                    $newFieldNum = mysqli_num_fields($fields);

                    // If we're inserting a new custom-list type field, create a new list and get its ID
                    if ($newFieldType == 12) {
                        // Get the first available lst_ID for insertion.  lst_ID 0-9 are reserved for permanent lists.
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
                    $sSQL = "INSERT INTO `groupprop_master`
                            ( `grp_ID` , `prop_ID` , `prop_Field` , `prop_Name` , `prop_Description` , `type_ID` , `prop_Special` )
                            VALUES ('" . $iGroupID . "', '" . $newRowNum . "', 'c" . $newFieldNum . "', '" . $newFieldName . "', '" . $newFieldDesc . "', '" . $newFieldType . "', $newSpecial);";
                    RunQuery($sSQL);

                    // Insert into the group-specific properties table
                    $sSQL = 'ALTER TABLE `groupprop_' . $iGroupID . '` ADD `c' . $newFieldNum . '` ';

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
        $sSQL = 'SELECT * FROM groupprop_master WHERE grp_ID = ' . $iGroupID . ' ORDER BY prop_ID';

        $rsPropList = RunQuery($sSQL);
        $numRows = mysqli_num_rows($rsPropList);

        // Create arrays of the properties.
        for ($row = 1; $row <= $numRows; $row++) {
            $aRow = mysqli_fetch_array($rsPropList, MYSQLI_BOTH);
            extract($aRow);

            // This is probably more clear than using a multi-dimensional array
            $aNameFields[$row] = $prop_Name;
            $aDescFields[$row] = $prop_Description;
            $aSpecialFields[$row] = $prop_Special;
            $aFieldFields[$row] = $prop_Field;
            $aTypeFields[$row] = $type_ID;
            $aPersonDisplayFields[$row] = ($prop_PersonDisplay == 'true');
        }
    }

    // Construct the form
    ?>

    <form method="post" action="GroupPropsFormEditor.php?GroupID=<?= $iGroupID ?>" name="GroupPropFormEditor">
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    <i class="fa-solid fa-plus"></i>
                    <?= gettext('Add New') . ' ' . gettext('Field') ?>
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <label for="newFieldType" class="form-label"><?= gettext('Type') ?>:</label>
                        <select id="newFieldType" name="newFieldType" class="form-control">
                            <?php
                            for ($iOptionID = 1; $iOptionID <= count($aPropTypes); $iOptionID++) {
                                echo '<option value="' . InputUtils::escapeAttribute($iOptionID) . '">' . InputUtils::escapeHTML($aPropTypes[$iOptionID]) . '</option>';
                            }
                            ?>
                        </select>
                        <small class="form-text text-muted">
                            <a href="<?= SystemURLs::getSupportURL() ?>" target="_blank"><?= gettext('Help on types..') ?></a>
                        </small>
                    </div>
                    <div class="col-md-4">
                        <label for="newFieldName" class="form-label"><?= gettext('Name') ?>:</label>
                        <input type="text" id="newFieldName" class="form-control" name="newFieldName" maxlength="40">
                        <?php
                        if ($bNewNameError) {
                            echo '<small class="text-danger d-block mt-1">' . gettext('You must enter a name') . '</small>';
                        }
                        if ($bDuplicateNameError) {
                            echo '<small class="text-danger d-block mt-1">' . gettext('That field name already exists.') . '</small>';
                        }
                        ?>
                    </div>
                    <div class="col-md-4">
                        <label for="newFieldDesc" class="form-label"><?= gettext('Description') ?>:</label>
                        <input type="text" id="newFieldDesc" class="form-control" name="newFieldDesc" maxlength="60">
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="submit" class="btn btn-success btn-block" name="AddField">
                            <i class="fa-solid fa-plus"></i>
                            <?= gettext('Add') ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <?php
        if ($numRows == 0) {
        ?>
            <div class="alert alert-info" role="alert">
                <i class="fa-solid fa-info-circle"></i>
                <?= gettext('No properties have been added yet') ?>
            </div>
        <?php
        } else {
        ?>
            <div class="alert alert-warning" role="alert">
                <i class="fa-solid fa-exclamation-triangle"></i>
                <strong><?= gettext('Warning:') ?></strong>
                <?= gettext("Arrow and delete buttons take effect immediately. Field name changes will be lost if you do not 'Save Changes' before using an up, down, delete or 'add new' button!") ?>
            </div>
            <?php
            if ($bErrorFlag) {
            ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <strong><?= gettext('Invalid fields or selections.') ?></strong>
                    <?= gettext('Changes not saved! Please correct and try again!') ?>
                </div>
            <?php
            } ?>
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fa-solid fa-list"></i>
                        <?= gettext('Existing Group Properties') ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th><?= gettext('Type') ?></th>
                                    <th><?= gettext('Name') ?></th>
                                    <th><?= gettext('Description') ?></th>
                                    <th><?= gettext('Special option') ?></th>
                                    <th class="text-center"><?= gettext('Show in') ?><br><?= gettext('Person View') ?></th>
                                    <th><?= gettext('Actions') ?></th>
                                </tr>
                            </thead>
                            <tbody>

                    <?php

                    for ($row = 1; $row <= $numRows; $row++) {
                        ?>
                        <tr>
                            <td>
                                <span class="badge badge-primary"><?= InputUtils::escapeHTML($aPropTypes[$aTypeFields[$row]]) ?></span>
                            </td>
                            <td>
                                <input type="text" name="<?= $row ?>name" value="<?= InputUtils::escapeAttribute($aNameFields[$row]) ?>" class="form-control form-control-sm" maxlength="40">
                                <?php
                                if (array_key_exists($row, $aNameErrors) && $aNameErrors[$row]) {
                                    echo '<small class="text-danger d-block mt-1">' . gettext('You must enter a name') . '</small>';
                                } ?>
                            </td>
                            <td>
                                <textarea name="<?= $row ?>desc" class="form-control form-control-sm" rows="1" maxlength="60"><?= InputUtils::escapeHTML($aDescFields[$row]) ?></textarea>
                            </td>
                            <td>
                                <?php

                                if ($aTypeFields[$row] == 9) {
                                    echo '<select name="' . $row . 'special" class="form-control form-control-sm">';
                                    echo '<option value="0" selected>' . gettext('Select a group') . '</option>';

                                    $sSQL = 'SELECT grp_ID,grp_Name FROM group_grp ORDER BY grp_Name';

                                    $rsGroupList = RunQuery($sSQL);

                                    while ($aRow = mysqli_fetch_array($rsGroupList)) {
                                        extract($aRow);

                                        echo '<option value="' . htmlspecialchars($grp_ID, ENT_QUOTES, 'UTF-8') . '"';
                                        if ($aSpecialFields[$row] == $grp_ID) {
                                            echo ' selected';
                                        }
                                        echo '>' . htmlspecialchars($grp_Name, ENT_QUOTES, 'UTF-8') . '</option>';
                                    }

                                    echo '</select>';

                                    if ($aSpecialErrors[$row]) {
                                        echo '<small class="text-danger d-block mt-1">' . gettext('You must select a group.') . '</small>';
                                    }
                                } elseif ($aTypeFields[$row] == 12) {
                                    echo '<a href="javascript:void(0)" onclick="window.open(\'OptionManager.php?mode=groupcustom&ListID=' . htmlspecialchars($aSpecialFields[$row], ENT_QUOTES, 'UTF-8') . '\',\'ListOptions\',\'toolbar=no,status=no,width=400,height=500\')">' . gettext('Edit List Options') . '</a>';
                                } else {
                                    echo '&nbsp;';
                                } ?>
                            </td>
                            <td class="text-center">
                                <input type="checkbox" name="<?= $row ?>show" value="1" <?php if ($aPersonDisplayFields[$row]) {
                                    echo ' checked';
                                } ?>>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <?php
                                    $fieldNameJs = htmlspecialchars(json_encode($aNameFields[$row]), ENT_QUOTES, 'UTF-8');
                                    $fieldIdJs = htmlspecialchars(json_encode($aFieldFields[$row]), ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="confirmDeleteField(<?= $fieldNameJs ?>, <?= $row ?>, <?= $fieldIdJs ?>)">
                                        <i class="fa-solid fa-trash"></i>
                                        <?= gettext('Delete') ?>
                                    </button>
                                    <?php
                                    if ($row != 1) {
                                        echo '<a href="GroupPropsFormRowOps.php?GroupID=' . $iGroupID . '&PropID=' . $row . '&Field=' . htmlspecialchars($aFieldFields[$row], ENT_QUOTES, 'UTF-8') . '&Action=up" class="btn btn-outline-secondary" title="' . gettext('Move up') . '"><i class="fa-solid fa-arrow-up"></i></a>';
                                    }
                                    if ($row < $numRows) {
                                        echo '<a href="GroupPropsFormRowOps.php?GroupID=' . $iGroupID . '&PropID=' . $row . '&Field=' . htmlspecialchars($aFieldFields[$row], ENT_QUOTES, 'UTF-8') . '&Action=down" class="btn btn-outline-secondary" title="' . gettext('Move down') . '"><i class="fa-solid fa-arrow-down"></i></a>';
                                    } ?>
                                </div>
                            </td>
                        </tr>
                        <?php
                    } ?>

                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-center my-3">
                <button type="submit" class="btn btn-primary" name="SaveChanges">
                    <i class="fa-solid fa-save"></i>
                    <?= gettext('Save Changes') ?>
                </button>
            </div>
        <?php
        } ?>
    </form>
</div>
<?php
require_once __DIR__ . '/Include/Footer.php';
