<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\ListOption;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\RedirectUtils;

$mode = trim($_GET['mode']);

// Check security for the mode selected.
switch ($mode) {
    case 'famroles':
    case 'classes':
        AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isMenuOptionsEnabled(), 'MenuOptions');
        break;

    case 'grptypes':
    case 'grproles':
    case 'groupcustom':
        AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isManageGroupsEnabled(), 'ManageGroups');
        break;

    case 'custom':
    case 'famcustom':
    case 'securitygrp':
        AuthenticationManager::redirectHomeIfNotAdmin();
        break;

    default:
        RedirectUtils::redirect('v2/dashboard');
        break;
}

// Select the proper settings for the editor mode
switch ($mode) {
    case 'famroles':
        //It don't work for postuguese because in it adjective come after noum
        $noun = gettext('Role');
        //In the same way, the plural isn't only add s
        $adjplusname = gettext('Family Role');
        $adjplusnameplural = gettext('Family Roles');
        $sPageTitle = gettext('Family Roles Editor');
        $listID = 2;
        $embedded = false;
        break;
    case 'classes':
        $noun = gettext('Classification');
        $adjplusname = gettext('Person Classification');
        $adjplusnameplural = gettext('Person Classifications');
        $sPageTitle = gettext('Person Classifications Editor');
        $listID = 1;
        $embedded = false;
        break;
    case 'grptypes':
        $noun = gettext('Type');
        $adjplusname = gettext('Group Type');
        $adjplusnameplural = gettext('Group Types');
        $sPageTitle = gettext('Group Types Editor');
        $listID = 3;
        $embedded = false;
        break;
    case 'securitygrp':
        $noun = gettext('Group');
        $adjplusname = gettext('Security Group');
        $adjplusnameplural = gettext('Security Groups');
        $sPageTitle = gettext('Security Groups Editor');
        $listID = 5;
        $embedded = false;
        break;
    case 'grproles':
        $noun = gettext('Role');
        $adjplusname = gettext('Group Member Role');
        $adjplusnameplural = gettext('Group Member Roles');
        $sPageTitle = gettext('Group Member Roles Editor');
        $listID = InputUtils::legacyFilterInput($_GET['ListID'], 'int');
        $embedded = true;

        $sSQL = 'SELECT grp_DefaultRole FROM group_grp WHERE grp_RoleListID = ' . $listID;
        $rsTemp = RunQuery($sSQL);

        // Validate that this list ID is really for a group roles list. (for security)
        if (mysqli_num_rows($rsTemp) == 0) {
            RedirectUtils::redirect('v2/dashboard');
            break;
        }

        $aTemp = mysqli_fetch_array($rsTemp);
        $iDefaultRole = $aTemp[0];

        break;
    case 'custom':
        $noun = gettext('Option');
        $adjplusname = gettext('Person Custom List Option');
        $adjplusnameplural = gettext('Person Custom List Options');
        $sPageTitle = gettext('Person Custom List Options Editor');
        $listID = InputUtils::legacyFilterInput($_GET['ListID'], 'int');
        $embedded = true;

        $sSQL = "SELECT '' FROM person_custom_master WHERE type_ID = 12 AND custom_Special = " . $listID;
        $rsTemp = RunQuery($sSQL);

        // Validate that this is a valid person-custom field custom list
        if (mysqli_num_rows($rsTemp) == 0) {
            RedirectUtils::redirect('v2/dashboard');
            break;
        }

        break;
    case 'groupcustom':
        $noun = gettext('Option');
        $adjplusname = gettext('Custom List Option');
        $adjplusnameplural = gettext('Custom List Options');
        $sPageTitle = gettext('Custom List Options Editor');
        $listID = InputUtils::legacyFilterInput($_GET['ListID'], 'int');
        $embedded = true;

        $sSQL = "SELECT '' FROM groupprop_master WHERE type_ID = 12 AND prop_Special = " . $listID;
        $rsTemp = RunQuery($sSQL);

        // Validate that this is a valid group-specific-property field custom list
        if (mysqli_num_rows($rsTemp) == 0) {
            RedirectUtils::redirect('v2/dashboard');
            break;
        }

        break;
    case 'famcustom':
        $noun = gettext('Option');
        $adjplusname = gettext('Family Custom List Option');
        $adjplusnameplural = gettext('Family Custom List Options');
        $sPageTitle = gettext('Family Custom List Options Editor');
        $listID = InputUtils::legacyFilterInput($_GET['ListID'], 'int');
        $embedded = true;

        $sSQL = "SELECT '' FROM family_custom_master WHERE type_ID = 12 AND fam_custom_Special = " . $listID;
        $rsTemp = RunQuery($sSQL);

        // Validate that this is a valid family_custom field custom list
        if (mysqli_num_rows($rsTemp) == 0) {
            RedirectUtils::redirect('v2/dashboard');
            break;
        }

        break;
    default:
        RedirectUtils::redirect('v2/dashboard');
        break;
}

