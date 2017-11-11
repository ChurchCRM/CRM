<?php
/*******************************************************************************
 *
 *  filename    : CartToGroup.php
 *  last change : 2003-06-23
 *  description : Add cart records to a group
 *
 *  http://www.churchcrm.io/
 *  Copyright 2001-2003 Phillip Hullquist, Deane Barker, Chris Gebhardt
  *
 ******************************************************************************/

// Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Service\GroupService;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\GroupQuery;

$groupService = new GroupService();

// Security: User must have Manage Groups & Roles permission
if (!$_SESSION['bManageGroups']) {
    Redirect('Menu.php');
    exit;
}

// Was the form submitted?
if ((isset($_GET['groupeID']) || isset($_POST['Submit'])) && count($_SESSION['aPeopleCart']) > 0) {

  // Get the GroupID
    $iGroupID = InputUtils::LegacyFilterInput($_POST['GroupID'], 'int');

    if (empty($iGroupID))// if empty
	    $iGroupID = InputUtils::LegacyFilterInput($_GET['groupeID'], 'int');
    
    if (array_key_exists('GroupRole', $_POST)) {
        $iGroupRole = InputUtils::LegacyFilterInput($_POST['GroupRole'], 'int');
    } else {
        $iGroupRole = 0;
    }

    // Loop through the session array
    $iCount = 0;
    while ($element = each($_SESSION['aPeopleCart'])) {
        $groupService->addUserToGroup($iGroupID, $_SESSION['aPeopleCart'][$element['key']], $iGroupRole);
        $iCount += 1;
    }

    $sGlobalMessage = $iCount.' records(s) successfully added to selected Group.';

    Redirect('GroupView.php?GroupID='.$iGroupID.'&Action=EmptyCart');
}

$ormGroups = GroupQuery::Create()
								->orderByName()
								->find();

// Set the page title and include HTML header
$sPageTitle = gettext('Add Cart to Group');
require 'Include/Header.php';

if (count($_SESSION['aPeopleCart']) > 0) {
    ?>

  <script src="skin/js/GroupRoles.js"></script>

  <!-- Default box -->
  <div class="box">
    <div class="box-body">
      <p align="center"><?= gettext('Select the group to which you would like to add your cart') ?>:</p>
      <form method="post">
        <table align="center">
          <tr>
            <td class="LabelColumn"><?= gettext('Select Group') ?>:</td>
            <td class="TextColumn">
              <?php
              // Create the group select drop-down
              echo '<select id="GroupID" name="GroupID" onChange="UpdateRoles();"><option value="0">'.gettext('None').'</option>';
							foreach ($ormGroups as $ormGroup)
							{
								echo '<option value="'.$ormGroup->getID().'">'.$ormGroup->getName().'</option>';
							}
							echo '</select>'; ?>
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
          <input type="submit" class="btn btn-primary" name="Submit" value="<?= gettext('Add to Group') ?>">
          <BR><BR>--<?= gettext('OR') ?>--<BR><BR>
          <button type="button" id="addToGroup" class="btn btn-info"> <?= gettext('Create New Group + add the Cart') ?> </button>
          <BR><BR>
        </p>
      </form>
    </div></div>
  <?php
} else {
        echo '<p align="center" class="LargeText">'.gettext('Your cart is empty!').'</p>';
    }
    

require 'Include/Footer.php';
?>

<script>
$(document).ready(function (e, confirmed) {
  $("#addToGroup").click(function () {
    bootbox.prompt({
      title: i18next.t("Add a Group Name "),
      value: i18next.t("Default Name Group"),
      onEscape: true,
      closeButton: true,
      buttons: {
        confirm: {
          label:  i18next.t('Yes'),
            className: 'btn-success'
        },
        cancel: {
          label:  i18next.t('No'),
          className: 'btn-danger'
        }
      },
      callback: function (result)
      {
      	if (result)
      	{
	      	var newGroup = {'groupName': result};
	      	
					$.ajax({
						method: "POST",
						url: window.CRM.root + "/api/groups/",               //call the groups api handler located at window.CRM.root
						data: JSON.stringify(newGroup),                      // stringify the object we created earlier, and add it to the data payload
						contentType: "application/json; charset=utf-8",
						dataType: "json"
					}).done(function (data) {                               //yippie, we got something good back from the server
						var id = data.Id;
						location.href = 'CartToGroup.php?groupeID='+id;
					});
				}
       }
    });
  });
});

</script>
