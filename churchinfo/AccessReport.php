<?php
/*******************************************************************************
 *
 *  filename    : AccessReport.php
 *  last change : 2003-09-03
 *  description : form to invoke user access report
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

// If CSVAdminOnly option is enabled and user is not admin, redirect to the menu.
if (!$_SESSION['bAdmin'] && $bCSVAdminOnly) {
	Redirect("Menu.php");
	exit;
}

// Set the page title and include HTML header
$sPageTitle = gettext("Access report");
require "Include/Header.php";

?>

<form method="POST" action="Reports/AccessReport.php">

<p align="center">
<BR>
<input type="submit" class="icButton" name="Submit" <?php echo 'value="' . gettext("Create Report") . '"'; ?>>
<input type="button" class="icButton" name="Cancel" <?php echo 'value="' . gettext("Cancel") . '"'; ?> onclick="javascript:document.location='Menu.php';">
</p>
</form>

<?php
require "Include/Footer.php";
?>