$iNewNameError = 0;

// Check if we're adding a field
if (isset($_POST['AddField'])) {
    $newFieldName = InputUtils::legacyFilterInput($_POST['newFieldName']);

    if (strlen($newFieldName) === 0) {
        $iNewNameError = 1;
    } else {
        // Check for a duplicate option name
        $sSQL = "SELECT '' FROM list_lst WHERE lst_ID = $listID AND lst_OptionName = '" . $newFieldName . "'";
        $rsCount = RunQuery($sSQL);
        if (mysqli_num_rows($rsCount) > 0) {
            $iNewNameError = 2;
        } else {
            // Get count of the options
            $sSQL = "SELECT '' FROM list_lst WHERE lst_ID = $listID";
            $rsTemp = RunQuery($sSQL);
            $numRows = mysqli_num_rows($rsTemp);
            $newOptionSequence = $numRows + 1;

            // Get the new OptionID
            $sSQL = "SELECT MAX(lst_OptionID) FROM list_lst WHERE lst_ID = $listID";
            $rsTemp = RunQuery($sSQL);
            $aTemp = mysqli_fetch_array($rsTemp);
            $newOptionID = $aTemp[0] + 1;

            // Insert into the appropriate options table
            $listOption = new ListOption();
            $listOption
                ->setId($listID)
                ->setOptionId($newOptionID)
                ->setOptionName($newFieldName)
                ->setOptionSequence($newOptionSequence);
            $listOption->save();

            $iNewNameError = 0;
        }
    }
}

$bErrorFlag = false;
$bDuplicateFound = false;

// Get the original list of options..
//ADDITION - get Sequence Also
$sSQL = "SELECT lst_OptionName, lst_OptionID, lst_OptionSequence FROM list_lst WHERE lst_ID=$listID ORDER BY lst_OptionSequence";
$rsList = RunQuery($sSQL);
$numRows = mysqli_num_rows($rsList);

$aNameErrors = [];
for ($row = 1; $row <= $numRows; $row++) {
    $aNameErrors[$row] = 0;
}

if (isset($_POST['SaveChanges'])) {
    for ($row = 1; $row <= $numRows; $row++) {
        $aRow = mysqli_fetch_array($rsList, MYSQLI_BOTH);
        $aOldNameFields[$row] = $aRow['lst_OptionName'];
        $aIDs[$row] = $aRow['lst_OptionID'];

        //addition save off sequence also
        $aSeqs[$row] = $aRow['lst_OptionSequence'];

        $aNameFields[$row] = InputUtils::legacyFilterInput($_POST[$row . 'name']);
    }

    for ($row = 1; $row <= $numRows; $row++) {
        if (strlen($aNameFields[$row]) === 0) {
            $aNameErrors[$row] = 1;
            $bErrorFlag = true;
        } elseif ($row < $numRows) {
            $aNameErrors[$row] = 0;
            for ($rowcmp = $row + 1; $rowcmp <= $numRows; $rowcmp++) {
                if ($aNameFields[$row] == $aNameFields[$rowcmp]) {
                    $bErrorFlag = true;
                    $bDuplicateFound = true;
                    $aNameErrors[$row] = 2;
                    break;
                }
            }
        } else {
            $aNameErrors[$row] = 0;
        }
    }

    // If no errors, then update.
    if (!$bErrorFlag) {
        for ($row = 1; $row <= $numRows; $row++) {
            // Update the type's name if it has changed from what was previously stored
            if ($aOldNameFields[$row] != $aNameFields[$row]) {
                // Sanitize input to prevent XSS stored in role names
                $sanitizedName = InputUtils::sanitizeText($aNameFields[$row]);
                // Use Propel ORM to update the option name
                $option = ListOptionQuery::create()
                    ->filterById((int)$listID)
                    ->filterByOptionSequence((int)$row)
                    ->findOne();
                if ($option !== null) {
                    $option->setOptionName($sanitizedName)->save();
                }
            }
        }
    }
}

