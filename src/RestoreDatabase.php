<?php

/*******************************************************************************
 *
 *  filename    : RestoreDatabase.php
 *  last change : 2016-01-04
 *




 *
 ******************************************************************************/

//Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\RedirectUtils;

// Security: User must have Manage Groups permission
AuthenticationManager::redirectHomeIfNotAdmin();

//Set the page title
$sPageTitle = gettext('Restore Database');
require 'Include/Header.php';
?>
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><?= gettext('Select Database Files') ?></h3>
  </div>
  <div class="card-body">
    <p><?= gettext('Select a backup file to restore') ?></p>
    <p><?= gettext('CAUTION: This will completely erase the existing database, and replace it with the backup') ?></p>
    <p><?= gettext('If you upload a backup from ChurchInfo, or a previous version of ChurchCRM, it will be automatically upgraded to the current database schema') ?></p>
    <p><?= gettext("Maximum upload size")?>: <span class="maxUploadSize"></span></p>
    <form id="restoredatabase" action="<?= $sRootPath ?>/api/database/restore" method="POST"
          enctype="multipart/form-data">
      <input type="file" name="restoreFile" id="restoreFile" multiple=""><br>
      <label for="restorePassword"><?= gettext("Password (if any)") ?>:</label>
      <input type="text" name="restorePassword" /><br/>
      <button type="submit" class="btn btn-primary"><?= gettext('Upload Files') ?></button>
    </form>
  </div>
</div>
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><?= gettext('Restore Status:') ?></h3>&nbsp;<h3 class="card-title" id="restorestatus"
                                                        style="color:red"><?= gettext('No Restore Running') ?></h3>
    <div id="restoreMessages"></div>
    <span id="restoreNextStep"></span>
  </div>
</div>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
  $('#restoredatabase').submit(function (event) {
    event.preventDefault();

    var formData = new FormData($(this)[0]);
    if (window.FileReader) { // if the browser supports FileReader, validate the file locally before uploading.
       var file = document.getElementById('restoreFile').files[0];
       if (file.size > window.CRM.maxUploadSizeBytes){
         window.CRM.DisplayErrorMessage("/api/database/restore",{message: "<?= gettext('The selected file exceeds this servers maximum upload size of') ?>: " + window.CRM.maxUploadSize});
         return false;
       }
    }
     $("#restorestatus").css("color", "orange");
    $("#restorestatus").html("<?= gettext('Restore Running, Please wait.')?>");
    $.ajax({ // not converting this to window.CRM.APIRequest because multipart/form-data
      url: window.CRM.root + '/api/database/restore',
      type: 'POST',
      data: formData,
      cache: false,
      contentType: false,
      enctype: 'multipart/form-data',
      processData: false,
      dataType: 'json'
    })
      .done(function (data) {
        if (data.Messages.length > 0) {
          $.each(data.Messages, function (index, value) {
            var inhtml = '<h4><i class="icon fa fa-ban"></i> Alert!</h4>' + value;
            $("<div>").addClass("alert alert-danger").html(inhtml).appendTo("#restoreMessages");
          });
        }
        $("#restorestatus").css("color", "green");
        $("#restorestatus").html("<?= gettext('Restore Complete')?>");
        $("#restoreNextStep").html('<a href="Logoff.php" class="btn btn-primary"><?= gettext('Login to restored Database')?></a>');
      }).fail(function () {
      $("#restorestatus").css("color", "red");
      $("#restorestatus").html("<?= gettext('Restore Error.')?>");
    });
    return false;
  });
</script>

<?php
require 'Include/Footer.php';
?>
