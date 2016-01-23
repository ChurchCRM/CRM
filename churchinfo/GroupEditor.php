<?php
/*******************************************************************************
 *
 *  filename    : GroupEditor.php
 *  last change : 2003-04-15
 *  website     : http://www.churchcrm.io
 *  copyright   : Copyright 2001, 2002, 2003 Deane Barker, Chris Gebhardt
 *                Copyright 2004-2012 Michael Wilt
 *
 *  ChurchCRM is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

//Include the function library
require "Include/Config.php";
require "Include/Functions.php";
require "service/GroupService.php";

// Security: User must have Manage Groups permission
if (!$_SESSION['bManageGroups'])
{
    Redirect("Menu.php");
    exit;
}

//Set the page title
$sPageTitle = gettext("Group Editor");
$groupService = new GroupService();
//Get the GroupID from the querystring
if (array_key_exists ("GroupID", $_GET))
    $iGroupID = FilterInput($_GET["GroupID"],'int');
else
{
    Redirect("GroupList.php");
}
$bEmptyCart = (array_key_exists ("EmptyCart", $_GET) && $_GET["EmptyCart"] == "yes") && 
               array_key_exists ('aPeopleCart', $_SESSION) && count($_SESSION['aPeopleCart']) > 0;
$bNameError = False;

//get this group from the group service.
$thisGroup = $groupService->getGroups($iGroupID);
// Get Group Types for the drop-down
$rsGroupTypes = $groupService->getGroupTypes();
//Group Group Role List 
$rsGroupRoleSeed = $groupService->getGroupRoleTemplateGroups();
require "Include/Header.php";

?>
<link rel="stylesheet" type="text/css" href="<?= $sURLPath; ?>/vendor/almasaeed2010/adminlte/plugins/datatables/dataTables.bootstrap.css">
<script src="<?= $sURLPath; ?>/vendor/almasaeed2010/adminlte/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="<?= $sURLPath; ?>/vendor/almasaeed2010/adminlte/plugins/datatables/dataTables.bootstrap.js"></script>


<link rel="stylesheet" type="text/css" href="<?= $sURLPath; ?>/vendor/almasaeed2010/adminlte/plugins/datatables/extensions/TableTools/css/dataTables.tableTools.css">
<script type="text/javascript" language="javascript" src="<?= $sURLPath; ?>/vendor/almasaeed2010/adminlte/plugins/datatables/extensions/TableTools/js/dataTables.tableTools.min.js"></script>


<!-- GROUP SPECIFIC PROPERTIES MODAL-->
     <div class="modal fade" id="groupSpecificPropertiesModal" tabindex="-1" role="dialog" aria-labelledby="deleteGroup" aria-hidden="true">
            <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                            <h4 class="modal-title" id="gsproperties-label"></h4>
                        </div>
                        <div class="modal-body">
                        <span style="color: red">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                            <button name="setgroupSpecificProperties" id="setgroupSpecificProperties" type="button" class="btn btn-danger">asdf</button>
                        </div>
                    </div>
            </div>
        </div>
<!-- END GROUP SPECIFIC PROPERTIES MODAL-->

<div class="box">
<div class="box-header">
<h3 class="box-title">Group Settings</h3>
</div>
<div class="box-body">

    <form name="GroupEdit" id = "groupEditForm" method="post" action="GroupEditor.php?GroupID=<?php echo $iGroupID ?>">
    <div class="form-group">
            <div class="row">
            <div class="col-xs-4">
                <label for="Name"><?php echo gettext("Name:"); ?></label>
                <input class="form-control" type="text" Name="Name" value="<?php echo htmlentities(stripslashes($thisGroup['groupName']),ENT_NOQUOTES, "UTF-8"); ?>">
                    <br>
                    <?php if ($bNameError) echo "<font color=\"red\">" . gettext("You must enter a name.") . "</font>"; ?><br>
            </div>
            </div>
            <div class="row">
            <div class="col-xs-4">
                <label for="Description"><?php echo gettext("Description:"); ?></label>
                <textarea  class="form-control" name="Description" cols="40" rows="5"><?php echo htmlentities(stripslashes($thisGroup['groupDescription']),ENT_NOQUOTES, "UTF-8"); ?></textarea></td>
            </div>
            </div>
            <div class="row">
            <div class="col-xs-3">
            
                    <label for="GroupType"><?php echo gettext("Type of Group:"); ?></label>
                    <select class="form-control input-small" name="GroupType">
                        <option value="0"><?php echo gettext("Unassigned"); ?></option>
                        <option value="0">-----------------------</option>
                        <?php
                        foreach ($rsGroupTypes as $groupType)
                        {
                            echo "<option value=\"" . $groupType['lst_OptionID'] . "\"";
                            if ($thisGroup['grp_Type'] == $groupType['lst_OptionID'])
                                echo " selected";
                            echo ">" . $groupType['lst_OptionName']."</option>";
                        }
                        ?>
                    </select>
            </div>
            </div>
            <div class="row">
            <div class="col-xs-3">
                <?php 
                // Show Role Clone fields only when adding new group
                if (strlen($iGroupID) < 1) { ?>
                    <b><?php echo gettext("Group Member Roles:"); ?></b>
                    
                    <?php echo gettext("Clone roles:"); ?>
                    <input type="checkbox" name="cloneGroupRole" id="cloneGroupRole" value="1">
                    </div>
                    <div class="col-xs-3" id="selectGroupIDDiv">
                    <?php echo gettext("from group:"); ?>
                    <select class="form-control input-small" name="seedGroupID" id="seedGroupID" >
                    <option value="0"><?php gettext("Select a group"); ?></option>
                    
                    <?php
                       foreach($rsGroupRoleSeed as $groupRoleTemplate)
                        {
                            echo "<option value=\"" . $groupRoleTemplate['grp_ID'] . "\">" . $groupRoleTemplate['grp_Name'] . "</option>";
                        }
                        echo "</select>";
                    ?>

            <?php } ?>
            </div>
            </div>
            <br>
            <div class="row">
            <div class="col-xs-6">
                <label for="UseGroupProps"><?php echo gettext("Group Specific Properties: "); ?></label>
           
                <?php
                    if ($thisGroup['grp_hasSpecialProps'])
                    {
                        echo "Enabled" ;
                        echo '<div class="btn-group"><button type="button" id="disableGroupProps" class="btn btn-danger">Disable Group Specific Properties</button>&nbsp;';
                        echo '<button type="button" class="btn btn-success groupSpecificProperties">Edit Group Specific Properties</button></div>';// href=\"GroupPropsFormEditor.php?GroupID=$iGroupID\">" . gettext("Edit Group-Specific Properties Form") . "</a>";
                    }
                    else
                        echo "Disabled <br>" ;
                        echo '<button type="button" id="enableGroupProps" class="btn btn-danger groupSpecificProperties">Enable Group Specific Properties</button>&nbsp;'; 
                ?>
            </div>
            </div>
            <br>
            <div class="row">    
            <div class="col-xs-3">
                <input type="submit" id="saveGroup" class="btn btn-primary" <?php echo 'value="' . gettext("Save") . '"'; ?> Name="GroupSubmit">
            </div>
            </div>
        </div>
    </form>

</div>
</div>
<div class="box">
<div class="box-header">
<h3 class="box-title"><?php echo gettext("Group Roles:"); ?></h3>
</div>
<div class="box-body">

<div class="alert alert-info alert-dismissable">
		<i class="fa fa-info"></i>
		<button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
		<strong></strong>Group name changes are saved as soon as the box loses focus</div>

<?php
if (strlen($iGroupID) > 0)
{
    ?>
    <table class="table" id="groupRoleTable">
</table>

  <label for="newRole">New Role: </label><input type="text" class="form-control" id="newRole" name="newRole">
  <br>
  <button type="button" id="addNewRole" class="btn btn-primary">Add New Role</button>
  
  
  <?php
    
    
    
  
}
else
{
    ?><b class="MediumLargeText"><?php echo gettext("Initial Group Creation:  Group roles can be edited after the first save."); ?></b><br><br><?php
}
?>
</div></div>
<script>
var defaultRoleID= <?php echo ($thisGroup['grp_DefaultRole']?  $thisGroup['grp_DefaultRole'] : 1) ?>;
var dataT = 0;
var groupRoleData = <?php echo json_encode($groupService->getGroupRoles($iGroupID)); ?>;
var roleCount = groupRoleData.length;

$("document").ready(function(){
       
    $(".groupSpecificProperties").click(function (e){
            var groupPropertyAction = e.currentTarget.id;
            if (groupPropertyAction == "enableGroupProps")
            {
                $("#groupSpecificPropertiesModal").modal("show");
                $("#gsproperties-label").text("Confirm Enable Group Specific Properties");
                $("#groupSpecificPropertiesModal .modal-body span").text("This will create a group-specific properties table for this group.  You should then add needed properties with the Group-Specific Properties Form Editor.");
                $("#setgroupSpecificProperties").text("Enable Group Specific Properties");
            }
            else
            {
                  $("#groupSpecificPropertiesModal").modal("show");
                $("#gsproperties-label").text("Confirm Disable Group Specific Properties");
                $("#groupSpecificPropertiesModal .modal-body span").text("Are you sure you want to remove the group-specific person properties?  All group member properties data will be lost!");
                $("#setgroupSpecificProperties").text("Disable Group Specific Properties");
            }
    });
    
    

    $("#selectGroupIDDiv").hide();
    
    $("#cloneGroupRole").click(function(e){
    if (e.target.checked)
        $("#selectGroupIDDiv").show();
    else
    {
        $("#selectGroupIDDiv").hide();
        $("#seedGroupID").prop('selectedIndex',0);
    }
    });

    $("#groupEditForm").submit(function(e) {
        e.preventDefault();
        var groupID=<?php echo $iGroupID?>;
        var formData ={
            "groupName": $("input[name='Name']").val(),
            "description": $("textarea[name='Description']").val(),
            "groupType" : $("select[name='GroupType'] option:selected").val()
            
        };
        console.log(formData);
        $.ajax({
            method: "POST",
            url:   "/api/groups/"+groupID,
            data:  JSON.stringify(formData)
        }).done(function(data){
           console.log(data);
        });

    });
    
    $("#addNewRole").click(function(e) {
        var newRoleName = $("#newRole").val();
        var groupID=<?php echo $iGroupID?>;
        $.ajax({
            method: "POST",
            url:    "/api/groups/"+groupID+"/roles",
            data:  '{"roleName":"'+newRoleName+'"}'
        }).done(function(data){
            var newRole = data.newRole;
            var newRow={"lst_OptionName":newRole.roleName,"lst_OptionID":newRole.roleID,"lst_OptionSequence":newRole.sequence};
            roleCount+=1;
            var node = dataT.row.add(newRow).node();
            dataT.rows().invalidate().draw(true);
            $("#newRole").val('');
            //location.reload(); // this shouldn't be necessary
        });
        
    });
    
    $(document).on('click','.deleteRole', function(e) {
        var roleID = e.currentTarget.id.split("-")[1];
        var groupID=<?php echo $iGroupID?>;
        console.log("deleting group role: "+roleID);
        $.ajax({
            method: "DELETE",
            url:    "/api/groups/"+groupID+"/roles/"+roleID
        }).done(function(data){
            console.log(data);
            dataT.clear();
            dataT.rows.add(data);
            dataT.rows().invalidate().draw(true);
            
            
            
        });
    });
    
    $(document).on('click','.rollOrder', function (e) {
       var groupID=<?php echo $iGroupID?>;
       var roleID = e.currentTarget.id.split("-")[1]; // get the ID of the role that we're manipulating
       var roleSequenceAction =  e.currentTarget.id.split("-")[0];  //determine whether we're increasing or decreasing this role's sequence number
       var newRoleSequence =0;      //create a variable at the function scope to store the new role's sequence
       var currentRoleSequence = dataT.cell(function(idx,data,node) { if  (data.lst_OptionID == roleID){console.log(data); return true;} } ,2).data(); //get the sequence number of the selected role
       console.log("current sequence: "+currentRoleSequence);
       if (roleSequenceAction == "roleUp")
       {
           newRoleSequence = Number(currentRoleSequence)-1;  //decrease the role's sequence number
       }
       else if(roleSequenceAction == "roleDown")
       {
           newRoleSequence = Number(currentRoleSequence)+1; // increase the role's sequenc number
       }
       //try
       //{
            replaceRow = dataT.row(function(idx,data,node) { if  (data.lst_OptionSequence == newRoleSequence){return true;}});
            console.log("------------");
            var d = replaceRow.data();
            console.log(d);
            d.lst_OptionSequence=currentRoleSequence;
            setGroupRoleOrder(groupID,d.lst_OptionID,d.lst_OptionSequence);
            console.log(d);
            replaceRow.data(d);
            console.log("************");
       //}
       //catch(err)
       //{
        //   console.log("no cells to replace - something was funky.");
       //}
      dataT.cell(function(idx,data,node) { if  (data.lst_OptionID == roleID){return true;}}, 2).data(newRoleSequence); // set our role to the new sequence number
      setGroupRoleOrder(groupID,roleID,newRoleSequence);       
      dataT.rows().invalidate().draw(true);
      dataT.order([[ 2, "asc" ]]).draw();
      
    });
    
    $(document).on('change','.roleName',function(e){
        var groupID=<?php echo $iGroupID?>;
        var groupRoleName = e.target.value;
        var roleID=e.target.id.split("-")[1];
        $.ajax({
            method: "POST",
            url:    "/api/groups/"+groupID+"/roles/"+roleID,
            data: '{"groupRoleName":"'+groupRoleName+'"}'
        }).done(function(data){
        });
        
    });
    
    $(".defaultRole").click(function(e){
        var groupID=<?php echo $iGroupID?>;
        var roleID=e.target.id.split("-")[1];
        $.ajax({
            method: "POST",
            url:    "/api/groups/"+groupID+"/defaultRole",
            data: '{"roleID":"'+roleID+'"}'
        }).done(function(data){
            $(".table tr:gt(0)").each(function(){  //iterate through all rows of the role table, skipping the first row (index0) using JQuery gt selector
                var rowID = $(this).attr("id").split("-")[1]; //get the Role ID based on the html ID attribute
                if ( rowID== roleID)  // If the row we're on is the row conatining the "default" button that was clicked 
                {
                     $("td:nth-child(2)", this).empty(); // empty the third TD element
                     $("td:nth-child(2)", this).html('<strong><i class="fa fa-check"></i> Default</strong>');  //replace the button with the [Check] Default Text
                }
                else if (rowID== defaultRoleID)  // if the row we're on is the row containing the previuos default role
                {
                    $("td:nth-child(2)", this).empty();  // empty the third TD element.
                     $("td:nth-child(2)", this).html('<button type="button" id="defaultRole-'+rowID+'" class="btn btn-success defaultRole">Default</button></td>');  //replace the [Check] Default text with a button to allow the user to set this as default again
                }
            }
            );
            defaultRoleID=roleID; //update the local variable representing the default role id
             // re-register the JQuery handlers since we changed the DOM, and new buttons will not have an action bound.
        });
    }); 

    dataT =  $("#groupRoleTable").DataTable({
    data:groupRoleData,
    columns: [
        {
            width: 'auto',
            title:'Role Name',
            data:'lst_OptionName',
            render: function  (data, type, full, meta ) {
                if ( type === 'display')
                    return '<input type="text" value="'+data+'">'; 
                else
                    return data;
            }
        },
        {
            width: 'auto',
            title:'Make Default',
            render: function  (data, type, full, meta ) {
                   if (full.lst_OptionID == defaultRoleID)
                   {
                       return "<strong><i class=\"fa fa-check\"></i> Default</strong>";
                   }
                   else
                   {
                       return '<button type="button" id="defaultRole-'+ full.lst_OptionID +'" class="btn btn-success defaultRole">Default</button>';
                   }
            }
        },
        {
            width: '200px',
            title:'Sequence',
            data:'lst_OptionSequence',
            className: "dt-body-center",
            render: function  (data, type, full, meta ) {
                if (type === 'display'){
                var sequenceCell = "";
                if( data > 1 )
                {
                    sequenceCell += '<button type="button" id="roleUp-'+full.lst_OptionID+'" class="btn rollOrder"> <i class="fa fa-arrow-up"></i></button>&nbsp;';
                }
                sequenceCell += data;
                console.log(roleCount);
                if (data != roleCount)
                {
                    sequenceCell += '&nbsp;<button type="button" id="roleDown-'+full.lst_OptionID+'" class="btn rollOrder"> <i class="fa fa-arrow-down"></i></button>';
                }
                return sequenceCell;
                }
                else
                {
                    return data;
                }
            }
        },
         {
            width: 'auto',
            title:'Delete',
            render: function  (data, type, full, meta ) {
                return '<button type="button" id="roleDelete-'+full.lst_OptionID+'" class="btn btn-danger deleteRole">Delete</button>';
                   
            }
        },
        
    ],
    "order": [[ 3, "asc" ]]
    });
    
     // initialize the event handlers when the document is ready.  Don't do it here, since we need to be able to initialize these handlers on the fly in response to user action.
});


function setGroupRoleOrder(groupID,roleID,groupRoleOrder)
{
    $.ajax({
        method: "POST",
        url:    "/api/groups/"+groupID+"/roles/"+roleID,
        data:   '{"groupRoleOrder":"'+groupRoleOrder+'"}'
    }).done(function(data){
    });
}



</script>
<?php
require "Include/Footer.php";
?>