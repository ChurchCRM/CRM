<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\VolunteerOpportunity;
use ChurchCRM\model\ChurchCRM\VolunteerOpportunityQuery;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

// Security: User must have proper permission
// For now ... require $bAdmin
// Future ... $bManageVol
AuthenticationManager::redirectHomeIfNotAdmin();

// top down design....
// title line
// separator line
// warning line
// first input line: [ Save Changes ] [ Exit ]
// column titles
// first record: text box with order, up, down, delete ; Name, Desc, Active radio buttons
// and so on
// action is change of order number, up, down, delete, Name, Desc, or Active, or Add New

$iOpp = -1;
$sAction = '';
$iRowNum = -1;
$bErrorFlag = false;
$aNameErrors = [];
$bNewNameError = false;

if (array_key_exists('act', $_GET) || array_key_exists('act', $_POST)) {
    $sAction = InputUtils::legacyFilterInput($_GET['act'] ?? $_POST['act'] ?? '');
}
if (array_key_exists('Opp', $_GET) || array_key_exists('Opp', $_POST)) {
    $iOpp = InputUtils::filterInt($_GET['Opp'] ?? $_POST['Opp'] ?? -1);
}
if (array_key_exists('row_num', $_GET)) {
    $iRowNum = InputUtils::filterInt($_GET['row_num']);
}

$sDeleteError = '';

if ($sAction === 'delete' && $iOpp > 0) {
    // Delete Confirmation Page

    // Security: User must have Delete records permission
    // Otherwise, redirect to the main menu
    AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isDeleteRecordsEnabled(), 'DeleteRecords');

    $sSQL = "SELECT * FROM `volunteeropportunity_vol` WHERE `vol_ID` = '" . $iOpp . "'";
    $rsOpps = RunQuery($sSQL);
    $aRow = mysqli_fetch_array($rsOpps);
    extract($aRow);

    $sPageTitle = gettext('Delete Confirmation') . ': ' . gettext('Volunteer Opportunity');
    require_once __DIR__ . '/Include/Header.php';
?>
    <div class="container-fluid mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card border-danger">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0">
                            <i class="fa-solid fa-exclamation-triangle"></i>
                            <?= gettext('Confirm Volunteer Opportunity Deletion') ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning" role="alert">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            <?= gettext('Please confirm deletion of') ?>:
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <tr>
                                    <th><?= gettext('Order') ?></th>
                                    <th><?= gettext('Name') ?></th>
                                    <th><?= gettext('Description') ?></th>
                                </tr>
                                <tr>
                                    <td><span class="badge badge-secondary"><?= $vol_Order ?></span></td>
                                    <td><?= InputUtils::escapeHTML($vol_Name) ?></td>
                                    <td><?= InputUtils::escapeHTML($vol_Description) ?></td>
                                </tr>
                            </table>
                        </div>
                        <?php
                        $sSQL = 'SELECT `per_FirstName`, `per_LastName` FROM `person_per` ';
                        $sSQL .= 'LEFT JOIN `person2volunteeropp_p2vo` ';
                        $sSQL .= 'ON `p2vo_per_ID`=`per_ID` ';
                        $sSQL .= "WHERE `p2vo_vol_ID` = '" . $iOpp . "' ";
                        $sSQL .= 'ORDER BY `per_LastName`, `per_FirstName` ';
                        $rsPeople = RunQuery($sSQL);
                        $numRows = mysqli_num_rows($rsPeople);
                        if ($numRows > 0) {
                            echo "<div class='alert alert-warning mt-3' role='alert'><i class='fa-solid fa-exclamation-circle'></i> <strong>" . gettext('Warning') . "!</strong> " . gettext('There are people assigned to this Volunteer Opportunity. Deletion will unassign:') . "</div>";
                            echo "<div class='ms-3 mb-3'>";
                            for ($i = 0; $i < $numRows; $i++) {
                                $aRow = mysqli_fetch_array($rsPeople);
                                extract($aRow);
                                echo "<div><i class='fa-solid fa-person'></i> " . InputUtils::escapeHTML($per_FirstName) . " " . InputUtils::escapeHTML($per_LastName) . "</div>";
                            }
                            echo "</div>";
                        }
                        ?>
                        <div class="d-flex justify-content-center mt-4">
                            <form method="POST" action="VolunteerOpportunityEditor.php" class="d-inline mr-2">
                                <input type="hidden" name="act" value="ConfDelete">
                                <input type="hidden" name="Opp" value="<?= $iOpp ?>">
                                <?= CSRFUtils::getTokenInputField('deleteVolunteerOpportunity') ?>
                                <button type="submit" class="btn btn-danger">
                                    <i class="fa-solid fa-trash"></i>
                                    <?= gettext('Yes, delete this Opportunity') ?>
                                </button>
                            </form>
                            <a href="VolunteerOpportunityEditor.php" class="btn btn-secondary">
                                <i class="fa-solid fa-ban"></i>
                                <?= gettext('No, cancel this deletion') ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/Include/Footer.php';
    exit;
}

