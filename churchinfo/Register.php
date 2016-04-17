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

// Set the page title and include HTML header
$sPageTitle = gettext("Software Registration");

require "Include/Header.php";


// Read in report settings from database
$rsConfig = mysql_query("SELECT cfg_name, IFNULL(cfg_value, cfg_default) AS value FROM config_cfg WHERE cfg_section='ChurchInfoReport'");
if ($rsConfig) {
	while (list($cfg_name, $cfg_value) = mysql_fetch_row($rsConfig)) {
		$reportConfig[$cfg_name] = $cfg_value;
	}
}
$sName = $reportConfig["sChurchName"];
$sAddress = $reportConfig["sChurchAddress"];
$sCity = $reportConfig["sChurchCity"];
$sState = $reportConfig["sChurchState"];
$sZip = $reportConfig["sChurchZip"];
$sCountry = $sDefaultCountry;
$sComments = "";
$sEmail = $reportConfig["sChurchEmail"];

$sEmailSubject = "ChurchCRM registration";

$sEmailMessage =
	"Church name: " . $sName . "\n" .
	"Address: " . $sAddress . "\n" .
	"City: " .$sCity . "\n" .
	"State: " .$sState . "\n" .
	"Zip: " .$sZip . "\n" .
	"Country:  " .$sCountry . "\n" .
	"Email: " .$sEmail . "\n" .
	"Additional comments: " . $sComments . "\n";

// Poke the message into email_message_pending_emp so EmailSend can find it
$sSQL = "INSERT INTO email_message_pending_emp ".
        "SET " . 
			"emp_usr_id='" .$_SESSION['iUserID']. "',".
			"emp_to_send='0'," .
			"emp_subject='" . mysql_real_escape_string($sEmailSubject). "',".
			"emp_message='" . mysql_real_escape_string($sEmailMessage). "'";
RunQuery($sSQL);

// Turn off the registration flag so the menu option is less obtrusive
$sSQL = "UPDATE config_cfg SET cfg_value = 1 WHERE cfg_name='bRegistered'";
RunQuery($sSQL);
$bRegistered = 1;

$sSQL = "UPDATE menuconfig_mcf SET content = 'Update registration' WHERE name = 'register' AND parent = 'admin'";
RunQuery($sSQL);

?>

<div class="box">
	<div class="box-header">
		<?php
		echo gettext ("Please register your copy of ChurchCRM by checking over this information and pressing the Send button.  ");
		echo gettext ("This information is used only to track the usage of this software.  ");
		?>
	</div>
	<form method="post" action="EmailSend.php" name="Register">
	<div class="box-body">
		<input type="hidden" name="emaillist[]" class="form-control" value="info@churchcrm.io">
			<?php
			echo gettext('Subject:');
			echo '<br><input type="text" class="form-control" name="emailsubject" size="80" value="';
			echo htmlspecialchars($sEmailSubject) . '"></input>'."\n";

			echo '<br>' . gettext('Message:');
			echo '<br><textarea class="form-control" name="emailmessage" rows="20" cols="72">';
			echo htmlspecialchars($sEmailMessage) . '</textarea>'."\n";
			?>
	</div>
	<div class="box-footer">
		<div class="pull-right">
				<input type="submit" class="btn btn-primary" value="<?= gettext("Send") ?>" name="Submit">
		</div>
		<input type="button" class="btn btn-default" value="<?= gettext("Cancel") ?>" name="Cancel" onclick="javascript:document.location='Menu.php';">
	</div>
	</form>
</div>
<div class="box box-warning">
	<?= gettext ("If you need to make changes go to Admin->Edit General Settings and Admin->Edit Report Settings.  "); ?>
</div>

<?php require "Include/Footer.php" ?>
