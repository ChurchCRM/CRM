<?php
/*******************************************************************************
 *
 *  filename    : AutoPaymentDelete.php
 *  last change : 2004-6-12
 *  website     : http://www.churchcrm.io
 *  copyright   : Copyright 2001, 2002, 2003 Deane Barker, Chris Gebhardt, Michael Wilt
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

//Set the page title
$sPageTitle = gettext("Confirm Delete Automatic payment");

$iAutID = FilterInput($_GET["AutID"],'int');
$linkBack = FilterInput($_GET["linkBack"]);

// Security: User must have Add or Edit Records permission to use this form in those manners
// Clean error handling: (such as somebody typing an incorrect URL ?PersonID= manually)
if (strlen($iAutID) > 0)
{
	if (!$_SESSION['bEditRecords'])
	{
		Redirect("Menu.php");
		exit;
	}
	$sSQL = "SELECT '' FROM autopayment_aut WHERE aut_ID = " . $iAutID;
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
	$sSQL = "DELETE FROM `autopayment_aut` WHERE `aut_ID` = '" . $iAutID . "' LIMIT 1;";
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

<form method="post" action="AutoPaymentDelete.php?<?= "AutID=" . $iAutID . "&linkBack=" . $linkBack ?>" name="AutoPaymentDelete">

<table cellpadding="3" align="center">

	<tr>
		<td align="center">
			<input type="submit" class="btn" value="<?= gettext("Delete") ?>" name="Delete">
			<input type="submit" class="btn" value="<?= gettext("Cancel") ?>" name="Cancel">
		</td>
	</tr>
</table>
</form>

<?php require "Include/Footer.php" ?>
