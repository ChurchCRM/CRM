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

//Get the GroupID from the querystring
$iGroupID = 0;
if (array_key_exists ("GroupID", $_GET))
    $iGroupID = FilterInput($_GET["GroupID"],'int');

$bEmptyCart = (array_key_exists ("EmptyCart", $_GET) && $_GET["EmptyCart"] == "yes") && 
               array_key_exists ('aPeopleCart', $_SESSION) && count($_SESSION['aPeopleCart']) > 0;

$bNameError = False;
$bErrorFlag = False;
        
//Is this the second pass?
if (isset($_POST["GroupSubmit"]))
{

    //Assign everything locally
    $sName = FilterInputArr($_POST, "Name");
    $iGroupType = FilterInputArr($_POST, "GroupType",'int');
    $iDefaultRole = FilterInputArr($_POST,"DefaultRole",'int');
    $sDescription = FilterInputArr($_POST,"Description");
    $bUseGroupProps = FilterInputArr($_POST,"UseGroupProps");
    $cloneGroupRole = FilterInputArr($_POST,"cloneGroupRole",'int');
    $seedGroupID = FilterInputArr($_POST,"seedGroupID",'int');

    //Did they enter a Name?
    if (strlen($sName) < 1)
    {
        $bNameError = True;
        $bErrorFlag = True;

    }

    // If no errors, then let's update...
    if (!$bErrorFlag)
    {
        // Are we creating a new group?
        if (strlen($iGroupID) < 1)
        {
            //Get a new Role List ID
            $sSQL = "SELECT MAX(lst_ID) FROM list_lst";
            $aTemp = mysql_fetch_array(RunQuery($sSQL));
            if ($aTemp[0] > 9)
                $newListID = $aTemp[0] + 1;
            else
                $newListID = 10;

            if ($bUseGroupProps)
                $sUseProps = 'true';
            else
                $sUseProps = 'false';
            $sSQL = "INSERT INTO group_grp (grp_Name, grp_Type, grp_Description, grp_hasSpecialProps, grp_DefaultRole, grp_RoleListID) VALUES ('" . $sName . "', " . $iGroupType . ", '" . $sDescription . "', '" . $sUseProps . "', '1', " . $newListID . ")";

            $bGetKeyBack = True;
            $bCreateGroupProps = $bUseGroupProps;
        }
        else
        {
            $sSQLtest = "SELECT grp_hasSpecialProps FROM group_grp WHERE grp_ID = " . $iGroupID;
            $rstest = RunQuery($sSQLtest);
            $aRow = mysql_fetch_array($rstest);

            $bCreateGroupProps = ($aRow[0] == 'false') && $bUseGroupProps;
            $bDeleteGroupProps = ($aRow[0] == 'true') && !$bUseGroupProps;

            $sSQL = "UPDATE group_grp SET grp_Name='" . $sName . "', grp_Type='" . $iGroupType . "', grp_Description='" . $sDescription . "'";

            if ($bCreateGroupProps)
                $sSQL .= ", grp_hasSpecialProps = 'true'";

            if ($bDeleteGroupProps)
            {
                $sSQL .= ", grp_hasSpecialProps = 'false'";
                $sSQLp = "DROP TABLE groupprop_" . $iGroupID;
                RunQuery($sSQLp);

                // need to delete the master index stuff
                $sSQLp = "DELETE FROM groupprop_master WHERE grp_ID = " . $iGroupID;
                RunQuery($sSQLp);
            }

            $sSQL .= " WHERE grp_ID = " . $iGroupID;
            $bGetKeyBack = False;
        }

        // execute the SQL
        RunQuery($sSQL);

        //If the user added a new record, we need to key back to the route to the GroupView page
        if ($bGetKeyBack)
        {
            //Get the key back
            $iGroupID = mysql_insert_id($cnInfoCentral);

            if (($cloneGroupRole) && ($seedGroupID>0)) {
                $sSQL = "SELECT list_lst.* FROM list_lst, group_grp WHERE group_grp.grp_RoleListID = list_lst.lst_ID AND group_grp.grp_id = $seedGroupID ORDER BY list_lst.lst_OptionID";
                $rsRoleSeed = RunQuery($sSQL);
                while ($aRow = mysql_fetch_array($rsRoleSeed))
                {
                    extract ($aRow);
                    $useOptionName = mysql_real_escape_string($lst_OptionName);
                    $sSQL = "INSERT INTO list_lst VALUES ($newListID, $lst_OptionID, $lst_OptionSequence, '$useOptionName')";
                    RunQuery($sSQL);
                }
            } else 
            {
                $sSQL = "INSERT INTO list_lst VALUES ($newListID, 1, 1,'Member')";
                RunQuery($sSQL);
            }
        }

        // Create a table for group-specific properties
        if ( $bCreateGroupProps )
        {
            $sSQLp = "CREATE TABLE groupprop_" . $iGroupID . " (
                        per_ID mediumint(8) unsigned NOT NULL default '0',
                        PRIMARY KEY  (per_ID),
                          UNIQUE KEY per_ID (per_ID)
                        ) ENGINE=MyISAM;";
            RunQuery($sSQLp);

            // If this is an existing group, add rows in this table for each member
            if ( !$bGetKeyBack )
            {
                $sSQL = "SELECT per_ID FROM person_per INNER JOIN person2group2role_p2g2r ON per_ID = p2g2r_per_ID WHERE p2g2r_grp_ID = " . $iGroupID . " ORDER BY per_ID";
                $rsGroupMembers = RunQuery($sSQL);

                while ($aRow = mysql_fetch_array($rsGroupMembers))
                {
                    $sSQLr = "INSERT INTO groupprop_" . $iGroupID . " ( `per_ID` ) VALUES ( '" . $aRow[0] . "' );";
                    RunQuery($sSQLr);
                }
            }
        }

        if (array_key_exists ("EmptyCart", $_POST) && $_POST["EmptyCart"] && count($_SESSION['aPeopleCart']) > 0)
        {
            $iCount = 0;
            while ($element = each($_SESSION['aPeopleCart'])) {
                AddToGroup($_SESSION['aPeopleCart'][$element['key']],$iGroupID,$iDefaultRole);
                $iCount += 1;
            }

            $sGlobalMessage = $iCount . " records(s) successfully added to selected Group.";

            Redirect("GroupEditor.php?GroupID=" . $iGroupID . "&Action=EmptyCart");
        }
        else
        {
            Redirect("GroupEditor.php?GroupID=$iGroupID");
        }
    }

}
else
{
    //FirstPass
    //Are we editing or adding?
    if (strlen($iGroupID) > 0)
    {
        //Editing....
        //Get the information on this familyAge Groups for the drop down
        $sSQL = "SELECT * FROM group_grp WHERE grp_ID = " . $iGroupID;
        $rsGroup = RunQuery($sSQL);
        $aRow = mysql_fetch_array($rsGroup);

        $iGroupID = $aRow["grp_ID"];
        $iGroupType = $aRow["grp_Type"];
        $iDefaultRole = $aRow["grp_DefaultRole"];
        $iRoleListID = $aRow["grp_RoleListID"];
        $sName = $aRow["grp_Name"];
        $sDescription = $aRow["grp_Description"];
        $bHasSpecialProps = ($aRow["grp_hasSpecialProps"] == 'true');
    }
}

