<?php

/*******************************************************************************
 *
 *  filename    : GroupEditor.php
 *  last change : 2003-04-15
 *  website     : https://churchcrm.io
 *  copyright   : Copyright 2001, 2002, 2003 Deane Barker, Chris Gebhardt
 *                Copyright 2004-2012 Michael Wilt
 *
 ******************************************************************************/

//Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\Service\GroupService;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

// Security: User must have Manage Groups permission
if (!AuthenticationManager::getCurrentUser()->isManageGroupsEnabled()) {
    RedirectUtils::redirect('Menu.php');
    exit;
}

//Set the page title
$sPageTitle = gettext('Group Editor');
$groupService = new GroupService();
//Get the GroupID from the querystring.  Redirect to Menu if no groupID is present, since this is an edit-only form.
if (array_key_exists('GroupID', $_GET)) {
    $iGroupID = InputUtils::legacyFilterInput($_GET['GroupID'], 'int');
} else {
    RedirectUtils::redirect('GroupList.php');
}

$thisGroup = GroupQuery::create()->findOneById($iGroupID);   //get this group from the group service.
$rsGroupTypes = ListOptionQuery::create()->filterById('3')->find();     // Get Group Types for the drop-down
$rsGroupRoleSeed = GroupQuery::create()->filterByRoleListId(['min' => 0], $comparison)->find();     //Group Group Role List
require 'Include/Header.php';
?>
<!-- GROUP SPECIFIC PROPERTIES MODAL-->
<div class="modal fade" id="groupSpecificPropertiesModal" tabindex="-1" role="dialog" aria-labelledby="deleteGroup" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title" id="gsproperties-label"></h4>
      </div>
      <div class="modal-body">
        <span style="color: red"></span>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal"><?= gettext('Close')?></button>
        <button name="setgroupSpecificProperties" id="setgroupSpecificProperties" type="button" class="btn btn-danger"></button>
      </div>
    </div>
  </div>
</div>
<!-- END GROUP SPECIFIC PROPERTIES MODAL-->

<div class="card">
  <div class="card-header">
    <h3 class="card-title"><?= (($thisGroup->isSundaySchool()) ? gettext("Special Group Settings : Sunday School Type") : gettext('Group Settings')) ?></h3>
  </div>
  <div class="card-body">
    <form name="groupEditForm" id="groupEditForm">
      <div class="form-group">
        <div class="row">
          <div class="col-sm-4">
            <label for="Name"><?= gettext('Name') ?>:</label>
            <input class="form-control" type="text" Name="Name" value="<?= htmlentities(stripslashes($thisGroup->getName()), ENT_NOQUOTES, 'UTF-8') ?>">
          </div>
        </div>
        <div class="row">
          <div class="col-sm-4">
            <label for="Description"><?= gettext('Description') ?>:</label>
            <textarea  class="form-control" name="Description" cols="40" rows="5"><?= htmlentities(stripslashes($thisGroup->getDescription()), ENT_NOQUOTES, 'UTF-8') ?></textarea></td>
          </div>
        </div>
        <div class="row">
          <div class="col-sm-3">
            <label for="GroupType"><?= gettext('Type of Group') ?>:</label>
            <?php
            if ($thisGroup->isSundaySchool()) {
                $hide = "style=\"display:none;\"";
            } else {
                $hide = "";
            }
            ?>
            <select class="form-control input-small" name="GroupType" <?= $hide ?>>
              <option value="0"><?= gettext('Unassigned') ?></option>
              <option value="" disabled>-----------------------</option>
              <?php
                foreach ($rsGroupTypes as $groupType) {
                    echo '<option value="' . $groupType->getOptionId() . '"';
                    if ($thisGroup->getType() == $groupType->getOptionId()) {
                        echo ' selected';
                    }
                    echo '>' . $groupType->getOptionName() . '</option>';
                } ?>
            </select>
            <?php
            if ($thisGroup->isSundaySchool()) {
                ?>
                <b><?= gettext("Sunday School") ?></b>
                <p><?= gettext("Sunday School group can't be modified, only in this two cases :")?></p>
                <ul>
                                <li>
                                    <?= gettext("You can create/delete sunday school group. ")?>
                                </li>
                                <li>
                                    <?= gettext("Add new roles, but not modify or rename the Student and the Teacher roles.")?>
                                </li>
                </ul>
                <?php
            } ?>
          </div>
        </div>
        <div class="row">
          <div class="col-sm-3">
            <?php
