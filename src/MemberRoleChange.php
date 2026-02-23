<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

// Security: User must have Manage Groups & Roles permission
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isManageGroupsEnabled(), 'ManageGroups');

$sPageTitle = gettext('Member Role Change');

// Get the GroupID from the querystring
$iGroupID = InputUtils::legacyFilterInput($_GET['GroupID'], 'int');

// Get the PersonID from the querystring
$iPersonID = InputUtils::legacyFilterInput($_GET['PersonID'], 'int');

// Get the return location flag from the querystring
$iReturn = $_GET['Return'];

// Was the form submitted?
if (isset($_POST['Submit'])) {
    //Get the new role
    $iNewRole = InputUtils::legacyFilterInput($_POST['NewRole']);

    //Update the database
    $sSQL = 'UPDATE person2group2role_p2g2r SET p2g2r_rle_ID = ' . $iNewRole . " WHERE p2g2r_per_ID = $iPersonID AND p2g2r_grp_ID = $iGroupID";
    RunQuery($sSQL);

    //Reroute back to the proper location
    if ($iReturn) {
        RedirectUtils::redirect('GroupView.php?GroupID=' . $iGroupID);
    } else {
        RedirectUtils::redirect('PersonView.php?PersonID=' . $iPersonID);
    }
}

// Get their current role
$sSQL = 'SELECT per_FirstName, per_LastName, grp_Name, grp_RoleListID, lst_OptionID, ' .
        'lst_OptionName AS sRoleName, p2g2r_rle_ID AS iRoleID ' .
        'FROM person_per ' .
        'LEFT JOIN person2group2role_p2g2r ON p2g2r_per_ID = per_ID ' .
        'LEFT JOIN group_grp ON p2g2r_grp_ID = grp_ID ' .
        'LEFT JOIN list_lst ON lst_ID = grp_RoleListID ' .
        "WHERE per_ID = $iPersonID AND grp_ID = $iGroupID " .
        'AND lst_OptionID=p2g2r_rle_ID ';

$rsCurrentRole = mysqli_fetch_array(RunQuery($sSQL));
extract($rsCurrentRole);

// Get all the possible roles
$sSQL = "SELECT * FROM list_lst WHERE lst_ID = $grp_RoleListID ORDER BY lst_OptionSequence";
$rsAllRoles = RunQuery($sSQL);

require_once __DIR__ . '/Include/Header.php'

?>

<div class="card card-body">
    <form method="post" action="MemberRoleChange.php?GroupID=<?= $iGroupID ?>&PersonID=<?= $iPersonID ?>&Return=<?= $iReturn ?>">

        <div class="form-group row">
            <label class="col-sm-3 col-form-label font-weight-bold text-right"><?= gettext('Group Name') ?>:</label>
            <div class="col-sm-9 col-form-label"><?= InputUtils::escapeHTML($grp_Name) ?></div>
        </div>

        <div class="form-group row">
            <label class="col-sm-3 col-form-label font-weight-bold text-right"><?= gettext("Member's Name") ?>:</label>
            <div class="col-sm-9 col-form-label"><?= InputUtils::escapeHTML($per_LastName) . ', ' . InputUtils::escapeHTML($per_FirstName) ?></div>
        </div>

        <div class="form-group row">
            <label class="col-sm-3 col-form-label font-weight-bold text-right"><?= gettext('Current Role') ?>:</label>
            <div class="col-sm-9 col-form-label"><?= gettext($sRoleName) ?></div>
        </div>

        <div class="form-group row">
            <label class="col-sm-3 col-form-label font-weight-bold text-right" for="NewRole"><?= gettext('New Role') ?>:</label>
            <div class="col-sm-4">
                <select name="NewRole" id="NewRole" class="form-control">
                    <?php
                    // Loop through all the possible roles
                    while ($aRow = mysqli_fetch_array($rsAllRoles)) {
                        extract($aRow);

                        // If this is the current role, select it
                        $sSelected = ($iRoleID == $lst_OptionID) ? 'selected' : '';
                        echo '<option value="' . $lst_OptionID . '" ' . $sSelected . '>' . gettext($lst_OptionName) . '</option>';
                    }
                    ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <input type="submit" class="btn btn-primary" name="Submit" value="<?= gettext('Update') ?>">
            <?php
            if ($iReturn) {
                echo ' <a href="GroupView.php?GroupID=' . $iGroupID . '" class="btn btn-secondary ml-2">' . gettext('Cancel') . '</a>';
            } else {
                echo ' <a href="PersonView.php?PersonID=' . $iPersonID . '" class="btn btn-secondary ml-2">' . gettext('Cancel') . '</a>';
            }
            ?>
        </div>

    </form>
</div>
<?php
require_once __DIR__ . '/Include/Footer.php';
