<?php
/*******************************************************************************
 *
 *  filename    : Register.php
 *  website     : http://www.churchcrm.io
 *  copyright   : Copyright 2005 Michael Wilt
  *
 ******************************************************************************/

//Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\SystemService;

// Set the page title and include HTML header
$sPageTitle = gettext('Software Registration');
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
$domainName = $_SERVER['HTTP_HOST'].str_replace('Register.php', '', $_SERVER['REQUEST_URI']);
$ChurchCRMURL = $protocol.$domainName;

require 'Include/Header.php';
?>

<div class="card card-warning">
  <div class="card-body">
	  <?= gettext('If you need to make changes to registration data, go to '); ?><a href="<?= SystemURLs::getRootPath() ?>/SystemSettings.php"><?= gettext('Admin->Edit General Settings'); ?></a>
  </div>
</div>

<div class="card card-primary">
	<div class="card-header">
		<?php
        echo gettext('Please register your copy of ChurchCRM by checking over this information and pressing the Send button.  ');
        echo gettext('This information is used only to track the usage of this software.  ');
        ?>
	</div>
	<form id="registerForm">
	<div class="card-body">
    <?= gettext('Church Name') ?>: <?= SystemConfig::getValue('sChurchName'); ?><br>
    <?= gettext('Version') ?>: <?= SystemService::getInstalledVersion(); ?><br>
    <?= gettext('Address') ?>: <?= SystemConfig::getValue('sChurchAddress'); ?><br>
    <?= gettext('City') ?>: <?= SystemConfig::getValue('sChurchCity'); ?><br>
    <?= gettext('State') ?>: <?= SystemConfig::getValue('sChurchState'); ?><br>
    <?= gettext('Zip') ?>: <?= SystemConfig::getValue('sChurchZip'); ?><br>
    <?= gettext('Country') ?>: <?= SystemConfig::getValue('sChurchCountry'); ?><br>
    <?= gettext('Church Email') ?>: <?= SystemConfig::getValue('sChurchEmail'); ?><br>
    ChurchCRM <?= gettext('Base URL') ?>: <?= $ChurchCRMURL ?><br>
		<br> <?= gettext('Message') ?>:
		<br><textarea class="form-control" name="emailmessage" rows="20" cols="72"><?= htmlspecialchars($sEmailMessage) ?> </textarea>
	</div>
	<div class="card-footer">
    <input type="hidden" name="ChurchCRMURL" value="<?= $ChurchCRMURL ?>"/>
		<input type="submit" class="btn btn-primary" value="<?= gettext('Send') ?>" name="Submit">
		<input type="button" class="btn btn-default" value="<?= gettext('Cancel') ?>" name="Cancel" onclick="javascript:document.location='Menu.php';">
	</div>
	</form>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
$(document).ready(function () {
  $("#registerForm").on("submit", function (ev) {
    ev.preventDefault();
    window.CRM.APIRequest({
      method: "POST",
      path: "register",
      data: JSON.stringify({
        emailmessage: $("textarea[name=emailmessage]").val(),
        ChurchCRMURL: $("input[name=ChurchCRMURL]").val()
      }),
      success: function (data) {
        window.location.href = window.CRM.root+"/";
      }
    });
  });
});
</script>

<?php require 'Include/Footer.php' ?>
