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

 var groupData = <?php $json = $groupService->getGroupJSON($groupService->getGroups()); if ($json) { echo $json; } else { echo 0; } ?>;

if (!$.isArray(groupData.groups))
{
    groupData.groups=[groupData.groups];
}
console.log(groupData.groups);
var dataT = 0;
$(document).ready(function() {

    $("#addNewGroup").click(function (e){
        var newGroup = {'groupName':$("#groupName").val()};
        console.log(newGroup);
        $.ajax({
            method: "POST",
            url:   window.CRM.root + "/api/groups",
            data:  JSON.stringify(newGroup)
        }).done(function(data){
            console.log(data);
            dataT.row.add(data);
            dataT.rows().invalidate().draw(true);
        });
    });

    dataT = $("#groupsTable").DataTable({
    data:groupData.groups,
    columns: [
    {
        width: 'auto',
        title:'Group Name',
        data:'groupName',
        render: function  (data, type, full, meta ) {
            return '<a href=\'GroupView.php?GroupID='+full.id+'\'><span class="fa-stack"><i class="fa fa-square fa-stack-2x"></i><i class="fa fa-search-plus fa-stack-1x fa-inverse"></i></span></a><a href=\'GroupEditor.php?GroupID='+full.id+'\'><span class="fa-stack"><i class="fa fa-square fa-stack-2x"></i><i class="fa fa-pencil fa-stack-1x fa-inverse"></i></span></a>'+data;
        }
    },
    {
        width: 'auto',
        title:'Members',
        data:'memberCount',
        searchable: false
    },
    {
        width: 'auto',
        title:'Group Cart Status',
        data:'groupCartStatus',
        searchable: false,
        render: function  (data, type, full, meta ) {

            if(data)
            {
                return "<span>All members of this group are in the cart</span><a onclick=\"saveScrollCoordinates()\" class=\"btn btn-danger\"  href=\"GroupList.php?RemoveGroupFromPeopleCart="+full.id+"\">Remove all</a>";
            }
            else
            {
                 return "<span>Not all members of this group are in the cart</span><br><a onclick=\"saveScrollCoordinates()\" class=\"btn btn-primary\" href=\"GroupList.php?AddGroupToPeopleCart="+full.id+"\">Add all</a>";
            }
        }
    },
    {
        width: 'auto',
        title:'Group Type',
        data:'groupType',
        searchable: true
    }
    ]
});
});
</script>

<?php
require 'Include/Footer.php';
?>
