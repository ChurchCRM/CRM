<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\ListOption;
use ChurchCRM\model\ChurchCRM\PersonCustomMasterQuery;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

// Security: user must be administrator to use this page
AuthenticationManager::redirectHomeIfNotAdmin();

$sPageTitle = gettext('Custom Person Fields Editor');

require_once __DIR__ . '/Include/Header.php'; ?>

<div class="card card-body">
    <?php

    $bErrorFlag = false;
    $bNewNameError = false;
    $bDuplicateNameError = false;
    $aNameErrors = [];

    // Does the user want to save changes to text fields?
    if (isset($_POST['SaveChanges'])) {
        // Fill in the other needed custom field data arrays not gathered from the form submit
        $sSQL = 'SELECT * FROM person_custom_master ORDER BY custom_Order';
        $rsCustomFields = RunQuery($sSQL);
        $numRows = mysqli_num_rows($rsCustomFields);

        for ($row = 1; $row <= $numRows; $row++) {
            $aRow = mysqli_fetch_array($rsCustomFields, MYSQLI_BOTH);
            extract($aRow);

            $aFieldFields[$row] = $custom_Field;
            $aTypeFields[$row] = $type_ID;
            if (isset($custom_Special)) {
                $aSpecialFields[$row] = $custom_Special;
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
                // Use Propel ORM instead of raw SQL to prevent time-based blind SQL injection (GHSA-47q3-c874-mqvp)
                $customField = PersonCustomMasterQuery::create()
                    ->findOneById($aFieldFields[$iFieldID]);
                
                if ($customField !== null) {
                    $customField
                        ->setName($aNameFields[$iFieldID])
                        ->setSpecial($aSpecialFields[$iFieldID])
                        ->setFieldSec((int)$aFieldSecurity[$iFieldID])
                        ->save();
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
                $sSQL = 'SELECT custom_Name FROM person_custom_master';
                $rsCustomNames = RunQuery($sSQL);
                while ($aRow = mysqli_fetch_array($rsCustomNames)) {
                    if ($aRow[0] == $newFieldName) {
                        $bDuplicateNameError = true;
                        break;
                    }
                }

                if (!$bDuplicateNameError) {
                    global $cnInfoCentral;
                    // Find the highest existing field number in the table to determine the next free one.
                    // This is essentially an auto-incrementing system where deleted numbers are not re-used.
                    $fields = mysqli_query($cnInfoCentral, 'SHOW COLUMNS FROM person_custom');
                    $last = mysqli_num_rows($fields) - 1;

                    // Set the new field number based on the highest existing.  Chop off the "c" at the beginning of the old one's name.
                    // The "c#" naming scheme is necessary because MySQL 3.23 doesn't allow numeric-only field (table column) names.
                    $fields = mysqli_query($cnInfoCentral, 'SELECT * FROM person_custom');
                    $fieldInfo = mysqli_fetch_field_direct($fields, $last);
                    $newFieldNum = (int) mb_substr($fieldInfo->name, 1) + 1;

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
                    $newOrderID = $last + 1;
                    $sSQL = "INSERT INTO person_custom_master
                        (custom_Order , custom_Field , custom_Name ,  custom_Special , custom_FieldSec, type_ID)
                        VALUES ('" . $newOrderID . "', 'c" . $newFieldNum . "', '" . $newFieldName . "', " . $newSpecial . ", '" . $newFieldSec . "', '" . $newFieldType . "');";
                    RunQuery($sSQL);

                    // Insert into the custom fields table
                    $sSQL = 'ALTER TABLE person_custom ADD c' . $newFieldNum . ' ';

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
        $sSQL = 'SELECT * FROM person_custom_master ORDER BY custom_Order';

        $rsCustomFields = RunQuery($sSQL);
        $numRows = mysqli_num_rows($rsCustomFields);

        // Create arrays of the fields.
        for ($row = 1; $row <= $numRows; $row++) {
            $aRow = mysqli_fetch_array($rsCustomFields, MYSQLI_BOTH);
            extract($aRow);

            $aNameFields[$row] = $custom_Name;
            $aSpecialFields[$row] = $custom_Special;
            $aFieldFields[$row] = $custom_Field;
            $aTypeFields[$row] = $type_ID;
            $aFieldSecurity[$row] = $custom_FieldSec;
        }
    }
    // Prepare Security Group list
    $sSQL = 'SELECT * FROM list_lst WHERE lst_ID = 5 ORDER BY lst_OptionSequence';
    $rsSecurityGrp = RunQuery($sSQL);
    $aSecurityType = [];

    $aSecurityGrp = [];
    while ($aRow = mysqli_fetch_array($rsSecurityGrp)) {
        $aSecurityGrp[] = $aRow;
        extract($aRow);
        $aSecurityType[$lst_OptionID] = $lst_OptionName;
    }
    function GetSecurityList($aSecGrp, $fld_name, $currOpt = 'bAll')
    {
        $sOptList = '<select name="' . $fld_name . '" class="form-control form-control-sm">';
        $grp_Count = count($aSecGrp);

        for ($i = 0; $i < $grp_Count; $i++) {
            $aAryRow = $aSecGrp[$i];
            //extract($aAryRow);
            $sOptList .= '<option value="' . $aAryRow['lst_OptionID'] . '"';
            //        echo "lst_OptionName:".$aAryRow['lst_OptionName']."<br>";
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
    <script nonce="<?= SystemURLs::getCSPNonce() ?>">
        function confirmDeleteField(fieldName, fieldId) {
            var msg = <?= json_encode(gettext('Are you sure you want to delete')) ?> + ' "' + fieldName + '"?';
            msg += '<br><br><strong>' + <?= json_encode(gettext('Warning:')) ?> + '</strong> ';
            msg += <?= json_encode(gettext('By deleting this field, you will irrevocably lose all person data assigned for this field!')) ?>;
            bootbox.confirm({
                title: <?= json_encode(gettext('Delete Confirmation')) ?>,
                message: msg,
                buttons: {
                    cancel: { label: <?= json_encode(gettext('Cancel')) ?>, className: 'btn-secondary' },
                    confirm: { label: <?= json_encode(gettext('Delete')) ?>, className: 'btn-danger' }
                },
                callback: function(result) {
                    if (result) {
                        // Submit deletion as a POST request with CSRF protection
                        var form = document.createElement('form');
                        form.method = 'POST';
                        form.action = 'PersonCustomFieldsRowOps.php';

                        var fieldInput = document.createElement('input');
                        fieldInput.type = 'hidden';
                        fieldInput.name = 'Field';
                        fieldInput.value = fieldId;
                        form.appendChild(fieldInput);

                        var actionInput = document.createElement('input');
                        actionInput.type = 'hidden';
                        actionInput.name = 'Action';
                        actionInput.value = 'delete';
                        form.appendChild(actionInput);

                        var csrfInput = document.createElement('input');
                        csrfInput.type = 'hidden';
                        csrfInput.name = 'csrf_token';
                        csrfInput.value = <?= json_encode(CSRFUtils::generateToken('deletePersonCustomField')) ?>;
                        form.appendChild(csrfInput);

                        document.body.appendChild(form);
                        form.submit();
                    }
                }
            });
            return false;
        }

        <?php if (isset($_GET['deleted']) && $_GET['deleted'] === '1'): ?>
        $(document).ready(function() {
            window.CRM.notify(
                <?= json_encode(gettext('Field deleted successfully')) ?>,
                { type: 'success' }
            );
        });
        <?php endif; ?>
    </script>

    <form method="post" action="PersonCustomFieldsEditor.php" name="PersonCustomFieldsEditor">
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
                    </div>
                    <div class="col-md-3">
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
                    <div class="col-md-3">
                        <label for="newFieldSec" class="form-label"><?= gettext('Security Option') ?></label>
                        <div id="newFieldSec">
                            <?= GetSecurityList($aSecurityGrp, 'newFieldSec') ?>
                        </div>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-success w-100" name="AddField">
                            <i class="fa-solid fa-plus"></i>
                            <?= gettext('Add New') . ' ' . gettext('Field') ?>
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
                <?= gettext('No custom person fields have been added yet') ?>
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
                        <?= gettext('Existing Custom Person Fields') ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th><?= gettext('Type') ?></th>
                                    <th><?= gettext('Name') ?></th>
                                    <th><?= gettext('Special option') ?></th>
                                    <th><?= gettext('Security Option') ?></th>
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
                                    echo '<a href="javascript:void(0)" onclick="window.open(\'OptionManager.php?mode=custom&ListID=' . htmlspecialchars($aSpecialFields[$row], ENT_QUOTES, 'UTF-8') . '\',\'ListOptions\',\'toolbar=no,status=no,width=400,height=500\')">' . gettext('Edit List Options') . '</a>';
                                } else {
                                    echo '&nbsp;';
                                } ?>

                            </td>
                            <td>
                                <?php
                                if (isset($aSecurityType[$aFieldSecurity[$row]])) {
                                    echo GetSecurityList($aSecurityGrp, $row . 'FieldSec', $aSecurityType[$aFieldSecurity[$row]]);
                                } else {
                                    echo GetSecurityList($aSecurityGrp, $row . 'FieldSec');
                                } ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <?php
                                    $fieldNameJs = htmlspecialchars(json_encode($aNameFields[$row]), ENT_QUOTES, 'UTF-8');
                                    $fieldIdJs = htmlspecialchars(json_encode($aFieldFields[$row]), ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="confirmDeleteField(<?= $fieldNameJs ?>, <?= $fieldIdJs ?>)">
                                        <i class="fa-solid fa-trash"></i>
                                        <?= gettext('Delete') ?>
                                    </button>
                                    <?php
                                    if ($row != 1) {
                                        echo '<a href="PersonCustomFieldsRowOps.php?OrderID=' . $row . '&Field=' . htmlspecialchars($aFieldFields[$row], ENT_QUOTES, 'UTF-8') . '&Action=up" class="btn btn-outline-secondary" title="' . gettext('Move up') . '"><i class="fa-solid fa-arrow-up"></i></a>';
                                    }
                                    if ($row < $numRows) {
                                        echo '<a href="PersonCustomFieldsRowOps.php?OrderID=' . $row . '&Field=' . htmlspecialchars($aFieldFields[$row], ENT_QUOTES, 'UTF-8') . '&Action=down" class="btn btn-outline-secondary" title="' . gettext('Move down') . '"><i class="fa-solid fa-arrow-down"></i></a>';
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
