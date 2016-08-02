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
        <h3 class="box-title"><?= gettext("This tool will assist you in manually backing up the ChurchCRM database.") ?></h3>
    </div>
    <div class="box-body">
        <ul>
        <li><?= gettext("You should make a manual backup at least once a week unless you already have a regular backup procedule for your systems.") ?></li><br>
        <li><?= gettext("After you download the backup file, you should make two copies. Put one of them in a fire-proof safe on-site and the other in a safe location off-site.") ?></li><br>
        <li><?= gettext("If you are concerned about confidentiality of data stored in the ChurchCRM database, you should encrypt the backup data if it will be stored somewhere potentially accessible to others") ?></li><br>
        <li><?= gettext("For added backup security, you can e-mail the backup to yourself at an e-mail account hosted off-site or to a trusted friend.  Be sure to use encryption if you do this, however.") ?></li>
        </ul>
        <BR><BR>
        <form method="post" action="<?= sRootPath ?>/api/database/backup" id="BackupDatabase">
        <?= gettext("Select archive type:") ?>
        <?php if ($hasGZIP) { ?><input type="radio" name="archiveType" value="0"><?= gettext("GZip") ?><?php } ?>
        <!--<?php if ($hasZIP) { ?><input type="radio" name="archiveType" value="1"><?= gettext("Zip") ?><?php } ?>-->
        <input type="radio" name="archiveType" value="2" checked><?= gettext("Uncompressed") ?>
        <input type="radio" name="archiveType" value="3" checked><?= gettext("tar.gz (Include Photos)") ?>
        <BR><BR>
        <?php if ($hasPGP) { ?>
        <input type="checkbox" name="encryptBackup" value="1"><?= gettext("Encrypt backup file with a password?") ?>
        &nbsp;&nbsp;&nbsp;
        <?= gettext("Password:") ?><input type="password" name="pw1">
        <?= gettext("Re-type Password:") ?><input type="password" name="pw2">
        <BR><span id="passworderror" style="color: red"></span><BR><BR>
        <?php } ?>
        <input type="button" class="btn btn-primary" id="doBackup" <?= 'value="' . gettext("Generate and Download Backup") . '"' ?>>
        <input type="button" class="btn btn-primary" id="doRemoteBackup" <?= 'value="' . gettext("Generate and Ship Backup to External Storage") . '"' ?>>

        </form>
    </div>
</div>
<div class="box">
    <div class="box-header">
        <h3 class="box-title">Backup Status: </h3>&nbsp;<h3 class="box-title" id="backupstatus" style="color:red">No Backup Running</h3>
    </div>
     <div class="box-body" id="resultFiles">
     </div>
</div>

<script>

function doBackup(isRemote)
{
  var endpointURL = "";
  if(isRemote)
  {
    endpointURL = window.CRM.root +'/api/database/backupRemote';
  }
  else
  {
    endpointURL = window.CRM.root +'/api/database/backup';
  }
  event.preventDefault();
  var errorflag =0;
  if ($("input[name=encryptBackup]").is(':checked'))
  {
    if ($('input[name=pw1]').val() =="")
    {
      $("#passworderror").html("You must enter a password");
      errorflag=1;
    }
    if ($('input[name=pw1]').val() != $('input[name=pw2]').val())
    {
      $("#passworderror").html("Passwords must match");
      errorflag=1;
    }
  }
  if (!errorflag)
  {
    $("#passworderror").html(" ");
    // get the form data
    // there are many ways to get this data using jQuery (you can use the class or id also)
    var formData = {
      'iArchiveType'              : $('input[name=archiveType]:checked').val(),
      'bEncryptBackup'            : $("input[name=encryptBackup]").is(':checked'),
      'password'                  : $('input[name=pw1]').val()
    };
    $("#backupstatus").css("color","orange");
    $("#backupstatus").html("Backup Running, Please wait.");
    console.log(formData);

   //process the form
   $.ajax({
      type        : 'POST', // define the type of HTTP verb we want to use (POST for our form)
      url         : endpointURL, // the url where we want to POST
      data        : JSON.stringify(formData), // our data object
      dataType    : 'json', // what type of data do we expect back from the server
      encode      : true
    })
    .done(function(data) {
      console.log(data);
      var downloadButton = "<button class=\"btn btn-primary\" id=\"downloadbutton\" role=\"button\" onclick=\"javascript:downloadbutton('"+data.filename+"')\"><i class='fa fa-download'></i>  "+data.filename+"</button>";
      $("#backupstatus").css("color","green");
      if(isRemote)
      {
        $("#backupstatus").html("Backup Generated and copied to remote server");
      }
      else
      {
        $("#backupstatus").html("Backup Complete, Ready for Download.");
        $("#resultFiles").html(downloadButton);
      }
    }).fail(function()  {
      $("#backupstatus").css("color","red");
      $("#backupstatus").html("Backup Error.");
    });
  }
}
  
$('#doBackup').click(function(event) {
  doBackup (0);
});

$('#doRemoteBackup').click(function(event) {
  doBackup(1);
});

function downloadbutton(filename) {
    window.location = window.CRM.root +"/api/database/download/"+filename;
    $("#backupstatus").css("color","green");
    $("#backupstatus").html("Backup Downloaded, Copy on server removed");
    $("#downloadbutton").attr("disabled","true");
}
</script>
<?php require "Include/Footer.php" ?>
