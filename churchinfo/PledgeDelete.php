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
if (isset($_POST["Delete"]))
{
	$sSQL = "DELETE FROM `pledge_plg` WHERE `plg_plgID` = '" . $iPledgeID . "' LIMIT 1;";
	//Execute the SQL
	RunQuery($sSQL);
	if ($linkBack <> "") {
		Redirect ($linkBack);
	}
} else if (isset ($_POST["Cancel"])) {
	Redirect ($linkBack);
}

require "Include/Header.php";

?>

<form method="post" action="<?php echo $_SERVER['PHP_SELF'] . "?PledgeID=" . $iPledgeID . "&linkBack=" . $linkBack; ?>" name="PledgeDelete">

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
