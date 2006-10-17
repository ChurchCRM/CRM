<?php
/*******************************************************************************
 *
 *  filename    : EmailPreview.php
 *  description : Displays preview of email
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

// Security: Both global and user permissions needed to send email.
// Otherwise, re-direct them to the main menu.
if (!($bEmailSend && $bSendPHPMail))
{
	Redirect("Menu.php");
	exit;
}

// Set the page title and include HTML header
$sPageTitle = gettext("Email Preview");
require "Include/Header.php";

$email_array = $_POST['emaillist'];

if (is_array($email_array))
{
	//Get the Title and Message
    $subject = htmlspecialchars(stripslashes($_POST['emailtitle']));
    $message = htmlspecialchars(stripslashes($_POST['emailmessage']));

	$bcc_list = implode(", ", $email_array);

	//Print the From, To, and Email List with the Subject and Message
    echo "<hr>\r\n";
    echo "<p class=\"MediumText\"><b>" . gettext("From:") . "</b> \"" . $sFromName . "\""
    . " &lt;" . $sFromEmailAddress . "&gt;<br>";
    echo "<b>" . gettext("To:") . "</b> "  . $bcc_list . "<br>";
    echo "<b>" . gettext("Subject:") . "</b> "  . $subject . "<br>";
    echo "</p><hr><textarea cols=\"72\" rows=\"20\" readonly class=\"MediumText\" style=\"border:0px;\">";
    echo $message . "</textarea><br>";
    echo "<hr>";

	echo "<table><tr><td>";
		echo "<form action=\"CartView.php#email\" method=\"POST\">";
		echo "<input type=\"hidden\" name=\"emailtitle\" value=\"" . $subject . "\">";
		echo "<input type=\"hidden\" name=\"emailmessage\" value=\"" . $message . "\">";
		echo "<input class=\"icButton\" type=\"submit\" name=\"redo\" value=\"Edit Email\"></form>";
	echo "</td><td>";

		echo "<form action=\"EmailSend.php\" method=\"POST\">";
		foreach ($email_array as $email_address)
		{
			echo "<input type=\"hidden\" name=\"emaillist[]\" value=\"" . $email_address . "\">";
		}
		echo "<input type=\"hidden\" name=\"emailtitle\" value=\"" . $subject . "\">";
		echo "<input type=\"hidden\" name=\"emailmessage\" value=\"" . $message . "\">";
		echo "<input class=\"icButton\" type=\"submit\" name=\"submit\" value=\"Send Email\">";
// BCC commented out.  PHPMailer sends emails one at a time so there is no need to conceal
// one address from another.  Also, some spam filters block email if To line is empty.  So
// BCC is not a good idea.
// Even so, this should be enabled in a future version of ChurchInfo via General Settings.
//		echo " <input class=\"icButton\" type=\"submit\" name=\"submitBCC\" value=\"Send Email using BCC\">";
		echo "</form>";

	echo "</td></tr></table>";
}
else
{
	echo 'No email addresses specified!';
}
?>
