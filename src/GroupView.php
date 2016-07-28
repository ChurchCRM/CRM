<?php
/*******************************************************************************
*
*  filename    : GroupView.php
*  website     : http://www.churchcrm.io
*  copyright   : Copyright 2001-2003 Deane Barker, Chris Gebhardt
*
*  Additional Contributors:
*  2006-2007 Ed Davis
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
$sPageTitle = gettext('Group View');

//Get the GroupID out of the querystring
$iGroupID = FilterInput($_GET['GroupID'],'int');
$personService = new PersonService();
$groupService = new GroupService();


//Do they want to add this group to their cart?
if (array_key_exists('Action', $_GET) && $_GET['Action'] == 'AddGroupToCart')
{
    //Get all the members of this group
    $sSQL = 'SELECT per_ID FROM person_per, person2group2role_p2g2r WHERE per_ID = p2g2r_per_ID AND p2g2r_grp_ID = ' . $iGroupID;
    $rsGroupMembers = RunQuery($sSQL);

    //Loop through the recordset
    while ($aRow = mysql_fetch_array($rsGroupMembers))
    {
        extract($aRow);

        //Add each person to the cart
        AddToPeopleCart($per_ID);
    }
}

//Get the data on this group
$sSQL = 'SELECT * FROM group_grp WHERE grp_ID = ' . $iGroupID;
$aGroupData = mysql_fetch_array(RunQuery($sSQL));
extract($aGroupData);

//Look up the default role name
$sSQL = "SELECT lst_OptionName FROM list_lst WHERE lst_ID = $grp_RoleListID AND lst_OptionID = " . $grp_DefaultRole;
$aDefaultRole = mysql_fetch_array(RunQuery($sSQL));
$sDefaultRole = $aDefaultRole[0];

//Get the count of members
$sSQL = 'SELECT COUNT(*) AS iTotalMembers FROM person2group2role_p2g2r WHERE p2g2r_grp_ID = ' . $iGroupID;
$rsTotalMembers = mysql_fetch_array(RunQuery($sSQL));
extract($rsTotalMembers);

//Get the group's type name
if ($grp_Type > 0)
{
    $sSQL = 'SELECT lst_OptionName FROM list_lst WHERE lst_ID = 3 AND lst_OptionID = ' . $grp_Type;
    $rsGroupType = mysql_fetch_array(RunQuery($sSQL));
    $sGroupType = $rsGroupType[0];
}
else
    $sGroupType = gettext('Undefined');

//Get the Properties assigned to this Group
$sSQL = "SELECT pro_Name, pro_ID, pro_Prompt, r2p_Value, prt_Name, pro_prt_ID
        FROM record2property_r2p
        LEFT JOIN property_pro ON pro_ID = r2p_pro_ID
        LEFT JOIN propertytype_prt ON propertytype_prt.prt_ID = property_pro.pro_prt_ID
        WHERE pro_Class = 'g' AND r2p_record_ID = " . $iGroupID .
        " ORDER BY prt_Name, pro_Name";
$rsAssignedProperties = RunQuery($sSQL);

//Get all the properties
$sSQL = "SELECT * FROM property_pro WHERE pro_Class = 'g' ORDER BY pro_Name";
$rsProperties = RunQuery($sSQL);

// Lookup the Group's Name from GroupID
$sSQL = 'SELECT grp_Name FROM group_grp WHERE grp_ID = ' . $iGroupID;
$rsGrpName = RunQuery($sSQL);
$aTemp = mysql_fetch_array($rsGrpName);

// Get data for the form as it now exists..
$sSQL = 'SELECT * FROM groupprop_master WHERE grp_ID = ' . $iGroupID . ' ORDER BY prop_ID';
$rsPropList = RunQuery($sSQL);
$numRows = mysql_num_rows($rsPropList);

require 'Include/Header.php'; ?>

<div class="box">
    <div class="box-header with-border">
        <h3 class="box-title">Group Functions</h3>
    </div>
    <div class="box-body">

<?php

if ($_SESSION['bManageGroups'])
{
    echo '<a class="btn btn-app" href="GroupEditor.php?GroupID=' . $grp_ID . '"><i class="fa fa-pencil"></i>' . gettext('Edit this Group') . '</a>';
    echo '<a class="btn btn-app" data-toggle="modal" data-target="#deleteGroup"><i class="fa fa-trash"></i>' . gettext('Delete this Group') . '</a>';
    ?>
    <!-- GROUP DELETE MODAL-->
     <div class="modal fade" id="deleteGroup" tabindex="-1" role="dialog" aria-labelledby="deleteGroup" aria-hidden="true">
            <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                            <h4 class="modal-title" id="upload-Image-label"><?= gettext("Confirm Delete Group") ?></h4>
                        </div>
                        <div class="modal-body">
                        <span style="color: red">
                           <?= gettext("Please confirm deletion of this group record:") ?>

                             <p class="ShadedBox">
                                <?= $grp_Name ?>
                            </p>

                             <p class="LargeError">
                                <?= gettext("This will also delete all Roles and Group-Specific Property data associated with this Group record.") ?>
                            </p>
                            <?= gettext("All group membership and properties will be destroyed.  The group members themselves will not be altered.") ?>
                            <br><br>
                            <span style="color:black">I Understand &nbsp;<input type="checkbox" name="chkClear"id="chkClear" ></span>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                            <button name="deleteGroupButton" id="deleteGroupButton" type="button" class="btn btn-danger" disabled><?= gettext("Delete Group") ?></button>
                        </div>
                    </div>
            </div>
        </div>
    <!--END GROUP DELETE MODAL-->

    <!-- MEMBER ROLE MODAL-->
     <div class="modal fade" id="changeMembership" tabindex="-1" role="dialog" aria-labelledby="deleteGroup" aria-hidden="true">
            <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                            <h4 class="modal-title" id="upload-Image-label">Change Member Role</h4>
                        </div>
                        <div class="modal-body">
                        <span style="color: red"><?= gettext("Please select target role for member:") ?></span>
                        <input type="hidden" id="changeingMemberID">
                        <p class="ShadedBox" id="changingMemberName"></p>
                        <select name="newRoleSelection" id="newRoleSelection">
                        <?php foreach ($groupService->getGroupRoles($iGroupID) as $role)
                        {
                            echo '<option value="'.$role['lst_OptionID'].'">'.$role['lst_OptionName'].'</option>';
                        }
                        ?>
                        </select>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                            <button name="confirmMembershipChange" id="confirmMembershipChange" type="button" class="btn btn-danger"><?= gettext("Change Membership") ?></button>
                        </div>
                    </div>
            </div>
        </div>
    <!--END MEMBER ROLE MODAL-->



    <?php
    if ($grp_hasSpecialProps == 'true')
    {
        echo '<a class="btn btn-app" href="GroupPropsFormEditor.php?GroupID=' . $grp_ID . '"><i class="fa fa-list-alt"></i>' . gettext('Edit Group-Specific Properties Form') . '</a>';
    }
}
echo '<a class="btn btn-app" href="GroupView.php?Action=AddGroupToCart&amp;GroupID=' . $grp_ID . '"><i class="fa fa-users"></i>' . gettext('Add Group Members to Cart') . '</a>';
echo '<a class="btn btn-app" href="GroupMeeting.php?GroupID=' . $grp_ID . '&amp;Name=' . $grp_Name . '&amp;linkBack=GroupView.php?GroupID=' . $grp_ID . '"><i class="fa fa-calendar-o"></i>' . gettext('Schedule a meeting') . '</a>';

echo '<a class="btn btn-app" href="MapUsingGoogle.php?GroupID=' . $grp_ID . '"><i class="fa fa-map-marker"></i>' . gettext('Map this group') . '</a>';

// Email Group link
// Note: This will email entire group, even if a specific role is currently selected.
$sSQL = "SELECT per_Email, fam_Email, lst_OptionName as virt_RoleName
            FROM person_per
            LEFT JOIN person2group2role_p2g2r ON per_ID = p2g2r_per_ID
            LEFT JOIN group_grp ON grp_ID = p2g2r_grp_ID
            LEFT JOIN family_fam ON per_fam_ID = family_fam.fam_ID
            INNER JOIN list_lst on  grp_RoleListID = lst_ID AND p2g2r_rle_ID = lst_OptionID
        WHERE per_ID NOT IN
            (SELECT per_ID
                FROM person_per
                INNER JOIN record2property_r2p ON r2p_record_ID = per_ID
                INNER JOIN property_pro ON r2p_pro_ID = pro_ID AND pro_Name = 'Do Not Email')
            AND p2g2r_grp_ID = " . $iGroupID;
$rsEmailList = RunQuery($sSQL);
$sEmailLink = '';
while (list ($per_Email, $fam_Email, $virt_RoleName) = mysql_fetch_row($rsEmailList))
{
    $sEmail = SelectWhichInfo($per_Email, $fam_Email, False);
    if ($sEmail)
    {
        /* if ($sEmailLink) // Don't put delimiter before first email
            $sEmailLink .= $sMailtoDelimiter; */
        // Add email only if email address is not already in string
        if (!stristr($sEmailLink, $sEmail))
        {
            $sEmailLink .= $sEmail .= $sMailtoDelimiter;
            $roleEmails->$virt_RoleName .= $sEmail.= $sMailtoDelimiter;
        }
    }
}
if ($sEmailLink)
{
    // Add default email if default email has been set and is not already in string
    if ($sToEmailAddress != '' && $sToEmailAddress != 'myReceiveEmailAddress'
                               && !stristr($sEmailLink, $sToEmailAddress))
        $sEmailLink .= $sMailtoDelimiter . $sToEmailAddress;
    $sEmailLink = urlencode($sEmailLink);  // Mailto should comply with RFC 2368
    
    if ($bEmailMailto) { // Does user have permission to email groups
    // Display link
     ?>
      <div class="btn-group">
        <a  class="btn btn-app" href="mailto:<?= mb_substr($sEmailLink,0,-3) ?>"><i class="fa fa-send-o"></i><?= gettext('Email Group')?></a>
        <button type="button" class="btn btn-app dropdown-toggle" data-toggle="dropdown" >
          <span class="caret"></span>
          <span class="sr-only">Toggle Dropdown</span>
        </button>
        <ul class="dropdown-menu" role="menu">
         <?php generateGroupRoleEmailDropdown($roleEmails,"mailto:") ?>
        </ul>
      </div>
    
      <div class="btn-group">
        <a class="btn btn-app" href="mailto:?bcc=<?= mb_substr($sEmailLink,0,-3) ?>"><i class="fa fa-send"></i><?=gettext('Email (BCC)') ?></a>
         <button type="button" class="btn btn-app dropdown-toggle" data-toggle="dropdown" >
          <span class="caret"></span>
          <span class="sr-only">Toggle Dropdown</span>
        </button>
        <ul class="dropdown-menu" role="menu">
         <?php generateGroupRoleEmailDropdown($roleEmails,"mailto:?bcc=") ?>
        </ul>
      </div>
    
    <?php
    }
    
}
// Group Text Message Comma Delimited - added by RSBC
// Note: This will provide cell phone numbers for the entire group, even if a specific role is currently selected.
$sSQL = "SELECT per_CellPhone, fam_CellPhone
            FROM person_per
            LEFT JOIN person2group2role_p2g2r ON per_ID = p2g2r_per_ID
            LEFT JOIN group_grp ON grp_ID = p2g2r_grp_ID
            LEFT JOIN family_fam ON per_fam_ID = family_fam.fam_ID
        WHERE per_ID NOT IN
            (SELECT per_ID
            FROM person_per
            INNER JOIN record2property_r2p ON r2p_record_ID = per_ID
            INNER JOIN property_pro ON r2p_pro_ID = pro_ID AND pro_Name = 'Do Not SMS')
        AND p2g2r_grp_ID = " . $iGroupID;
