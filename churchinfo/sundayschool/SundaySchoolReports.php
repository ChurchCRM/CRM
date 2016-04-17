<?php
/*******************************************************************************
 *
 *  filename    : SundaySchoolReports.php
 *  last change : 2003-09-03
 *  description : form to invoke Sunday School reports
 *
 *  ChurchCRM is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *  edited by S. Shaffer May/June 2006 - added capability to include multiple groups.  Group reports are printed with a page break between group selections.
 *
 ******************************************************************************/

// Include the function library
require "../Include/Config.php";
require "../Include/Functions.php";

// Get all the groups
$sSQL = "SELECT * FROM group_grp ORDER BY grp_Name";
$rsGroups = RunQuery($sSQL);

// Set the page title and include HTML header
$sPageTitle = gettext("Sunday School Reports");
require "../Include/Header.php";

// Is this the second pass?
if (isset($_POST["SubmitClassList"]) || isset($_POST["SubmitClassAttendance"])) {
  $iFYID = FilterInput($_POST["FYID"], 'int');
  $dFirstSunday = FilterInput($_POST["FirstSunday"]);
  $dLastSunday = FilterInput($_POST["LastSunday"]);
  $dNoSchool1 = FilterInput($_POST["NoSchool1"]);
  $dNoSchool2 = FilterInput($_POST["NoSchool2"]);
  $dNoSchool3 = FilterInput($_POST["NoSchool3"]);
  $dNoSchool4 = FilterInput($_POST["NoSchool4"]);
  $dNoSchool5 = FilterInput($_POST["NoSchool5"]);
  $dNoSchool6 = FilterInput($_POST["NoSchool6"]);
  $dNoSchool7 = FilterInput($_POST["NoSchool7"]);
  $dNoSchool8 = FilterInput($_POST["NoSchool8"]);
  $iExtraStudents = FilterInput($_POST["ExtraStudents"], 'int');
  $iExtraTeachers = FilterInput($_POST["ExtraTeachers"], 'int');
  $_SESSION['idefaultFY'] = $iFYID;

  $bAtLeastOneGroup = false;

  if (!empty($_POST["GroupID"])) {
    $count = 0;
    foreach ($_POST["GroupID"] as $Grp) {
      $aGroups[$count++] = FilterInput($Grp, 'int');
    }
    $aGrpID = implode(",", $aGroups);
    $bAtLeastOneGroup = true;
  }
  $allroles = FilterInput($_POST["allroles"]);

  $_SESSION['dCalStart'] = $dFirstSunday;
  $_SESSION['dCalEnd'] = $dLastSunday;
  $_SESSION['dCalNoSchool1'] = $dNoSchool1;
  $_SESSION['dCalNoSchool2'] = $dNoSchool2;
  $_SESSION['dCalNoSchool3'] = $dNoSchool3;
  $_SESSION['dCalNoSchool4'] = $dNoSchool4;
  $_SESSION['dCalNoSchool5'] = $dNoSchool5;
  $_SESSION['dCalNoSchool6'] = $dNoSchool6;
  $_SESSION['dCalNoSchool7'] = $dNoSchool7;
  $_SESSION['dCalNoSchool8'] = $dNoSchool8;

  if ($bAtLeastOneGroup && isset($_POST["SubmitClassList"])) {
//		Redirect ("Reports/ClassList.php?GroupID=" . $iGroupID . "&FYID=" . $iFYID . "&FirstSunday=" . $dFirstSunday . "&LastSunday=" . $dLastSunday);
    Redirect("Reports/ClassList.php?GroupID=" . $aGrpID . "&FYID=" . $iFYID . "&FirstSunday=" . $dFirstSunday . "&LastSunday=" . $dLastSunday . "&AllRoles=" . $allroles);
  } else if ($bAtLeastOneGroup && isset($_POST["SubmitClassAttendance"])) {
    $toStr = "Reports/ClassAttendance.php?";
//	      $toStr .= "GroupID=" . $iGroupID;
    $toStr .= "GroupID=" . $aGrpID;
    $toStr .= "&FYID=" . $iFYID;
    $toStr .= "&FirstSunday=" . $dFirstSunday;
    $toStr .= "&LastSunday=" . $dLastSunday;
    $toStr .= "&AllRoles=" . $allroles;
    if ($dNoSchool1)
      $toStr .= "&NoSchool1=" . $dNoSchool1;
    if ($dNoSchool2)
      $toStr .= "&NoSchool2=" . $dNoSchool2;
    if ($dNoSchool3)
      $toStr .= "&NoSchool3=" . $dNoSchool3;
    if ($dNoSchool4)
      $toStr .= "&NoSchool4=" . $dNoSchool4;
    if ($dNoSchool5)
      $toStr .= "&NoSchool5=" . $dNoSchool5;
    if ($dNoSchool6)
      $toStr .= "&NoSchool6=" . $dNoSchool6;
    if ($dNoSchool7)
      $toStr .= "&NoSchool7=" . $dNoSchool7;
    if ($dNoSchool8)
      $toStr .= "&NoSchool8=" . $dNoSchool8;
    if ($iExtraStudents)
      $toStr .= "&ExtraStudents=" . $iExtraStudents;
    if ($iExtraTeachers)
      $toStr .= "&ExtraTeachers=" . $iExtraTeachers;
    Redirect($toStr);
  } else if (!$bAtLeastOneGroup) {
    echo gettext("At least one group must be selected to make class lists or attendance sheets.");
  }
} else {
  $iFYID = $_SESSION['idefaultFY'];
  $iGroupID = 0;
  $dFirstSunday = $_SESSION['dCalStart'];
  $dLastSunday = $_SESSION['dCalEnd'];
  $dNoSchool1 = $_SESSION['dCalNoSchool1'];
  $dNoSchool2 = $_SESSION['dCalNoSchool2'];
  $dNoSchool3 = $_SESSION['dCalNoSchool3'];
  $dNoSchool4 = $_SESSION['dCalNoSchool4'];
  $dNoSchool5 = $_SESSION['dCalNoSchool5'];
  $dNoSchool6 = $_SESSION['dCalNoSchool6'];
  $dNoSchool7 = $_SESSION['dCalNoSchool7'];
  $dNoSchool8 = $_SESSION['dCalNoSchool8'];
  $iExtraStudents = 0;
  $iExtraTeachers = 0;
}
?>
<div class="box">
  <div class="box-header with-border">
    <h3 class="box-title">Report Details</h3>
  </div>
  <div class="box-body">
    <form method="post" action="SundaySchoolReports.php">

      <table class="table table-simple-padding" align="left">
        <tr>
          <td><?= gettext("Select Group: \nTo select multiple hold CTL") ?></td>
          <td>
            <?php
            // Create the group select drop-down
            echo "<select id=\"GroupID\" name=\"GroupID[]\" multiple size=\"8\" onChange=\"UpdateRoles();\"><option value=\"0\">" . gettext('None') . "</option>";
            while ($aRow = mysql_fetch_array($rsGroups)) {
              extract($aRow);
              echo "<option value=\"" . $grp_ID . "\">" . $grp_Name . "</option>";
            }
            echo "</select><br>";
            echo "Multiple groups will have a Page Break between Groups<br>";
            echo "<input type=\"checkbox\" Name=\"allroles\" value=\"1\" checked>";
            echo "List all Roles (unchecked will list Teacher/Student roles only)";
            ?>
          </td>
        </tr>

        <tr>
          <td><?= gettext("Fiscal Year:") ?></td>
          <td class="TextColumnWithBottomBorder">
            <?php PrintFYIDSelect($iFYID, "FYID") ?>
          </td>
        </tr>

        <tr>
          <td><?= gettext("First Sunday:") ?></td>
          <td><input type="text" name="FirstSunday" value="<?= $dFirstSunday ?>" maxlength="10" id="FirstSunday" size="11"></td>
        </tr>

        <tr>
          <td><?= gettext("Last Sunday:") ?></td>
          <td><input type="text" name="LastSunday" value="<?= $dLastSunday ?>" maxlength="10" id="LastSunday" size="11"></td>
        </tr>

        <tr>
          <td><?= gettext("No Sunday School:") ?></td>
          <td><input type="text" name="NoSchool1" value="<?= $dNoSchool1 ?>" maxlength="10" id="NoSchool1" size="11"></td>
        </tr>

        <tr>
          <td><?= gettext("No Sunday School:") ?></td>
          <td><input type="text" name="NoSchool2" value="<?= $dNoSchool2 ?>" maxlength="10" id="NoSchool2" size="11"></td>
        </tr>

        <tr>
          <td><?= gettext("No Sunday School:") ?></td>
          <td><input type="text" name="NoSchool3" value="<?= $dNoSchool3 ?>" maxlength="10" id="NoSchool3" size="11"></td>
        </tr>

        <tr>
          <td><?= gettext("No Sunday School:") ?></td>
          <td><input type="text" name="NoSchool4" value="<?= $dNoSchool4 ?>" maxlength="10" id="NoSchool4" size="11"></td>
        </tr>

        <tr>
          <td><?= gettext("No Sunday School:") ?></td>
          <td><input type="text" name="NoSchool5" value="<?= $dNoSchool5 ?>" maxlength="10" id="NoSchool5" size="11"></td>
        </tr>

        <tr>
          <td><?= gettext("No Sunday School:") ?></td>
          <td><input type="text" name="NoSchool6" value="<?= $dNoSchool6 ?>" maxlength="10" id="NoSchool6" size="11"></td>
        </tr>

        <tr>
          <td><?= gettext("No Sunday School:") ?></td>
          <td><input type="text" name="NoSchool7" value="<?= $dNoSchool7 ?>" maxlength="10" id="NoSchool7" size="11"></td>
        </tr>

        <tr>
          <td><?= gettext("No Sunday School:") ?></td>
          <td><input type="text" name="NoSchool8" value="<?= $dNoSchool8 ?>" maxlength="10" id="NoSchool8" size="11"></td>
        </tr>

        <tr>
          <td><?= gettext("Extra Students:") ?></td>
          <td><input type="text" name="ExtraStudents" value="<?= $iExtraStudents ?>" id="ExtraStudents" size="11">&nbsp;</td>
        </tr>
        <tr>
          <td><?= gettext("Extra Teachers:") ?></td>
          <td><input type="text" name="ExtraTeachers" value="<?= $iExtraTeachers ?>" id="ExtraTeachers" size="11">&nbsp;</td>
        </tr>
        <tr>
          <td><br/></td>
        </tr>
        <tr>
          <td><input type="submit" class="btn btn-primary" name="SubmitClassList" value="<?= gettext("Create Class List") ?>"></td>
          <td><input type="submit" class="btn btn-info" name="SubmitClassAttendance" value="<?= gettext("Create Attendance Sheet") ?>"></td>
          <td><input type="button" class="btn" name="Cancel" value="<?= gettext("Cancel") ?>" onclick="javascript:document.location = 'Menu.php';"></td>
        </tr>
      </table>
    </form>
  </div>
</div>
<script>
  $("#FirstSunday").datepicker({format: 'yyyy-mm-dd'});
  $("#LastSunday").datepicker({format: 'yyyy-mm-dd'});
  $("#NoSchool1").datepicker({format: 'yyyy-mm-dd'});
  $("#NoSchool2").datepicker({format: 'yyyy-mm-dd'});
  $("#NoSchool3").datepicker({format: 'yyyy-mm-dd'});
  $("#NoSchool4").datepicker({format: 'yyyy-mm-dd'});
  $("#NoSchool5").datepicker({format: 'yyyy-mm-dd'});
  $("#NoSchool6").datepicker({format: 'yyyy-mm-dd'});
  $("#NoSchool7").datepicker({format: 'yyyy-mm-dd'});
  $("#NoSchool8").datepicker({format: 'yyyy-mm-dd'});
</script>

<?php
require "../Include/Footer.php";
?>
