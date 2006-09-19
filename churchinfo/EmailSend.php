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

/* In order to use phpmailer use must Install phpmailer on your server as follows

Copy class.phpmailer.php into your php.ini include_path. If you are
using the SMTP mailer then place class.smtp.php in your path as well.
In the language directory you will find several files like 
phpmailer.lang-en.php.  If you look right before the .php extension 
that there are two letters.  These represent the language type of the 
translation file.  For instance "en" is the English file and "br" is 
the Portuguese file.  Chose the file that best fits with your language 
and place it in the PHP include path.  If your language is English 
then you have nothing more to do.  If it is a different language then 
you must point PHPMailer to the correct translation.  To do this, call 
the PHPMailer SetLanguage method like so:

// To load the Portuguese version
$mail->SetLanguage("br", "/optional/path/to/language/directory");
*/

$bHavePHPMailerClass = FALSE;
$bHaveSMTPClass = FALSE;
$bHavePHPMailerLanguage = FALSE;

$sLanguage = "en";  // In the future set PHPMailer Language in General Settings

$pathArray = explode( PATH_SEPARATOR, get_include_path() );
foreach ($pathArray as $onePath) {
    $sPHPMailerClass = $onePath . DIRECTORY_SEPARATOR . "class.phpmailer.php";
    if (file_exists($sPHPMailerClass) && is_readable($sPHPMailerClass)) {
        require_once ("class.phpmailer.php");
        $bHavePHPMailerClass = TRUE;
    }
    $sSMTPClass = $onePath . DIRECTORY_SEPARATOR . "class.smtp.php";
    if (file_exists($sSMTPClass) && is_readable($sSMTPClass)) {
        require_once ("class.smtp.php");
        $bHaveSMTPClass = TRUE;
    }
    $sSMTPClass = $onePath . DIRECTORY_SEPARATOR . "class.smtp.php";
    if (file_exists($sSMTPClass) && is_readable($sSMTPClass)) {
        require_once ("class.smtp.php");
        $bHaveSMTPClass = TRUE;
    }
    $sTestLanguageFile = $onePath . DIRECTORY_SEPARATOR . "phpmailer.lang-" . $sLanguage . ".php";
    if (file_exists($sTestLanguageFile) && is_readable($sTestLanguageFile)) {
        $sLanguagePath= $onePath . DIRECTORY_SEPARATOR;
        $bHavePHPMailerLanguage = TRUE;
    }   

}

$bPHPMAILER_Installed = $bHavePHPMailerClass && $bHaveSMTPClass && $bHavePHPMailerLanguage;

// Set the page title and include HTML header
$sPageTitle = gettext("Email Sent");
require "Include/Header.php";

if(!$bPHPMAILER_Installed) {
    echo    "<br>" . gettext("ERROR: PHPMailer is not properly installed on this server.")
    .       "<br>" . gettext("PHPMailer is required in order to send emails from this server.") 
    .       "<br>". gettext("View the file churchinfo/Include/phpmailer/README for installation instructions.");
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
    // In the future handle this through a General Settings option

    $mail->SetLanguage($sLanguage, $sLanguagePath);
    if($mail->IsError())
        echo "PHPMailer Error with SetLanguage().  Other errors (if any) may not report.<br>";

    $mail->IsSMTP();                    // tell the class to use SMTP
    $mail->SMTPKeepAlive = true;        // keep connection open until last email sent
    $mail->SMTPAuth = $sSMTPAuth;       // Server requires authentication

    if ($sSMTPAuth) {
        $mail->Username = $sSMTPUser;	// SMTP username
        $mail->Password = $sSMTPPass;	// SMTP password
    }

    // Note: These optional settings for sending email from server should
    // be stored in User Settings.
	if ($_SESSION['sEmailAddress'] <> "") {
        $sFromEmailAddress = $_SESSION['sEmailAddress'];
        $sFromName = $_SESSION['UserFirstName'] . " " . $_SESSION['UserLastName'];
    }

    $mail->From = $sFromEmailAddress;	// From email address
    $mail->FromName = $sFromName;		// From name

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

    foreach ($email_array as $email_address)
    {
        $mail->Subject = stripslashes($_POST['emailtitle']);
        $mail->Body = stripslashes($_POST['emailmessage']);
        if ($_POST['submitBCC'])
            $mail->AddBCC($email_address);
        else
            $mail->AddAddress($email_address);

        echo '<b>' . $email_address . '</b>';
        if(!$mail->Send())
            echo "There has been a mail error sending to " . $email_address 
            . "<br>" . "Mailer Error: " . $mail->ErrorInfo;

        $mail->ClearAddresses();
        $mail->ClearBCCs();
        echo ' Sent! <br>';
    }
    $mail->SmtpClose();
}
else
{
    echo gettext("No email addresses specified!");
}
?>
