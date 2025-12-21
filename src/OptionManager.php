<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\ListOption;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\RedirectUtils;

$mode = InputUtils::legacyFilterInput($_GET['mode']);

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

//Set the starting row color
$sRowClass = 'RowColorA';

// Use a minimal page header if this form is going to be used within a frame
if ($embedded) {
    require_once __DIR__ . '/Include/Header-Minimal.php';
} else {    // in portuguese, this doesn't work because adjectives come after nouns
    //$sPageTitle = $adj . ' ' . $noun . "s ".gettext("Editor");
    require_once __DIR__ . '/Include/Header.php';
}

?>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    function confirmDelete(itemName, deleteUrl) {
        var msg = <?= json_encode(gettext('Are you sure you want to delete')) ?> + ' "' + itemName + '"?';
        msg += '<br><br><strong>' + <?= json_encode(gettext('Warning:')) ?> + '</strong> ';
        msg += <?= json_encode(gettext('This will remove it from all people currently assigned to it.')) ?>;
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
                    form.action = 'OptionManagerRowOps.php';
                    
                    // Parse the deleteUrl to extract parameters
                    var url = new URL(deleteUrl, window.location.origin + window.location.pathname);
                    var params = new URLSearchParams(url.search);
                    
                    // Add all URL parameters as hidden inputs
                    params.forEach(function(value, key) {
                        var input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = key;
                        input.value = value;
                        form.appendChild(input);
                    });
                    
                    // Add CSRF token
                    var csrfInput = document.createElement('input');
                    csrfInput.type = 'hidden';
                    csrfInput.name = 'csrf_token';
                    csrfInput.value = <?= json_encode(CSRFUtils::generateToken('deleteOptionManagerItem')) ?>;
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
            <?= json_encode(gettext('Item deleted successfully')) ?>,
            { type: 'success' }
        );
    });
    <?php endif; ?>
</script>