// Get Group Types for the drop-down
$sSQL = "SELECT * FROM list_lst WHERE lst_ID = 3 ORDER BY lst_OptionSequence";
$rsGroupTypes = RunQuery($sSQL);

//Group Group Role List 
$sSQL = "SELECT * FROM group_grp WHERE grp_RoleListID > 0 ORDER BY grp_Name";
$rsGroupRoleSeed = RunQuery($sSQL);

require "Include/Header.php";

?>
<link rel="stylesheet" type="text/css" href="<?= $sURLPath; ?>/vendor/almasaeed2010/adminlte/plugins/datatables/dataTables.bootstrap.css">
<script src="<?= $sURLPath; ?>/vendor/almasaeed2010/adminlte/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="<?= $sURLPath; ?>/vendor/almasaeed2010/adminlte/plugins/datatables/dataTables.bootstrap.js"></script>


<link rel="stylesheet" type="text/css" href="<?= $sURLPath; ?>/vendor/almasaeed2010/adminlte/plugins/datatables/extensions/TableTools/css/dataTables.tableTools.css">
<script type="text/javascript" language="javascript" src="<?= $sURLPath; ?>/vendor/almasaeed2010/adminlte/plugins/datatables/extensions/TableTools/js/dataTables.tableTools.min.js"></script>

<script type="text/javascript">
bStatus = false;

function confirmDelete() {
    if (!bStatus) {
        bStatus = confirm(<?php echo "'" . gettext("Are you sure you want to remove the group-specific person properties?  All group member properties data will be lost!") . "'"; ?>);
        document.GroupEdit.UseGroupProps.checked = !bStatus;
    }
    else
        bStatus = false;
}
function confirmAdd() {
    if (!bStatus) {
        bStatus = confirm(<?php echo "'" . gettext("This will create a group-specific properties table for this group.  You should then add needed properties with the Group-Specific Properties Form Editor.") . "'"; ?>);
        document.GroupEdit.UseGroupProps.checked = bStatus;
    }
    else
        bStatus = false;
}
</script>

