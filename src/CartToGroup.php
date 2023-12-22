<?php

/*******************************************************************************
 *
 *  filename    : CartToGroup.php
 *  last change : 2003-06-23
 *  description : Add cart records to a group
 *
 *  https://churchcrm.io/
 *  Copyright 2001-2003 Phillip Hullquist, Deane Barker, Chris Gebhardt, Logel Philippe
  *
 ******************************************************************************/

// Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\Cart;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

// Security: User must have Manage Groups & Roles permission
if (!AuthenticationManager::getCurrentUser()->isManageGroupsEnabled()) {
    RedirectUtils::redirect('Menu.php');
    exit;
}

// Was the form submitted?
if ((isset($_GET['groupeCreationID']) || isset($_POST['Submit'])) && count($_SESSION['aPeopleCart']) > 0) {
    // Get the GroupID
    if (isset($_POST['Submit'])) {
        $iGroupID = InputUtils::legacyFilterInput($_POST['GroupID'], 'int');
    } else {
        $iGroupID = InputUtils::legacyFilterInput($_GET['groupeCreationID'], 'int');
    }

    if (array_key_exists('GroupRole', $_POST)) {
        $iGroupRole = InputUtils::legacyFilterInput($_POST['GroupRole'], 'int');
    } else {
        $iGroupRole = 0;
    }

    Cart::emptyToGroup($iGroupID, $iGroupRole);

    $sGlobalMessage = $iCount . ' records(s) successfully added to selected Group.';

    RedirectUtils::redirect('GroupView.php?GroupID=' . $iGroupID . '&Action=EmptyCart');
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
  <div class="card">
    <div class="card-body">
      <p align="center"><?= gettext('Select the group to which you would like to add your cart') ?>:</p>
      <form method="post">
        <table align="center">
          <tr>
            <td class="LabelColumn"><?= gettext('Select Group') ?>:</td>
            <td class="TextColumn">
              <?php
              // Create the group select drop-down
                echo '<select id="GroupID" name="GroupID" onChange="UpdateRoles();"><option value="0">' . gettext('None') . '</option>';
                foreach ($ormGroups as $ormGroup) {
                    echo '<option value="' . $ormGroup->getID() . '">' . $ormGroup->getName() . '</option>';
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
          <BR><BR>--<?= gettext('OR Create a Group and add the CART in ONE ACTION') ?>--<BR><BR>
          <button type="button" id="addToGroup" class="btn btn-info"> <?= gettext('Create Group + ADD Cart') ?> </button>
          <BR><BR>
        </p>
      </form>
    </div></div>
    <?php
} else {
        echo '<p align="center" class="LargeText">' . gettext('Your cart is empty!') . '</p>';
}


require 'Include/Footer.php';
?>
