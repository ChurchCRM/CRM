<?php
/*******************************************************************************
 *
 *  filename    : UserList.php
 *  last change : 2003-01-07
 *  description : displays a list of all users
 *
 *  http://www.infocentral.org/
 *  Copyright 2001-2002 Phillip Hullquist, Deane Barker
 *
 *  InfoCentral is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

// Include the function library
require "Include/Config.php";
require "Include/Functions.php";

// Security: User must be an Admin to access this page.
// Otherwise, re-direct them to the main menu.
if (!$_SESSION['bAdmin'])
{
	Redirect("Menu.php");
	exit;
}

$iResetLoginCount = FilterInput($_GET["ResetLoginCount"],'int');
if ($iResetLoginCount > 0)
{
	$sSQL = "UPDATE user_usr SET usr_FailedLogins = 0 WHERE usr_per_ID = " . $iResetLoginCount;
	RunQuery($sSQL);
}

// Get all the User records
$sSQL = "SELECT * FROM user_usr INNER JOIN person_per ON user_usr.usr_per_ID = person_per.per_ID ORDER BY per_LastName";
$rsUsers = RunQuery($sSQL);

// Set the page title and include HTML header
$sPageTitle = gettext("User Listing");
require "Include/Header.php";

?>

<p align="center"><a href="UserEditor.php"><?php echo gettext("Add a New User"); ?></a></p>

<table cellpadding="4" align="center" cellspacing="0" width="100%">
	<tr class="TableHeader">
		<td><b><?php echo gettext("Name"); ?></b></td>
		<td align="center"><b><?php echo gettext("Last Login"); ?></b></td>
		<td align="center"><b><?php echo gettext("Total Logins"); ?></b></td>
		<td align="center"><b><?php echo gettext("Failed Logins"); ?></b></td>
		<td align="center" colspan="2"><b><?php echo gettext("Password"); ?></b></td>
		<td><b><?php echo gettext("Edit"); ?></b></td>
		<td><b><?php echo gettext("Delete"); ?></b></td>
	</tr>
<?php

//Set the initial row color
$sRowClass = "RowColorA";

//Loop through the person recordset
while ($aRow = mysql_fetch_array($rsUsers)) {

	extract($aRow);

	//Alternate the row color
	$sRowClass = AlternateRowStyle($sRowClass);

	//Display the row
?>
	<tr class="<?php echo $sRowClass; ?>">
		<td>
		<?php
			echo "<a href=\"PersonView.php?PersonID=" . $per_ID . "\">" . FormatFullName($per_Title, $per_FirstName, $per_MiddleName, $per_LastName, $per_Suffix, 1) . "</a>";
		?>
		</td>
		<td align="center"><?php echo $usr_LastLogin; ?></td>
		<td align="center"><?php echo $usr_LoginCount; ?></td>
		<td align="center">
		<?php
			if ($iMaxFailedLogins > 0 && $usr_FailedLogins >= $iMaxFailedLogins)
				echo "<span style=\"color: red;\">" . $usr_FailedLogins . "<br></span><a href=\"UserList.php?ResetLoginCount=$per_ID\">" . gettext("Reset") . "</a>";
			else
				echo $usr_FailedLogins;
		?>
		</td>
		<td align="right"><a href="UserPasswordChange.php?PersonID=<?php echo $per_ID; ?>&FromUserList=True"><?php echo gettext("Change"); ?></a></td>
		<td align="left"><?php if ($per_ID != $_SESSION['iUserID']) echo "<a href=\"UserReset.php?PersonID=$per_ID&FromUserList=True\">" . gettext("Reset") . "</a>"; else echo "&nbsp;"; ?></td>
		<td><a href="UserEditor.php?PersonID=<?php echo $per_ID; ?>"><?php echo gettext("Edit"); ?></a></td>
		<td><a href="UserDelete.php?PersonID=<?php echo $per_ID; ?>"><?php echo gettext("Delete"); ?></a></td>
	</tr>
<?php
}
?>
</table>

<?php
require "Include/Footer.php";
?>