$rsPhoneList = RunQuery($sSQL);
$sPhoneLink = '';
$sCommaDelimiter = ', ';
while (list ($per_CellPhone, $fam_CellPhone) = mysql_fetch_row($rsPhoneList))
{
    $sPhone = SelectWhichInfo($per_CellPhone, $fam_CellPhone, False);
    if ($sPhone)
    {
        /* if ($sPhoneLink) // Don't put delimiter before first phone
            $sPhoneLink .= $sCommaDelimiter; */
        // Add phone only if phone is not already in string
        if (!stristr($sPhoneLink, $sPhone))
            $sPhoneLink .= $sPhone .= $sCommaDelimiter;
    }
}
if ($sPhoneLink)
{
    if ($bEmailMailto) { // Does user have permission to email groups

    // Display link
    echo '<a class="btn btn-app" href="javascript:void(0)" onclick="allPhonesCommaD()"><i class="fa fa-mobile-phone"></i> Text Group</a>';
    echo '<script>function allPhonesCommaD() {prompt("Press CTRL + C to copy all group members\' phone numbers", "'. mb_substr($sPhoneLink,0,-2) .'")};</script>';
    }
}

?>
</div>
</div>




<div class="box">
    <div class="box-header with-border">
        <h3 class="box-title">Group Properties</h3>
    </div>
    <div class="box-body">

