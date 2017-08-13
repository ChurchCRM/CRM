<?php
/*******************************************************************************
*
*  filename    : GroupList.php
*  website     : http://www.churchcrm.io
*  copyright   : Copyright 2001, 2002 Deane Barker
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
require 'Include/Header.php'; ?>

<div class="box box-body">
<table class="table" id="groupsTable">
</table>
<?php
if ($_SESSION['bManageGroups']) {
    ?>


<br>
<form action="#" method="get" class="form">
    <label for="addNewGruop"><?= gettext('Add New Group') ?> :</label>
    <input class="form-control newGroup" name="groupName" id="groupName" style="width:100%">
    <br>
    <button type="button" class="btn btn-primary" id="addNewGroup"><?= gettext('Add New Group') ?></button>
</form>
<?php
}
?>

</div>
<script src="skin/js/GroupList.js" type="text/javascript"></script>


<?php
require 'Include/Footer.php';
?>
