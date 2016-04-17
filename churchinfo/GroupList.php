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
*
*
*  Copyright Contributors
*
*  ChurchCRM is free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  This file best viewed in a text editor with tabs stops set to 4 characters
*
******************************************************************************/
//Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';
require 'Service/GroupService.php';

//Set the page title
$sPageTitle = gettext('Group Listing');
$groupService = new GroupService();
require 'Include/Header.php'; ?>

<div class="box box-body">
<table class="table" id="groupsTable">
</table>
<?php
if ($_SESSION['bManageGroups'])
{ ?>


<br>
<form action="#" method="get" class="form">
    <label for="addNewGruop"><?= gettext("Add New Group: ") ?></label>
    <input class="form-control newGroup" name="groupName" id="groupName" style="width:100%">
    <br>
    <button type="button" class="btn btn-primary" id="addNewGroup">Add New Group</button>
</form>
<?php
}
?>

</div>
<script>
    //get the group data from the GroupService.
    var groupData = <?php $json = $groupService->getGroupJSON($groupService->getGroups()); if ($json) { echo $json; } else { echo 0; } ?>;    
    //if the data is not an array, make it into one so that the dataTables code can parse it correctly.
    if (!$.isArray(groupData.groups))
    {
        groupData.groups=[groupData.groups];
    }
    var dataT = 0; //make a new global variable for the dataTable.
</script>
<script src="skin/js/GroupList.js" type="text/javascript"></script>
<?php
require 'Include/Footer.php';
?>