<table border="0" width="100%" cellspacing="0" cellpadding="5">
<tr>
    <td width="25%" valign="top" align="center">
        <div class="LightShadedBox">
            <b class="LargeText"><?= $grp_Name ?></b>
            <br>
            <?= $grp_Description ?>
            <br><br>
            <table width="98%">
                <tr>
                    <td align="center"><div class="TinyShadedBox"><font size="3">
                    <?= gettext('Total Members:') ?> <?= $iTotalMembers ?>
                    <br>
                    <?= gettext('Type of Group:') ?> <?= $sGroupType ?>
                    <br>
                    <?= gettext('Default Role:') ?> <?= $sDefaultRole ?>
                    </font></div></td>
                </tr>
            </table>
        </div>
    </td>
    <td width="75%" valign="top" align="left">

    <b><?= gettext('Group-Specific Properties:') ?></b>

    <?php
    if ($grp_hasSpecialProps == 'true')
    {
        // Create arrays of the properties.
        for ($row = 1; $row <= $numRows; $row++)
        {
            $aRow = mysql_fetch_array($rsPropList, MYSQL_BOTH);
            extract($aRow);

            $aNameFields[$row] = $prop_Name;
            $aDescFields[$row] = $prop_Description;
            $aFieldFields[$row] = $prop_Field;
            $aTypeFields[$row] = $type_ID;
        }

        // Construct the table

        if (!$numRows)
        {
            echo '<p>No member properties have been created</p>';
        }
        else
        {
            ?>
            <table width="100%" cellpadding="2" cellspacing="0">
            <tr class="TableHeader">
                <td><?= gettext('Type') ?></td>
                <td><?= gettext('Name') ?></td>
                <td><?= gettext('Description') ?></td>
            </tr>
            <?php

            $sRowClass = 'RowColorA';
            for ($row=1; $row <= $numRows; $row++)
            {
                $sRowClass = AlternateRowStyle($sRowClass);
                echo '<tr class="'.$sRowClass.'">';
                echo '<td>' . $aPropTypes[$aTypeFields[$row]] . '</td>';
                echo '<td>' . $aNameFields[$row] . '</td>';
                echo '<td>' . $aDescFields[$row] . '&nbsp;</td>';
                echo '</tr>';
            }
            echo '</table>';
        }
    }
    else
        echo '<p>' . gettext('Disabled for this group.') . '</p>';

    //Print Assigned Properties
    echo '<br>';
    echo '<b>' . gettext('Assigned Properties:') . '</b>';
    $sAssignedProperties = ',';

    //Was anything returned?
    if (mysql_num_rows($rsAssignedProperties) == 0)
    {
        // No, indicate nothing returned
        echo '<p align="center">' . gettext('No property assignments.') . '</p>';
    }
    else
    {
        // Display table of properties
        ?>
        <table width="100%" cellpadding="2" cellspacing="0">
        <tr class="TableHeader">
        <td width="15%" valign="top"><b><?= gettext('Type') ?></b>
        <td valign="top"><b><?= gettext('Name') ?></b>
        <td valign="top"><b><?= gettext('Value') ?></td>
        <?php

        if ($_SESSION['bManageGroups'])
        {
            echo '<td valign="top"><b>' . gettext('Edit Value') . '</td>';
            echo '<td valign="top"><b>' . gettext('Remove') . '</td>';
        }
        echo '</tr>';

        $last_pro_prt_ID = '';
        $bIsFirst = true;

        //Loop through the rows
        while ($aRow = mysql_fetch_array($rsAssignedProperties))
        {
            $pro_Prompt = '';
            $r2p_Value = '';

            extract($aRow);

            if ($pro_prt_ID != $last_pro_prt_ID)
            {
                echo '<tr class="';
                if ($bIsFirst)
                    echo 'RowColorB';
                else
                    echo 'RowColorC';
                echo '"><td><b>' . $prt_Name . '</b></td>';

                $bIsFirst = false;
                $last_pro_prt_ID = $pro_prt_ID;
                $sRowClass = 'RowColorB';
            }
            else
            {
                echo '<tr class="' . $sRowClass . '">';
                echo '<td valign="top">&nbsp;</td>';
            }

            echo '<td valign="top">' . $pro_Name . '&nbsp;</td>';
            echo '<td valign="top">' . $r2p_Value . '&nbsp;</td>';

            if (strlen($pro_Prompt) > 0 && $_SESSION['bManageGroups'])
            {
                echo '<td valign="top"><a href="PropertyAssign.php?GroupID=' . $iGroupID . '&amp;PropertyID=' . $pro_ID . '">' . gettext('Edit Value') . '</a></td>';
            }
            else
            {
                echo '<td>&nbsp;</td>';
            }

            if ($_SESSION['bManageGroups'])
            {
                echo '<td valign="top"><a href="PropertyUnassign.php?GroupID=' . $iGroupID . '&amp;PropertyID=' . $pro_ID . '">' . gettext('Remove') . '</a>';
            }
            else
            {
                echo '<td>&nbsp;</td>';
            }

            echo '</tr>';

            //Alternate the row style
            $sRowClass = AlternateRowStyle($sRowClass);

            $sAssignedProperties .= $pro_ID . ",";
        }

        echo '</table>';
    }

    if ($_SESSION['bManageGroups'])
    {
        echo '<form method="post" action="PropertyAssign.php?GroupID=' . $iGroupID . '">';
        echo '<p align="center">';
        echo '<span>' . gettext('Assign a New Property:') . '</span>';
        echo '<select name="PropertyID">';

        while ($aRow = mysql_fetch_array($rsProperties))
        {
            extract($aRow);

            //If the property doesn't already exist for this Person, write the <OPTION> tag
            if (strlen(strstr($sAssignedProperties,',' . $pro_ID . ',')) == 0)
            {
                echo '<option value="' . $pro_ID . '">' . $pro_Name . '</option>';
            }

        }

        echo '</select>';
        echo '<input type="submit" class="btn" value="' . gettext('Assign') . '" name="Submit" style="font-size: 8pt;">';
        echo '</p></form>';
    }
    else
    {
        echo '<br><br><br>';
    }
