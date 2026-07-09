<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\Cart;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\view\PageHeader;

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

    $iCount = isset($_SESSION['aPeopleCart']) ? count($_SESSION['aPeopleCart']) : 0;
    Cart::emptyToGroup($iGroupID, $iGroupRole);

    $_SESSION['sGlobalMessage'] = sprintf(ngettext('%d Person successfully added to selected Group.', '%d People successfully added to selected Group.', $iCount), $iCount);
    $_SESSION['sGlobalMessageClass'] = 'success';

    RedirectUtils::redirect('groups/view/' . $iGroupID);
}

$ormGroups = GroupQuery::create()->orderByName()->find();

$sPageTitle = gettext('Add Cart to Group');
$sPageSubtitle = gettext('Assign cart items to a group');
$aBreadcrumbs = PageHeader::breadcrumbs([
    [gettext('Groups'), '/groups/dashboard'],
    [gettext('Add Cart to Group')],
]);
require_once __DIR__ . '/Include/Header.php';

if (count($_SESSION['aPeopleCart']) > 0) {
    ?>

  <script src="skin/js/GroupRoles.js"></script>

  <div class="card">
    <div class="card-body">
      <p class="mb-3"><?= gettext('Select the group to which you would like to add your cart') ?>:</p>
      <form method="post">
        <div class="mb-3">
          <label class="form-label" for="GroupID"><?= gettext('Select Group') ?>:</label>
          <select id="GroupID" name="GroupID" class="form-select" onChange="UpdateRoles();">
            <option value="0"><?= gettext('None') ?></option>
            <?php foreach ($ormGroups as $ormGroup) {
                echo '<option value="' . InputUtils::escapeAttribute($ormGroup->getID()) . '">' . InputUtils::escapeHTML($ormGroup->getName()) . '</option>';
            } ?>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label" for="GroupRole"><?= gettext('Select Role') ?>:</label>
          <select name="GroupRole" id="GroupRole" class="form-select">
            <option><?= gettext('No Group Selected') ?></option>
          </select>
        </div>

        <div class="d-flex flex-column align-items-center gap-2">
          <input type="submit" class="btn btn-primary" name="Submit" value="<?= gettext('Add to Group') ?>">
          <hr class="w-100">
          <p class="text-secondary mb-1">— <?= gettext('OR Create a Group and add the CART in ONE ACTION') ?> —</p>
          <button type="button" id="addToGroup" class="btn btn-info"><?= gettext('Create Group + ADD Cart') ?></button>
        </div>
      </form>
    </div>
  </div>
    <?php
} else {
    echo '<div class="alert alert-warning">' . gettext('Your cart is empty!') . '</div>';
}

require_once __DIR__ . '/Include/Footer.php';
