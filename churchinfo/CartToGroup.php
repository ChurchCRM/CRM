<?php
/*******************************************************************************
 *
 *  filename    : CartToGroup.php
 *  last change : 2003-06-23
 *  description : Add cart records to a group
 *
 *  http://www.churchcrm.io/
 *  Copyright 2001-2003 Phillip Hullquist, Deane Barker, Chris Gebhardt
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

// Was the form submitted?
if (isset($_POST["Submit"]) && count($_SESSION['aPeopleCart']) > 0)
{

  // Get the GroupID
  $iGroupID = FilterInput($_POST["GroupID"], 'int');
  if (array_key_exists("GroupRole", $_POST))
    $iGroupRole = FilterInput($_POST["GroupRole"], 'int');
  else
    $iGroupRole = 0;

  // Loop through the session array
  $iCount = 0;
  while ($element = each($_SESSION['aPeopleCart']))
  {
    $groupService->addUserToGroup($iGroupID, $_SESSION['aPeopleCart'][$element['key']], $iGroupRole);
    $iCount += 1;
  }

  $sGlobalMessage = $iCount . " records(s) successfully added to selected Group.";

  Redirect("GroupView.php?GroupID=" . $iGroupID . "&Action=EmptyCart");
}

// Get all the groups
$sSQL = "SELECT * FROM group_grp ORDER BY grp_Name";
$rsGroups = RunQuery($sSQL);

// Set the page title and include HTML header
$sPageTitle = gettext("Add Cart to Group");
require "Include/Header.php";

if (count($_SESSION['aPeopleCart']) > 0)
{
  ?>

  <script src="js/RPCDummyAjax.js"></script>

  <!-- Default box -->
  <div class="box">
    <div class="box-body">
      <p align="center"><?= gettext("Select the group to which you would like to add your cart:") ?></p>
      <form method="post">
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
          <input type="submit" class="btn btn-primary" name="Submit" value="<?= gettext("Add to Group") ?>">
          <BR><BR>--<?= gettext("OR") ?>--<BR><BR>
          <a href="GroupEditor.php?EmptyCart=yes" class="btn btn-info"><i class="fa fa-add"></i><?= gettext("Create a New Group") ?></a>
          <BR><BR>
        </p>
      </form>
    </div></div>
  <?php
}
else
  echo "<p align=\"center\" class=\"LargeText\">" . gettext("Your cart is empty!") . "</p>";

require "Include/Footer.php";
?>
