<?php
/*******************************************************************************
 *
 *  filename    : Canvas05Editor.php
 *  last change : 2004-6-12
 *  website     : http://www.infocentral.org
 *  copyright   : Copyright 2001, 2002, 2003 Deane Barker, Chris Gebhardt, Michael Wilt
 *
 *  InfoCentral is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

//Include the function library
require "Include/Config.php";
require "Include/Functions.php";

$iCanvas05ID = FilterInput($_GET["Canvas05ID"],'int');
$linkBack = FilterInput($_GET["linkBack"]);
$iFamily = FilterInput($_GET["FamilyID"]);

//Get Family name
$sSQL = "SELECT fam_Name FROM family_fam where fam_ID = " . $iFamily;
$rsFamily = RunQuery($sSQL);
extract(mysql_fetch_array($rsFamily));

$sPageTitle = gettext("Canvas 2005 Input for the " . $fam_Name . " family");

//Is this the second pass?
if (isset($_POST["Submit"]))
{
	$tChurchColor = FilterInput($_POST["ChurchColor"]);
	$tDoingRight = FilterInput($_POST["DoingRight"]);
	$tCanImprove = FilterInput($_POST["CanImprove"]);
	$tPledgeByMar31 = FilterInput($_POST["PledgeByMar31"]);
	$tComments = FilterInput($_POST["Comments"]);

	// New canvas input (add)
	if (strlen($iCanvas05ID) < 1)
	{
		$sSQL = "INSERT INTO canvas05_c05 (c05_famID, c05_churchColor, c05_doingRight, c05_canImprove, c05_pledgeByMar31, c05_comments)
		VALUES (" . $iFamily . ", \"" . $tChurchColor . "\", \"" . $tDoingRight . "\", \"" . $tCanImprove . "\", \"" . $tPledgeByMar31 . "\", \"" . $tComments . "\")";

	// Existing record (update)
	} else {
		$sSQL = "UPDATE canvas05_c05 SET c05_churchColor = \"" . $tChurchColor . "\", c05_doingRight = \"" . $tDoingRight . "\", c05_canImprove = \"" . $tCanImprove . "\", c05_pledgeByMar31 = \"" . $tPledgeByMar31 . "\", c05_comments = \"" . $tComments . "\" WHERE c05_FamID = " . $iFamily;
	}

	//Execute the SQL
	RunQuery($sSQL);

	if (isset($_POST["Submit"]))
	{
		// Check for redirection to another page after saving information: (ie. PledgeEditor.php?previousPage=prev.php?a=1;b=2;c=3)
		if ($linkBack != "") {
			Redirect($linkBack);
		} else {
			//Send to the view of this pledge
			Redirect("Canvas05Editor.php?FamilyID=" . $iFamily . "&linkBack=", $linkBack);
		}
	}

} else {
	$sSQL = "SELECT * FROM canvas05_c05 WHERE c05_famID = " . $iFamily;
	$rsCanvas05 = RunQuery($sSQL);
	if (mysql_num_rows ($rsCanvas05) > 0) {
		extract(mysql_fetch_array($rsCanvas05));

		$tChurchColor = $c05_churchColor;
		$tDoingRight = $c05_doingRight;
		$tCanImprove = $c05_canImprove;
		$tPledgeByMar31 = $c05_pledgeByMar31;
		$tComments = $c05_comments;
	} else {
	}
}

require "Include/Header.php";

?>

<form method="post" action="Canvas05Editor.php?<?php echo "FamilyID=" . $iFamily . "&linkBack=" . $linkBack; ?>" name="Canvas05Editor">

<table cellpadding="3" align="center">

	<tr>
		<td align="center">
			<input type="submit" class="icButton" value="<?php echo gettext("Save"); ?>" name="Submit">
			<input type="button" class="icButton" value="<?php echo gettext("Cancel"); ?>" name="Cancel" onclick="javascript:document.location='<?php if (strlen($linkBack) > 0) { echo $linkBack; } else {echo "Menu.php"; } ?>';">
		</td>
	</tr>

	<tr>
		<td>
		<table cellpadding="3">
			<tr>
				<td class="LabelColumn"><?php echo gettext("Do you like the color of the church?");?></td>
				<td><textarea name="ChurchColor" rows="3" cols="90"><?php echo $tChurchColor?></textarea></td>
			</tr>
	
			<tr>
				<td class="LabelColumn"><?php echo gettext("What are we doing right?");?></td>
				<td><textarea name="DoingRight" rows="3" cols="90"><?php echo $tDoingRight?></textarea></td>
			</tr>
	
			<tr>
				<td class="LabelColumn"><?php echo gettext("How can we improve?");?></td>
				<td><textarea name="CanImprove" rows="3" cols="90"><?php echo $tCanImprove?></textarea></td>
			</tr>
	
			<tr>
				<td class="LabelColumn"><?php echo gettext("Will you pledge by March 31?");?></td>
				<td><textarea name="PledgeByMar31" rows="3" cols="90"><?php echo $tPledgeByMar31?></textarea></td>
			</tr>
	
			<tr>
				<td class="LabelColumn"><?php echo gettext("Canvasser Comments:");?></td>
				<td><textarea name="Comments" rows="3" cols="90"><?php echo $tComments?></textarea></td>
			</tr>
	
		</table>
		</td>
	</form>
</table>

<?php
require "Include/Footer.php";
?>
