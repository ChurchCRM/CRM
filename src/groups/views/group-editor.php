<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>
<!-- GROUP SPECIFIC PROPERTIES MODAL-->
<div class="modal fade" id="groupSpecificPropertiesModal" tabindex="-1" role="dialog" aria-labelledby="deleteGroup" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        <h4 class="modal-title" id="gsproperties-label"></h4>
      </div>
      <div class="modal-body">
        <span class="text-danger"></span>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= gettext('Close') ?></button>
        <button name="setgroupSpecificProperties" id="setgroupSpecificProperties" type="button" class="btn btn-danger"></button>
      </div>
    </div>
  </div>
</div>
<!-- END GROUP SPECIFIC PROPERTIES MODAL-->

<div class="card">
  <div class="card-header d-flex align-items-center">
    <h3 class="card-title"><?= ($thisGroup->isSundaySchool()) ? gettext('Special Group Settings : Sunday School Type') : gettext('Group Settings') ?></h3>
  </div>
  <div class="card-body p-4">
    <form name="groupEditForm" id="groupEditForm">
      <div class="row mb-4">
        <div class="col-sm-4">
          <label for="Name" class="form-label"><?= gettext('Name') ?>:</label>
          <input class="form-control" type="text" Name="Name" value="<?= InputUtils::escapeAttribute($thisGroup->getName()) ?>">
        </div>
      </div>
      <div class="row mb-4">
        <div class="col-sm-3">
          <label for="GroupType" class="form-label"><?= gettext('Type of Group') ?>:</label>
          <?php if ($thisGroup->isSundaySchool()): ?>
          <select class="form-select input-small d-none" name="GroupType">
          <?php else: ?>
          <select class="form-select input-small" name="GroupType">
          <?php endif; ?>
            <option value="0"><?= gettext('Unassigned') ?></option>
            <option value="" disabled>-----------------------</option>
            <?php foreach ($rsGroupTypes as $groupType): ?>
              <option value="<?= InputUtils::escapeAttribute($groupType->getOptionId()) ?>"<?= $thisGroup->getType() == $groupType->getOptionId() ? ' selected' : '' ?>><?= InputUtils::escapeHTML($groupType->getOptionName()) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if ($thisGroup->isSundaySchool()): ?>
              <div class="mt-3">
                <p class="fw-semibold mb-2"><?= gettext('Sunday School') ?></p>
                <p class="text-muted small"><?= gettext("Sunday School group can't be modified, only in this two cases") ?>:</p>
                <ul class="ps-3">
                    <li><?= gettext('You can create/delete sunday school group.') ?></li>
                    <li><?= gettext('Add new roles, but not modify or rename the Student and the Teacher roles.') ?></li>
                </ul>
              </div>
          <?php endif; ?>
        </div>
      </div>
      <div class="row mb-4">
        <div class="col-sm-4">
          <label for="Description" class="form-label"><?= gettext('Description') ?>:</label>
          <textarea class="form-control" name="Description" cols="40" rows="5"><?= InputUtils::escapeAttribute($thisGroup->getDescription()) ?></textarea>
        </div>
      </div>
      <div class="row">
        <div class="col-sm-6">
          <input type="submit" id="saveGroup" class="btn btn-primary" value="<?= gettext('Save') ?>" Name="GroupSubmit">
        </div>
      </div>
    </form>
  </div>
