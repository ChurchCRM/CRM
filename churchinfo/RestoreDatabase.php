<?php
/*******************************************************************************
 *
 *  filename    : RestoreDatabase.php
 *  last change : 2016-1-2
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

// Security: User must have Manage Groups permission
if (!$_SESSION['bAdmin'])
{
	Redirect("Menu.php");
	exit;
}

//Set the page title
$sPageTitle = gettext("Restore Database");
require 'Include/Header.php';
?>

<form id="fileupload" action="/api/database/restore" method="POST" enctype="multipart/form-data">
<input type="file" name="restoreFile" multiple=""></span>     
<button type="submit">Upload Files</button>
</form>

<!-- PACE -->
<script src="<?= $sURLPath; ?>/vendor/AdminLTE/plugins/pace/pace.min.js"></script>
<?php
require "Include/Footer.php";
?>