if ($sAction === 'ConfDelete' && $iOpp > 0) {
    // Security: User must have Delete records permission
    // Otherwise, redirect to the main menu
    AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isDeleteRecordsEnabled(), 'DeleteRecords');

    // Verify CSRF token for POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!CSRFUtils::verifyRequest($_POST, 'deleteVolunteerOpportunity')) {
            http_response_code(403);
            die(gettext('Invalid CSRF token'));
        }
    }

    // get the order value for the record being deleted
    $sSQL = "SELECT vol_Order from volunteeropportunity_vol WHERE vol_ID='$iOpp'";
    $rsOrder = RunQuery($sSQL);
    $aRow = mysqli_fetch_array($rsOrder);
    $orderVal = $aRow[0];
    $sSQL = "DELETE FROM `volunteeropportunity_vol` WHERE `vol_ID` = '" . $iOpp . "'";
    RunQuery($sSQL);
    $sSQL = "DELETE FROM `person2volunteeropp_p2vo` WHERE `p2vo_vol_ID` = '" . $iOpp . "'";
    RunQuery($sSQL);
    // pull back all the vol_Order fields that are higher than the one just deleted
    $sSQL = "UPDATE volunteeropportunity_vol SET vol_Order=vol_Order-1 WHERE vol_Order>=$orderVal";
    RunQuery($sSQL);
}

if ($iRowNum === 0) {
    // Skip data integrity check if we are only changing the ordering
    // by moving items up or down.
    // System response is too slow to do these checks every time the page
    // is viewed.

    // Data integrity checks performed when adding or deleting records.
    // Also on initial page view

    // Data integrity check #1
    // The default value of `vol_Order` is '0'.  But '0' is not assigned.
    // If we find a '0' add it to the end of the list by changing it to
    // MAX(vol_Order)+1.

    $sSQL = "SELECT `vol_ID` FROM `volunteeropportunity_vol` WHERE vol_Order = '0' ";
    $sSQL .= 'ORDER BY `vol_ID`';
    $rsOrder = RunQuery($sSQL);
    $numRows = mysqli_num_rows($rsOrder);
    if ($numRows) {
        $sSQL = 'SELECT MAX(`vol_Order`) AS `Max_vol_Order` FROM `volunteeropportunity_vol`';
        $rsMax = RunQuery($sSQL);
        $aRow = mysqli_fetch_array($rsMax);
        extract($aRow);
        for ($row = 1; $row <= $numRows; $row++) {
            $aRow = mysqli_fetch_array($rsOrder);
            extract($aRow);
            $num_vol_Order = $Max_vol_Order + $row;
            $volunteerOpp = VolunteerOpportunityQuery::create()->findOneById($vol_ID);
            $volunteerOpp->setOrder($num_vol_Order);
            $volunteerOpp->save();
        }
    }

    // Data integrity check #2
    // re-order the vol_Order field just in case there is a missing number(s)
    $sSQL = 'SELECT * FROM `volunteeropportunity_vol` ORDER by `vol_Order`';
    $rsOpps = RunQuery($sSQL);
    $numRows = mysqli_num_rows($rsOpps);

    $orderCounter = 1;
    for ($row = 1; $row <= $numRows; $row++) {
        $aRow = mysqli_fetch_array($rsOpps);
        extract($aRow);
        if ($orderCounter != $vol_Order) {
            $volunteerOpp = VolunteerOpportunityQuery::create()->findOneById($vol_ID);
            $volunteerOpp->setOrder($orderCounter);
            $volunteerOpp->save();
        }
        ++$orderCounter;
    }
}

$sPageTitle = gettext('Volunteer Opportunity Editor');

require_once __DIR__ . '/Include/Header.php';

