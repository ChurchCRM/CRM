<?php
/*******************************************************************************
 *
 *  filename    : ManageEnvelopes.php
 *  last change : 2005-02-21
 *  website     : http://www.churchdb.org
 *  copyright   : Copyright 2006 Michael Wilt
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

require "Include/EnvelopeFunctions.php";

//Set the page title
$sPageTitle = gettext("Envelope Manager");

// Security: User must have finance permission to use this form
if (!$_SESSION['bFinance'])
{
	Redirect("Menu.php");
	exit;
}

// Service the action buttons
if (isset($_POST["AssignAllFamilies"])) {
	if (isset($_POST["AssignAllFamiliesConfirm"])) {
		$bMembersOnly = isset($_POST["MembersOnly"]);
		$processNews = EnvelopeAssignAllFamilies ($bMembersOnly);
	} else {
		$processNews = gettext ("Not confirmed.");
	}
}
if (isset($_POST["BriefingSheets"])) {
	redirect ("Reports/EnvelopeReport.php");
}

require "Include/Header.php";

echo "<p><B>" . $processNews . "</B></p>"; // Report any action just taken by button processing

?>

<form method="post" action="ManageEnvelopes.php" name="ManageEnvelopes">

<table border width="100%" align="left">
	<tr>
		<td align="center" width="25%">
			<input type="submit" class="icButton" value="<?php echo gettext("Assign envelopes to all familes"); ?>" 
			 name="AssignAllFamilies">
		</td>
		<td align="left" width="75%">
			<?php echo gettext("<p>Assign new envelope numbers to all families.  Any existing assignments will be replaced.  This feature will only execute if &quot;Check fo Confirm&quot; is enabled to inhibit accidental execution.</p>"); ?>
			<p><input type="checkbox" name="MembersOnly"><?php echo gettext("Members only (families with at least one church member)");?></p>
			<p><input type="checkbox" name="AssignAllFamiliesConfirm"><?php echo gettext("Check to confirm");?></p>
		</td>
	</tr>

	<tr>
		<td align="center" width="25%">
			<input type="submit" class="icButton" value="<?php echo gettext("Envelope List"); ?>" 
			 name="BriefingSheets">
		</td>
		<td align="left" width="75%">
			<?php echo gettext("Generate a PDF containing all the envelope assignments, sorted by family name."); ?>
		</td>
	</tr>

</table>

</form>


<?php
require "Include/Footer.php";
?>