?>



</td>
</tr>
</table>
</div>
</div>

<div class="box">
    <div class="box-header with-border">
        <h3 class="box-title"><?= gettext('Group Members:') ?></h3>
    </div>
    <div class="box-body">
<!-- START GROUP MEMBERS LISTING for group $iGroupID; -->
<?

$sSQL = "SELECT grp_RoleListID,grp_hasSpecialProps FROM group_grp WHERE grp_ID =" . $iGroupID;
$aTemp = mysql_fetch_array(RunQuery($sSQL));
$iRoleListID = $aTemp[0];



?>

<table class="table" id="membersTable">
</table>
</form>
<!-- END GROUP MEMBERS LISTING -->
<form action="#" method="get" class="sidebar-form">
    <label for="addGroupMember"><?= gettext("Add Group Member: ") ?></label>
    <select class="form-control personSearch" name="addGroupMember" style="width:100%">
    </select>
</form>
</div>
</div>

<script>
var groupMembers = <?= json_encode($groupService->getGroupMembers($iGroupID)); ?>;
console.log(groupMembers);
var dataT = 0;

$(document).ready(function() {

    dataT = $("#membersTable").DataTable({
    data:groupMembers,
    columns: [
       {
            width: 'auto',
            title: 'Name',
            data: 'name',
            render: function (data,type,full,meta) {
                return '<img src="'+ full.photo + '" class="direct-chat-img"> &nbsp <a href="PersonView.php?PersonID="' +full.per_ID+ '"><a target="_top" href="PersonView.php?PersonID='+full.per_ID+'">'+ full.displayName+'</a>';
            }
        },
        {
            width: 'auto',
            title: 'Group Role',
            data: 'groupRole',
            render: function (data,type,full,meta) {
                return data+'<button class="changeMembership" id="changeRole-'+full.per_ID+'"><i class="fa fa-pencil"></i></button>';
            }
        },
        {
            width: 'auto',
            title: 'Address',
            render: function (data,type,full,meta) {
                return full.fam_Address1+" "+full.fam_Address2;
            }
        },
        {
            width: 'auto',
            title: 'City',
            data: 'fam_City'
        },
        {
            width: 'auto',
            title: 'State',
            data: 'fam_State'
        },
        {
            width: 'auto',
            title: 'ZIP',
            data: 'fam_Zip'
        },
        {
            width: 'auto',
            title: 'Cell Phone',
            data: 'fam_CellPhone'
        },
        {
            width: 'auto',
            title: 'E-mail',
            data: 'fam_Email'
        },
        {
            width: 'auto',
            title: 'Remove User from Group',
            render: function (data,type,full,meta) {
                return '<button type="button" class="btn btn-danger removeUserGroup" id="rguid-'+full.per_ID+'">Remove User from Group</button>';
            }
        }
    ]
    });
    initHandlers();


    $(".personSearch").select2({
        minimumInputLength: 2,
        ajax: {
            url: function (params){
                    return window.CRM.root + "/api/persons/search/"+params.term;
            },
            dataType: 'json',
            delay: 250,
            data: "",
            processResults: function (data, params) {
                var idKey = 1;
                var results = new Array();
                $.each(data, function (key,value) {
                    var groupName = Object.keys(value)[0];
                    var ckeys = value[groupName];
                    var resultGroup = {
                        id: key,
                        text: groupName,
                        children:[]
                    };
                    idKey++;
                    var children = new Array();
                    $.each(ckeys, function (ckey,cvalue) {
                        var childObject = {
                            id: idKey,
                            objid:cvalue.id,
                            text: cvalue.displayName,
                            uri: cvalue.uri
                        };
                        idKey++;
                        resultGroup.children.push(childObject);
                    });
                    results.push(resultGroup);
                });
                return {results: results};
            },
            cache: true
        }
    });
    $(".personSearch").on("select2:select",function (e) {
        $.ajax({
            method: "POST",
            url: window.CRM.root + "/api/groups/<?= $iGroupID ?>/adduser/"+e.params.data.objid,
            dataType: "json"
        }).done(function (data){
           var person = data[0];
           var node = dataT.row.add(person).node();
           dataT.rows().invalidate().draw(true);
           initHandlers();
        });
        $(".personSearch").select2("val", "");
    });





});



