<?php
/*******************************************************************************
 *
 *  filename    : BackupDatabase.php
 *  last change : 2016-01-04
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

if (isset($sGZIPname)) $hasGZIP = true;
if (isset($sZIPname)) $hasZIP = true;
if (isset($sPGPname)) $hasPGP = true;



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
        <li><?php echo gettext("If you are concerned about confidentiality of data stored in the ChurchCRM database, you should encrypt the backup data if it will be stored somewhere potentially accessible to others"); ?></li><br>
        <li><?php echo gettext("For added backup security, you can e-mail the backup to yourself at an e-mail account hosted off-site or to a trusted friend.  Be sure to use encryption if you do this, however."); ?></li>
        </ul>
        <BR><BR>
        <form method="post" action="/api/database/backup" id="BackupDatabase">
        <?php echo gettext("Select archive type:"); ?>
        <?php if ($hasGZIP) { ?><input type="radio" name="archiveType" value="0"><?php echo gettext("GZip"); ?><?php } ?>
        <?php if ($hasZIP) { ?><input type="radio" name="archiveType" value="1"><?php echo gettext("Zip"); ?><?php } ?>
        <input type="radio" name="archiveType" value="2" checked><?php echo gettext("Uncompressed"); ?>
        <input type="radio" name="archiveType" value="3" checked><?php echo gettext("tar.gz (Include Photos)"); ?>
        <BR><BR>
        <?php if ($hasPGP) { ?>
        <input type="checkbox" name="encryptBackup" value="1"><?php echo gettext("Encrypt backup file with a password?"); ?>
        &nbsp;&nbsp;&nbsp;
        <?php echo gettext("Password:"); ?><input type="password" name="pw1">
        <?php echo gettext("Re-type Password:"); ?><input type="password" name="pw2">
        <BR><BR><BR>
        <?php } ?>
        <input type="submit" class="btn btn-primary" name="doBackup" <?php echo 'value="' . gettext("Generate and Download Backup") . '"'; ?>>
        </form>
    </div>
</div>
<div class="box">
    <div class="box-header">
        <h3 class="box-title">Backup Files Ready to Download</h3>
    </div>
     <div class="box-body" id="resultFiles">
     </div>
</div>
    
<script>
$('#BackupDatabase').submit(function(event) {

        // get the form data
        // there are many ways to get this data using jQuery (you can use the class or id also)
        var formData = {
            'iArchiveType'              : $('input[name=archiveType]:checked').val(),
            'bEncryptBackup'            : $("input[name=encryptBackup]").is(':checked'),
            'password'                  : $('input[name=pw1]').val()
        };
		
        console.log(formData);

       //process the form
       $.ajax({
            type        : 'POST', // define the type of HTTP verb we want to use (POST for our form)
            url         : '/api/database/backup', // the url where we want to POST
            data        : JSON.stringify(formData), // our data object
            dataType    : 'json', // what type of data do we expect back from the server
            encode      : true
        })
        .done(function(data) {
            console.log(data);
            var downloadButton = "<a role=\"button\" href=\""+ data.saveTo + "\" target=\"_blank\" download>" + data.filename + "</a>";
            $("#resultFiles").html(downloadButton);
        });
        event.preventDefault();
    });
</script>
<?php
require "Include/Footer.php";
?>