// Does the user want to save changes to text fields?
if (isset($_POST['SaveChanges'])) {
    $sSQL = 'SELECT * FROM `volunteeropportunity_vol`';
    $rsOpps = RunQuery($sSQL);
    $numRows = mysqli_num_rows($rsOpps);

    for ($iFieldID = 1; $iFieldID <= $numRows; $iFieldID++) {
        $nameName = $iFieldID . 'name';
        $descName = $iFieldID . 'desc';
        if (array_key_exists($nameName, $_POST)) {
            $aNameFields[$iFieldID] = InputUtils::legacyFilterInput($_POST[$nameName]);

            if (strlen($aNameFields[$iFieldID]) === 0) {
                $aNameErrors[$iFieldID] = true;
                $bErrorFlag = true;
            } else {
                $aNameErrors[$iFieldID] = false;
            }

            $aDescFields[$iFieldID] = InputUtils::legacyFilterInput($_POST[$descName]);

            $aRow = mysqli_fetch_array($rsOpps);
            $aIDFields[$iFieldID] = $aRow[0];
        }
    }

    // If no errors, then update.
    if (!$bErrorFlag) {
        for ($iFieldID = 1; $iFieldID <= $numRows; $iFieldID++) {
            if (array_key_exists($iFieldID, $aNameFields)) {
                $volunteerOpp = VolunteerOpportunityQuery::create()->findOneById($aIDFields[$iFieldID]);
                $volunteerOpp
                    ->setName($aNameFields[$iFieldID])
                    ->setDescription($aDescFields[$iFieldID]);
                $volunteerOpp->save();
            }
        }
    }
} else {
    if (isset($_POST['AddField'])) { // Check if we're adding a VolOp
        $newFieldName = InputUtils::legacyFilterInput($_POST['newFieldName']);
        $newFieldDesc = InputUtils::legacyFilterInput($_POST['newFieldDesc']);
        if (strlen($newFieldName) === 0) {
            $bNewNameError = true;
        } else { // Insert into table
            // There must be an easier way to get the number of rows in order to generate the last order number.
            $sSQL = 'SELECT * FROM `volunteeropportunity_vol`';
            $rsOpps = RunQuery($sSQL);
            $numRows = mysqli_num_rows($rsOpps);
            $newOrder = $numRows + 1;

            $volunteerOpp = new VolunteerOpportunity();
            $volunteerOpp
                ->setOrder($newOrder)
                ->setName($newFieldName)
                ->setDescription($newFieldDesc);
            $volunteerOpp->save();

            $bNewNameError = false;
        }
    }
    // Get data for the form as it now exists
    $sSQL = 'SELECT * FROM `volunteeropportunity_vol`';

    $rsOpps = RunQuery($sSQL);
    $numRows = mysqli_num_rows($rsOpps);

    // Create arrays of Volunteer Opportunities
    for ($row = 1; $row <= $numRows; $row++) {
        $aRow = mysqli_fetch_array($rsOpps, MYSQLI_BOTH);
        extract($aRow);
        $rowIndex = $vol_Order; // Is this dangerous? The vol_Order field had better be correct.
        $aIDFields[$rowIndex] = $vol_ID;
        $aNameFields[$rowIndex] = $vol_Name;
        $aDescFields[$rowIndex] = $vol_Description;
    }
}

