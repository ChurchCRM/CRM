<?php

require_once 'Include/Config.php';
require_once 'Include/Functions.php';

$sPageTitle = gettext('Group Listing');
require_once 'Include/Header.php';

use ChurchCRM\Authentication\AuthenticationManager;

?>

<?php
if (AuthenticationManager::getCurrentUser()->isManageGroupsEnabled()) {
    ?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><?= gettext('Add New Group') ?></h3>
    </div>
    <div class="card-body">
        <form action="#" method="get" class="form-inline">
            <div class="form-group mr-2">
                <label for="groupName" class="sr-only"><?= gettext('Group Name') ?></label>
                <input type="text" class="form-control" name="groupName" id="groupName" placeholder="<?= gettext('Enter group name') ?>" required>
            </div>
            <button type="button" class="btn btn-primary" id="addNewGroup">
                <i class="fa fa-plus"></i> <?= gettext('Add Group') ?>
            </button>
        </form>
    </div>
</div>
<br>
    <?php
}
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><?= gettext('Groups') ?></h3>
    </div>
    <div class="card-body">
        <table class="table" id="groupsTable">
        </table>
    </div>
</div>

<script src="skin/js/GroupList.js"></script>

<?php
require_once 'Include/Footer.php';
