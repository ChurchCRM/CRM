<?php
/*******************************************************************************
 *
 *  filename    : FindDepositSlip.php
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

$iDepositSlipID = $_SESSION['iCurrentDeposit'];

//Set the page title
$sPageTitle = gettext("Find Deposit Slip");

// Security: User must have finance permission to use this form
if (!$_SESSION['bFinance'])
{
	Redirect("Menu.php");
	exit;
}

//Is this the second pass?
if (isset($_POST["FindDepositSlipSubmit"]))
{
	//Get all the variables from the request object and assign them locally
	$dDate = FilterInput($_POST["Date"]);
	$iID = FilterInput($_POST["ID"]);

	if ($iID > 0) {
		$sSQL = "SELECT * FROM deposit_dep WHERE dep_ID = " . $iID;
		$rsDepositSlip = RunQuery($sSQL);
		extract(mysql_fetch_array($rsDepositSlip));
		if ($dep_ID > 0) {
			$iDepositSlipID = $dep_ID;
			$_SESSION['iCurrentDeposit'] = $iDepositSlipID;
			Redirect("DepositSlipEditor.php?new=0");
			exit;
		}
	}

	if (strlen ($dDate) > 0) {
		$sSQL = "SELECT * FROM deposit_dep WHERE dep_Date = '" . $dDate . "'";
		$rsDepositSlip = RunQuery($sSQL);
		extract(mysql_fetch_array($rsDepositSlip));
		if ($dep_ID > 0) {
			$iDepositSlipID = $dep_ID;
			$_SESSION['iCurrentDeposit'] = $iDepositSlipID;
			Redirect("DepositSlipEditor.php?new=0");
			exit;
		}
	}
}

require "Include/Header.php";

?>

<form method="post" action="<?php echo $_SERVER['PHP_SELF']?>" name="FindDepositSlip">

<table cellpadding="3" align="center">

	<tr>
		<td align="center">
			<input type="submit" class="icButton" value="<?php echo gettext("Find"); ?>" name="FindDepositSlipSubmit">
			<input type="button" class="icButton" value="<?php echo gettext("Cancel"); ?>" name="FindDepositSlipCancel" onclick="javascript:document.location='Menu.php';">
		</td>
	</tr>

	<tr>
		<td>
		<table cellpadding="3">
			<tr>
				<td class="LabelColumn"><?php echo gettext("Number:"); ?></td>
				<td class="TextColumn"><input type="text" name="ID" id="ID"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php addToolTip("Format: YYYY-MM-DD<br>or enter the date by clicking on the calendar icon to the right."); ?><?php echo gettext("Date:"); ?></td>
				<td class="TextColumn"><input type="text" name="Date" maxlength="10" id="sel1" size="11">&nbsp;<input type="image" onclick="return showCalendar('sel1', 'y-mm-dd');" src="Images/calendar.gif"> <span class="SmallText"><?php echo gettext("[format: YYYY-MM-DD]"); ?></span></td>
			</tr>
		</table>
		</td>
	</form>
</table>

<?php
require "Include/Footer.php";
?>