// Construct the form
    ?>
    <div class="container-fluid mt-4">
        <form method="post" action="VolunteerOpportunityEditor.php" name="OppsEditor">

                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="fa-solid fa-plus"></i>
                            <?= gettext('Add New') . ' ' . gettext('Volunteer Opportunity') ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" for="newFieldName"><?= gettext('Name') ?></label>
                                    <input type="text" id="newFieldName" name="newFieldName" maxlength="30" class="form-control form-control-sm">
                                    <?php if ($bNewNameError) {
                                        echo '<small class="text-danger d-block mt-1"><i class="fa-solid fa-circle-exclamation"></i> ' . gettext('You must enter a name') . '</small>';
                                    } ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" for="newFieldDesc"><?= gettext('Description') ?></label>
                                    <input type="text" id="newFieldDesc" name="newFieldDesc" maxlength="100" class="form-control form-control-sm">
                                </div>
                            </div>
                        </div>
                        <div class="text-center">
                            <button type="submit" class="btn btn-success" name="AddField">
                                <i class="fa-solid fa-plus"></i>
                                <?= gettext('Add New') . ' ' . gettext('Opportunity') ?>
                            </button>
                        </div>
                    </div>
                </div>

                <?php
                if ($numRows == 0) {
                ?>
                    <div class="alert alert-info" role="alert">
                        <i class="fa-solid fa-circle-info"></i>
                        <?= gettext('No volunteer opportunities have been added yet') ?>
                    </div>
                <?php
                } else {
                    // if an 'action' (up/down arrow clicked, or order was input)
                    if ($iRowNum && $sAction != '') {
                        // cast as int and couple with switch for sql injection prevention for $row_num
                        $swapRow = $iRowNum;
                        if ($sAction === 'up') {
                            $newRow = --$iRowNum;
                        } elseif ($sAction === 'down') {
                            $newRow = ++$iRowNum;
                        } else {
                            $newRow = $iRowNum;
                        }

                        if (array_key_exists($swapRow, $aIDFields)) {
                            $volunteerOpp = VolunteerOpportunityQuery::create()->findOneById($aIDFields[$swapRow]);
                            $volunteerOpp->setOrder($newRow);
                            $volunteerOpp->save();
                        }

                        if (array_key_exists($newRow, $aIDFields)) {
                            $volunteerOpp = VolunteerOpportunityQuery::create()->findOneById($aIDFields[$newRow]);
                            $volunteerOpp->setOrder($swapRow);
                            $volunteerOpp->save();
                        }

                        // now update internal data to match
                        if (array_key_exists($swapRow, $aIDFields)) {
                            $saveID = $aIDFields[$swapRow];
                            $saveName = $aNameFields[$swapRow];
                            $saveDesc = $aDescFields[$swapRow];
                            $aIDFields[$newRow] = $saveID;
                            $aNameFields[$newRow] = $saveName;
                            $aDescFields[$newRow] = $saveDesc;
                        }

                        if (array_key_exists($newRow, $aIDFields)) {
                            $aIDFields[$swapRow] = $aIDFields[$newRow];
                            $aNameFields[$swapRow] = $aNameFields[$newRow];
                            $aDescFields[$swapRow] = $aDescFields[$newRow];
                        }
                    }
                ?>

                <div class="alert alert-warning" role="alert">
                    <i class="fa-solid fa-exclamation-triangle"></i>
                    <strong><?= gettext('Warning:') ?></strong>
                    <?= gettext("ADD, Delete, and ordering changes are immediate. Name and Description changes must be saved by clicking 'Save Changes'.") ?>
                </div>

                <?php
                if ($bErrorFlag) {
                    echo '<div class="alert alert-danger" role="alert"><i class="fa-solid fa-circle-exclamation"></i> <strong>' . gettext('Error') . ':</strong> ' . gettext('Invalid fields or selections. Changes not saved! Please correct and try again!') . '</div>';
                }
                if (strlen($sDeleteError) > 0) {
                    echo '<div class="alert alert-danger" role="alert"><i class="fa-solid fa-circle-exclamation"></i> <strong>' . gettext('Error') . ':</strong> ' . $sDeleteError . '</div>';
                }
                ?>

                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fa-solid fa-list"></i>
                            <?= gettext('Existing Volunteer Opportunities') ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th><?= gettext('Order') ?></th>
                                        <th><?= gettext('Name') ?></th>
                                        <th><?= gettext('Description') ?></th>
                                        <th class="text-center"><?= gettext('Actions') ?></th>
                                    </tr>
                                </thead>
                                <tbody>

                        <?php
                        for ($row = 1; $row <= $numRows; $row++) {
                            if (array_key_exists($row, $aNameFields)) {
                                echo '<tr>';
                                echo '<td><span class="badge badge-secondary">' . $row . '</span></td>';
                                echo '<td>';
                                echo '<input type="text" name="' . $row . 'name" value="' . InputUtils::escapeAttribute($aNameFields[$row]) . '" class="form-control form-control-sm" maxlength="30">';
                                if (array_key_exists($row, $aNameErrors) && $aNameErrors[$row]) {
                                    echo '<small class="text-danger d-block mt-1"><i class="fa-solid fa-circle-exclamation"></i> ' . gettext('You must enter a name') . '</small>';
                                }
                                echo '</td>';
                                echo '<td>';
                                echo '<input type="text" name="' . $row . 'desc" value="' . InputUtils::escapeAttribute($aDescFields[$row]) . '" class="form-control form-control-sm" maxlength="100">';
                                echo '</td>';
                                echo '<td>';
                                echo '<div class="btn-group btn-group-sm" role="group">';
                                echo '<a href="VolunteerOpportunityEditor.php?act=delete&amp;Opp=' . $aIDFields[$row] . '" class="btn btn-danger" title="' . gettext('Delete') . '"><i class="fa-solid fa-trash"></i> ' . gettext('Delete') . '</a>';
                                if ($row !== 1) {
                                    echo '<a href="VolunteerOpportunityEditor.php?act=up&amp;row_num=' . $row . '" class="btn btn-outline-secondary" title="' . gettext('Move up') . '"><i class="fa-solid fa-arrow-up"></i></a>';
                                }
                                if ($row != $numRows) {
                                    echo '<a href="VolunteerOpportunityEditor.php?act=down&amp;row_num=' . $row . '" class="btn btn-outline-secondary" title="' . gettext('Move down') . '"><i class="fa-solid fa-arrow-down"></i></a>';
                                }
                                echo '</div>';
                                echo '</td>';
                                echo '</tr>';
                            }
                        }
                        ?>

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
                    <button type="button" class="btn btn-secondary" name="Exit" onclick="document.location='v2/dashboard'">
                        <i class="fa-solid fa-arrow-right-from-bracket"></i>
                        <?= gettext('Exit') ?>
                    </button>
                </div>
        <?php } ?>
        </form>
    </div>
    <?php
    require_once __DIR__ . '/Include/Footer.php';
