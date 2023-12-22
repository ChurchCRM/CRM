<?php

/*******************************************************************************
 *
 *  filename    : MemberRoleChange.php
 *  last change : 2003-04-03
 *  website     : https://churchcrm.io
 *  copyright   : Copyright 2001-2003 Deane Barker, Lewis Franklin
  *
 ******************************************************************************/

//Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

// Security: User must have Manage Groups & Roles permission
if (!AuthenticationManager::getCurrentUser()->isManageGroupsEnabled()) {
    RedirectUtils::redirect('Menu.php');
    exit;
}

//Set the page title
$sPageTitle = gettext('Member Role Change');

//Get the GroupID from the querystring
$iGroupID = InputUtils::legacyFilterInput($_GET['GroupID'], 'int');

//Get the PersonID from the querystring
$iPersonID = InputUtils::legacyFilterInput($_GET['PersonID'], 'int');

//Get the return location flag from the querystring
$iReturn = $_GET['Return'];

//Was the form submitted?
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

//Get their current role
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

//Get all the possible roles
$sSQL = "SELECT * FROM list_lst WHERE lst_ID = $grp_RoleListID ORDER BY lst_OptionSequence";
$rsAllRoles = RunQuery($sSQL);

//Include the header
require 'Include/Header.php'

?>

<form method="post" action="MemberRoleChange.php?GroupID=<?= $iGroupID ?>&PersonID=<?= $iPersonID ?>&Return=<?= $iReturn ?>">

<table cellpadding="4">
    <tr>
        <td align="right"><b><?= gettext('Group Name') ?>:</b></td>
        <td><?php echo $grp_Name ?></td>
    </tr>
    <tr>
        <td align="right"><b><?= gettext("Member's Name") ?>:</b></td>
        <td><?php echo $per_LastName . ', ' . $per_FirstName ?></td>
    </tr>
    <tr>
        <td align="right"><b><?= gettext('Current Role') ?>:</b></td>
        <td><?php echo gettext($sRoleName) ?></td>
    </tr>
    <tr>
        <td align="right"><b><?= gettext('New Role') ?>:</b></td>
        <td>
            <select name="NewRole">
                <?php

                //Loop through all the possible roles
                while ($aRow = mysqli_fetch_array($rsAllRoles)) {
                    extract($aRow);

                    //If this is the current role, select it
                    if ($iRoleID == $lst_OptionID) {
                        $sSelected = 'selected';
                    } else {
                        $sSelected = '';
                    }
                    //Write the <option> tag
                    echo '<option value="' . $lst_OptionID . '" ' . $sSelected . '>' . gettext($lst_OptionName) . '</option>';
                }
                ?>
            </select>
        </td>
    </tr>
    <tr>
        <td colspan="2" align="center">
            <input type="submit" class="btn btn-default" name="Submit" value="<?= gettext('Update') ?>">
            <?php
            if ($iReturn) {
                echo '&nbsp;&nbsp;<input type="button" class="btn btn-default" name="Cancel" value="' . gettext('Cancel') . "\" onclick=\"document.location='GroupView.php?GroupID=" . $iGroupID . "';\">";
            } else {
                echo '&nbsp;&nbsp;<input type="button" class="btn btn-default" name="Cancel" value="' . gettext('Cancel') . "\" onclick=\"document.location='PersonView.php?PersonID=" . $iPersonID . "';\">";
            }
            ?>
        </td>
    </tr>
</table>
</form>

<?php require 'Include/Footer.php' ?>