<div class="box">
<div class="box-header">
<h3 class="box-title">Group Settings</h3>
</div>
<div class="box-body">
    <form name="GroupEdit" method="post" action="GroupEditor.php?GroupID=<?php echo $iGroupID ?>">
    <div class="form-group">
            <div class="row">
            <div class="col-xs-4">
                <label for="Name"><?php echo gettext("Name:"); ?></label>
                <input class="form-control" type="text" Name="Name" value="<?php echo htmlentities(stripslashes($sName),ENT_NOQUOTES, "UTF-8"); ?>">
                    <br>
                    <?php if ($bNameError) echo "<font color=\"red\">" . gettext("You must enter a name.") . "</font>"; ?><br>
            </div>
            </div>
            <div class="row">
            <div class="col-xs-4">
                <label for="Description"><?php echo gettext("Description:"); ?></label>
                <textarea  class="form-control" name="Description" cols="40" rows="5"><?php echo htmlentities(stripslashes($sDescription),ENT_NOQUOTES, "UTF-8"); ?></textarea></td>
            </div>
            </div>
            <div class="row">
            <div class="col-xs-3">
                    <label for="GroupType"><?php echo gettext("Type of Group:"); ?></label>
                    <select class="form-control input-small" name="GroupType">
                        <option value="0"><?php echo gettext("Unassigned"); ?></option>
                        <option value="0">-----------------------</option>
                        <?php
                        while ($aRow = mysql_fetch_array($rsGroupTypes))
                        {
                            extract($aRow);
                            echo "<option value=\"" . $lst_OptionID . "\"";
                            if ($iGroupType == $lst_OptionID)
                                echo " selected";
                            echo ">" . $lst_OptionName . "</option>";
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
                        while ($aRow = mysql_fetch_array($rsGroupRoleSeed))
                        {
                            extract($aRow);
                            echo "<option value=\"" . $grp_ID . "\">" . $grp_Name . "</option>";
                        }
                        echo "</select>";
                    ?>

            <?php } ?>
            </div>
            </div>
            <div class="row">
            <div class="col-xs-3">
                <label for="UseGroupProps"><?php echo gettext("Use group-specific properties?"); ?></label>
                <?php
                    if ($bHasSpecialProps)
                    {
                        echo "<input  type=\"checkbox\" name=\"UseGroupProps\" value=\"1\" onChange=\"confirmDelete();\" checked><br><br>";
                        echo "<a class=\"SmallText\" href=\"GroupPropsFormEditor.php?GroupID=$iGroupID\">" . gettext("Edit Group-Specific Properties Form") . "</a>";
                    }
                    else
                        echo "<input type=\"checkbox\" name=\"UseGroupProps\" value=\"1\" onChange=\"confirmAdd();\">";
                ?>
                <label for="EmptyCart"><?php echo gettext("Empty Cart to this Group?"); ?></label>
                <input type="checkbox" name="EmptyCart" value="1" <?php if ($bEmptyCart) { echo " checked"; } ?>></td>
            </div>
            </div>
            <div class="row">    
            <div class="col-xs-3">
                <input type="submit" class="btn btn-primary" <?php echo 'value="' . gettext("Save") . '"'; ?> Name="GroupSubmit">
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
    <table class="table" id="roleTable">
        <thead>
        <tr>
            <th>Role Name</th>
            <th>Make Default</th>
            <th>Sequence</th>
            <th>Move Up</th>
            <th>Move Down</th>
            <th>Delete</th>
        </tr>
        </thead>
        
    <?
  // <!-- -->
  $groupService = new GroupService();
  $group = $groupService->getGroupByID($iGroupID);
  foreach ($group['roles'] as $role)
  {?>
    <tr id="roleRow-<?php echo $role["lst_OptionID"];?>">
        <td><input type="text" class="form-control roleName" id="roleName-<?php echo $role["lst_OptionID"];?>" name="roleName" value="<?php echo $role['lst_OptionName'] ?>"></td>
        <td><?php if($group['grp_DefaultRole'] == $role['lst_OptionID'])  //If the role we're looking at now is equal to the default role property of the group, then echo the default string.  Otherwise, give the user a button.
        {?>
        <strong><i class="fa fa-check"></i> Default</strong>
        <?php } else { ?>
        <button type="button" id="defaultRole-<?php echo $role["lst_OptionID"];?>" class="btn btn-success defaultRole">Default</button><?php } ?>
        </td>
        <td><?php echo $role['lst_OptionSequence'];?></td>
        <td><button type="button" id="roleUp-<?php echo $role["lst_OptionID"];?>" class="btn rollOrder" <?php if($role['lst_OptionSequence']==1){echo "disabled";}?>><i class="fa fa-arrow-up"></i></button></td>
        <td><button type="button" id="roleDown-<?php echo $role["lst_OptionID"];?>" class="btn rollOrder" <?php if($role['lst_OptionSequence']==count($group['roles'])){echo "disabled";}?>><i class="fa fa-arrow-down"></i></button></td>
        <td><button type="button" class="btn btn-danger deleteRole">Delete</button></td>
        
    </tr>
  <?php
  }
  ?>
  </table>
  <button type="button" class="btn btn-primary">Add New Role</button>
  
  
  <?php
    
    
    
  
}
else
{
    ?><b class="MediumLargeText"><?php echo gettext("Initial Group Creation:  Group roles can be edited after the first save."); ?></b><br><br><?php
}
?>
</div></div>
<script>
var defaultRoleID= <?php echo $group['grp_DefaultRole'] ?>;
var dataT = 0;
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

