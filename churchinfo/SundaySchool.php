<?php
/*******************************************************************************
 *
 *  filename    : SundaySchool.php
 *  last change : 2003-09-03
 *  description : form to invoke Sunday School reports
 *
 *  ChurchInfo is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

// Include the function library
require "Include/Config.php";
require "Include/Functions.php";

// Get all the groups
$sSQL = "SELECT * FROM group_grp ORDER BY grp_Name";
$rsGroups = RunQuery($sSQL);

// Set the page title and include HTML header
$sPageTitle = gettext("Sunday School Reports");
require "Include/Header.php";

// Is this the second pass?
if (isset($_POST["SubmitClassList"]) || isset($_POST["SubmitClassAttendance"])) {
   $iGroupID = FilterInput($_POST['GroupID'],'int');
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

   if (isset($_POST["SubmitClassList"])) {
      Redirect ("Reports/ClassList.php?GroupID=" . $iGroupID . "&FYID=" . $iFYID . "&FirstSunday=" . $dFirstSunday . "&LastSunday=" . $dLastSunday);
   } else if (isset($_POST["SubmitClassAttendance"])) {
      $toStr = "Reports/ClassAttendance.php?";
      $toStr .= "GroupID=" . $iGroupID;
      $toStr .= "&FYID=" . $iFYID;
      $toStr .= "&FirstSunday=" . $dFirstSunday;
      $toStr .= "&LastSunday=" . $dLastSunday;
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
      Redirect ($toStr);
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
}

?>

<form method="post" action="<?php echo $_SERVER['PHP_SELF']?>">

<table cellpadding="3" align="left">
	<tr>
		<td class="LabelColumn"><?php echo gettext("Select Group:"); ?></td>
		<td class="TextColumn">
			<?php
			// Create the group select drop-down
			echo "<select id=\"GroupID\" name=\"GroupID\" onChange=\"UpdateRoles();\"><option value=\"0\">". gettext('None') . "</option>";
			while ($aRow = mysql_fetch_array($rsGroups)) {
				extract($aRow);
				echo "<option value=\"" . $grp_ID . "\">" . $grp_Name . "</option>";
			}
			echo "</select>";
			?>
		</td>
	</tr>

   <tr>
      <td class="LabelColumn"><?php echo gettext("Fiscal Year:"); ?></td>
      <td class="TextColumnWithBottomBorder">
		<?php PrintFYIDSelect ($iFYID, "FYID") ?>
      </td>
   </tr>

	<tr>
		<td class="LabelColumn"><?php addToolTip("Format: YYYY-MM-DD<br>or enter the date by clicking on the calendar icon to the right."); ?><?php echo gettext("First Sunday:"); ?></td>
		<td class="TextColumn"><input type="text" name="FirstSunday" value="<?php echo $dFirstSunday; ?>" maxlength="10" id="sel1" size="11">&nbsp;<input type="image" onclick="return showCalendar('sel1', 'y-mm-dd');" src="Images/calendar.gif"> <span class="SmallText"><?php echo gettext("[format: YYYY-MM-DD]"); ?></span></td>
	</tr>

	<tr>
		<td class="LabelColumn"><?php addToolTip("Format: YYYY-MM-DD<br>or enter the date by clicking on the calendar icon to the right."); ?><?php echo gettext("Last Sunday:"); ?></td>
		<td class="TextColumn"><input type="text" name="LastSunday" value="<?php echo $dLastSunday; ?>" maxlength="10" id="sel2" size="11">&nbsp;<input type="image" onclick="return showCalendar('sel2', 'y-mm-dd');" src="Images/calendar.gif"> <span class="SmallText"><?php echo gettext("[format: YYYY-MM-DD]"); ?></span></td>
	</tr>

	<tr>
		<td class="LabelColumn"><?php addToolTip("Format: YYYY-MM-DD<br>or enter the date by clicking on the calendar icon to the right."); ?><?php echo gettext("No Sunday School:"); ?></td>
		<td class="TextColumn"><input type="text" name="NoSchool1" value="<?php echo $dNoSchool1; ?>" maxlength="10" id="NoSchool1" size="11">&nbsp;<input type="image" onclick="return showCalendar('NoSchool1', 'y-mm-dd');" src="Images/calendar.gif"> <span class="SmallText"><?php echo gettext("[format: YYYY-MM-DD]"); ?></span></td>
	</tr>

	<tr>
		<td class="LabelColumn"><?php addToolTip("Format: YYYY-MM-DD<br>or enter the date by clicking on the calendar icon to the right."); ?><?php echo gettext("No Sunday School:"); ?></td>
		<td class="TextColumn"><input type="text" name="NoSchool2" value="<?php echo $dNoSchool2; ?>" maxlength="10" id="NoSchool2" size="11">&nbsp;<input type="image" onclick="return showCalendar('NoSchool2', 'y-mm-dd');" src="Images/calendar.gif"> <span class="SmallText"><?php echo gettext("[format: YYYY-MM-DD]"); ?></span></td>
	</tr>

	<tr>
		<td class="LabelColumn"><?php addToolTip("Format: YYYY-MM-DD<br>or enter the date by clicking on the calendar icon to the right."); ?><?php echo gettext("No Sunday School:"); ?></td>
		<td class="TextColumn"><input type="text" name="NoSchool3" value="<?php echo $dNoSchool3; ?>" maxlength="10" id="NoSchool3" size="11">&nbsp;<input type="image" onclick="return showCalendar('NoSchool3', 'y-mm-dd');" src="Images/calendar.gif"> <span class="SmallText"><?php echo gettext("[format: YYYY-MM-DD]"); ?></span></td>
	</tr>

	<tr>
		<td class="LabelColumn"><?php addToolTip("Format: YYYY-MM-DD<br>or enter the date by clicking on the calendar icon to the right."); ?><?php echo gettext("No Sunday School:"); ?></td>
		<td class="TextColumn"><input type="text" name="NoSchool4" value="<?php echo $dNoSchool4; ?>" maxlength="10" id="NoSchool4" size="11">&nbsp;<input type="image" onclick="return showCalendar('NoSchool4', 'y-mm-dd');" src="Images/calendar.gif"> <span class="SmallText"><?php echo gettext("[format: YYYY-MM-DD]"); ?></span></td>
	</tr>

	<tr>
		<td class="LabelColumn"><?php addToolTip("Format: YYYY-MM-DD<br>or enter the date by clicking on the calendar icon to the right."); ?><?php echo gettext("No Sunday School:"); ?></td>
		<td class="TextColumn"><input type="text" name="NoSchool5" value="<?php echo $dNoSchool5; ?>" maxlength="10" id="NoSchool5" size="11">&nbsp;<input type="image" onclick="return showCalendar('NoSchool5', 'y-mm-dd');" src="Images/calendar.gif"> <span class="SmallText"><?php echo gettext("[format: YYYY-MM-DD]"); ?></span></td>
	</tr>

	<tr>
		<td class="LabelColumn"><?php addToolTip("Format: YYYY-MM-DD<br>or enter the date by clicking on the calendar icon to the right."); ?><?php echo gettext("No Sunday School:"); ?></td>
		<td class="TextColumn"><input type="text" name="NoSchool6" value="<?php echo $dNoSchool6; ?>" maxlength="10" id="NoSchool6" size="11">&nbsp;<input type="image" onclick="return showCalendar('NoSchool6', 'y-mm-dd');" src="Images/calendar.gif"> <span class="SmallText"><?php echo gettext("[format: YYYY-MM-DD]"); ?></span></td>
	</tr>

	<tr>
		<td class="LabelColumn"><?php addToolTip("Format: YYYY-MM-DD<br>or enter the date by clicking on the calendar icon to the right."); ?><?php echo gettext("No Sunday School:"); ?></td>
		<td class="TextColumn"><input type="text" name="NoSchool7" value="<?php echo $dNoSchool7; ?>" maxlength="10" id="NoSchool7" size="11">&nbsp;<input type="image" onclick="return showCalendar('NoSchool7', 'y-mm-dd');" src="Images/calendar.gif"> <span class="SmallText"><?php echo gettext("[format: YYYY-MM-DD]"); ?></span></td>
	</tr>

	<tr>
		<td class="LabelColumn"><?php addToolTip("Format: YYYY-MM-DD<br>or enter the date by clicking on the calendar icon to the right."); ?><?php echo gettext("No Sunday School:"); ?></td>
		<td class="TextColumn"><input type="text" name="NoSchool8" value="<?php echo $dNoSchool8; ?>" maxlength="10" id="NoSchool8" size="11">&nbsp;<input type="image" onclick="return showCalendar('NoSchool8', 'y-mm-dd');" src="Images/calendar.gif"> <span class="SmallText"><?php echo gettext("[format: YYYY-MM-DD]"); ?></span></td>
	</tr>

	<tr>
		<td class="LabelColumn"><?php addToolTip("Number of extra rows for write-in students"); ?><?php echo gettext("Extra Students:"); ?></td>
		<td class="TextColumn"><input type="text" name="ExtraStudents" value="<?php echo $iExtraStudents; ?>" id="ExtraStudents" size="11">&nbsp;</td>
	</tr>

	<tr>
		<td class="LabelColumn"><?php addToolTip("Number of extra rows for write-in teachers"); ?><?php echo gettext("Extra Teachers:"); ?></td>
		<td class="TextColumn"><input type="text" name="ExtraTeachers" value="<?php echo $iExtraTeachers; ?>" id="ExtraTeachers" size="11">&nbsp;</td>
	</tr>

   <tr>
      <td><input type="submit" class="icButton" name="SubmitClassList" <?php echo 'value="' . gettext("Create Class List") . '"'; ?>></td>
      <td><input type="submit" class="icButton" name="SubmitClassAttendance" <?php echo 'value="' . gettext("Create Attendance Sheet") . '"'; ?>></td>
      <td><input type="button" class="icButton" name="Cancel" <?php echo 'value="' . gettext("Cancel") . '"'; ?> onclick="javascript:document.location='Menu.php';"></td>
   </tr>

</table>

</form>

<?php
require "Include/Footer.php";
?>
