<?php
/*******************************************************************************
 *
 *  filename    : Menu.php
 *  last change : 2002-03-24
 *  description : menu that appears after login, shows login attemptsO
 *
 *  http://www.churchdb.org/
 *  Copyright 2001-2002 Phillip Hullquist, Deane Barker, Michael Wilt
 *
 *  ChurchInfo is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

// Include the function library
require "Include/Config.php";
require "Include/Functions.php";

// Set the page title
$sPageTitle = gettext("Welcome to ChurchInfo");

require "Include/Header.php";

?>

<p><?php echo gettext("Hi") . " " . $_SESSION['UserFirstName'] . " " . gettext("welcome back"); ?>.</p>

<p>
<?php
if ($_SESSION['iLoginCount'] == 0) {
	echo gettext("This is your first login.");
} else {
	echo gettext("You last logged in on ") . strftime("%A, %B %e, %Y",$_SESSION['dLastLogin']) . ' ' . gettext("at") . ' ' . strftime("%r",$_SESSION['dLastLogin']) . ".";
}
?>
</p>

<p><?php echo gettext("There were"); ?> <?php echo $_SESSION['iFailedLogins']; ?> <?php echo gettext("failed login attempt(s) since your last successful login."); ?></p>

<?php
require "Include/Footer.php";
?>