// Show Role Clone fields only when adding new group
            if (strlen($iGroupID) < 1) {
                ?>
              <b><?= gettext('Group Member Roles') ?>:</b>

                <?= gettext('Clone roles') ?>:
              <input type="checkbox" name="cloneGroupRole" id="cloneGroupRole" value="1">
            </div>
            <div class="col-sm-3" id="selectGroupIDDiv">
                <?= gettext('from group') ?>:
              <select class="form-control input-small" name="seedGroupID" id="seedGroupID" >
                <option value="0"><?php gettext('Select a group'); ?></option>

                <?php
                foreach ($rsGroupRoleSeed as $groupRoleTemplate) {
                    echo '<option value="' . $groupRoleTemplate['grp_ID'] . '">' . $groupRoleTemplate['grp_Name'] . '</option>';
                } ?>
              </select><?php
            }
            ?>
          </div>
        </div>
        <br>
        <div class="row">
          <div class="col-sm-6">
            <label for="UseGroupProps"><?= gettext('Group Specific Properties: ') ?></label>

            <?php
            if ($thisGroup->getHasSpecialProps()) {
                echo gettext('Enabled') . '<br/>';
                echo '<button type="button" id="disableGroupProps" class="btn btn-danger groupSpecificProperties">' . gettext('Disable Group Specific Properties') . '</button><br/>';
                echo '<a  class="btn btn-success" href="GroupPropsFormEditor.php?GroupID=' . $iGroupID . '">' . gettext('Edit Group-Specific Properties Form') . ' </a>';
            } else {
                echo gettext('Disabled') . '<br/>';
                echo '<button type="button" id="enableGroupProps" class="btn btn-danger groupSpecificProperties">' . gettext('Enable Group Specific Properties') . '</button>&nbsp;';
            }
            ?>
          </div>
        </div>
        <br>
        <div class="row">
          <div class="col-sm-3">
            <input type="submit" id="saveGroup" class="btn btn-primary" <?= 'value="' . gettext('Save') . '"' ?> Name="GroupSubmit">
          </div>
        </div>
      </div>
    </form>
  </div>
</div>
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><?= gettext('Group Roles') ?>:</h3>
  </div>
  <div class="card-body">
    <div class="alert alert-info alert-dismissable">
      <i class="fa fa-info"></i>
      <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
      <strong></strong><?= gettext('Group role name changes are saved as soon as the box loses focus')?>
    </div>
      <div class="table-responsive">
    <table class="table" class="table" id="groupRoleTable">
    </table>
      </div>
    <label for="newRole"><?= gettext('New Role')?>: </label><input type="text" class="form-control" id="newRole" name="newRole">
    <br>
    <button type="button" id="addNewRole" class="btn btn-primary"><?= gettext('Add New Role')?></button>
  </div>
</div>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
  //setup some document-global variables for later on in the javascript
  var defaultRoleID = <?= ($thisGroup->getDefaultRole() ? $thisGroup->getDefaultRole() : 1) ?>;
  var dataT = 0;
  var groupRoleData = <?= json_encode($groupService->getGroupRoles($iGroupID)); ?>;
  var roleCount = groupRoleData.length;
  var groupID =<?= $iGroupID ?>;
</script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/js/GroupEditor.js"></script>

<?php require 'Include/Footer.php' ?>
