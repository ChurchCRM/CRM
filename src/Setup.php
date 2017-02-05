<?php

if (file_exists('Include/Config.php')) {
    header('Location: index.php');
}

// don't depend on autoloader here, just in case validation doesn't pass.
if (!(file_exists('ChurchCRM/dto/SystemURLs.php') && file_exists('ChurchCRM/Service/AppIntegrityService.php'))) {
    echo gettext("One or more required setup files are missing.  Please verify you downloaded the correct ChurchCRM package");
    exit;
}

require_once 'ChurchCRM/dto/SystemURLs.php';
ChurchCRM\dto\SystemURLs::init(str_replace('/Setup.php', '', $_SERVER['REQUEST_URI']), '', dirname(__FILE__));
require_once 'ChurchCRM/Service/AppIntegrityService.php';

if (isset($_POST['Setup'])) {
    $template = file_get_contents('Include/Config.php.example');
    $template = str_replace('||DB_SERVER_NAME||', $_POST['DB_SERVER_NAME'], $template);
    $template = str_replace('||DB_NAME||', $_POST['DB_NAME'], $template);
    $template = str_replace('||DB_USER||', $_POST['DB_USER'], $template);
    $template = str_replace('||DB_PASSWORD||', $_POST['DB_PASSWORD'], $template);
    $template = str_replace('||ROOT_PATH||', $_POST['ROOT_PATH'], $template);
    $template = str_replace('||URL||', $_POST['URL'], $template);
    file_put_contents('Include/Config.php', $template);
    header('Location: index.php');
    exit();
}

if (isset($_GET['SystemIntegrityCheck'])) {
    $AppIntegrity = ChurchCRM\Service\AppIntegrityService::verifyApplicationIntegrity();
    echo $AppIntegrity['status'];
    exit();
}

if (isset($_GET['SystemPrerequisiteCheck'])) {
    $required = ChurchCRM\Service\AppIntegrityService::getApplicationPrerequisites();
    header('Content-Type: application/json');
    echo json_encode($required);
    exit;
}

$URL = 'http'.(isset($_SERVER['HTTPS']) ? 's' : '').'://'.$_SERVER['HTTP_HOST'].'/';

error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', '1');

if (!function_exists('bindtextdomain')) {
    function gettext($string)
    {
        return $string + 4;
    }
}

$sPageTitle = gettext('ChurchCRM – Setup');
require 'Include/HeaderNotLoggedIn.php';
?>
<script>
window.CRM = {};
window.CRM.prerequisites = [];
window.CRM.checkIntegrity = function () {
  window.CRM.renderPrerequisite("ChurchCRM File Integrity Check","pending");
  $.ajax({
    url: "<?= \ChurchCRM\dto\SystemURLs::getRootPath() ?>/Setup.php?SystemIntegrityCheck=1",
    method: "GET"
  }).done(function(data){
    if (data == "success" ) 
    {
        window.CRM.renderPrerequisite("ChurchCRM File Integrity Check","pass");
    }
    else
    {
        window.CRM.renderPrerequisite("ChurchCRM File Integrity Check","fail");
    }
   window.CRM.evaluateReadyness();
  }).fail(function(){
    window.CRM.renderPrerequisite("ChurchCRM File Integrity Check","fail");
  });
};

