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
$sPageTitle = gettext('Group Listing');

require 'Include/Header.php';?>

<link rel="stylesheet" type="text/css" href="<?= $sURLPath; ?>/vendor/AdminLTE/plugins/datatables/dataTables.bootstrap.css">
<script src="<?= $sURLPath; ?>/vendor/AdminLTE/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="<?= $sURLPath; ?>/vendor/AdminLTE/plugins/datatables/dataTables.bootstrap.js"></script>


<link rel="stylesheet" type="text/css" href="<?= $sURLPath; ?>/vendor/AdminLTE/plugins/datatables/extensions/TableTools/css/dataTables.tableTools.css">
<script type="text/javascript" language="javascript" src="<?= $sURLPath; ?>/vendor/AdminLTE/plugins/datatables/extensions/TableTools/js/dataTables.tableTools.min.js"></script>


<?php
if ($_SESSION['bManageGroups']) 
{?>
    <div class="pull-right">
    <a class="btn btn-success" role="button" href="GroupEditor.php"> <span class="fa fa-plus" aria-hidden="true"></span> Add Group</a>
    <p>
    </div>
    <br/><br/></p>
    <?php
}
?>

<div class="box box-body">

<?php

//Get all group records
//Added "OR grp_Type = 0" to display Unassigned Groups 
$sSQL = "SELECT * FROM group_grp LEFT JOIN list_lst "
      . "ON grp_Type = lst_OptionID "
      . "WHERE lst_ID='3' OR grp_Type = 0 "
      . "ORDER BY lst_OptionSequence, grp_Name";

$rsGroups = RunQuery($sSQL);
?>
<table class="table" id="groupsTable">
    <thead>
        <tr>
        <th><?php echo gettext('Name')?></th>
        <th><?php echo gettext('Members')?></th>
        <th><?php echo gettext('Type')?></th>
        <th><?php echo gettext('Add to Cart')?></th>
        <th><?php echo gettext('Remove from Cart')?></th>
        </tr>
    </thead>
<?php

//Set the initial row color
$sRowClass = 'RowColorA';

//Loop through the person recordset
while ($aRow = mysql_fetch_array($rsGroups))
{
    extract($aRow);

    //Alternate the row color
    $sRowClass = AlternateRowStyle($sRowClass);

    //Get the count for this group
    $sSQL = "SELECT Count(*) AS iCount FROM person2group2role_p2g2r " .
            "WHERE p2g2r_grp_ID='$grp_ID'";
    $rsMemberCount = mysql_fetch_array(RunQuery($sSQL));
    extract($rsMemberCount);
        
    //Get the group's type name
    if ($grp_Type > 0)
    {
        $sSQL =    "SELECT lst_OptionName FROM list_lst WHERE " . 
                "lst_ID=3 AND lst_OptionID = " . $grp_Type;
        $rsGroupType = mysql_fetch_array(RunQuery($sSQL));
        $sGroupType = $rsGroupType[0];
    }
    else
        $sGroupType = gettext('Undefined');

        //Display the row

        ?>
        <tr>
                <td>
                <a href='GroupView.php?GroupID=<?php echo $grp_ID?>'>
                        <span class="fa-stack">
                            <i class="fa fa-square fa-stack-2x"></i>
                            <i class="fa fa-search-plus fa-stack-1x fa-inverse"></i>
                        </span>
                        </a>
                        <a href='GroupEditor.php?GroupID=<?php echo $grp_ID?>'>
                        <span class="fa-stack">
                            <i class="fa fa-square fa-stack-2x"></i>
                            <i class="fa fa-pencil fa-stack-1x fa-inverse"></i>
                        </span>
                        </a><?php echo $grp_Name?></td>
                <td><?php echo $iCount?></td>
                <td><?php echo $sGroupType?></td>
                <td><?php

        $sSQL =    "SELECT p2g2r_per_ID FROM person2group2role_p2g2r " .
                "WHERE p2g2r_grp_ID='$grp_ID'";
        $rsGroupMembers = RunQuery($sSQL);

        $bNoneInCart = TRUE;
        $bAllInCart = TRUE;
        //Loop through the recordset
        while ($aPeople = mysql_fetch_array($rsGroupMembers))
        {
            extract($aPeople);

            if (!isset($_SESSION['aPeopleCart']))
                $bAllInCart = FALSE; // Cart does not exist.  This person is not in cart.
            elseif (!in_array($p2g2r_per_ID, $_SESSION['aPeopleCart'], false))
                $bAllInCart = FALSE; // This person is not in cart.
            elseif (in_array($p2g2r_per_ID, $_SESSION['aPeopleCart'], false))
                $bNoneInCart = FALSE; // This person is in the cart
        }

        if (!$bAllInCart)
        {
            // Add to cart ... screen should return to this location
            // after this group is added to cart
            echo '<a onclick="saveScrollCoordinates()" class="btn btn-primary"
                    href="GroupList.php?AddGroupToPeopleCart=' .$grp_ID. '">' .
                    gettext('Add all') . '</a>';
        } else {
            echo '&nbsp;';
        }
    

        echo '</td><td align="center">';

        if (!$bNoneInCart)
        {
            // Add to cart ... screen should return to this location
            // after this group is removed from cart
            echo '    <a onclick="saveScrollCoordinates()" class="btn btn-danger"
                    href="GroupList.php?RemoveGroupFromPeopleCart=' .$grp_ID. '">' .
                    gettext('Remove all') . '</a>';
        } else {
            echo '&nbsp;';
        }

        echo '</td>';
    }
?>

</table>

</div>
<script>
$("#groupsTable").dataTable();
</script>

<?php

require 'Include/Footer.php';
?>