function initHandlers()
{
     $("#chkClear").click(function(e){
             $("#deleteGroupButton").prop("disabled",!e.target.checked);
     });

     $(".removeUserGroup").click(function(e) {
        var userid=e.currentTarget.id.split("-")[1];
        console.log(userid);
        $.ajax({
            method: "POST",
            url: window.CRM.root + "/api/groups/<?= $iGroupID ?>/removeuser/"+userid,
            dataType: "json"
        }).done(function(data){
            dataT.row(function(idx,data,node) { if  (data.per_ID == userid){return true;} } ).remove();
            dataT.rows().invalidate().draw(true);
            initHandlers();
        });
    });

     $(".changeMembership").click(function(e){
        var userid=e.currentTarget.id.split("-")[1];
        console.log(userid);
        $("#changeingMemberID").val(dataT.row(function(idx,data,node) { if  (data.per_ID == userid){return true;} }).data().per_ID);
        $("#changingMemberName").text(dataT.row(function(idx,data,node) { if  (data.per_ID == userid){return true;} }).data().displayName);
        $('#changeMembership').modal('show');

    });

    $("#confirmMembershipChange").click(function(e){
        var changeingMemberID = $("#changeingMemberID").val();
        $.ajax({
            method: "POST",
            url: window.CRM.root + "/api/groups/<?= $iGroupID ?>/userRole/" + changeingMemberID,
            data: JSON.stringify({'roleID': $("#newRoleSelection option:selected").val()}),
            dataType: "json"
        }).done(function(data){
            console.log(data);
            dataT.row(function(idx,data,node) { if  (data.per_ID == changeingMemberID){return true;} }).data(data[0]);
            dataT.rows().invalidate().draw(true);
            initHandlers();
            $('#changeMembership').modal('hide');
        });
    });

    $("#deleteGroupButton").click(function(e){
      console.log(e);
      $.ajax({
            method: "POST",
            url: window.CRM.root + "/api/groups/<?= $iGroupID ?>",
            dataType: "json",
            encode: true,
            data: {"_METHOD":"DELETE"}
        }).done(function(data){
            console.log(data);
            if (data.success)
                window.location.href = "GroupList.php";
        });
    });
}
</script>

<?php require 'Include/Footer.php' ?>