window.CRM.checkPrerequisites = function () {
  $.ajax({
    url: "<?= \ChurchCRM\dto\SystemURLs::getRootPath() ?>/Setup.php?SystemPrerequisiteCheck=1",
    method: "GET",
    contentType: "application/json"
  }).done(function(data){
    $.each(data, function (key,value) {
      if (value)
      {
        status="pass";
      }
      else
      {
        status="fail";
      }
      window.CRM.renderPrerequisite(key,status);
    });
  });
};
window.CRM.renderPrerequisite = function (name, status) {
  var td = {};
  if (status == "pass")
  {
     td = {
      class: 'text-blue',
      html: '&check;'
    };
  }
  else if(status =="pending")
  {
    td = {
      class: 'text-orange',
      html: '<i class="fa fa-spinner fa-spin"></i>'
    };
  }
  else if (status == "fail")
  {
     td = {
      class: 'text-red',
      html: '&#x2717;'
    };
  }
  var id = name.replace(/[^A-z0-9]/g,'');
  window.CRM.prerequisites[id] = status;
  var domElement = "#"+id;
  var prerequisite = $("<tr>",{ id: id }).append(
    $("<td>",{text:name})).append(
      $("<td>",td));

  if ($(domElement).length != 0 )
  {
    $(domElement).replaceWith(prerequisite);
  }
  else
  {
    $("#prerequisites").append(prerequisite);
  }
  
  
};
window.CRM.evaluateReadyness = function () {
  window.CRM.setupCheckStatus = "pass";
  
  for (key in window.CRM.prerequisites) {
   
    if (window.CRM.prerequisites[key] == "pending")
    {
      window.CRM.setupCheckStatus = "pending";
      break; //if there are still checks pending, leave the global state at pending.
    }
    else if (window.CRM.prerequisites[key] == "fail")
    {
      window.CRM.setupCheckStatus = "fail";  // if a check fails, then the whole server fails.
      break;
    }
  };
  
  if (window.CRM.setupCheckStatus == "pass") 
  {
    $(".box-header h3").text("<?= gettext('This server is ChurchCRM ready!') ?>");
    $("#setupPage").css("display","");
    $("#dangerContinue").css("display","none");
  }
  else if (window.CRM.setupCheckStatus == "pending") 
  {
    $(".box-header h3").text("<?= gettext("We're still determining if this server is ready for ChurchCRM.") ?>");
    $("#setupPage").css("display","none");
    $("#dangerContinue").css("display","");
  }
  else 
  {
    $(".box-header h3").text("<?= gettext("This server isn't quite ready for ChurchCRM.") ?>");
    $("#setupPage").css("display","none");
    $("#dangerContinue").css("display","");
  }
};

$("document").ready(function(){
  $("#dangerContinue").click(function(){
    $("#setupPage").css("display","");
  });
  window.CRM.checkIntegrity();
  window.CRM.checkPrerequisites();
  window.CRM.evaluateReadyness();
  
});
</script>
<div class='container' style="padding-bottom:40px;">
  <h3>ChurchCRM – Setup</h3>

  <div class="row">
    <div class="col-lg-6">
      <div class="box">
        <div class="box-header">
            <h3>This server isn't quite ready for ChurchCRM.</h3>
        </div>
        <div class="box-body">
          <div style="width:100%; text-align:center">
            <button class="btn btn-warning" syle="margin-top:-10px, margin-bottom: 15px" id="dangerContinue"><?= gettext("I know what I'm doing.  Install ChurchCRM Anyway") ?></button>
          </div>
          <p><?= gettext("In case you like to know the nitty gritty details, here's what we look for in a server. It's kind of like dating, but more technical.") ?></p>
          
          <h3>Requirements</h3>

          <table class="table" id="prerequisites">
           
          </table>

          <h3>Useful Server Info</h3>

          <table class="table">
            <tr>
              <td>Max file upload size</td>
              <td><?php echo ini_get('upload_max_filesize') ?></td>
            </tr>
            <tr>
              <td>Max POST size</td>
              <td><?php echo ini_get('post_max_size') ?></td>
            </tr>
            <tr>
              <td>PHP Memory Limit</td>
              <td><?php echo ini_get('memory_limit') ?></td>
            </tr>
          </table>
        </div>
      </div>
     
    </div>
      <div id="setupPage" class="col-lg-6" >
        <div class="box">
          <div class="box-body">
            <form target="_self" method="post">
              <div class="form-group">

                <div class="row">
                  <div class="col-md-4">
                    <label for="DB_SERVER_NAME"><?= gettext('DB Server Name') ?>:</label>
                    <input type="text" name="DB_SERVER_NAME" id="DB_SERVER_NAME" value="localhost" class="form-control"
                           required>
                  </div>
                  <div class="col-md-4">
                    <label for="DB_NAME"><?= gettext('DB Name') ?>:</label>
                    <input type="text" name="DB_NAME" id="DB_NAME" value="churchcrm" class="form-control" required>
                  </div>
                  <div class="col-md-4">
                    <label for="DB_USER"><?= gettext('DB User') ?>:</label>
                    <input type="text" name="DB_USER" id="DB_USER" value="churchcrm" class="form-control" required>
                  </div>
                  <div class="col-md-4">
                    <label for="DB_PASSWORD"><?= gettext('DB Password') ?>:</label>
                    <input type="password" name="DB_PASSWORD" id="DB_PASSWORD" value="churchcrm" class="form-control"
                           required>
                  </div>

                  <div class="col-md-4">
                    <label for="ROOT_PATH"><?= gettext('Root Path') ?>:</label>
                    <input type="text" name="ROOT_PATH" id="ROOT_PATH" value="<?= \ChurchCRM\dto\SystemURLs::getRootPath() ?>" class="form-control">
                  </div>

                  <div class="col-md-4">
                    <label for="URL"><?= gettext('Base URL') ?>:</label>
                    <input type="text" name="URL" id="URL" value="<?= $URL ?>" class="form-control" required>
                  </div>
                </div>
              </div>
              <input type="submit" class="btn btn-primary" value="<?= gettext('Setup') ?>" name="Setup">
            </form>
          </div>
        </div>
      </div>
  </div>
</div>
<?php
  require 'Include/FooterNotLoggedIn.php';
?>
