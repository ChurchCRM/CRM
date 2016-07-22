<?php

if (file_exists ( 'Include/Config.php')) {
  header("Location: index.php" );
} 

if (isset($_POST["Setup"])) {
  $template = file_get_contents("Include/Config.php.example");
  $template = str_replace("||DB_SERVER_NAME||", $_POST["DB_SERVER_NAME"], $template);
  $template = str_replace("||DB_NAME||", $_POST["DB_NAME"], $template);
  $template = str_replace("||DB_USER||", $_POST["DB_USER"], $template);
  $template = str_replace("||DB_PASSWORD||", $_POST["DB_PASSWORD"], $template);
  $template = str_replace("||ROOT_PATH||", $_POST["ROOT_PATH"], $template);
  $template = str_replace("||URL||", $_POST["URL"], $template);
  file_put_contents("Include/Config.php", $template);
  header("Location: index.php" );
  exit();
}

$temp = $_SERVER['REQUEST_URI'];
$rootPath = "/" . str_replace("/Setup.php", "", $temp);
if ($rootPath = "/") {
  $rootPath = "";
}
$URL = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . "/";

$is_ready = TRUE;
error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', '1');
$required = array(
  'PHP 5.6+' => version_compare(PHP_VERSION, '5.6.0', '>='),
  'PCRE and UTF-8 Support' => function_exists('preg_match') && @preg_match('/^.$/u', 'ñ') && @preg_match('/^\pL$/u', 'ñ'),
  'Multibyte Encoding' => extension_loaded('mbstring'),
  'Mcrypt' => extension_loaded('mcrypt'),
  'Mod Rewrite' => hasModRewrite('mod_rewrite'),
  'GD Library for image manipulation' => (extension_loaded('gd') && function_exists('gd_info')),
  'FileInfo Extension for image manipulation' => extension_loaded('fileinfo'),
  'cURL' => function_exists('curl_version'),
  'locale/gettext' => function_exists('bindtextdomain')
);

foreach ($required as $feature => $pass) {
  if ($pass === FALSE) {
    $is_ready = FALSE;
  }
}

function hasApacheModule($module)
{
  if (function_exists('apache_get_modules')) {
    return in_array($module, apache_get_modules());
  }

  return false;
}

function hasModRewrite()
{
  $check = hasApacheModule('mod_rewrite');

  if (!$check && function_exists('shell_exec')) {
    $check = strpos(shell_exec('/usr/local/apache/bin/apachectl -l'), 'mod_rewrite') !== FALSE;
  }

  return $check;
}

$sPageTitle = "ChurchCRM – Setup";
require("Include/HeaderNotLoggedIn.php");
?>
<script>
$("document").ready(function(){
$("#dangerContinue").click(function(){
  $("#setupPage").css("display","");
})
});
</script>
<div class='container'>
  <h3>ChurchCRM – Setup</h3>

  <div class="row">
    <div class="col-lg-6">
      <div class="box">
        <div class="box-header">
          <?php if ($is_ready): ?>
            <h3>This server is ChurchCRM ready!</h3>
          <?php else: ?>
            <h3>This server isn't quite ready for ChurchCRM.</h3>
          <?php endif; ?>
        </div>
        <div class="box-body">
          <p>In case you like to know the nitty gritty details, here's what we look for in a server. It's kind of like
            dating, but more technical.</p>

          <h3>Requirements</h3>

          <table class="table">
            <?php foreach ($required as $label => $passed): ?>
              <tr>
                <td><?php echo $label ?></td>
                <td
                  class="<?php echo ($passed) ? 'text-blue' : 'text-red' ?>"><?php echo ($passed) ? '&check;' : '&#x2717;' ?></td>
              </tr>
            <?php endforeach ?>
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
       <?php if (!$is_ready) { echo '<button class="btn btn-warning" id="dangerContinue">I know what I\'m doing.  Install ChurchCRM Anyway</button>'; } ?>
    </div>
      <div id="setupPage" class="col-lg-6"  <?php if (!$is_ready) { echo 'style="display:none"'; } ?>> 
        <div class="box">
          <div class="box-body">
            <form target="_self" method="post">
              <div class="form-group">

                <div class="row">
                  <div class="col-md-4">
                    <label for="DB_SERVER_NAME"><?= gettext("DB Server Name:") ?></label>
                    <input type="text" name="DB_SERVER_NAME" id="DB_SERVER_NAME" value="localhost" class="form-control"
                           required>
                  </div>
                  <div class="col-md-4">
                    <label for="DB_NAME"><?= gettext("DB Name:") ?></label>
                    <input type="text" name="DB_NAME" id="DB_NAME" value="churchcrm" class="form-control" required>
                  </div>
                  <div class="col-md-4">
                    <label for="DB_USER"><?= gettext("DB User:") ?></label>
                    <input type="text" name="DB_USER" id="DB_USER" value="churchcrm" class="form-control" required>
                  </div>
                  <div class="col-md-4">
                    <label for="DB_PASSWORD"><?= gettext("DB Password:") ?></label>
                    <input type="password" name="DB_PASSWORD" id="DB_PASSWORD" value="churchcrm" class="form-control"
                           required>
                  </div>

                  <div class="col-md-4">
                    <label for="ROOT_PATH"><?= gettext("Root Path:") ?></label>
                    <input type="text" name="ROOT_PATH" id="ROOT_PATH" value="<?= $rootPath ?>" class="form-control">
                  </div>

                  <div class="col-md-4">
                    <label for="URL"><?= gettext("Base URL:") ?></label>
                    <input type="text" name="URL" id="URL" value="<?= $URL ?>" class="form-control" required>
                  </div>
                </div>
              </div>
              <input type="submit" class="btn btn-primary" value="<?= gettext("Setup") ?>" name="Setup">
            </form>
          </div>
        </div>
      </div>
    <?php
    require("Include/FooterNotLoggedIn.php");
    ?>