<div class="card">
    <div class="card-body">
        <form method="post" action="OptionManager.php?<?= "mode=$mode&ListID=$listID" ?>" name="OptionManager">

            <?php

            if ($bErrorFlag) {
                ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <strong><?= gettext('Error:') ?></strong>
                    <?php
                    if ($bDuplicateFound) {
                        echo gettext('Error: Duplicate') . ' ' . $adjplusnameplural . ' ' . gettext('are not allowed.');
                    } else {
                        echo gettext('Invalid fields or selections. Changes not saved! Please correct and try again!');
                    }
                    ?>
                </div>
                <?php
            }
            ?>

            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fa-solid fa-plus"></i>
                        <?= gettext('Add New') . ' ' . $adjplusname ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <label for="newFieldName" class="form-label"><?= gettext('Name for New') . ' ' . $noun ?>:</label>
                            <input class="form-control" type="text" id="newFieldName" name="newFieldName" maxlength="40">
                            <?php
                            if ($iNewNameError > 0) {
                                echo '<small class="text-danger d-block mt-1"><i class="fa-solid fa-circle-exclamation"></i> ';
                                if ($iNewNameError == 1) {
                                    echo gettext('You must enter a name');
                                } else {
                                    echo gettext('A ') . $noun . gettext(' by that name already exists.');
                                }
                                echo '</small>';
                            }
                            ?>
                        </div>
                    </div>
                    <div class="text-center mt-3">
                        <button type="submit" class="btn btn-success" name="AddField">
                            <i class="fa-solid fa-plus"></i>
                            <?= gettext('Add New') . ' ' . $adjplusname ?>
                        </button>
                    </div>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fa-solid fa-list"></i>
                        <?= gettext('Existing') . ' ' . $adjplusnameplural ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 80px;"><?= gettext('Order') ?></th>
                                    <th><?= gettext('Name') ?></th>
                                    <?php
                                    if ($mode == 'grproles') {
                                        echo '<th style="width: 120px;">' . gettext('Default') . '</th>';
                                    }
                                    if ($mode === 'classes') {
                                        echo '<th style="width: 100px;">' . gettext('Inactive') . '</th>';
                                    }
                                    ?>
                                    <th class="text-center" style="width: 200px;"><?= gettext('Actions') ?></th>
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
                            <td>
                                <span class="badge badge-secondary">
                                    <?php
                                    if ($mode == 'grproles' && $aIDs[$row] == $iDefaultRole) {
                                        echo '<span class="badge badge-info">' . gettext('Default') . '</span> ';
                                    }
                                    echo $row;
                                    ?>
                                </span>
                            </td>
                            <td>
                                <input class="form-control form-control-sm" type="text" name="<?= $row . 'name' ?>" value="<?= InputUtils::escapeAttribute($aNameFields[$row]) ?>" maxlength="40">
                                <?php

                                if ($aNameErrors[$row] == 1) {
                                    echo '<small class="text-danger d-block mt-1">' . gettext('You must enter a name') . '</small>';
                                } elseif ($aNameErrors[$row] == 2) {
                                    echo '<small class="text-danger d-block mt-1">' . gettext('Duplicate name found.') . '</small>';
                                } ?>
                            </td>
                            <?php
                            if ($mode == 'grproles') {
                                echo '<td><button type="button" class="btn btn-sm btn-outline-primary" onclick="document.location=\'OptionManagerRowOps.php?mode=' . InputUtils::escapeAttribute($mode) . '&ListID=' . InputUtils::escapeAttribute($listID) . '&ID=' . InputUtils::escapeAttribute($aIDs[$row]) . '&Action=makedefault\';">' . gettext('Make Default') . '</button></td>';
                            }
                            if ($mode === 'classes') {
                                echo '<td>';
                                $check = in_array($aIDs[$row], $aInactiveClasses) ? "checked" : "";
                                echo '<div class="form-check"><input id="inactive' . InputUtils::escapeAttribute($aIDs[$row]) . '" type="checkbox" class="form-check-input" onclick="$.get(\'OptionManagerRowOps.php?mode=' . InputUtils::escapeAttribute($mode) . '&Order=' . InputUtils::escapeAttribute($aSeqs[$row]) . '&ListID=' . InputUtils::escapeAttribute($listID) . '&ID=' . InputUtils::escapeAttribute($aIDs[$row]) . '&Action=Inactive\')" ' . $check . '><label class="form-check-label" for="inactive' . InputUtils::escapeAttribute($aIDs[$row]) . '">' . gettext('Inactive') . '</label></div>';
                                echo '</td>';
                            }
                            ?>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <?php
                                    if ($numRows > 0) {
                                        $deleteUrl = 'OptionManagerRowOps.php?mode=' . urlencode($mode) . '&Order=' . urlencode($aSeqs[$row]) . '&ListID=' . urlencode($listID) . '&ID=' . urlencode($aIDs[$row]) . '&Action=delete';
                                        $itemNameJs = InputUtils::escapeAttribute(json_encode($aNameFields[$row]));
                                        $deleteUrlJs = InputUtils::escapeAttribute(json_encode($deleteUrl));
                                        echo '<button type="button" class="btn btn-danger" onclick="confirmDelete(' . $itemNameJs . ', ' . $deleteUrlJs . ')"><i class="fa-solid fa-trash"></i> ' . gettext('Delete') . '</button>';
                                    }
                                    if ($row != 1) {
                                        echo '<a href="OptionManagerRowOps.php?mode=' . InputUtils::escapeAttribute($mode) . '&Order=' . InputUtils::escapeAttribute($aSeqs[$row]) . '&ListID=' . InputUtils::escapeAttribute($listID) . '&ID=' . InputUtils::escapeAttribute($aIDs[$row]) . '&Action=up" class="btn btn-outline-secondary" title="' . gettext('Move up') . '"><i class="fa-solid fa-arrow-up"></i></a>';
                                    }
                                    if ($row < $numRows) {
                                        echo '<a href="OptionManagerRowOps.php?mode=' . InputUtils::escapeAttribute($mode) . '&Order=' . InputUtils::escapeAttribute($aSeqs[$row]) . '&ListID=' . InputUtils::escapeAttribute($listID) . '&ID=' . InputUtils::escapeAttribute($aIDs[$row]) . '&Action=down" class="btn btn-outline-secondary" title="' . gettext('Move down') . '"><i class="fa-solid fa-arrow-down"></i></a>';
                                    }
                                    ?>
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

    <div class="d-flex mt-3 justify-content-center">
        <button type="submit" class="btn btn-primary mr-2" name="SaveChanges">
            <i class="fa-solid fa-save"></i>
            <?= gettext('Save Changes') ?>
        </button>

                <?php if ($mode == 'groupcustom' || $mode == 'custom' || $mode == 'famcustom') {
                ?>
                    <button type="button" class="btn btn-secondary" name="Exit" onclick="javascript:window.close();">
                        <i class="fa-solid fa-ban"></i>
                        <?= gettext('Exit') ?>
                    </button>
                <?php
                } elseif ($mode != 'grproles') {
                ?>
                    <button type="button" class="btn btn-secondary" name="Exit" onclick="javascript:document.location='v2/dashboard';">
                        <i class="fa-solid fa-ban"></i>
                        <?= gettext('Exit') ?>
                    </button>
                <?php
                } ?>
            </div>
    </div>
</div>
</form>
<?php
if ($embedded) {
    echo '</body></html>';
} else {
    require_once __DIR__ . '/Include/Footer.php';
}
