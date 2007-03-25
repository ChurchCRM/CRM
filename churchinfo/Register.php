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

require "Include/ReportFunctions.php";

// *****
// Force PHPMailer to the include path (this script only)
$sPHPMailerPath = dirname(__FILE__).DIRECTORY_SEPARATOR.'Include'
.DIRECTORY_SEPARATOR.'phpmailer'.DIRECTORY_SEPARATOR;
$sIncludePath = '.'.PATH_SEPARATOR.$sPHPMailerPath;
ini_set('include_path',$sIncludePath);
// The include_path will automatically be restored upon completion of this script
// *****

$bHavePHPMailerClass = FALSE;
$bHaveSMTPClass = FALSE;
$bHavePHPMailerLanguage = FALSE;

$sLangCode = substr ($sLanguage, 0, 2); // Strip the language code from the beginning of the language_country code

$sPHPMailerClass = $sPHPMailerPath.'class.phpmailer.php';
if (file_exists($sPHPMailerClass) && is_readable($sPHPMailerClass)) {
    require_once ($sPHPMailerClass);
    $bHavePHPMailerClass = TRUE;
    $sFoundPHPMailerClass = $sPHPMailerClass;
}

$sSMTPClass = $sPHPMailerPath.'class.smtp.php';
if (file_exists($sSMTPClass) && is_readable($sSMTPClass)) {
    require_once ($sSMTPClass);
    $bHaveSMTPClass = TRUE;
    $sFoundSMTPClass = $sSMTPClass;
}

$sTestLanguageFile = $sPHPMailerPath.'language'.DIRECTORY_SEPARATOR
.'phpmailer.lang-'.$sLangCode.'.php';
if (file_exists($sTestLanguageFile) && is_readable($sTestLanguageFile)) {
    $sLanguagePath = $sPHPMailerPath.'language'.DIRECTORY_SEPARATOR;
    $bHavePHPMailerLanguage = TRUE;
    $sFoundLanguageFile = $sTestLanguageFile;
}

// This value is checked after the header is printed
$bPHPMAILER_Installed = $bHavePHPMailerClass && $bHaveSMTPClass && $bHavePHPMailerLanguage;

//Is this the second pass?
if (isset($_POST["Submit"]))
{
	$sName = FilterInput($_POST["Name"]);
	$sAddress = FilterInput($_POST["Address"]);
	$sCity = FilterInput($_POST["City"]);
	$sState = FilterInput($_POST["State"]);
	$sZip = FilterInput($_POST["Zip"]);
	$sCountry = FilterInput($_POST["Country"]);
	$sEmail = FilterInput($_POST["Email"]);
	$sComments = FilterInput($_POST["Comments"]);

	$mail = new PHPMailer;

    // Set the language for PHPMailer
    $mail->SetLanguage($sLangCode, $sLanguagePath);
    if($mail->IsError())
        echo 'PHPMailer Error with SetLanguage().  Other errors (if any) may not report.<br>';

    $mail->IsSMTP();                    // tell the class to use SMTP
    $mail->SMTPKeepAlive = true;        // keep connection open until last email sent
    $mail->SMTPAuth = $sSMTPAuth;       // Server requires authentication

    if ($sSMTPAuth) {
        $mail->Username = $sSMTPUser;	// SMTP username
        $mail->Password = $sSMTPPass;	// SMTP password
    }

    $delimeter = strpos($sSMTPHost, ':');
    if ($delimeter === FALSE) {
        $sSMTPPort = 25;                // Default port number
    } else {
        $sSMTPPort = substr($sSMTPHost, $delimeter+1);
        $sSMTPHost = substr($sSMTPHost, 0, $delimeter);   
    }

    if (is_int($sSMTPPort))
        $mail->Port = $sSMTPPort;
    else
        $mail->Port = 25;

    $mail->Host = $sSMTPHost;           // SMTP server name

    $mail->From = $sFromEmailAddress;	// From email address (User Settings)
    $mail->FromName = $sFromName;		// From name (User Settings)

	$mail->Subject = "ChurchInfo registration";
	$mail->Body = "Church name: " . $sName . "\n" .
				  "Address: " . $sAddress . "\n" .
				  "City: " .$sCity . "\n" .
				  "State: " .$sState . "\n" .
				  "Zip: " .$sZip . "\n" .
				  "Country:  " .$sCountry . "\n" .
				  "Email: " .$sEmail . "\n" .
				  "Comments: " .$sComments . "\n" .
				  "";
	$sEmail = "register@churchdb.org";
	$mail->AddAddress($sEmail);
	if(!$mail->Send()) {
		echo "There has been a mail error sending to " . $sEmail . "<br>";
		echo $mail->ErrorInfo;
		echo "bHavePHPMailerLanguage" . $bHavePHPMailerLanguage;
	}

	$mail->SmtpClose();

	$sSQL = "UPDATE config_cfg SET cfg_value = 1 WHERE cfg_name='bRegistered'";
	RunQuery($sSQL);
	$bRegistered = 1;

	Redirect("Menu.php");

} else {
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
	$sCountry = "";
	$sComments = "";
	$sEmail = $reportConfig["sChurchEmail"];
}

