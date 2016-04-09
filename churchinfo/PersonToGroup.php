<?php
/*******************************************************************************
 *
 *  filename    : PersonToGroup.php
 *  last change : 2003-06-23
 *  description : Add a person record to a group after selection of group
 *  	and role.  This is a companion script to the Group Assign Helper.
 *
 *  http://www.churchcrm.io/
 *  Copyright 2003 Chris Gebhardt
 *
 *  ChurchCRM is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

// Include the function library
require "Include/Config.php";
require "Include/Functions.php";
require_once "Service/GroupService.php";

$groupService = new GroupService();

// Security: User must have Manage Groups & Roles permission
if (!$_SESSION['bManageGroups'])
{
  Redirect("Menu.php");
  exit;
}

$iPersonID = FilterInput($_GET["PersonID"], 'int');

// Was the form submitted?
if (isset($_POST["Submit"]))
{
  // Get the GroupID
  $iGroupID = FilterInput($_POST["GroupID"], 'int');
  $iGroupRole = FilterInput($_POST["GroupRole"], 'int');

  $sPreviousQuery = strip_tags($_POST["prevquery"]);
  $groupService->addUserToGroup($iGroupID, $iPersonID, $iGroupRole);

  Redirect("SelectList.php?$sPreviousQuery");
}
else
  $sPreviousQuery = strip_tags(rawurldecode($_GET["prevquery"]));

// Get all the groups
$sSQL = "SELECT * FROM group_grp ORDER BY grp_Name";
$rsGroups = RunQuery($sSQL);

// Set the page title and include HTML header
$sPageTitle = gettext("Add Person to Group");
require "Include/Header.php";
?>

<script src="js/RPCDummyAjax.js"></script>

<p align="center"><?= gettext("Select the group to add this person to:") ?></p>
<form method="post" action="PersonToGroup.php?PersonID=<?= $iPersonID ?>">
  <input type="hidden" name="prevquery" value="<?= $sPreviousQuery ?>">
  <table align="center">
    <tr>
      <td class="LabelColumn"><?= gettext("Select Group:") ?></td>
      <td class="TextColumn">
        <?php
// Create the group select drop-down
        echo "<select id=\"GroupID\" name=\"GroupID\" onChange=\"UpdateRoles();\"><option value=\"0\">" . gettext("None") . "</option>";
        while ($aRow = mysql_fetch_array($rsGroups))
        {
          extract($aRow);
          echo "<option value=\"" . $grp_ID . "\">" . $grp_Name . "</option>";
        }
        echo "</select>";
        ?>
      </td>
    </tr>
    <tr>
      <td class="LabelColumn"><?= gettext("Select Role:") ?></td>
      <td class="TextColumn"> 
        <select name="GroupRole" id="GroupRole">
          <option><?= gettext("No Group Selected") ?></option>
        </select>
      </td>
    </tr>
  </table>
  <p align="center">
    <BR>
    <input type="submit" class="btn" name="Submit" value="<?= gettext("Add to Group") ?>">
    <BR><BR>
  </p>
</form>

<?php require "Include/Footer.php" ?>
