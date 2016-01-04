<?php
/*******************************************************************************
 *
 *  filename    : BackupDatabase.php
 *  last change : 2003-04-03
 *  description : Creates a backup file of the database.
 *
 *  http://www.churchcrm.io/
 *  Copyright 2003 Chris Gebhardt
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

if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    die ("The Backup Utility will not work on a Windows based Server");
} 



// Set the page title and include HTML header
$sPageTitle = gettext("Backup Database");
require "Include/Header.php";

?>
<div class="box">
    <div class="box-header">
        <h3 class="box-title"><?php echo gettext("This tool will assist you in manually backing up the ChurchCRM database."); ?></h3>
    </div>
    <div class="box-body">
        <ul>
        <li><?php echo gettext("You should make a manual backup at least once a week unless you already have a regular backup procedule for your systems."); ?></li><br>
        <li><?php echo gettext("After you download the backup file, you should make two copies. Put one of them in a fire-proof safe on-site and the other in a safe location off-site."); ?></li><br>
        <li><?php echo gettext("If you are concerned about confidentiality of data stored in the InfoCentral database, you should encrypt the backup data if it will be stored somewhere potentially accessible to others"); ?></li><br>
        <li><?php echo gettext("For added backup security, you can e-mail the backup to yourself at an e-mail account hosted off-site or to a trusted friend.  Be sure to use encryption if you do this, however."); ?></li>
        </ul>
        <BR><BR>
        <form method="post" action="BackupDatabase.php" name="BackupDatabase">
        <?php echo gettext("Select archive type:"); ?>
        <?php if ($hasGZIP) { ?><input type="radio" name="archiveType" value="0"><?php echo gettext("GZip"); ?><?php } ?>
        <?php if ($hasZIP) { ?><input type="radio" name="archiveType" value="1"><?php echo gettext("Zip"); ?><?php } ?>
        <input type="radio" name="archiveType" value="2" checked><?php echo gettext("Uncompressed"); ?>
        <BR><BR>
        <?php if ($hasPGP) { ?>
        <input type="checkbox" name="encryptBackup" value="1"><?php echo gettext("Encrypt backup file with a password?"); ?>
        &nbsp;&nbsp;&nbsp;
        <?php echo gettext("Password:"); ?><input type="password" name="pw1">&nbsp;&nbsp;
        <?php echo gettext("Re-type Password:"); ?><input type="password" name="pw2">
        <BR><?php echo "<font color=\"red\">$sPasswordError</font>"; ?><BR><BR><BR>
        <?php } ?>
        <input type="submit" name="doBackup" <?php echo 'value="' . gettext("Generate and Download Backup") . '"'; ?>>
        <input type="submit" name="delete" <?php echo 'value="' . gettext("Delete Temp Files") . '"'; ?>>
        </form>
    </div>
</div>

<?php
require "Include/Footer.php";
?>
