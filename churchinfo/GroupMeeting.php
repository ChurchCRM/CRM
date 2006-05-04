<?php
/*******************************************************************************
 *
 *  filename    : GroupMeeting.php
 *  last change : 2004-11-7
 *  website     : http://www.churchdb.org
 *  copyright   : Copyright 2001, 2002, 2003, 2004 Deane Barker, Chris Gebhardt, Michael Wilt
 *
 *  ChurchInfo is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

//Include the function library
require "Include/Config.php";
require "Include/Functions.php";

//Get the GroupID out of the querystring
$iGroupID = FilterInput($_GET["GroupID"],'int');
$linkBack = FilterInput($_GET["linkBack"]);
$tName = FilterInput($_GET["Name"]);

//Set the page title
$sPageTitle = gettext("Schedule Group Meeting");

//Is this the second pass?
if (isset($_POST["Submit"]))
{
	$dDate = FilterInput($_POST["Date"]);
	$iHour = FilterInput($_POST["Hour"]);
	$iMinutes = FilterInput($_POST["Minutes"]);
	$nNotifyAhead = FilterInput($_POST["NotifyAhead"]);
	$tName = FilterInput($_POST["Name"]);
	$tDescription = FilterInput($_POST["Description"]);
	$nDuration = FilterInput($_POST["Duration"]);

	// Validate Date
	if (strlen($dDate) > 0)
	{
		list($iYear, $iMonth, $iDay) = sscanf($dDate,"%04d-%02d-%02d");
		if ( !checkdate($iMonth,$iDay,$iYear) )
		{
			$sDateError = "<span style=\"color: red; \">" . gettext("Not a valid Date") . "</span>";
			$bErrorFlag = true;
		}
	}

	//If no errors, then let's update...
	if (!$bErrorFlag)
	{
		//Get all the members of this group
		$sSQL = "SELECT * FROM person_per, person2group2role_p2g2r WHERE per_ID = p2g2r_per_ID AND p2g2r_grp_ID = " . $iGroupID;
		$rsGroupMembers = RunQuery($sSQL);

		$calDbId = mysql_select_db ($sWEBCALENDARDB);

		$q = "SELECT MAX(cal_id) AS calID FROM webcal_entry";
		$rsEventID = mysql_query ($q);
		extract(mysql_fetch_array($rsEventID));

		$calID = $calID + 1;

		$datestr = sprintf ("%04d%02d%02d", substr ($dDate, 0, 4), substr ($dDate, 5, 2), substr ($dDate, 8, 2));
		$timestr = sprintf ("%02d%02d00", $iHour, $iMinutes);
		$modtimestr = sprintf ("%02d%02d00", date ("h"), date ("m"));
		$q = "INSERT INTO webcal_entry (cal_id,
		                                cal_create_by, 
		                                cal_date, 
										cal_time, 
										cal_mod_date, 
										cal_mod_time, 
										cal_duration, 
										cal_type, 
										cal_access, 
										cal_name, 
										cal_description)
								VALUES (" . 
								        $calID . "," .
								        "\"__public__\", " .
								        "'" . $datestr . "'," .
										"'" . $timestr . "'," .
										"'" . date ("Ymd") . "'," .
										"'" . $modtimestr . "'," .
										($nDuration * 60) . "," .
										"'E'," .
										"'P'," .
										"'" . $tName . "'," .
										"'" . $tDescription . "')";
		mysql_query ($q);

		$q = "INSERT INTO webcal_entry_user (cal_id,
		                                     cal_login,
											 cal_status)
									VALUES (" . $calID . "," .
									        "'__public__'," .
											"'A')";
		mysql_query ($q);

		$q = "INSERT INTO webcal_site_extras (cal_id,
		                                      cal_name,
											  cal_type,
											  cal_date,
											  cal_remind,
											  cal_data)
									VALUES (" . $calID . "," .
											"'Reminder'," .
											"7," .
											"0," .
											"1," .
											"'" . ($nNotifyAhead * 60 * 24) . "')";
		mysql_query ($q);

		mysql_select_db ($sDATABASE);
		while ($aRow = mysql_fetch_array($rsGroupMembers))
		{
			extract($aRow);
			$calDbId = mysql_select_db ($sWEBCALENDARDB);
			$q = "INSERT INTO webcal_entry_ext_user (cal_id,
			                                         cal_fullname,
													 cal_email)
											VALUES (" . $calID . "," .
											        "'" . $per_FirstName . " " . $per_LastName . "'," .
													"'" . $per_Email . "')";
			mysql_query ($q);

			mysql_select_db ($sDATABASE);
		}

		mysql_select_db ($sDATABASE);

		Redirect ($linkBack);
	}

} else {
	//Set defaults

	$iHour =19;
	$iMinutes = 0;
}

require "Include/Header.php";

?>

<p>Important note: this form may be used to schedule a WebCalendar meeting.  Once the 
meeting is scheduled any changes must be made within WebCalendar.  All members of
this group will be added to the meeting as external users of WebCalendar.</p>

<form method="post" action="GroupMeeting.php?<?php echo "GroupID=" . $iGroupID . "&linkBack=" . $linkBack . "&Name=" . $tName; ?>" name="GroupMeeting">

<table cellpadding="3" align="center">

	<tr>
		<td align="center">
			<input type="submit" class="icButton" value="<?php echo gettext("Submit"); ?>" name="Submit">
			<input type="button" class="icButton" value="<?php echo gettext("Cancel"); ?>" name="Cancel" onclick="javascript:document.location='<?php if (strlen($linkBack) > 0) { echo $linkBack; } else {echo "Menu.php"; } ?>';">
		</td>
	</tr>

	<tr>
		<td>
		<table cellpadding="3">
			<tr>
				<td class="LabelColumn"><?php echo gettext("Meeting name"); ?></td>
				<td class="TextColumn"><input type="text" name="Name" id="Name" value="<?php echo $tName; ?>"></td>
			</tr>
	
			<tr>
				<td class="LabelColumn"><?php echo gettext("Meeting description"); ?></td>
				<td class="TextColumn"><input type="text" name="Description" id="Description" value="<?php echo $tDescription; ?>"></td>
			</tr>
	
			<tr>
				<td class="LabelColumn"><?php addToolTip("Format: YYYY-MM-DD<br>or enter the date by clicking on the calendar icon to the right."); ?><?php echo gettext("Date"); ?></td>
				<td class="TextColumn"><input type="text" name="Date" value="<?php echo $dDate; ?>" maxlength="10" id="sel1" size="11">&nbsp;<input type="image" onclick="return showCalendar('sel1', 'y-mm-dd');" src="Images/calendar.gif"> <span class="SmallText"><?php echo gettext("[format: YYYY-MM-DD]"); ?></span><font color="red"><?php echo $sDateError ?></font></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("Time"); ?></td>
				<td class="TextColumnWithBottomBorder">
					<select name="Hour">
						<option value="0" <?php if ($iHour == 0) { echo "selected"; } ?>><?php echo gettext("Midnight"); ?></option>
						<option value="1" <?php if ($iHour == 1) { echo "selected"; } ?>><?php echo gettext("1"); ?></option>
						<option value="2" <?php if ($iHour == 2) { echo "selected"; } ?>><?php echo gettext("2"); ?></option>
						<option value="3" <?php if ($iHour == 3) { echo "selected"; } ?>><?php echo gettext("3"); ?></option>
						<option value="4" <?php if ($iHour == 4) { echo "selected"; } ?>><?php echo gettext("4"); ?></option>
						<option value="5" <?php if ($iHour == 5) { echo "selected"; } ?>><?php echo gettext("5"); ?></option>
						<option value="6" <?php if ($iHour == 6) { echo "selected"; } ?>><?php echo gettext("6"); ?></option>
						<option value="7" <?php if ($iHour == 7) { echo "selected"; } ?>><?php echo gettext("7"); ?></option>
						<option value="8" <?php if ($iHour == 8) { echo "selected"; } ?>><?php echo gettext("8"); ?></option>
						<option value="9" <?php if ($iHour == 9) { echo "selected"; } ?>><?php echo gettext("9"); ?></option>
						<option value="10" <?php if ($iHour == 10) { echo "selected"; } ?>><?php echo gettext("10"); ?></option>
						<option value="11" <?php if ($iHour == 11) { echo "selected"; } ?>><?php echo gettext("11"); ?></option>
						<option value="12" <?php if ($iHour == 12) { echo "selected"; } ?>><?php echo gettext("Noon"); ?></option>
						<option value="13" <?php if ($iHour == 13) { echo "selected"; } ?>><?php echo gettext("1"); ?></option>
						<option value="14" <?php if ($iHour == 14) { echo "selected"; } ?>><?php echo gettext("2"); ?></option>
						<option value="15" <?php if ($iHour == 15) { echo "selected"; } ?>><?php echo gettext("3"); ?></option>
						<option value="16" <?php if ($iHour == 16) { echo "selected"; } ?>><?php echo gettext("4"); ?></option>
						<option value="17" <?php if ($iHour == 17) { echo "selected"; } ?>><?php echo gettext("5"); ?></option>
						<option value="18" <?php if ($iHour == 18) { echo "selected"; } ?>><?php echo gettext("6"); ?></option>
						<option value="19" <?php if ($iHour == 19) { echo "selected"; } ?>><?php echo gettext("7"); ?></option>
						<option value="20" <?php if ($iHour == 20) { echo "selected"; } ?>><?php echo gettext("8"); ?></option>
						<option value="21" <?php if ($iHour == 21) { echo "selected"; } ?>><?php echo gettext("9"); ?></option>
						<option value="22" <?php if ($iHour == 22) { echo "selected"; } ?>><?php echo gettext("10"); ?></option>
						<option value="23" <?php if ($iHour == 23) { echo "selected"; } ?>><?php echo gettext("11"); ?></option>
					</select>
					<select name="Minutes">
						<option value="00" <?php if ($iMinutes == 0) { echo "selected"; } ?>><?php echo gettext("00"); ?></option>
						<option value="15" <?php if ($iMinutes == 15) { echo "selected"; } ?>><?php echo gettext("15"); ?></option>
						<option value="30" <?php if ($iMinutes == 30) { echo "selected"; } ?>><?php echo gettext("30"); ?></option>
						<option value="45" <?php if ($iMinutes == 45) { echo "selected"; } ?>><?php echo gettext("45"); ?></option>
					</select>
				</td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("Duration (hours)"); ?></td>
				<td class="TextColumn"><input type="text" name="Duration" id="Duration" value="<?php echo $nDuration; ?>"></td>
			</tr>
	
			<tr>
				<td class="LabelColumn"><?php echo gettext("Notify ahead (days)"); ?></td>
				<td class="TextColumn"><input type="text" name="NotifyAhead" id="NotifyAhead" value="<?php echo $nNotifyAhead; ?>"></td>
			</tr>
	
		</table>
		</td>
	</form>
</table>

<?php
require "Include/Footer.php";
?>
