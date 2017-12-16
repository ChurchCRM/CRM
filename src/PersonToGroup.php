<?php
/*******************************************************************************
 *
 *  filename    : PersonToGroup.php
 *  last change : 2003-06-23
 *  description : Add a person record to a group after selection of group
 *  	and role.  This is a companion script to the Group Assign Helper.
 *
 *  http://www.churchcrm.io/
 *  Copyright 2003 Chris Gebhardt
  *
 ******************************************************************************/

// Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Service\GroupService;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

$groupService = new GroupService();

// Security: User must have Manage Groups & Roles permission
if (!$_SESSION['bManageGroups']) {
    RedirectUtils::Redirect('Menu.php');
    exit;
}

$iPersonID = InputUtils::LegacyFilterInput($_GET['PersonID'], 'int');

// Was the form submitted?
if (isset($_POST['Submit'])) {
    // Get the GroupID
    $iGroupID = InputUtils::LegacyFilterInput($_POST['GroupID'], 'int');
    $iGroupRole = InputUtils::LegacyFilterInput($_POST['GroupRole'], 'int');

    $sPreviousQuery = strip_tags($_POST['prevquery']);
    $groupService->addUserToGroup($iGroupID, $iPersonID, $iGroupRole);

    RedirectUtils::Redirect("SelectList.php?$sPreviousQuery");
} else {
    $sPreviousQuery = strip_tags(rawurldecode($_GET['prevquery']));
}

// Get all the groups
$sSQL = 'SELECT * FROM group_grp ORDER BY grp_Name';
$rsGroups = RunQuery($sSQL);

// Set the page title and include HTML header
$sPageTitle = gettext('Add Person to Group');
require 'Include/Header.php';
?>

<script src="skin/js/GroupRoles.js"></script>

<p align="center"><?= gettext('Select the group to add this person to') ?>:</p>
<form method="post" action="PersonToGroup.php?PersonID=<?= $iPersonID ?>">
  <input type="hidden" name="prevquery" value="<?= $sPreviousQuery ?>">
  <table align="center">
    <tr>
      <td class="LabelColumn"><?= gettext('Select Group') ?>:</td>
      <td class="TextColumn">
        <?php
// Create the group select drop-down
        echo '<select id="GroupID" name="GroupID" onChange="UpdateRoles();"><option value="0">'.gettext('None').'</option>';
        while ($aRow = mysqli_fetch_array($rsGroups)) {
            extract($aRow);
            echo '<option value="'.$grp_ID.'">'.$grp_Name.'</option>';
        }
        echo '</select>';
        ?>
      </td>
    </tr>
    <tr>
      <td class="LabelColumn"><?= gettext('Select Role') ?>:</td>
      <td class="TextColumn"> 
        <select name="GroupRole" id="GroupRole">
          <option><?= gettext('No Group Selected') ?></option>
        </select>
      </td>
    </tr>
  </table>
  <p align="center">
    <BR>
    <input type="submit" class="btn" name="Submit" value="<?= gettext('Add to Group') ?>">
    <BR><BR>
  </p>
</form>

<?php require 'Include/Footer.php' ?>
