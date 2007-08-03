<?php
/*******************************************************************************
 *
 *  filename    : Register.php
 *  website     : http://www.churchdb.org
 *  copyright   : Copyright 2005 Michael Wilt
 *
 *  ChurchInfo is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

//Include the function library
require "Include/Config.php";

require "Include/Functions.php";
require "Include/Header.php";

echo gettext ("Please register your copy of ChurchInfo by checking over this information and pressing the Send button.  ");
echo gettext ("If you need to make changes go to Admin->Edit General Settings and Admin->Edit Report Settings.  ");
echo gettext ("This information is used only to track the usage of this software.  ");
echo gettext ("With this information, we are able to maintain a credible presence as a free option in the largely commercial world of church management software.  ");
echo gettext ("We will never sell or otherwise distribute this information.");

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

$sEmailSubject = "ChurchInfo registration";

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

<form method="post" action="EmailSend.php" name="Register">

<input type="hidden" name="emaillist[]" value="register@churchdb.org">

<table cellpadding="1" align="center">

	<tr>
		<td align="center">
			<input type="submit" class="icButton" value="<?php echo gettext("Send"); ?>" name="Submit">
			<input type="button" class="icButton" value="<?php echo gettext("Cancel"); ?>" name="Cancel" onclick="javascript:document.location='Menu.php';">
		</td>
	</tr>
</table>

<?php
echo gettext('Subject:');
echo '<br><input type="text" name="emailsubject" size="80" value="';
echo htmlspecialchars($sEmailSubject) . '"></input>'."\n";

echo '<br>' . gettext('Message:');
echo '<br><textarea name="emailmessage" rows="20" cols="72">';
echo htmlspecialchars($sEmailMessage) . '</textarea>'."\n";
?>

</form>

<?php
require "Include/Footer.php";
?>
