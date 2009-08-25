<?php
/*******************************************************************************
 *
 *  filename    : PledgeDelete.php
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

//Set the page title
$sPageTitle = gettext("Confirm Delete");

//Get the PledgeID out of the querystring
$iPledgeID = FilterInput($_GET["PledgeID"],'int');
$linkBack = FilterInput($_GET["linkBack"]);

// Security: User must have Add or Edit Records permission to use this form in those manners
// Clean error handling: (such as somebody typing an incorrect URL ?PersonID= manually)
if (strlen($iPledgeID) > 0)
{
	if (!$_SESSION['bEditRecords'])
	{
		Redirect("Menu.php");
		exit;
	}
	$sSQL = "SELECT '' FROM pledge_plg WHERE plg_plgID = " . $iPledgeID;
	if (mysql_num_rows(RunQuery($sSQL)) == 0)
	{
		Redirect("Menu.php");
		exit;
	}
}
elseif (!$_SESSION['bAddRecords'])
{
	Redirect("Menu.php");
	exit;
}

//Is this the second pass?
if (isset($_POST["Delete"])) {
	// because we're creating extra entries for same-check numbers to split out the giving, we need to query the DB and find like check numbers and delete all the entries with that same check number

	$sSQL = "SELECT plg_famID, plg_CheckNo, plg_date, plg_FYID, plg_method from pledge_plg where plg_plgID=\"" . $iPledgeID . "\";";
	$rsFam = RunQuery($sSQL);
	extract(mysql_fetch_array($rsFam));
	if ($plg_method == 'CHECK') {
		$sSQL = "SELECT plg_plgID from pledge_plg where plg_famID=\"" . $plg_famID . "\" AND  plg_CheckNo=\"" . $plg_CheckNo . "\" AND  plg_date=\"" . $plg_date . "\";";
		$rsPlgIDs = RunQuery($sSQL);

		while ($aRow = mysql_fetch_array($rsPlgIDs)) {
			extract($aRow);
			$plgIDs[] = $plg_plgID;
		}
	} else {
		$plgIDs[] = $iPledgeID;
	}

	foreach ($plgIDs as $plgID) {
		$sSQL = "DELETE FROM `pledge_plg` WHERE `plg_plgID` = '" . $plgID . "';";
		RunQuery($sSQL);
	}

	if ($linkBack <> "") {
		Redirect ($linkBack);
	}
} elseif (isset ($_POST["Cancel"])) {
	Redirect ($linkBack);
}

require "Include/Header.php";

?>

<form method="post" action="PledgeDelete.php?<?php echo "PledgeID=" . $iPledgeID . "&linkBack=" . $linkBack; ?>" name="PledgeDelete">

<table cellpadding="3" align="center">

	<tr>
		<td align="center">
			<input type="submit" class="icButton" value="<?php echo gettext("Delete"); ?>" name="Delete">
			<input type="submit" class="icButton" value="<?php echo gettext("Cancel"); ?>" name="Cancel">
		</td>
	</tr>
</table>

<?php
require "Include/Footer.php";
?>
