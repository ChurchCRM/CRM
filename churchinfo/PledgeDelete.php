<?php
/*******************************************************************************
 *
 *  filename    : PledgeDelete.php
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
$sPageTitle = gettext("Confirm Delete");

$linkBack = FilterInput($_GET["linkBack"]);
$sGroupKey = FilterInput($_GET["GroupKey"],'string'); 

// Security: User must have Add or Edit Records permission to use this form in those manners
// Clean error handling: (such as somebody typing an incorrect URL ?PersonID= manually)
if (!$_SESSION['bAddRecords']) {
	Redirect("Menu.php");
	exit;
}

//Is this the second pass?
if (isset($_POST["Delete"])) {
	$sSQL = "DELETE FROM `pledge_plg` WHERE `plg_GroupKey` = '" . $sGroupKey . "';";
	RunQuery($sSQL);

	if ($linkBack <> "") {
		Redirect ($linkBack);
	}
} elseif (isset ($_POST["Cancel"])) {
	Redirect ($linkBack);
}

require "Include/Header.php";

?>

<form method="post" action="PledgeDelete.php?<?= "GroupKey=" . $sGroupKey . "&linkBack=" . $linkBack ?>" name="PledgeDelete">

<table cellpadding="3" align="center">

	<tr>
		<td align="center">
			<input type="submit" class="btn" value="<?= gettext("Delete") ?>" name="Delete">
			<input type="submit" class="btn" value="<?= gettext("Cancel") ?>" name="Cancel">
		</td>
	</tr>
</table>

<?php require "Include/Footer.php" ?>
