<?php
/*******************************************************************************
 *
 *  filename    : PledgeDetails.php
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

//Set the page title
$sPageTitle = gettext("Electronic Transaction Details");

//Get the PledgeID out of the querystring
$iPledgeID = FilterInput($_GET["PledgeID"],'int');
$linkBack = FilterInput($_GET["linkBack"]);

// Security: User must have Finance permission to use this form.
// Clean error handling: (such as somebody typing an incorrect URL ?PersonID= manually)
if (! $_SESSION['bFinance'])
{
	Redirect("Menu.php");
	exit;
}

//Is this the second pass?
if (isset ($_POST["Back"])) {
	Redirect ($linkBack);
}

$sSQL = "SELECT * FROM pledge_plg WHERE plg_plgID = " . $iPledgeID;
$rsPledgeRec = RunQuery($sSQL);
extract (mysql_fetch_array ($rsPledgeRec));

$sSQL="SELECT * FROM result_res WHERE res_ID=" . $plg_aut_ResultID;
$rsResultRec = RunQuery($sSQL);
extract (mysql_fetch_array ($rsResultRec));

require "Include/Header.php";

echo $res_echotype2;

?>

<form method="post" action="<?php echo $_SERVER['PHP_SELF'] . "?PledgeID=" . $iPledgeID . "&linkBack=" . $linkBack; ?>" name="PledgeDelete">

<table cellpadding="3" align="center">

	<tr>
		<td align="center">
			<input type="submit" class="icButton" value="<?php echo gettext("Back"); ?>" name="Back">
		</td>
	</tr>
</table>

<?php
require "Include/Footer.php";
?>
