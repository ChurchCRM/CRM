<?php
/*******************************************************************************
 *
 *  filename    : Register.php
 *  website     : http://www.churchcrm.io
 *  copyright   : Copyright 2005 Michael Wilt
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

global $systemConfig;

// Set the page title and include HTML header
$sPageTitle = gettext("Software Registration");
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$domainName = $_SERVER['HTTP_HOST']. str_replace("Register.php", '', $_SERVER['REQUEST_URI']);
$ChurchCRMURL =  $protocol.$domainName;

require "Include/Header.php";
?>

<div class="box box-warning">
  <div class="box-body">
	  <?= gettext ("If you need to make changes to registration data, go to "); ?><a href="<?= $sRootPath ?>/SystemSettings.php"><?= gettext("Admin->Edit General Settings"); ?></a>
  </div>
</div>

<div class="box box-primary">
	<div class="box-header">
		<?php
		echo gettext ("Please register your copy of ChurchCRM by checking over this information and pressing the Send button.  ");
		echo gettext ("This information is used only to track the usage of this software.  ");
		?>
	</div>
	<form id="registerForm">
	<div class="box-body">
    <?= gettext('Church Name') ?>: <?= $systemConfig->getValue("sChurchName"); ?><br>
    <?= gettext('Version') ?>: <?= $systemService->getInstalledVersion(); ?><br>
    <?= gettext('Address') ?>: <?= $systemConfig->getValue("sChurchAddress"); ?><br>
    <?= gettext('City') ?>: <?= $systemConfig->getValue("sChurchCity"); ?><br>
    <?= gettext('State') ?>: <?= $systemConfig->getValue("sChurchState"); ?><br>
    <?= gettext('Zip') ?>: <?= $systemConfig->getValue("sChurchZip"); ?><br>
    <?= gettext('Country') ?>: <?= $systemConfig->getValue("sDefaultCountry"); ?><br>
    <?= gettext('Church Email') ?>: <?= $systemConfig->getValue("sChurchEmail"); ?><br>
    ChurchCRM <?= gettext('Base URL') ?>: <?= $ChurchCRMURL ?><br>
		<br> <?= gettext('Message') ?>:
		<br><textarea class="form-control" name="emailmessage" rows="20" cols="72"><?= htmlspecialchars($sEmailMessage) ?> </textarea>
	</div>
	<div class="box-footer">
    <input type="hidden" name="ChurchCRMURL" value="<?= $ChurchCRMURL ?>"/>
		<input type="submit" class="btn btn-primary" value="<?= gettext("Send") ?>" name="Submit">
		<input type="button" class="btn btn-default" value="<?= gettext("Cancel") ?>" name="Cancel" onclick="javascript:document.location='Menu.php';">
	</div>
	</form>
</div>

<script>
$(document).ready(function () {
  $("#registerForm").on("submit", function (ev) {
    ev.preventDefault();
    $.ajax({
      type: "POST",
      url: window.CRM.root + "/api/register",
      data: {
        emailmessage: $("textarea[name=emailmessage]").val(),
        ChurchCRMURL: $("input[name=ChurchCRMURL]").val()
      },
      success: function (data) {
        window.location.href = window.CRM.root+"/";
      }
    });
  });
});
</script>

<?php require "Include/Footer.php" ?>