require "Include/Header.php";

?>

<p>
<?php
echo gettext ("Please register your copy of ChurchInfo by filling in this information and pressing the Send button.  ");
echo gettext ("This information is used only to track the usage of this software.  ");
echo gettext ("With this information, we are able to maintain a credible presence as a free option in the largely commercial world of church management software.  ");
echo gettext ("We will never sell or otherwise distribute this information.");
?>
</p>

<form method="post" action="Register.php" name="Register">

<table cellpadding="1" align="center">

	<tr>
		<td align="center">
			<input type="submit" class="icButton" value="<?php echo gettext("Send"); ?>" name="Submit">
			<input type="button" class="icButton" value="<?php echo gettext("Cancel"); ?>" name="Cancel" onclick="javascript:document.location='Menu.php';">
		</td>
	</tr>

	<tr>
		<td>
		<table cellpadding="1">
			<tr>
				<td class="LabelColumn"><?php echo gettext("Church name");?></td>
				<td><textarea name="Name" rows="1" cols="90"><?php echo $sName?></textarea></td>
			</tr>
			<tr>
				<td class="LabelColumn"><?php echo gettext("Address");?></td>
				<td><textarea name="Address" rows="1" cols="90"><?php echo $sAddress?></textarea></td>
			</tr>
			<tr>
				<td class="LabelColumn"><?php echo gettext("City");?></td>
				<td><textarea name="City" rows="1" cols="90"><?php echo $sCity?></textarea></td>
			</tr>
			<tr>
				<td class="LabelColumn"><?php echo gettext("State");?></td>
				<td><textarea name="State" rows="1" cols="90"><?php echo $sState?></textarea></td>
			</tr>
			<tr>
				<td class="LabelColumn"><?php echo gettext("Zip code");?></td>
				<td><textarea name="Zip" rows="1" cols="90"><?php echo $sZip?></textarea></td>
			</tr>
			<tr>
				<td class="LabelColumn"><?php echo gettext("Country");?></td>
				<td><textarea name="Country" rows="1" cols="90"><?php echo $sCountry?></textarea></td>
			</tr>
			<tr>
				<td class="LabelColumn"><?php echo gettext("Email");?></td>
				<td><textarea name="Email" rows="1" cols="90"><?php echo $sEmail?></textarea></td>
			</tr>
			<tr>
				<td class="LabelColumn"><?php echo gettext("Comments");?></td>
				<td><textarea name="Comments" rows="1" cols="90"><?php echo $sComments?></textarea></td>
			</tr>

		</table>
		</td>
	</form>
</table>

<?php
require "Include/Footer.php";
?>
