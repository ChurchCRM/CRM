<?php
/*******************************************************************************
 *
 *  filename    : EmailSend.php
 *  description : Sends emails and lists addresses
 *
 *  http://www.infocentral.org/
 *  Copyright 2001-2003 Lewis Franklin
 *
 *  InfoCentral is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/
 
// Include the function library
require "Include/Config.php";
require "Include/Functions.php";
require "Include/ReportFunctions.php";

// Load the PHPMailer library
LoadLib_PHPMailer();

// Set the page title and include HTML header
$sPageTitle = gettext("Email Sent");
require "Include/Header.php";

$mail = new ICMail;

$email_array = $_POST['emaillist'];

if ( is_array($email_array) == TRUE )
{
	$mail->IsSMTP();
	$mail->SMTPKeepAlive = true;
	foreach ($email_array as $email_address)
	{
			$mail->Subject = stripslashes($_POST['emailtitle']);
			$mail->Body = stripslashes($_POST['emailmessage']);
			$mail->AddAddress($email_address);
			echo '<b>' . $email_address . '</b><br />';
			if(!$mail->Send())
				echo "There has been a mail error sending to " . $email_address . "<br>";
			$mail->ClearAddresses();
	}
	$mail->SmtpClose();
}
else
{
	echo gettext("No email addresses specified!");
}
?>
