<?php
/*******************************************************************************
*
*  filename    : EmailSend.php
*  description : Sends emails and lists addresses
*
*  http://www.churchdb.org/
*  Copyright 2001-2003 Lewis Franklin
*
*  Additional Contributors:
*  2006 Ed Davis
*
*
*  Copyright Contributors
*
*  ChurchInfo is free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  This file best viewed in a text editor with tabs stops set to 4 characters.
*  Please configure your editor to use soft tabs (4 spaces for a tab) instead
*  of hard tab characters.
*
******************************************************************************/

// Include the function library
require "Include/Config.php";
require "Include/Functions.php";

// *****
// Force PHPMailer to the include path (this script only)
$sPHPMailerPath = dirname(__FILE__).DIRECTORY_SEPARATOR.'Include'
.DIRECTORY_SEPARATOR.'phpmailer'.DIRECTORY_SEPARATOR;
$sIncludePath = ".:".$sPHPMailerPath;
ini_set('include_path',$sIncludePath);
// The include_path will automatically be restored upon completion of this script
// *****

$bHavePHPMailerClass = FALSE;
$bHaveSMTPClass = FALSE;
$bHavePHPMailerLanguage = FALSE;

$sLanguage = "en";  // In the future set PHPMailer Language in General Settings

$sPHPMailerClass = $sPHPMailerPath."class.phpmailer.php";
if (file_exists($sPHPMailerClass) && is_readable($sPHPMailerClass)) {
    require_once ("$sPHPMailerClass");
    $bHavePHPMailerClass = TRUE;
    $sFoundPHPMailerClass = $sPHPMailerClass;
}

$sSMTPClass = $sPHPMailerPath."class.smtp.php";
if (file_exists($sSMTPClass) && is_readable($sSMTPClass)) {
    require_once ("$sSMTPClass");
    $bHaveSMTPClass = TRUE;
    $sFoundSMTPClass = $sSMTPClass;
}

$sTestLanguageFile = $sPHPMailerPath.'language'.DIRECTORY_SEPARATOR
.'phpmailer.lang-'.$sLanguage.'.php';
if (file_exists($sTestLanguageFile) && is_readable($sTestLanguageFile)) {
    $sLanguagePath = $sPHPMailerPath.'language'.DIRECTORY_SEPARATOR;
    $bHavePHPMailerLanguage = TRUE;
    $sFoundLanguageFile = $sTestLanguageFile;
}

$bPHPMAILER_Installed = $bHavePHPMailerClass && $bHaveSMTPClass && $bHavePHPMailerLanguage;

// Set the page title and include HTML header
$sPageTitle = gettext("Email Sent");
require "Include/Header.php";

if(!$bPHPMAILER_Installed) {
    echo    "<br>" . gettext("ERROR: PHPMailer is not properly installed on this server.")
    .       "<br>" . gettext("PHPMailer is required in order to send emails from this server.");

    echo "<br><br>include_path = " . ini_get('include_path');

    if ($bHavePHPMailerClass)
        echo "<br><br>Found: " . $sFoundPHPMailerClass;
    else
        echo "<br><br>Unable to find file: class.phpmailer.php";


    if ($bHaveSMTPClass)
        echo "<br>Found: " . $sFoundSMTPClass;
    else
        echo "<br>Unable to find file: class.smtp.php";


    if ($bHavePHPMailerLanguage)
        echo "<br>Found: " . $sFoundLanguageFile;
    else
        echo "<br>Unable to find file: phpmailer.lang-" . $sLanguage . ".php";

    exit;
}

// Security: User must be an Admin to access this page.
// Otherwise, re-direct them to the main menu.
//if (!$_SESSION['bAdmin'])
//{
//	Redirect("Menu.php");
//	exit;
//}

$mail = new PHPMailer();

$email_array = $_POST['emaillist'];

if ( is_array($email_array) == TRUE )
{

    // Set the language for PHPMailer
    $mail->SetLanguage($sLanguage, $sLanguagePath);
    if($mail->IsError())
        echo "PHPMailer Error with SetLanguage().  Other errors (if any) may not report.<br>";

    // Note: These optional settings for sending email from server should
    // be stored in User Settings.
	if ($_SESSION['sEmailAddress'] <> "") {
        $sFromEmailAddress = $_SESSION['sEmailAddress'];
        $sFromName = $_SESSION['UserFirstName'] . " " . $_SESSION['UserLastName'];
    }

    $mail->From = $sFromEmailAddress;	// From email address
    $mail->FromName = $sFromName;		// From name

    if (strtolower($sSendType)=="smtp") {

        $mail->IsSMTP();                    // tell the class to use SMTP
        $mail->SMTPKeepAlive = true;        // keep connection open until last email sent
        $mail->SMTPAuth = $sSMTPAuth;       // Server requires authentication

        if ($sSMTPAuth) {
            $mail->Username = $sSMTPUser;	// SMTP username
            $mail->Password = $sSMTPPass;	// SMTP password
        }

        $delimeter = strpos($sSMTPHost, ":");
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
    } else {
        $mail->IsSendmail();                // tell the class to use Sendmail
    }

    foreach ($email_array as $email_address)
    {
        $mail->Subject = stripslashes($_POST['emailtitle']);
        $mail->Body = stripslashes($_POST['emailmessage']);
        if ($_POST['submitBCC'])
            $mail->AddBCC($email_address);
        else
            $mail->AddAddress($email_address);

        echo '<b>' . $email_address . '</b>';
        if(!$mail->Send()) {
            echo "Unable to send to: " . $email_address 
            . "<br>" . "Mailer Error: " . $mail->ErrorInfo;
        } else {
            echo ' Sent! <br>';
        }
        $mail->ClearAddresses();
        $mail->ClearBCCs();
    }
    
    if (strtolower($sSendType)=="smtp")
        $mail->SmtpClose();
}
else
{
    echo gettext("No email addresses specified!");
}
?>