// Get data for the form as it now exists..

$sSQL = "SELECT lst_OptionName, lst_OptionID, lst_OptionSequence FROM list_lst WHERE lst_ID = $listID ORDER BY lst_OptionSequence";
$rsRows = RunQuery($sSQL);
$numRows = mysqli_num_rows($rsRows);

// Create arrays of the option names and IDs
for ($row = 1; $row <= $numRows; $row++) {
    $aRow = mysqli_fetch_array($rsRows, MYSQLI_BOTH);

    if (!$bErrorFlag) {
        $aNameFields[$row] = $aRow['lst_OptionName'];
    }

    $aIDs[$row] = $aRow['lst_OptionID'];
    //addition save off sequence also
    $aSeqs[$row] = $aRow['lst_OptionSequence'];
}

// Use a minimal page header if this form is going to be used within a frame
if ($embedded) {
    require_once __DIR__ . '/Include/Header-Minimal.php';
} else {    // in portuguese, this doesn't work because adjectives come after nouns
    //$sPageTitle = $adj . ' ' . $noun . "s ".gettext("Editor");
    require_once __DIR__ . '/Include/Header.php';
}

?>
<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="fa fa-list"></i> <?= $sPageTitle ?>
                    </h4>
                </div>
                <div class="card-body">
                    <form method="post" action="OptionManager.php?<?= "mode=$mode&ListID=$listID" ?>" name="OptionManager">

                        <div class="alert alert-warning mb-4">
                            <i class="fa fa-exclamation-triangle"></i>
                            <?= gettext('Warning: Removing will reset all assignments for all people with the assignment!') ?>
                        </div>

                        <?php
                        if ($bErrorFlag) {
                            echo '<div class="alert alert-danger">';
                            if ($bDuplicateFound) {
                                echo '<div class="mb-2"><i class="fa fa-times-circle"></i> ' . gettext('Error: Duplicate') . ' ' . $adjplusnameplural . ' ' . gettext('are not allowed.') . '</div>';
                            }
                            echo '<div><i class="fa fa-exclamation"></i> ' . gettext('Invalid fields or selections. Changes not saved! Please correct and try again!') . '</div>';
                            echo '</div>';
                        }
                        ?>

                        <div class="table-responsive mb-4">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 60px;" class="text-center"><?= gettext('Order') ?></th>
                                        <th style="width: 120px;" class="text-center"><?= gettext('Actions') ?></th>
                                        <th><?= $adjplusname ?></th>
                                        <?php if ($mode == 'grproles') { ?>
                                            <th class="text-center" style="width: 150px;"><?= gettext('Default') ?></th>
                                        <?php } ?>
                                        <?php if ($mode === 'classes') { ?>
                                            <th class="text-center" style="width: 120px;"><?= gettext('Status') ?></th>
                                        <?php } ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $aInactiveClassificationIds = explode(',', SystemConfig::getValue('sInactiveClassification'));
                                    $aInactiveClasses = array_filter($aInactiveClassificationIds, fn($k) => is_numeric($k));

                                    if (count($aInactiveClassificationIds) !== count($aInactiveClasses)) {
                                        LoggerUtils::getAppLogger()->warning('Encountered invalid configuration(s) for sInactiveClassification, please fix this');
                                    }

                                    for ($row = 1; $row <= $numRows; $row++) {
                                    ?>
                                        <tr>
                                            <td class="text-center">
                                                <strong><?php
                                                if ($mode == 'grproles' && $aIDs[$row] == $iDefaultRole) {
                                                    echo '<span class="badge bg-success">' . gettext('Default') . '</span> ';
                                                }
                                                echo $row; ?></strong>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <?php
                                                    if ($row != 1) {
                                                        echo "<a href=\"OptionManagerRowOps.php?mode=$mode&Order=$aSeqs[$row]&ListID=$listID&ID=" . $aIDs[$row] . "\" class=\"btn btn-outline-secondary\" title=\"" . gettext('Move Up') . "\"><i class=\"fa fa-arrow-up\"></i></a>";
                                                    }
                                                    if ($row < $numRows) {
                                                        echo "<a href=\"OptionManagerRowOps.php?mode=$mode&Order=$aSeqs[$row]&ListID=$listID&ID=" . $aIDs[$row] . "\" class=\"btn btn-outline-secondary\" title=\"" . gettext('Move Down') . "\"><i class=\"fa fa-arrow-down\"></i></a>";
                                                    }
                                                    if ($numRows > 0) {
                                                        echo "<a href=\"OptionManagerRowOps.php?mode=$mode&Order=$aSeqs[$row]&ListID=$listID&ID=" . $aIDs[$row] . "&Action=delete\" class=\"btn btn-outline-danger\" title=\"" . gettext('Delete') . "\"><i class=\"fa fa-trash\"></i></a>";
                                                    }
                                                    ?>
                                                </div>
                                            </td>
                                            <td>
                                                <input class="form-control form-control-sm" type="text" name="<?= $row . 'name' ?>" value="<?= InputUtils::escapeAttribute($aNameFields[$row]) ?>" maxlength="40">
                                                <?php
                                                if ($aNameErrors[$row] == 1) {
                                                    echo '<div class="text-danger small mt-1"><i class="fa fa-times-circle"></i> ' . gettext('You must enter a name') . '</div>';
                                                } elseif ($aNameErrors[$row] == 2) {
                                                    echo '<div class="text-danger small mt-1"><i class="fa fa-times-circle"></i> ' . gettext('Duplicate name found.') . '</div>';
                                                }
                                                ?>
                                            </td>
                                            <?php
                                            if ($mode == 'grproles') {
                                                echo '<td class="text-center"><a href="OptionManagerRowOps.php?mode=' . $mode . '&ListID=' . $listID . '&ID=' . $aIDs[$row] . "&Action=makedefault\" class=\"btn btn-sm btn-outline-primary\">" . gettext('Make Default') . "</a></td>";
                                            }
                                            if ($mode === 'classes') {
                                                echo '<td class="text-center">';
                                                $check = in_array($aIDs[$row], $aInactiveClasses) ? "checked" : "";
                                                echo "<input id='inactive$aIDs[$row]' type=\"checkbox\" class=\"form-check-input\" onclick=\"$.get('OptionManagerRowOps.php?mode=$mode&Order=$aSeqs[$row]&ListID=$listID&ID=" . $aIDs[$row] . "&Action=Inactive')\" $check>";
                                                echo " <span class=\"small\">" . gettext("Inactive") . "</span>";
                                                echo "</td>";
                                            }
                                            ?>
                                        </tr>
                                    <?php
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex gap-2 mb-4">
                            <button type="submit" class="btn btn-primary" name="SaveChanges">
                                <i class="fa fa-save"></i> <?= gettext('Save Changes') ?>
                            </button>

                            <?php if ($mode == 'groupcustom' || $mode == 'custom' || $mode == 'famcustom') {
                            ?>
                                <button type="button" class="btn btn-secondary" name="Exit" onclick="javascript:window.close();">
                                    <?= gettext('Exit') ?>
                                </button>
                            <?php
                            } elseif ($mode != 'grproles') {
                            ?>
                                <a href="v2/dashboard" class="btn btn-secondary">
                                    <?= gettext('Exit') ?>
                                </a>
                            <?php
                            } ?>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fa fa-plus"></i> <?= gettext('Add New') . ' ' . $adjplusname ?>
                    </h5>
                </div>
                <div class="card-body">
                    <form method="post" action="OptionManager.php?<?= "mode=$mode&ListID=$listID" ?>" name="OptionManager">
                        <div class="mb-3">
                            <label for="newFieldName" class="form-label"><?= gettext('Name for New') . ' ' . $noun ?><span class="text-danger">*</span></label>
                            <input class="form-control" type="text" id="newFieldName" name="newFieldName" maxlength="40" placeholder="<?= gettext('Enter a name') ?>" required>
                        </div>
                        <?php
                        if ($iNewNameError > 0) {
                            echo '<div class="alert alert-danger mb-3">';
                            if ($iNewNameError == 1) {
                                echo '<i class="fa fa-times-circle"></i> ' . gettext('Error: You must enter a name');
                            } else {
                                echo '<i class="fa fa-times-circle"></i> ' . gettext('Error: A ') . $noun . gettext(' by that name already exists.');
                            }
                            echo '</div>';
                        }
                        ?>
                        <button type="submit" class="btn btn-success" name="AddField">
                            <i class="fa fa-plus"></i> <?= gettext('Add New') . ' ' . $adjplusname ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
if ($embedded) {
    echo '</body></html>';
} else {
    require_once __DIR__ . '/Include/Footer.php';
}
