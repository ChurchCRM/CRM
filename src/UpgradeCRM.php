<?php

// Include the function library
require 'Include/Config.php';
$bSuppressSessionTests = TRUE;
require 'Include/Functions.php';

// Set the page title and include HTML header
$sPageTitle = "Upgrade ChurchCRM";
require ("Include/HeaderNotLoggedIn.php");
?>


<div class="login-box">
    <div class="login-logo">
        Upgrade <b>Church</b>CRM</a>
    </div>
    <div class="login-box-body" id="backupPhase">
      <p class="login-box-msg"><?= gettext('Step 1: Backup Database') ?></p>
      <input type="button" class="btn btn-primary" id="doBackup" <?= 'value="' . gettext("Generate Database Backup") . '"' ?>>
      <span id="backupStatus"></span>
      <div id="resultFiles">
      </div>
    </div>
    <div class="login-box-body" style="display:none" id="fetchPhase">
      <p class="login-box-msg"><?= gettext('Step 2: Fetch Update Package on Server') ?></p>
      <input type="button" class="btn btn-primary" id="fetchUpdate" <?= 'value="' . gettext("Fetch Update Files") . '"' ?>>
    </div>
  <div class="login-box-body" style="display:none" id="updatePhase">
      <p class="login-box-msg"><?= gettext('Step 3: Apply Update Package on Server') ?></p>
      <ul>
        <li>File Name: <span id="updateFileName"> </span></li>
        <li>Full Path: <span id="updateFullPath"> </span></li>
        <li>SHA1: <span id="updateSHA1"> </span></li>
      </ul>
      <input type="button" class="btn btn-warning" id="applyUpdate" <?= 'value="' . gettext("Apply Update Files") . '"' ?>>
    </div>
    <div class="login-box-body" style="display:none" id="finalPhase">
      <p class="login-box-msg"><?= gettext('Step 4: Login') ?></p>
      <a href="Login.php?Logoff=True" class="btn btn-primary">Login to Upgraded System</a>
    </div>
</div>
<script>
 $("#doBackup").click(function(){
   $.ajax({
      type        : 'POST', // define the type of HTTP verb we want to use (POST for our form)
      url         : window.CRM.root +'/api/database/backup', // the url where we want to POST
      data        : JSON.stringify({
        'iArchiveType'              : 3
      }), // our data object
      dataType    : 'json', // what type of data do we expect back from the server
      encode      : true,
      contentType: "application/json; charset=utf-8"
    })
    .done(function(data) {
      console.log(data);
      var downloadButton = "<button class=\"btn btn-primary\" id=\"downloadbutton\" role=\"button\" onclick=\"javascript:downloadbutton('"+data.filename+"')\"><i class='fa fa-download'></i>  "+data.filename+"</button>";
      $("#backupstatus").css("color","green");
      $("#backupstatus").html("Backup Complete, Ready for Download.");
      $("#resultFiles").html(downloadButton);
      $("#downloadbutton").click(function(){
        $("#fetchPhase").css('display','');
      });
    }).fail(function()  {
      $("#backupstatus").css("color","red");
      $("#backupstatus").html("Backup Error.");
    });
   
 });
 
 $("#fetchUpdate").click(function(){
    $.ajax({
      type : 'GET',
      url  : window.CRM.root +'/api/systemupgrade/downloadLatestRelease', // the url where we want to POST
      dataType    : 'json' // what type of data do we expect back from the server
    }).done(function(data){
      console.log(data);
      window.CRM.updateFile=data;
      $("#updateFileName").text(data.fileName);
      $("#updateFullPath").text(data.fullPath);
      $("#updateSHA1").text(data.sha1);
      $("#updatePhase").css('display','');
    });
   
 });
 
 $("#applyUpdate").click(function(){
   $.ajax({
      type : 'POST',
      url  : window.CRM.root +'/api/systemupgrade/doUpgrade', // the url where we want to POST
      data        : JSON.stringify({
        fullPath: window.CRM.updateFile.fullPath,
        sha1: window.CRM.updateFile.sha1
      }), // our data object
      dataType    : 'json', // what type of data do we expect back from the server
      encode      : true,
      contentType: "application/json; charset=utf-8"
    }).done(function(data){
      console.log(data);
      $("#finalPhase").css('display','');
    });
 });
 
function downloadbutton(filename) {
    window.location = window.CRM.root +"/api/database/download/"+filename;
    $("#backupstatus").css("color","green");
    $("#backupstatus").html("Backup Downloaded, Copy on server removed");
    $("#downloadbutton").attr("disabled","true");
}
</script>

<?php
// Add the page footer
require ("Include/FooterNotLoggedIn.php");

// Turn OFF output buffering
ob_end_flush();
?>
