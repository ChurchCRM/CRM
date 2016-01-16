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

//Set the page title
$sPageTitle = gettext('Group View');

//Get the GroupID out of the querystring
$iGroupID = FilterInput($_GET['GroupID'],'int');

//Do they want to add this group to their cart?
if (array_key_exists ('Action', $_GET) and $_GET['Action'] == 'AddGroupToCart')
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

require 'Include/Header.php';?>


<link rel="stylesheet" type="text/css" href="<?= $sURLPath; ?>/vendor/AdminLTE/plugins/datatables/dataTables.bootstrap.css">
<script src="<?= $sURLPath; ?>/vendor/AdminLTE/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="<?= $sURLPath; ?>/vendor/AdminLTE/plugins/datatables/dataTables.bootstrap.js"></script>


<link rel="stylesheet" type="text/css" href="<?= $sURLPath; ?>/vendor/AdminLTE/plugins/datatables/extensions/TableTools/css/dataTables.tableTools.css">
<script type="text/javascript" language="javascript" src="<?= $sURLPath; ?>/vendor/AdminLTE/plugins/datatables/extensions/TableTools/js/dataTables.tableTools.min.js"></script>

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
                            <h4 class="modal-title" id="upload-Image-label"><?php echo gettext("Confirm Delete Group") ?></h4>
                        </div>
                        <div class="modal-body">
                        <span style="color: red">
                           <?php echo gettext("Please confirm deletion of this group record:"); ?>
                         
                             <p class="ShadedBox">
                                <?php echo $grp_Name; ?>
                            </p>
                            
                             <p class="LargeError">
                                <?php echo gettext("This will also delete all Roles and Group-Specific Property data associated with this Group record."); ?>
                            </p>
                            <?php echo gettext("All group membership and properties will be destroyed.  The group members themselves will not be altered.");?>
                            <br><br>
                            <span style="color:black">I Understand &nbsp;<input type="checkbox" name="chkClear"></span>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                            <button name="deleteGroup" id="deleteGroup" type="button" class="btn btn-danger"><?php echo gettext("Delete Group") ?></button>
                        </div>
                    </div>
            </div>
        </div>
    <!--END GROUP DELETE MODAL-->
    
    
    
   
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
$sSQL = "SELECT per_Email, fam_Email
            FROM person_per
            LEFT JOIN person2group2role_p2g2r ON per_ID = p2g2r_per_ID
            LEFT JOIN group_grp ON grp_ID = p2g2r_grp_ID
            LEFT JOIN family_fam ON per_fam_ID = family_fam.fam_ID
        WHERE per_ID NOT IN 
            (SELECT per_ID 
                FROM person_per 
                INNER JOIN record2property_r2p ON r2p_record_ID = per_ID 
                INNER JOIN property_pro ON r2p_pro_ID = pro_ID AND pro_Name = 'Do Not Email') 
            AND p2g2r_grp_ID = " . $iGroupID;
