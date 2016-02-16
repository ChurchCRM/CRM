<?php
/*******************************************************************************
 *
 *  filename    : UserList.php
 *  last change : 2003-01-07
 *  description : displays a list of all users
 *
 *  http://www.churchcrm.io/
 *  Copyright 2001-2002 Phillip Hullquist, Deane Barker
 *
 *  ChurchCRM is free software; you can redistribute it and/or modify
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

if (isset ($_GET["ResetLoginCount"])) {
	$iResetLoginCount = FilterInput($_GET["ResetLoginCount"],'int');
} else {
	$iResetLoginCount = 0;
}

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
<!-- Default box -->
    <div class="box">
        <div class="box-body">
            <a href="UserEditor.php" class="btn btn-app"><i class="fa fa-user-plus"></i>New User</a>
            <a href="SettingsUser.php" class="btn btn-app"><i class="fa fa-wrench"></i>User Settings</a>
        </div>
    </div>
<div class="box">
    <div class="box-body no-padding">
        <table class="table table-hover">
            <tr>
                <td><b><?= gettext("Edit") ?></b></td>
                <td><b><?= gettext("Name") ?></b></td>
                <td align="center"><b><?= gettext("Last Login") ?></b></td>
                <td align="center"><b><?= gettext("Total Logins") ?></b></td>
                <td align="center"><b><?= gettext("Failed Logins") ?></b></td>
                <td align="center" colspan="2"><b><?= gettext("Password") ?></b></td>
                <td><b><?= gettext("Delete") ?></b></td>
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
	<tr>
        <td><a href="UserEditor.php?PersonID=<?= $per_ID ?>"><?= gettext("Edit") ?></a></td>
		<td>
		<?php
			echo "<a href=\"PersonView.php?PersonID=" . $per_ID . "\">" . FormatFullName($per_Title, $per_FirstName, $per_MiddleName, $per_LastName, $per_Suffix, 1) . "</a>";
		?>
		</td>
		<td align="center"><?= $usr_LastLogin ?></td>
		<td align="center"><?= $usr_LoginCount ?></td>
		<td align="center">
		<?php
			if ($iMaxFailedLogins > 0 && $usr_FailedLogins >= $iMaxFailedLogins)
				echo "<span style=\"color: red;\">" . $usr_FailedLogins . "<br></span><a href=\"UserList.php?ResetLoginCount=$per_ID\">" . gettext("Reset") . "</a>";
			else
				echo $usr_FailedLogins;
		?>
		</td>
		<td align="right"><a href="UserPasswordChange.php?PersonID=<?= $per_ID ?>&FromUserList=True"><?= gettext("Change") ?></a></td>
		<td align="left"><?php if ($per_ID != $_SESSION['iUserID']) echo "<a href=\"UserReset.php?PersonID=$per_ID&FromUserList=True\">" . gettext("Reset") . "</a>"; else echo "&nbsp;"; ?></td>
		<td><a href="UserDelete.php?PersonID=<?= $per_ID ?>"><?= gettext("Delete") ?></a></td>
	</tr>
<?php
}
?>
</table>
	</div>
	<!-- /.box-body -->
</div>
<!-- /.box -->

<?php require "Include/Footer.php" ?>
