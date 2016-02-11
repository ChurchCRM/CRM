<?php
/*******************************************************************************
 *
 *  filename    : UserReset.php
 *  last change : 2003-01-07
 *  description : resets the password on a user
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

// Get the PersonID out of the querystring
$iPersonID = FilterInput($_GET["PersonID"],'int');

// Security: User must be an Admin to access this page.
// Admins may not reset their own passwords either. (should use 'change' instead)
if (!$_SESSION['bAdmin'] || $iPersonID == $_SESSION['iUserID'])
{
	Redirect("Menu.php");
	exit;
}

// Do we have confirmation?
if (isset($_GET["Confirmed"]))
{
	$sSQL = "UPDATE user_usr SET usr_Password = '" . md5(strtolower($sDefault_Pass)) . "', usr_NeedPasswordChange = 1 WHERE usr_per_ID = " . $iPersonID;
	RunQuery($sSQL);

	Redirect("UserList.php");
}

// Get the data on this user
$sSQL = "SELECT per_FirstName, per_LastName FROM person_per WHERE per_ID = " . $iPersonID;
$rsUser = RunQuery($sSQL);
$aRow = mysql_fetch_array($rsUser);

// Set the page title and include HTML header
$sPageTitle = gettext("User Reset");
require "Include/Header.php";

?>
<!-- Default box -->
<div class="box box-info">
	<div class="box-header with-border">
<p><?= gettext("Please confirm the password reset of this user:") ?></p>

<p class="ShadedBox"><?= $aRow["per_LastName"] . ", " . $aRow["per_FirstName"] ?></p>

<a href="UserReset.php?PersonID=<?php echo $iPersonID ?>&Confirmed=Yes" class="btn btn-primary"><?= gettext("Yes, reset this User's password") ?></a>
<a href="UserList.php" class="btn btn-default"><?= gettext("No, cancel this operation") ?></a>
</div>
<!-- /.box-body -->
</div>
<!-- /.box -->

<?php require "Include/Footer.php" ?>