</div>
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><?= gettext('Group Specific Properties') ?></h3>
  </div>
  <div class="card-body p-4">
    <div class="d-flex align-items-center justify-content-between">
      <?php if ($thisGroup->getHasSpecialProps()): ?>
        <div>
          <p class="text-muted mb-2"><?= gettext('Custom properties for this group') ?></p>
          <span class="badge bg-success"><?= gettext('Enabled') ?></span>
        </div>
        <div class="d-flex gap-2">
          <button type="button" id="disableGroupProps" class="btn btn-sm btn-ghost-danger groupSpecificProperties">
            <i class="fa-solid fa-toggle-on me-1"></i><?= gettext('Disable') ?>
          </button>
          <a class="btn btn-sm btn-primary" href="<?= $sRootPath ?>/groups/<?= $iGroupID ?>/properties/form">
            <i class="fa-solid fa-pen me-1"></i><?= gettext('Edit Form') ?>
          </a>
        </div>
      <?php else: ?>
        <div>
          <p class="text-muted mb-2"><?= gettext('Add custom properties specific to this group') ?></p>
          <span class="badge bg-secondary"><?= gettext('Disabled') ?></span>
        </div>
        <button type="button" id="enableGroupProps" class="btn btn-sm btn-ghost-success groupSpecificProperties">
          <i class="fa-solid fa-toggle-off me-1"></i><?= gettext('Enable') ?>
        </button>
      <?php endif; ?>
    </div>
  </div>
</div>
<div class="card">
  <div class="card-header d-flex align-items-center justify-content-between">
    <h3 class="card-title"><?= gettext('Group Roles') ?>:</h3>
    <button type="button" class="btn btn-success" id="addNewRoleBtn" data-bs-toggle="modal" data-bs-target="#addRoleModal">
      <i class="fa-solid fa-plus me-1"></i><?= gettext('Add Role') ?>
    </button>
  </div>
  <div class="card-body p-4">
    <div class="alert alert-info alert-dismissable mb-4">
      <i class="fa-solid fa-info"></i>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      <strong></strong><?= gettext('Group role name changes are saved as soon as the box loses focus') ?>
    </div>
    <div class="table-responsive">
      <table class="table" id="groupRoleTable">
      </table>
    </div>
  </div>
</div>
<!-- ADD ROLE MODAL -->
<div class="modal fade" id="addRoleModal" tabindex="-1" role="dialog" aria-labelledby="addRoleModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addRoleModalLabel"><?= gettext('Add New Role') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= gettext('Close') ?>"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="newRole" class="form-label"><?= gettext('Role Name') ?></label>
          <input type="text" class="form-control" id="newRole" name="newRole"
            placeholder="<?= gettext('Enter role name') ?>" maxlength="50" autocomplete="off">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= gettext('Cancel') ?></button>
        <button type="button" id="submitNewRole" class="btn btn-success" disabled>
          <i class="fa-solid fa-plus me-1"></i><?= gettext('Add Role') ?>
        </button>
      </div>
    </div>
  </div>
</div>
<!-- END ADD ROLE MODAL -->
<!-- DELETE ROLE CONFIRMATION MODAL -->
<div class="modal fade" id="deleteRoleModal" tabindex="-1" role="dialog" aria-labelledby="deleteRoleModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="deleteRoleModalLabel"><?= gettext('Delete Confirmation') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= gettext('Close') ?>"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-warning d-none" id="lastRoleWarning">
          <i class="fa-solid fa-triangle-exclamation me-1"></i>
          <?= gettext('This is the only role in this group.') ?>
        </div>
        <p id="deleteRoleMessage"></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= gettext('Cancel') ?></button>
        <button type="button" id="confirmDeleteRole" class="btn btn-danger">
          <i class="fa-solid fa-trash me-1"></i><?= gettext('Delete') ?>
        </button>
      </div>
    </div>
  </div>
</div>
<!-- END DELETE ROLE CONFIRMATION MODAL -->
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
  var defaultRoleID = <?= ($thisGroup->getDefaultRole() ? $thisGroup->getDefaultRole() : 1) ?>;
  var dataT = 0;
  var groupRoleData = <?= json_encode($groupRoleData) ?>;
  var roleCount = groupRoleData.length;
  var groupID = <?= $iGroupID ?>;
</script>
<script src="<?= SystemURLs::assetVersioned('/skin/js/GroupEditor.js') ?>"></script>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
