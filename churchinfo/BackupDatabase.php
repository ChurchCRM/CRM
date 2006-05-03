<?php
/*******************************************************************************
 *
 *  filename    : BackupDatabase.php
 *  last change : 2003-04-03
 *  description : Creates a backup file of the database.
 *
 *  http://www.infocentral.org/
 *  Copyright 2003 Chris Gebhardt
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
if (!$_SESSION['bAdmin'] || !$bEnableBackupUtility)
{
	Redirect("Menu.php");
	exit;
}

// Delete any old backup files
exec("rm -f SQL/InfoCentral-Backup*");

// Check to see whether this installation has gzip, zip, and gpg
if (isset($sGZIPname)) $hasGZIP = true;
if (isset($sZIPname)) $hasZIP = true;
if (isset($sPGPname)) $hasPGP = true;

$iArchiveType = $_POST["archiveType"];
$bEncryptBackup = $_POST["encryptBackup"];
$sPassword1 = $_POST["pw1"];
$sPassword2 = $_POST["pw2"];
$bNoErrors = true;

if (isset($_POST["doBackup"]))
{
	if ($bEncryptBackup)
	{
		if ($sPassword1 != $sPassword2)
		{
			$sPasswordError = gettext("Password values entered were not the same! Please re-type them.");
			$bNoErrors = false;
		}
		elseif (strlen($sPassword1) < 8)
		{
			$sPasswordError = gettext("You must enter a password of at least 8 characters to encrypt this backup.");
			$bNoErrors = false;
		}
	}

	if ($bNoErrors)
	{
		$saveTo = "SQL/InfoCentral-Backup-" . date("Ymd-Gis") . ".sql";
		$backupCommand = "mysqldump -u $sUSER --password=$sPASSWORD $sDATABASE > $saveTo";
		exec($backupCommand, $returnString, $returnStatus);

		switch ($iArchiveType)
		{
			case 0:
				$compressCommand = "$sGZIPname $saveTo";
				$saveTo .= ".gz";
				exec($compressCommand, $returnString, $returnStatus);
				break;
			case 1:
				$archiveName = substr($saveTo, 0, -4);
				$compressCommand = "$sZIPname $archiveName $saveTo";
				$saveTo = $archiveName . ".zip";
				exec($compressCommand, $returnString, $returnStatus);
				break;
		}

		if ($bEncryptBackup)
		{
			putenv("GNUPGHOME=/tmp");
			$encryptCommand = "echo $sPassword1 | $sPGPname -q -c --batch --no-tty --passphrase-fd 0 $saveTo";
			$saveTo .= ".gpg";
			system($encryptCommand);
			$archiveType = 3;
		}

		switch ($iArchiveType)
		{
			case 0:
				header("Content-type: application/x-gzip");
				break;
			case 1:
				header("Content-type: application/x-zip");
				break;
			case 2:
				header("Content-type: text/plain");
				break;
			case 3:
				header("Content-type: application/pgp-encrypted");
				break;
		}

		$filename = substr($saveTo, 4);
		header("Content-Disposition: attachment; filename=$filename");

		readfile($saveTo);
		exit;
	}
}

// Set the page title and include HTML header
$sPageTitle = gettext("Backup Database");
require "Include/Header.php";

?>

<h3><?php echo gettext("This tool will assist you in manually backing up the InfoCentral database."); ?></h3>
<BR>
<h3><u><?php echo gettext("TIPS:"); ?></u></h3>
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

<?php
require "Include/Footer.php";
?>
