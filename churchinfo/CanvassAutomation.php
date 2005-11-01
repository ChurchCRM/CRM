<?php
/*******************************************************************************
 *
 *  filename    : CanvassAutomation.php
 *  last change : 2005-02-21
 *  website     : http://www.infocentral.org
 *  copyright   : Copyright 2001-2005 Deane Barker, Chris Gebhardt, Michael Wilt, Tim Dearborn
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

require "Include/CanvassUtilities.php";

//Set the page title
$sPageTitle = gettext("Canvass Automation");

// Security: User must have canvasser permission to use this form
if (!$_SESSION['bCanvasser'])
{
	Redirect("Menu.php");
	exit;
}

$iFYID = FilterInput($_POST["FYID"], 'int'); // Use FY from the form if it was set
if ($iFYID == 0)
	$iFYID = $_SESSION['idefaultFY'];

$_SESSION['idefaultFY'] = $iFYID; // Remember default fiscal year

// Service the action buttons
if (isset($_POST["SetDefaultFY"])) {
	if (isset($_POST["SetDefaultFYConfirm"])) {
		$processNews = CanvassSetDefaultFY ($iFYID);
	} else {
		$processNews = gettext ("Not confirmed.");
	}
}
if (isset($_POST["AssignCanvassers"])) {
	if (isset($_POST["AssignCanvassersConfirm"])) {
		$processNews = CanvassAssignCanvassers (gettext ("Canvassers"));
	} else {
		$processNews = gettext ("Not confirmed.");
	}
}
if (isset($_POST["AssignNonPledging"])) {
	if (isset($_POST["AssignNonPledgingConfirm"])) {
		$processNews = CanvassAssignNonPledging (gettext ("BraveCanvassers"), $iFYID);
	} else {
		$processNews = gettext ("Not confirmed.");
	}
}
if (isset($_POST["ClearCanvasserAssignments"])) {
	if (isset($_POST["ClearCanvasserAssignmentsConfirm"])) {
		CanvassClearCanvasserAssignments ();
		$processNews = gettext ("Cleared all canvasser assignments.");
	} else {
		$processNews = gettext ("Not confirmed.");
	}
}
if (isset($_POST["SetAllOkToCanvass"])) {
	if (isset($_POST["SetAllOkToCanvassConfirm"])) {
		CanvassSetAllOkToCanvass ();
		$processNews = gettext ("Set Ok To Canvass for all families.");
	} else {
		$processNews = gettext ("Not confirmed.");
	}
}
if (isset($_POST["ClearAllOkToCanvass"])) {
	if (isset($_POST["ClearAllOkToCanvassConfirm"])) {
		CanvassClearAllOkToCanvass ();
		$processNews = gettext ("Disabled Ok To Canvass for all families.");
	} else {
		$processNews = gettext ("ClearAllOkToCanvass button not confimed.");
	}
}
if (isset($_POST["BriefingSheets"])) {
	redirect ("Reports/CanvassReports.php?FYID=" . $iFYID . "&WhichReport=Briefing");
}
if (isset($_POST["ProgressReport"])) {
	redirect ("Reports/CanvassReports.php?FYID=" . $iFYID . "&WhichReport=Progress");
}
if (isset($_POST["SummaryReport"])) {
	redirect ("Reports/CanvassReports.php?FYID=" . $iFYID . "&WhichReport=Summary");
}
if (isset($_POST["NotInterestedReport"])) {
	redirect ("Reports/CanvassReports.php?FYID=" . $iFYID . "&WhichReport=NotInterested");
}

require "Include/Header.php";

echo "<p>" . $processNews . "</p>"; // Report any action just taken by button processing

?>

<form method="post" action="<?php echo $_SERVER['PHP_SELF']?>" name="CanvassAutomation">

<p>Fiscal Year:
<?php PrintFYIDSelect ($iFYID, "FYID") ?>
</p>

<table border width="100%" align="left">
	<tr>
		<td align="center" width="25%">
			<input type="submit" class="icButton" value="<?php echo gettext("Set default fiscal year"); ?>" 
			 name="SetDefaultFY">
		</td>
		<td align="left" width="75%">
			<?php echo gettext(""); ?>
			<p><input type="checkbox" name="SetDefaultFYConfirm"><?php echo gettext("Check to confirm");?></p>
		</td>
	</tr>

	<tr>
		<td align="center" width="25%">
			<input type="submit" class="icButton" value="<?php echo gettext("Assign Canvassers"); ?>" 
			 name="AssignCanvassers">
		</td>
		<td align="left" width="75%">
			<?php echo gettext("Randomly assign canvassers to all Families.  The Canvassers are 
			taken from the &quot;Canvassers&quot; Group."); ?>
			<p><input type="checkbox" name="AssignCanvassersConfirm"><?php echo gettext("Check to confirm");?></p>
		</td>
	</tr>

	<tr>
		<td align="center" width="25%">
			<input type="submit" class="icButton" value="<?php echo gettext("Assign To Non Pledging"); ?>" 
			 name="AssignNonPledging">
		</td>
		<td align="left" width="75%">
			<?php echo gettext("Randomly assign canvassers to non-pledging Families.  The Canvassers are 
			taken from the &quot;BraveCanvassers&quot; Group."); ?>
			<p><input type="checkbox" name="AssignNonPledgingConfirm"><?php echo gettext("Check to confirm");?></p>
		</td>
	</tr>

	<tr>
		<td align="center" width="25%">
			<input type="submit" class="icButton" value="<?php echo gettext("Clear Canvasser Assignments"); ?>" 
			 name="ClearCanvasserAssignments">
		</td>
		<td align="left" width="75%">
			<?php echo gettext("Clear all the canvasser assignments for all families.  <p>Important
			note: this will lose any canvasser assignments that have been made by hand.</p>"); ?>
			<input type="checkbox" name="ClearCanvasserAssignmentsConfirm"><?php echo gettext("Check to confirm");?>
		</td>
	</tr>

	<tr>
		<td align="center" width="25%">
			<input type="submit" class="icButton" value="<?php echo gettext("Enable Canvass for All Families"); ?>" 
			 name="SetAllOkToCanvass">
		</td>
		<td align="left" width="75%">
			<?php echo gettext("Turn on the &quot;Ok To Canvass&quot; field for all Families.  <p>Important
			note: this will lose any &quot;Ok To Canvass&quot; fields that have been set by hand.</p>"); ?>
			<input type="checkbox" name="SetAllOkToCanvassConfirm"><?php echo gettext("Check to confirm");?>
		</td>
	</tr>

	<tr>
		<td align="center" width="25%">
			<input type="submit" class="icButton" value="<?php echo gettext("Disable Canvass for All Families"); ?>" 
			 name="ClearAllOkToCanvass">
		</td>
		<td align="left" width="75%">
			<?php echo gettext("Turn off the &quot;Ok To Canvass&quot; field for all Families.  <p>Important
			note: this will lose any &quot;Ok To Canvass&quot; fields that have been set by hand.</p>"); ?>
			<input type="checkbox" name="ClearAllOkToCanvassConfirm"><?php echo gettext("Check to confirm");?>
		</td>
	</tr>

	<tr>
		<td align="center" width="25%">
			<input type="submit" class="icButton" value="<?php echo gettext("Briefing Sheets"); ?>" 
			 name="BriefingSheets">
		</td>
		<td align="left" width="75%">
			<?php echo gettext("Generate a PDF containing briefing sheets for all Families, sorted by canvasser."); ?>
		</td>
	</tr>

	<tr>
		<td align="center" width="25%">
			<input type="submit" class="icButton" value="<?php echo gettext("Progress Report"); ?>" 
			 name="ProgressReport">
		</td>
		<td align="left" width="75%">
			<?php echo gettext("Generate a PDF containing a progress report.  The progress report includes
			information on the overall progress of the canvass, and the progress of individual canvassers."); ?>
		</td>
	</tr>

	<tr>
		<td align="center" width="25%">
			<input type="submit" class="icButton" value="<?php echo gettext("Summary Report"); ?>" 
			 name="SummaryReport">
		</td>
		<td align="left" width="75%">
			<?php echo gettext("Generate a PDF containing a summary report.  The summary report includes
			comments extracted from the canvass data."); ?>
		</td>
	</tr>

	<tr>
		<td align="center" width="25%">
			<input type="submit" class="icButton" value="<?php echo gettext("Not Interested Report"); ?>" 
			 name="NotInterestedReport">
		</td>
		<td align="left" width="75%">
			<?php echo gettext("Generate a PDF containing a report of the families marked &quot;Not Interested&quot; 
			                    by the canvasser."); ?>
		</td>
	</tr>
</table>

</form>


<?php
require "Include/Footer.php";
?>
