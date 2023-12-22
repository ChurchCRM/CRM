<?php

/*******************************************************************************
*
*  filename    : GroupList.php
*  website     : https://churchcrm.io
*  copyright   : Copyright 2001, 2002 Deane Barker
*  update      : 2017-11-02, Philippe Logel
*
*
*  Additional Contributors:
*  2006 Ed Davis
*  2016 Charles Crossan

******************************************************************************/

//Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

//Set the page title
$sPageTitle = gettext('Group Listing');
require 'Include/Header.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;

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
    echo '<option>' . $groupType->getOptionName() . '</option>';
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
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
$( document).ready(function() {
    var gS = localStorage.getItem("groupSelect");
    if (gS != null)
    {
        tf = document.getElementById("table-filter");
        tf.selectedIndex = gS;

        window.groupSelect = tf.value;
    }
});

</script>

<?php
require 'Include/Footer.php';
?>
