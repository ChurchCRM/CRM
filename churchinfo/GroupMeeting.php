<?php
/*******************************************************************************
 *
 *  filename    : GroupMeeting.php
 *  last change : 2004-11-7
 *  website     : http://www.churchcrm.io
 *  copyright   : Copyright 2001, 2002, 2003, 2004 Deane Barker, Chris Gebhardt, Michael Wilt
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
	$iHour = FilterInput($_POST["hour"]);
	$iMinutes = FilterInput($_POST["minute"]);
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

		// Move the meeting date/time from here to UTC for storage
	    $timezoneHere = new DateTimeZone(date_default_timezone_get());
	    $timezoneUTC = new DateTimeZone('UTC');
		$dateTimeMeeting = new DateTime($dDate.$iHour.":".$iMinutes, $timezoneHere);
		$dateTimeMeeting->setTimezone($timezoneUTC);
		$dDate = $dateTimeMeeting->format ('Y-m-d');
		$iHour = $dateTimeMeeting->format ('H');
		$iMinutes = $dateTimeMeeting->format ('i'); 
		
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

<div class="alert alert-info alert-dismissable">
		<i class="fa fa-info"></i>
		<button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>&nbsp;&nbsp;Important note: this form may be used to schedule a WebCalendar meeting.  Once the 
meeting is scheduled any changes must be made within WebCalendar.  All members of
this group will be added to the meeting as external users of WebCalendar.
</div>
<div class="box box-body">
<form method="post" action="GroupMeeting.php?<?= "GroupID=" . $iGroupID . "&linkBack=" . $linkBack . "&Name=" . $tName ?>" name="GroupMeeting">
    <div class="form-group">
        <div class="row">
            <div class="col-xs-2">
            <label for="Name"><?= gettext("Meeting name") ?></label>
            <input class="form-control input-small" type="text" name="Name" id="Name" value="<?= $tName ?>">
            </div>
      </div> <p> </p> <div class="row">
            <div class="col-xs-2">
            <label for="Description"><?= gettext("Meeting description") ?></label>
            <input class="form-control input-small" type="text" name="Description" id="Description" value="<?= $tDescription ?>">
            </div>
      </div> <p> </p> <div class="row">
            <div class="col-xs-2">
            <label for="Date"><?= gettext("Date") ?></label>
            <input class="form-control input-small" type="text" name="Date" value="<?= $dDate ?>" maxlength="10" id="Date" size="11"><font color="red"><?php echo $sDateError ?></font>
            </div>
            <div class="col-xs-2">
            <label for="Time"><?= gettext("Time") ?></label>
            <div class="input-group bootstrap-timepicker timepicker">
                <input name="Time" id="Time" type="text" class="form-control input-small">
                <span class="input-group-addon"><i class="glyphicon glyphicon-time"></i></span>
            </div>
            </div>
      </div> <p> </p> <div class="row">
            <div class="col-xs-2">
            <?= gettext("Duration (hours)") ?>
            <input class="form-control input-small" type="text" name="Duration" id="Duration" value="<?= $nDuration ?>">

                            <?= gettext("Notify ahead (days)") ?>
                            <input type="text" name="NotifyAhead" id="NotifyAhead" value="<?= $nNotifyAhead ?>">
            </div>
      </div> <p> </p> <div class="row">
            <div class="col-xs-2">
                <input type="submit" class="btn btn-primary" value="<?= gettext("Submit") ?>" name="Submit">
            </div>
        </div>
    </div>
<form>

</div>
<script>
$("#Time").timepicker({showMeridian: false});
$("#Date").datepicker({format:'yyyy-mm-dd'});
</script>

<?php require "Include/Footer.php" ?>
