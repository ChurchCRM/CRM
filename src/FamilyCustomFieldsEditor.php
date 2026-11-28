<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\FamilyCustomMaster;
use ChurchCRM\model\ChurchCRM\FamilyCustomMasterQuery;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\ListOption;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\Map\FamilyCustomTableMap;
use ChurchCRM\Utils\CustomFieldUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\view\PageHeader;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Propel;

// Security: user must be administrator to use this page
AuthenticationManager::redirectHomeIfNotAdmin();

$aPropTypes = CustomFieldUtils::getPropTypes();

$sPageTitle = gettext('Custom Family Fields Editor');
$sPageSubtitle = gettext('Define custom fields to collect additional family data');
$aBreadcrumbs = PageHeader::breadcrumbs([
    [gettext('People'), '/people/dashboard'],
    [gettext('Custom Family Fields')],
]);
$sPageHeaderButtons = PageHeader::peopleSettingsButton();

require_once __DIR__ . '/Include/Header.php'; ?>

<?php

$bNewNameError = false;
$bDuplicateNameError = false;
$bErrorFlag = false;
$aNameErrors = [];

// Does the user want to save changes to text fields?
if (isset($_POST['SaveChanges'])) {
    // Fill in the other needed custom field data arrays not gathered from the form submit
    $customMasters = FamilyCustomMasterQuery::create()->orderByOrder()->find();
    $numRows = count($customMasters);

    $row = 1;
    foreach ($customMasters as $customField) {
        $aFieldFields[$row] = $customField->getField();
        $aTypeFields[$row] = $customField->getTypeId();
        $aSpecialFields[$row] = $customField->getCustomSpecial() ?? 'NULL';
        $row++;
    }

    for ($iFieldID = 1; $iFieldID <= $numRows; $iFieldID++) {
        $aNameFields[$iFieldID] = CustomFieldUtils::normalizeEditorFieldName($_POST[$iFieldID . 'name']);

        if (strlen($aNameFields[$iFieldID]) === 0) {
            $aNameErrors[$iFieldID] = true;
            $bErrorFlag = true;
        } else {
            $aNameErrors[$iFieldID] = false;
        }

        $aFieldSecurity[$iFieldID] = InputUtils::legacyFilterInput($_POST[$iFieldID . 'FieldSec'], 'int');

        if (isset($_POST[$iFieldID . 'special'])) {
            $aSpecialFields[$iFieldID] = InputUtils::legacyFilterInput($_POST[$iFieldID . 'special'], 'int');

            if ($aSpecialFields[$iFieldID] === 0) {
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
            $customField = FamilyCustomMasterQuery::create()
                ->findOneByField($aFieldFields[$iFieldID]);

            if ($customField !== null) {
                $customField
                    ->setName($aNameFields[$iFieldID])
                    ->setCustomSpecial($aSpecialFields[$iFieldID])
                    ->setFieldSecurity((int)$aFieldSecurity[$iFieldID])
                    ->save();
            }
        }
    }
} else {
    // Check if we're adding a field
    if (isset($_POST['AddField'])) {
        $newFieldType = InputUtils::filterInt($_POST['newFieldType']);
        $columnType = CustomFieldUtils::COLUMN_TYPES[$newFieldType] ?? null;
        $newFieldName = CustomFieldUtils::normalizeEditorFieldName($_POST['newFieldName']);
        $newFieldSec = InputUtils::legacyFilterInput($_POST['newFieldSec'], 'int');

        if (strlen($newFieldName) === 0) {
            $bNewNameError = true;
        } elseif ($columnType !== null) {
            $bDuplicateNameError = FamilyCustomMasterQuery::create()->filterByName($newFieldName)->count() > 0;

            if (!$bDuplicateNameError) {
                $connection = Propel::getWriteConnection(FamilyCustomTableMap::DATABASE_NAME);
                // Use the last physical column so field numbering follows the existing table layout.
                $columns = $connection->query('SHOW COLUMNS FROM family_custom')->fetchAll(PDO::FETCH_COLUMN);
                $newOrderID = count($columns);
                $newFieldNum = (int) mb_substr($columns[$newOrderID - 1], 1) + 1;
                $column = 'c' . $newFieldNum;
                if (!preg_match('/\Ac[0-9]+\z/', $column)) {
                    throw new UnexpectedValueException('Invalid custom field column');
                }

                // If we're inserting a new custom-list type field,
                // create a new list and get its ID
                if ($newFieldType == 12) {
                    // Get the first available lst_ID for insertion.
                    // lst_ID 0-9 are reserved for permanent lists.
                    $maxList = ListOptionQuery::create()->orderById(Criteria::DESC)->findOne();
                    $maxListId = (int) ($maxList?->getId() ?? 0);
                    $newListID = $maxListId > 9 ? $maxListId + 1 : 10;

                    // Insert into the lists table with an example option.
                    $listOption = new ListOption();
                    $listOption
                        ->setId($newListID)
                        ->setOptionId(1)
                        ->setOptionSequence(1)
                        ->setOptionName(gettext('Default Option'));
                    $listOption->save();

                } // end if ($newFieldType == 12)

                // Insert into the master table via ORM (avoids SQL injection from user-supplied field name/type)
                $familyCustomMaster = new FamilyCustomMaster();
                $familyCustomMaster
                    ->setOrder($newOrderID)
                    ->setField($column)
                    ->setName($newFieldName)
                    ->setCustomSpecial($newFieldType == 12 ? $newListID : null)
                    ->setFieldSecurity($newFieldSec)
                    ->setTypeId($newFieldType);
                $familyCustomMaster->save();

                $connection->exec('ALTER TABLE family_custom ADD ' . $column . ' ' . $columnType . ' DEFAULT NULL'); // nosemgrep: php.lang.security.injection.tainted-sql-string.tainted-sql-string

                $bNewNameError = false;
            }
        }
    }

    // Get data for the form as it now exists..
    $customMasters = FamilyCustomMasterQuery::create()->orderByOrder()->find();
    $numRows = count($customMasters);

    $row = 1;
    foreach ($customMasters as $customField) {
        $aNameFields[$row] = $customField->getName();
        $aSpecialFields[$row] = $customField->getCustomSpecial();
        $aFieldFields[$row] = $customField->getField();
        $aTypeFields[$row] = $customField->getTypeId();
        $aFieldSecurity[$row] = $customField->getFieldSecurity();
        $aNameErrors[$row] = false;
        $row++;
    }
}

// Prepare Security Group list
    $aSecurityType = [];
    $aSecurityGrp = [];
    foreach (ListOptionQuery::create()->filterById(5)->orderByOptionSequence()->find() as $option) {
        $aSecurityGrp[] = [
            'lst_OptionID' => $option->getOptionId(),
            'lst_OptionName' => $option->getOptionName(),
        ];
        $aSecurityType[$option->getOptionId()] = $option->getOptionName();
    }

    $familyFieldGroups = [];
    foreach (GroupQuery::create()->orderByName()->find() as $group) {
        $familyFieldGroups[] = ['grp_ID' => $group->getId(), 'grp_Name' => $group->getName()];
    }

function GetSecurityList($aSecGrp, $fld_name, $currOpt = 'bAll')
{
    $sOptList = '<select name="' . $fld_name . '" class="form-select form-select-sm">';
    $grp_Count = count($aSecGrp);

    for ($i = 0; $i < $grp_Count; $i++) {
        $aAryRow = $aSecGrp[$i];
        $sOptList .= '<option value="' . $aAryRow['lst_OptionID'] . '"';
        if ($aAryRow['lst_OptionName'] == $currOpt) {
            $sOptList .= ' selected';
        }
        $sOptList .= '>' . $aAryRow['lst_OptionName'] ."</option>\n";
    }
    $sOptList .= '</select>';

    return $sOptList;
}

// Construct the form
?>
<form method="post" action="FamilyCustomFieldsEditor.php" name="FamilyCustomFieldsEditor" data-custom-field-type="family">
    <div class="card mb-4">
        <div class="card-status-top bg-success"></div>
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fa-solid fa-plus"></i>
                <?= gettext('Add New Field') ?>
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <label for="newFieldType" class="form-label"><?= gettext('Type') ?>:</label>
                    <select id="newFieldType" name="newFieldType" class="form-select">
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
                        <?= gettext('Add New Field') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php
    if ($numRows === 0) {
    ?>
        <div class="alert alert-info">
            <i class="fa-solid fa-circle-info"></i>
            <?= gettext('No custom Family fields have been added yet') ?>
        </div>
    <?php
    } else {
    ?>
        <div class="alert alert-info" role="alert">
            <i class="fa-solid fa-circle-info me-1"></i>
            <?= gettext('Name changes require saving. Reorder and delete actions in the action menu take effect immediately.') ?>
        </div>
        <?php
        if ($bErrorFlag) {
        ?>
            <div class="alert alert-danger" role="alert">
                <i class="fa-solid fa-circle-exclamation"></i>
                <strong><?= gettext('Error') . ':' ?></strong>
                <?= gettext('Invalid fields or selections. Changes not saved! Please correct and try again!') ?>
            </div>
        <?php
        }
        ?>
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <h5 class="mb-0">
                    <i class="fa-solid fa-list me-2"></i>
                    <?= gettext('Existing Custom Family Fields') ?>
                </h5>
                <span class="badge bg-info text-white ms-auto"><?= $numRows ?> <?= gettext('fields') ?></span>
            </div>
            <div class="card-body overflow-visible">
                <table class="table table-hover table-sm">
                    <thead>
                        <tr>
                            <th><?= gettext('Type') ?></th>
                            <th><?= gettext('Name') ?></th>
                            <th><?= gettext('Special option') ?></th>
                            <th><?= gettext('Security Option') ?></th>
                            <th class="text-center no-export w-1"><?= gettext('Actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
            <?php

            for ($row = 1; $row <= $numRows; $row++) {
                ?>
                <tr>
                    <td>
                        <span class="badge bg-light text-dark"><?= InputUtils::escapeHTML($aPropTypes[$aTypeFields[$row]]) ?></span>
                    </td>
                    <td>
                        <input type="text" class="form-control form-control-sm" name="<?= $row . 'name' ?>" value="<?= InputUtils::escapeAttribute($aNameFields[$row]) ?>" maxlength="40">
                        <?php
                        if ($aNameErrors[$row]) {
                            echo '<small class="text-danger"><br>' . gettext('You must enter a name') . '</small>';
                        } ?>
                    </td>
                    <td>

                    <?php
                    if ($aTypeFields[$row] == 9) {
                        echo '<select name="' . $row . 'special" class="form-select form-select-sm">';
                        echo '<option value="0">' . gettext('Select a group') . '</option>';

                        foreach ($familyFieldGroups as $groupOption) {
                            echo '<option value="' . InputUtils::escapeAttribute($groupOption['grp_ID']) . '"';
                            if ($aSpecialFields[$row] == $groupOption['grp_ID']) {
                                echo ' selected';
                            }
                            echo '>' . InputUtils::escapeHTML($groupOption['grp_Name']) . '</option>';
                        }

                        echo '</select>';
                        if ($aSpecialErrors[$row]) {
                            echo '<small class="text-danger"><br>' . gettext('You must select a group.') . '</small>';
                        }
                    } elseif ($aTypeFields[$row] == 12) {
                        echo '<a href="' . SystemURLs::getRootPath() . '/admin/system/options?mode=famcustom&ListID=' . htmlspecialchars($aSpecialFields[$row], ENT_QUOTES, 'UTF-8') . '" target="_blank" class="btn btn-sm btn-outline-primary">' . gettext('Edit List Options') . '</a>';
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
                    <td class="w-1">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-ghost-secondary" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
                                <i class="fa-solid fa-ellipsis-vertical"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end">
                                <?php
                                if ($row > 1) {
                                    echo '<button type="button" class="dropdown-item js-reorder-field" data-field-id="' . htmlspecialchars($aFieldFields[$row], ENT_QUOTES, 'UTF-8') . '" data-direction="up"><i class="fa-solid fa-arrow-up me-2"></i>' . gettext('Move up') . '</button>';
                                }
                                if ($row < $numRows) {
                                    echo '<button type="button" class="dropdown-item js-reorder-field" data-field-id="' . htmlspecialchars($aFieldFields[$row], ENT_QUOTES, 'UTF-8') . '" data-direction="down"><i class="fa-solid fa-arrow-down me-2"></i>' . gettext('Move down') . '</button>';
                                }
                                if ($row != 1 || $row < $numRows) {
                                    echo '<div class="dropdown-divider"></div>';
                                }
                                ?>
                                <button type="button" class="dropdown-item text-danger js-delete-field"
                                    data-field-name="<?= InputUtils::escapeAttribute($aNameFields[$row]) ?>"
                                    data-field-id="<?= InputUtils::escapeAttribute($aFieldFields[$row]) ?>">
                                    <i class="fa-solid fa-trash me-2"></i><?= gettext('Delete') ?>
                                </button>
                            </div>
                        </div>
                    </td>

                </tr>
                <?php
    } ?>

                    </tbody>
                </table>
            </div>
        </div>
        <div class="d-flex justify-content-center my-3">
            <button type="submit" class="btn btn-primary" name="SaveChanges">
                <i class="fa-solid fa-floppy-disk"></i>
                <?= gettext('Save Changes') ?>
            </button>
        </div>
    <?php
    } ?>
    </form>
<script src="<?= SystemURLs::assetVersioned('/skin/v2/people-custom-fields-editor.min.js') ?>"></script>
<?php
require_once __DIR__ . '/Include/Footer.php';
