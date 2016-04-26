<?php
/*******************************************************************************
*
*  filename    : EmailEditor.php
*  description : Form for entering email subject and message
*
*  http://www.churchcrm.io/
*
*  LICENSE:
*  (C) Free Software Foundation, Inc.
*
*  ChurchCRM is free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 3 of the License, or
*  (at your option) any later version.
*
*  This program is distributed in the hope that it will be useful, but
*  WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
*  General Public License for more details.
*
*  http://www.gnu.org/licenses
*
*  This file best viewed in a text editor with tabs stops set to 4 characters
*
******************************************************************************/

// Include the function library
require "Include/Config.php";
require "Include/Functions.php";

if (array_key_exists ('emaillist', $_POST))
	$email_array = $_POST['emaillist'];
else
	$email_array = array ();

$bcc_list = implode(", ", $email_array);

// If editing, get Title and Message
$sEmailSubject = "";
$sEmailMessage = "";
$sEmailAttachName = "";

if (array_key_exists ('mysql', $_POST) && $_POST['mysql'] == 'true') {

    // There is a subject and message already stored in mysql
    $sSQL = "SELECT * FROM email_message_pending_emp ".
            "WHERE emp_usr_id='".$_SESSION['iUserID']."' LIMIT 1";

    $aRow = mysql_fetch_array(RunQuery($sSQL));
    extract($aRow);

    $sEmailSubject = $emp_subject;
    $sEmailMessage = $emp_message;
    $sEmailAttachName = $emp_attach_name;
}

// Security: Both global and user permissions needed to send email.
// Otherwise, re-direct them to the main menu.
if (!($bEmailSend && $bSendPHPMail))
{
	Redirect("Menu.php");
	exit;
}

// Set the page title and include HTML header
$sPageTitle = gettext("Compose Email");
require "Include/Header.php";


//Print the From, To, and Email
echo "<hr>\r\n";
echo '<p class="MediumText"><b>' . gettext("From:") . '</b> "' . $sFromName . '"'
. ' &lt;' . $sFromEmailAddress . '&gt;<br>';
echo '<b>' . gettext("To (blind):") . '</b> '  . $bcc_list . '<br>';


echo "\n<hr>";
echo '<div align="left"><table><tr><td align="left">';

echo '<form action="CartView.php#email" method="post" enctype="multipart/form-data">';

echo gettext('Subject:');
echo '<input type="text" name="emailsubject" size="80" value="';
echo htmlspecialchars($sEmailSubject) . '">'."<br>\n";

echo gettext('Attach file:');
echo "<input class=\"icTinyButton\" type=\"file\" name=\"Attach\"".(strlen($sEmailAttachName)>0?" value=\"$sEmailAttachName\"":"").">";

echo '<br>' . gettext('Message:');
echo '<br><textarea name="emailmessage" rows="20" cols="72">';
echo htmlspecialchars($sEmailMessage) . '</textarea>'."\n";

echo '<br><input class="btn" type="submit" name="submit" value="';
echo gettext('Save Email') . '"></form></td></tr></table></div>'."\n";

require "Include/Footer.php"; 

?>
