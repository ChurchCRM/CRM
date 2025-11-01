<?php

require_once 'Include/Config.php';
require_once 'Include/Functions.php';

$sPageTitle = gettext('Group Listing');
require_once 'Include/Header.php';

use ChurchCRM\Authentication\AuthenticationManager;

?>

<div class="card card-body">
<table class="table" id="groupsTable">
</table>
<?php
if (AuthenticationManager::getCurrentUser()->isManageGroupsEnabled()) {
    ?>

<br>
<form action="#" method="get" class="form">
    <label for="addNewGroup"><?= gettext('Add New Group') ?> :</label>
    <input class="form-control newGroup w-100" name="groupName" id="groupName">
    <br>
    <div class="text-right">
        <button type="button" class="btn btn-primary" id="addNewGroup"><?= gettext('Add New Group') ?></button>
    </div>
</form>
    <?php
}
?>
</div>

<script src="skin/js/GroupList.js"></script>

<?php
require_once 'Include/Footer.php';
