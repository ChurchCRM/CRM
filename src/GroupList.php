<?php

require_once 'Include/Config.php';
require_once 'Include/Functions.php';

$sPageTitle = gettext('Group Listing');
require_once 'Include/Header.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\Utils\InputUtils;

$rsGroupTypes = ListOptionQuery::create()->filterById('3')->find();

?>

<p>
<label>
<?= gettext("Show type of group:") ?>
<select id="table-filter" class="form-control input-sm">
<option value=""><?= gettext("All") ?></option>
<?php
  echo '<option>' . gettext("Unassigned") . '</option>';
foreach ($rsGroupTypes as $groupType) {
    echo '<option>' . InputUtils::legacyFilterInput($groupType->getOptionName()) . '</option>';
} ?>
</select>
</label>
</p>

<div class="card card-body">
<table class="table" id="groupsTable">
</table>
<?php
if (AuthenticationManager::getCurrentUser()->isManageGroupsEnabled()) {
    ?>

<br>
<form action="#" method="get" class="form">
    <label for="addNewGroup"><?= gettext('Add New Group') ?> :</label>
    <input class="form-control newGroup" name="groupName" id="groupName" style="width:100%">
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
