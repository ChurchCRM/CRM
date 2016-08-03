<?php
/*******************************************************************************
 *
 *  filename    : RestoreDatabase.php
 *  last change : 2016-01-04
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
<div class="box">
    <div class="box-header">
        <h3 class="box-title">Select Databse Files</h3>
    </div>
    <div class="box-body">
        <p>Select a backup file to restore</p>
        <p>CAUTION: This will completely erase the existing database, and replace it with the backup</p>
        <p>If you upload a backup from ChurchInfo, or a previous version of ChurchCRM, it will be automatically upgraded to the current database schema</p>

        <form id="restoredatabase" action="<?= sRootPath ?>/api/database/restore" method="POST" enctype="multipart/form-data">
        <input type="file" name="restoreFile" id="restoreFile" multiple=""><br> 
        <button type="submit" class="btn btn-primary">Upload Files</button>
        </form>
    </div>
</div>
<div class="box">
    <div class="box-header">
        <h3 class="box-title">Restore Status:</h3>&nbsp;<h3 class="box-title" id="restorestatus" style="color:red">No Restore Running</h3>
        <div id="restoreMessages"></div>
        <span id="restoreNextStep"></span>
    </div>
</div>
<script>
$('#restoredatabase').submit(function(event) {
  event.preventDefault();
  $("#restorestatus").css("color","orange");
  $("#restorestatus").html("Restore Running, Please wait.");
  var formData = new FormData($(this)[0]); 
  $.ajax({
    url: window.CRM.root + '/api/database/restore',
    type: 'POST',
    data: formData,
    cache: false,
    contentType: false,
    enctype: 'multipart/form-data',
    processData: false,
    dataType    : 'json'
  })
  .done(function(data) {
    if(data.Messages.length>0)
    {
      $.each(data.Messages, function(index,value)
      {
        var inhtml = '<h4><i class="icon fa fa-ban"></i> Alert!</h4>'+value;
        $("<div>").addClass("alert alert-danger").html(inhtml).appendTo("#restoreMessages");
      });
    }
    $("#restorestatus").css("color","green");
    $("#restorestatus").html("Restore Complete");
    $("#restoreNextStep").html('<a href="Login.php?Logoff=True" class="btn btn-primary">Login to restored Database</a>');
   }).fail(function()  {
    $("#restorestatus").css("color","red");
    $("#restorestatus").html("Restore Error.");
  });
  return false;
});
</script>
<!-- PACE -->
<script src="<?= $sRootPath ?>/skin/adminlte/plugins/pace/pace.min.js"></script>
<?php
require "Include/Footer.php";
?>