$rsEmailList = RunQuery($sSQL);
$sEmailLink = '';
while (list ($per_Email, $fam_Email) = mysql_fetch_row($rsEmailList))
{
    $sEmail = SelectWhichInfo($per_Email, $fam_Email, False);
    if ($sEmail)
    {
        /* if ($sEmailLink) // Don't put delimiter before first email
            $sEmailLink .= $sMailtoDelimiter; */
        // Add email only if email address is not already in string
        if (!stristr($sEmailLink, $sEmail))
            $sEmailLink .= $sEmail .= $sMailtoDelimiter;
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
    echo '<a class="btn btn-app" href="mailto:'. mb_substr($sEmailLink,0,-3) .'"><i class="fa fa-send-o"></i>'.gettext('Email Group').'</a>';
    echo '<a class="btn btn-app" href="mailto:?bcc='. mb_substr($sEmailLink,0,-3) .'"><i class="fa fa-send"></i>'.gettext('Email (BCC)').'</a>';
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
            <b class="LargeText"><?php echo $grp_Name; ?></b>
            <br>
            <?php echo $grp_Description; ?>
            <br><br>
            <table width="98%">
                <tr>
                    <td align="center"><div class="TinyShadedBox"><font size="3">
                    <?php echo gettext('Total Members:'); ?> <?php echo $iTotalMembers ?>
                    <br>
                    <?php echo gettext('Type of Group:'); ?> <?php echo $sGroupType ?>
                    <br>
                    <?php echo gettext('Default Role:'); ?> <?php echo $sDefaultRole ?>
                    </font></div></td>
                </tr>
            </table>
        </div>
    </td>
    <td width="75%" valign="top" align="left">

    <b><?php echo gettext('Group-Specific Properties:'); ?></b>

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
                <td><?php echo gettext('Type'); ?></td>
                <td><?php echo gettext('Name'); ?></td>
                <td><?php echo gettext('Description'); ?></td>
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
        <td width="15%" valign="top"><b><?php echo gettext('Type'); ?></b>
        <td valign="top"><b><?php echo gettext('Name'); ?></b>
        <td valign="top"><b><?php echo gettext('Value'); ?></td>
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
        echo '<p class="btn btn-app" align="center">';
        echo '<span class="btn btn-app">' . gettext('Assign a New Property:') . '</span>';
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
        <h3 class="box-title"><? echo gettext('Group Members:')?></h3>
    </div>
    <div class="box-body">
<!-- START GROUP MEMBERS LISTING for group $iGroupID; -->
<?
$personService = new PersonService();

$sSQL = "SELECT grp_RoleListID,grp_hasSpecialProps FROM group_grp WHERE grp_ID =" . $iGroupID;
$aTemp = mysql_fetch_array(RunQuery($sSQL));
$iRoleListID = $aTemp[0];


// Main select query
$sSQL = "SELECT per_ID, per_FirstName, LEFT(per_MiddleName,1) AS per_MiddleName, per_LastName, per_Title, per_Suffix, per_Address1, per_Address2, per_City, per_State, per_Zip, per_CellPhone, per_Country, per_Email, fam_Address1, fam_Address2, fam_City, fam_State, fam_Zip, fam_Country, fam_CellPhone, fam_Email, lst_OptionName
            FROM person_per
            LEFT JOIN person2group2role_p2g2r ON per_ID = p2g2r_per_ID
            LEFT JOIN list_lst ON p2g2r_rle_ID = lst_OptionID AND lst_ID = $iRoleListID
            LEFT JOIN group_grp ON grp_ID = p2g2r_grp_ID
            LEFT JOIN family_fam ON per_fam_ID = family_fam.fam_ID
        WHERE p2g2r_grp_ID = " . $iGroupID;


$sSQL_Result = RunQuery($sSQL);
?>

<table class="table" id="membersTable">
    <thead>
    <tr>
        <th><?php echo gettext("Name"); ?></th>
        <th><?php echo gettext("Group Role"); ?></th>
        <th><?php echo gettext("Address"); ?></th>
        <th><?php echo gettext("City"); ?></th>
        <th><?php echo gettext("State"); ?></th>
        <th><?php echo gettext("ZIP"); ?></th>
        <th><?php echo gettext("Cell Phone"); ?></th>
        <th><?php echo gettext("E-mail"); ?></th>
        <th><?php echo gettext("Remove User from Group"); ?></th>
    </tr>
    </thead>
    <tbody>
    <?php
    //Loop through the members
    while ($aRow = mysql_fetch_array($sSQL_Result))
    {
        $per_Title = "";
        $per_FirstName = "";
        $per_MiddleName = "";
        $per_LastName = "";
        $per_Suffix = "";
        $per_Address1 = "";
        $per_Address2 = "";
        $per_City = "";
        $per_State = "";
        $per_Zip = "";
        $per_Country = "";
        $per_HomePhone = "";
        $per_Email = "";
        $fam_Name = "";
        $fam_Address1 = "";
        $fam_Address2 = "";
        $fam_City = "";
        $fam_State = "";
        $fam_Zip = "";
        $fam_Country = "";
        $fam_HomePhone = "";
        $fam_Email = "";

        extract($aRow);


        // Assign the values locally, after selecting whether to display the family or person information
        SelectWhichAddress($sAddress1, $sAddress2, $per_Address1, $per_Address2, $fam_Address1, $fam_Address2, False);
        $sCity = SelectWhichInfo($per_City, $fam_City, False);
        $sState = SelectWhichInfo($per_State, $fam_State, False);
        $sZip = SelectWhichInfo($per_Zip, $fam_Zip, False);
        $sCountry = SelectWhichInfo($per_Country, $fam_Country, False);
        $sCellPhone = SelectWhichInfo(ExpandPhoneNumber($per_CellPhone,$sCountry,$dummy), 
                    ExpandPhoneNumber($fam_CellPhone,$fam_Country,$dummy), False); 
        $sEmail = SelectWhichInfo($per_Email, $fam_Email, False);
        //Display the row
        ?>
    <tr id="uid-<?php echo $per_ID; ?>">
        <td><?php
                echo "<img src=\"". $personService->photo($per_ID). "\" class=\"direct-chat-img\"> &nbsp <a href=\"PersonView.php?PersonID=\"" . $per_ID . "\"><a target=\"_top\" href=\"PersonView.php?PersonID=$per_ID\">" . FormatFullName($per_Title, $per_LastName, $per_FirstName, $per_MiddleName, $per_Suffix, 0) . "</a>"; ?>
        </td>        
        <td><?php
            if ($_SESSION['bManageGroups']) echo "<a target=\"_top\" href=\"MemberRoleChange.php?GroupID=" . $iGroupID . "&PersonID=" . $per_ID . "&Return=1\">";
            echo $lst_OptionName;
            if ($_SESSION['bManageGroups']) echo "</a>";
        ?></td>
        <td><?php echo $sAddress1;?><?php if ($sAddress1 != "" && $sAddress2 != "") { echo ", "; } ?><?php if ($sAddress2 != "") echo $sAddress2; ?></td>
        <td><?php echo $sCity ?></td>
        <td><?php echo $sState ?></td>
        <td><?php echo $sZip ?></td>
        <td><?php echo $sCellPhone ?></td>
        <td><?php echo $sEmail;?></td>
        <td><button type="button" class="btn btn-danger removeUserGroup" id="rguid-<?php echo $per_ID; ?>">Remove User from Group</button></td>
    </tr>
    <?php
    }
    
    ?>
    </tbody>
</table>
</form>
<!-- END GROUP MEMBERS LISTING -->
<form action="#" method="get" class="sidebar-form">
    <label for="addGroupMember"><?php echo gettext("Add Group Member: ");?></label>
    <select class="form-control personSearch" name="addGroupMember" style="width:100%">
    </select>
</form>
</div>
</div>

<script>
$(document).ready(function() {
   initHandlers();
    $("#membersTable").DataTable();
    $("document").ready(function(){

    $(".personSearch").select2({
        minimumInputLength: 2,
        ajax: {
            url: function (params){
                    return "api/persons/search/"+params.term;   
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
        console.log(e.params.data.objid);
        addUserToGroup(e.params.data.objid);
        addTableRow(e.params.data.objid);
        $(".personSearch").select2("val", "");
    });
    
});


    
});



function initHandlers()
{
     $(".removeUserGroup").click(function(e) {
        var userid=e.currentTarget.id.split("-")[1];
        console.log(userid);
        $.ajax({
            method: "POST",
            url: "/api/groups/<?php echo $iGroupID;?>/removeuser/"+userid,
            dataType: "json"
        }).done(function(data){
            var t = $("#membersTable").DataTable();
            console.log("Removing #uid-"+userid);
            t.row($("#uid-"+userid)).remove().draw(true);
        });
    });
    
    $("#deleteGroup").click(function(e){
      console.log(e);        
      $.ajax({
            method: "DELETE",
            url: "/api/groups/<?php echo $iGroupID;?>",
            dataType: "json"
        }).done(function(data){
            console.log(data);
            if (data.success)
                window.location.href = "GroupList.php";
        });
    });
}
function addUserToGroup(userid)
{
    $.ajax({
            method: "POST",
            url: "/api/groups/<?php echo $iGroupID;?>/adduser/"+userid,
            dataType: "json"
        });
    
}
function addTableRow(objID)
{
    $.ajax({
        method: "GET",
        url:    "/api/persons/"+objID,
        dataType:   "json"
    }).done(function (data){
        var person = data[0].persons; 
        var newRow=[
                '<img src = "'+person.photo+'" class="direct-chat-img"><a href="PersonView.php?PersonID='+person.per_ID+'">'+person.per_Title+' ' +person.per_FirstName+' '+person.per_LastName+'</a>',
                '<a href="MemberRoleChange.php?GroupID=<?php echo $grp_ID;?>&PersonID='+person.per_ID+'&Return=1"><?php echo $sDefaultRole ?></a>',
                person.per_Address1,
                person.per_City,
                person.per_State,
                person.per_Zip,
                person.per_CellPhone,
                person.per_Email,
                '<button class="btn btn-danger removeUserGroup" type="button" id="rguid-'+person.per_ID+'">Remove User from Group</button>'
                ];       

        var t = $("#membersTable").DataTable();
        var node = t.row.add(newRow).draw( true ).node();
        $(node).attr("id","uid-"+person.per_ID);
        initHandlers();
    });
   
}
</script>


<?php
require 'Include/Footer.php';
?>
