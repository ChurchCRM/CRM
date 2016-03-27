<?php
/*******************************************************************************
 *
 *  filename    : UserDelete.php
 *  last change : 2003-01-07
 *  description : confirms and deletes a user
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

// Get the PersonID from the querystring
$iPersonID = FilterInput($_GET["PersonID"],'int');

// Do we have confirmation?
if (isset($_GET["Confirmed"])) {

	// Delete the specified User record
	$sSQL = "DELETE FROM user_usr WHERE usr_per_ID = " . $iPersonID;
	RunQuery($sSQL);

	$sSQL = "DELETE FROM userconfig_ucfg WHERE ucfg_per_ID = " . $iPersonID;
	RunQuery($sSQL);

	// Redirect back to the list
	Redirect("UserList.php");

}

// Get the data on this user
$sSQL = "SELECT * FROM user_usr INNER JOIN person_per ON user_usr.usr_per_ID = person_per.per_ID WHERE usr_per_ID = " . $iPersonID;
$rsPerson = RunQuery($sSQL);
extract(mysql_fetch_array($rsPerson));

//Assign everything locally
$sUserName = $per_LastName . ", " . $per_FirstName;
$iPersonID = $per_ID;

// Set the page title and include HTML header
$sPageTitle = gettext("User Delete Confirmation");
require "Include/Header.php";

?>
<!-- Default box -->
<div class="box box-danger">
	<div class="box-header with-border">
<div class="callout callout-danger"> <?= gettext("Please confirm removal of user status from:")." <b>" . $sUserName ."</b>"; ?></div>


	<a href="UserDelete.php?Confirmed=Yes&PersonID=<?= $iPersonID ?>" class="btn btn-app btn-warning"><i class="fa fa-trash"></i><?= gettext("Delete") ?></a>
	<a href="UserList.php" class="btn btn-app"><i class="fa fa-users"></i><?= gettext("Cancel") ?></a>

	</div>
	<!-- /.box-body -->
</div>
<!-- /.box -->

<?php require "Include/Footer.php" ?>