$("document").ready(function(){
    initHandlers(); // initialize the event handlers when the document is ready.  Don't do it here, since we need to be able to initialize these handlers on the fly in response to user action.
    
    dataT = $("#roleTable").DataTable({
        "order":    [[2,"asc"]]
        
    });
    
});

function configureButtons(roleID,roleSequence,totalRoles)
{
   
   if (roleSequence == 1)
   {
        console.log("setting " +roleID+" to down only");
        $("#roleUp-"+roleID).prop('disabled',true);
        $("#roleDown-"+roleID).prop('disabled',false);
   }
   else if (roleSequence == totalRoles)
   {
        console.log("setting " +roleID+" to up only");
        $("#roleDown-"+roleID).prop('disabled',true);
        $("#roleUp-"+roleID).prop('disabled',false);
   }
   else
   {
        console.log("setting " +roleID+" to both");
       $("#roleUp-"+roleID).prop('disabled',false);
       $("#roleDown-"+roleID).prop('disabled',false);
   }
}


function initHandlers()  //funciton to initialize the JQuery button event handlers
{
    $(".deleteRole").click(function(e) {
        var roleID = e.currentTarget.id.split("-")[1];
        var groupID=<?php echo $iGroupID?>;
        var roleID=e.target.id.split("-")[1];
        $.ajax({
            method: "DELETE",
            url:    "/api/groups/"+groupID+"/roles/"+roleID
        }).done(function(data){
        });
    });
    

    $(".rollOrder").click(function (e) {
       var roleID = e.currentTarget.id.split("-")[1];
       var roleSequenceAction =  e.currentTarget.id.split("-")[0];
       console.log(roleSequenceAction);
       var newRoleSequence =0;
       var currentRoleSequence = dataT.cell("#roleRow-"+roleID,2).data();
       console.log("currentRoleSequence: "+currentRoleSequence);
       var totalRoles = dataT.data().length
       console.log("totalRoles: "+totalRoles);
       if (roleSequenceAction == "roleUp")
       {
           newRoleSequence = Number(currentRoleSequence)-1;
       }
       else if(roleSequenceAction == "roleDown")
       {
           newRoleSequence = Number(currentRoleSequence)+1;
       }
       console.log("newRoleSequence:" +newRoleSequence);
       sequenceColumn = dataT.columns(2).data()[0];
       console.log(sequenceColumn);
       for (var i=0;i< sequenceColumn.length;i++)
       {
           console.log("comparing" +sequenceColumn[i] + " with " +newRoleSequence);
           if (sequenceColumn[i] == newRoleSequence)
           {
               var rowID = $(dataT.row(i).node()).attr("id").split("-")[1];
               console.log("Row ID: "+rowID+" found at position: "+i+"Setting to: "+currentRoleSequence );
               dataT.cell(i,2).data(String(currentRoleSequence));
               configureButtons(rowID,currentRoleSequence,totalRoles)
           }
       }

       dataT.cell("#roleRow-"+roleID,2).data(String(newRoleSequence));
       configureButtons(roleID,newRoleSequence,totalRoles)      
       dataT.order([2,'asc']);
       dataT.draw(true);
    });
    
    
    $(".roleName").change(function(e){
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
            initHandlers(); // re-register the JQuery handlers since we changed the DOM, and new buttons will not have an action bound.
        });
    }); 
}
</script>
<?php
require "Include/Footer.php";
?>