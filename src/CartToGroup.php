<?php

require_once 'Include/Config.php';
require_once 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\Cart;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

// Security: User must have Manage Groups & Roles permission
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isManageGroupsEnabled(), 'ManageGroups');

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

$ormGroups = GroupQuery::create()->orderByName()->find();

$sPageTitle = gettext('Add Cart to Group');
require_once 'Include/Header.php';

if (count($_SESSION['aPeopleCart']) > 0) {
    ?>

  <script src="skin/js/GroupRoles.js"></script>

  <!-- Default box -->
  <div class="card">
    <div class="card-body">
      <p class="text-center"><?= gettext('Select the group to which you would like to add your cart') ?>:</p>
      <form method="post">
        <table class="mx-auto">
          <tr>
            <td class="LabelColumn"><?= gettext('Select Group') ?>:</td>
            <td class="TextColumn">
              <?php
              // Create the group select drop-down
                echo '<select id="GroupID" name="GroupID" onChange="UpdateRoles();"><option value="0">' . gettext('None') . '</option>';
                foreach ($ormGroups as $ormGroup) {
                    echo '<option value="' . $ormGroup->getID() . '">' . htmlspecialchars($ormGroup->getName(), ENT_QUOTES, 'UTF-8') . '</option>';
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
        <p class="text-center">
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
        echo '<p class="text-center LargeText">' . gettext('Your cart is empty!') . '</p>';
}

require_once 'Include/Footer.php';
