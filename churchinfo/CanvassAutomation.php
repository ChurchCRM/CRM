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
	$processNews = "ProgressReport button pressed.";
}

require "Include/Header.php";

echo "<p>" . $processNews . "</p>"; // Report any action just taken by button processing

?>

<form method="post" action="<?php echo $_SERVER['PHP_SELF']?>" name="CanvassAutomation">

<p>Fiscal Year: 
<select name="FYID">
	<option value="0"><?php echo gettext("Select Fiscal Year"); ?></option>
	<option value="1" <?php if ($iFYID == 1) { echo "selected"; } ?>><?php echo gettext("1996/97"); ?></option>
	<option value="2" <?php if ($iFYID == 2) { echo "selected"; } ?>><?php echo gettext("1997/98"); ?></option>
	<option value="3" <?php if ($iFYID == 3) { echo "selected"; } ?>><?php echo gettext("1998/99"); ?></option>
	<option value="4" <?php if ($iFYID == 4) { echo "selected"; } ?>><?php echo gettext("1999/00"); ?></option>
	<option value="5" <?php if ($iFYID == 5) { echo "selected"; } ?>><?php echo gettext("2000/01"); ?></option>
	<option value="6" <?php if ($iFYID == 6) { echo "selected"; } ?>><?php echo gettext("2001/02"); ?></option>
	<option value="7" <?php if ($iFYID == 7) { echo "selected"; } ?>><?php echo gettext("2002/03"); ?></option>
	<option value="8" <?php if ($iFYID == 8) { echo "selected"; } ?>><?php echo gettext("2003/04"); ?></option>
	<option value="9" <?php if ($iFYID == 9) { echo "selected"; } ?>><?php echo gettext("2004/05"); ?></option>
	<option value="10" <?php if ($iFYID == 10) { echo "selected"; } ?>><?php echo gettext("2005/06"); ?></option>
	<option value="11" <?php if ($iFYID == 11) { echo "selected"; } ?>><?php echo gettext("2006/07"); ?></option>
	<option value="12" <?php if ($iFYID == 12) { echo "selected"; } ?>><?php echo gettext("2007/08"); ?></option>
	<option value="13" <?php if ($iFYID == 13) { echo "selected"; } ?>><?php echo gettext("2008/09"); ?></option>
	<option value="14" <?php if ($iFYID == 14) { echo "selected"; } ?>><?php echo gettext("2009/10"); ?></option>
	<option value="15" <?php if ($iFYID == 15) { echo "selected"; } ?>><?php echo gettext("2010/11"); ?></option>
	<option value="16" <?php if ($iFYID == 16) { echo "selected"; } ?>><?php echo gettext("2011/12"); ?></option>
	<option value="17" <?php if ($iFYID == 17) { echo "selected"; } ?>><?php echo gettext("2012/13"); ?></option>
	<option value="18" <?php if ($iFYID == 18) { echo "selected"; } ?>><?php echo gettext("2013/14"); ?></option>
</select>
</p>

<table border width="100%" align="left">
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
			<?php echo gettext("Generate a PDF conaining a progress report.  The progress report includes
			information on the overall progress of the canvass, and the progress of individual canvassers."); ?>
		</td>
	</tr>
</table>

</form>


<?php
require "Include/Footer.php";
?>
