<?php
/*******************************************************************************
 *
 *  filename    : ReportList.php
 *  last change : 2003-03-20
 *  website     : http://www.infocentral.org
 *  copyright   : Copyright 2003 Chris Gebhardt
 *
 *  InfoCentral is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

require "Include/Config.php";
require "Include/Functions.php";

//Set the page title
$sPageTitle = gettext("Report Menu");

$today = getdate();
$year = $today['year'];

require "Include/Header.php";

?>

<p>
<a class="MediumText" href="GroupReports.php"><?php echo gettext("Reports on groups and roles"); ?></a>
<br>
<?php echo gettext("Report on group and roles selected (it may be a multi-page PDF)."); ?>
</p>


<?php if ($_SESSION['bAdmin'] || !$bCSVAdminOnly) { ?>
	<p>
	<a class="MediumText" href="DirectoryReports.php"><?php echo gettext("Members Directory"); ?></a>
	<br>
	<?php echo gettext("Printable directory of all members, grouped by family where assigned"); ?>
	</p>
<?php } ?>

<?php /*
<p>
<a href=""><?php echo gettext("Members Directory w/Photos"); ?></a>
<br>
<?php echo gettext("Printable directory of all members. Family photos where available / Individual photos otherwise."); ?>
</p> */ ?>

<p>
<a class="MediumText" href="LettersAndLabels.php"><?php echo gettext("Letters and Mailing Labels"); ?></a>
<br>
<?php echo gettext("Generate letters and mailing labels."); ?>
</p>

<p>
<a class="MediumText" href="SundaySchool.php"><?php echo gettext("Sunday School Reports"); ?></a>
<br>
<?php echo gettext("Generate class lists and attendance sheets"); ?>
</p>


<p>
<a class="MediumText" href="FinancialReports.php"><?php echo gettext("Financial Reports"); ?></a>
<br>
<?php echo gettext("Pledges and Payments"); ?>
</p>

<p>
<a class="MediumText" href="CanvassAutomation.php"><?php echo gettext("Canvass Automation"); ?></a>
<br>
<?php echo gettext("Automated support for conducting an every-member canvass."); ?>
</p>

<p>
<span class="MediumText"><u><?php echo gettext("Event Attendance"); ?></u></span>
<br>
<?php echo gettext("Generate attendance -AND- non-attendance reports for events"); ?>
<br>
<?php
$sSQL = "SELECT * FROM event_types";
$rsOpps = RunQuery($sSQL);
$numRows = mysql_num_rows($rsOpps);

// List all events
        for ($row = 1; $row <= $numRows; $row++)
        {
                $aRow = mysql_fetch_array($rsOpps);
                extract($aRow);
                echo '&nbsp;&nbsp;&nbsp;<a href="EventAttendance.php?Action=List&Event='.$type_id.'&Type='.gettext($type_name).'" title="List All '.gettext($type_name).' Events"><strong>'.gettext($type_name).'</strong></a>'."<br>\n";
        }
?>
</p>

<?php
require "Include/Footer.php";
?>
