<?php
/*******************************************************************************
*
*  filename    : CartView.php
*  website     : http://www.churchdb.org
*
*  Copyright 2001-2003 Phillip Hullquist, Deane Barker, Chris Gebhardt
*
*  Additional Contributors:
*  2006 Ed Davis
*
*
*  Copyright 2006 Contributors
*
*  ChurchInfo is free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  This file best viewed in a text editor with tabs stops set to 4 characters
*
******************************************************************************/

function ExportCartToCSV()
{
    $sSQL  =    " DROP TEMPORARY TABLE IF EXISTS tmp_canvassers ";
    RunQuery($sSQL);

    // Make temporary copy of person_per table and call it tmp_canvassers
    $sSQL  =    " CREATE TEMPORARY TABLE tmp_canvassers ".
                " SELECT * FROM person_per ";
    RunQuery($sSQL);

    $sSQL  =    " SELECT lst_OptionName AS Classification, fam_Name AS Family, ".
                " person_per.per_LastName AS Last_Name, ".
                " person_per.per_FirstName AS First_Name, ".
                " fam_HomePhone, person_per.per_HomePhone AS per_HomePhone, ".
                " fam_Address1, fam_Address2, fam_City, ".
                " fam_State, fam_Zip, person_per.per_DateEntered AS DateEntered, ".
                " tmp_canvassers.per_LastName AS Cnvsr_Last_Name, ".
                " tmp_canvassers.per_FirstName AS Cnvsr_First_Name ".
                " FROM person_per ".
                " LEFT JOIN family_fam ON fam_ID = person_per.per_fam_ID ".
                " LEFT JOIN list_lst ON lst_OptionID = person_per.per_cls_ID ".
                " LEFT JOIN tmp_canvassers ON tmp_canvassers.per_ID = fam_Canvasser ".
                " WHERE person_per.per_ID ".
                "     IN (" . ConvertCartToString($_SESSION['aPeopleCart']) . ") ".
                " AND lst_ID='1' ".
                " ORDER BY fam_Name, fam_ID, Last_Name, First_Name ";
	
	//Run the SQL
	$rsQueryResults = RunQuery($sSQL);

    $sCSVstring = "";

	if (mysql_error() != "")
	{
		$sCSVstring = gettext("An error occured: ") . mysql_errno() . "--" . mysql_error();
	}
	else
	{

		//Loop through the fields and write the header row
		for ($iCount = 0; $iCount < mysql_num_fields($rsQueryResults); $iCount++)
		{
            $sCSVstring .= mysql_field_name($rsQueryResults,$iCount) . ",";
		}

        $sCSVstring .= "\n";

		//Loop through the recordsert
		while($aRow =mysql_fetch_array($rsQueryResults))
		{
			//Loop through the fields and write each one
			for ($iCount = 0; $iCount < mysql_num_fields($rsQueryResults); $iCount++)
			{
				$sCSVstring .= $aRow[$iCount] . ",";
			}

			$sCSVstring .= "\n";
		}
	}

    header("Content-type: application/csv");
	header("Content-Disposition: attachment; filename=Cart-" . date("Ymd-Gis") . ".csv");
	header("Content-Transfer-Encoding: binary");
	header('Expires: 0');
	header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
	header('Pragma: public'); 
	echo $sCSVstring;
    exit;

}


// Include the function library

require "Include/Config.php";
require "Include/Functions.php";
require "Include/LabelFunctions.php";

if (isset($_POST["cartcsv"]))
{

    // If user does not have CSV Export permission, redirect to the menu.
    if (!$bExportCSV) 
    {
	   Redirect("Menu.php");
	   exit;
    }

    ExportCartToCSV();
    exit;
}

// Set the page title and include HTML header
$sPageTitle = gettext("View Your Cart");
require "Include/Header.php";

// Confirmation message that people where added to Event from Cart
if (count($_SESSION['aPeopleCart']) == 0) {
        if (!$_GET["Message"])
        {
            echo "<p align=\"center\" class=\"LargeText\">" . gettext("You have no items in your cart.") . "</p>";
        } else {
            switch ($_GET["Message"])
            {
                case "aMessage":
                    echo '<p align="center" class="LargeText">'.$_GET["iCount"].' '.($_GET["iCount"] == 1 ? "Record":"Records").' Emptied into Event ID:'.$_GET["iEID"].'</p>'."\n";
                break;
            }
        }
        echo '<p align="center"><input type="button" name="Exit" class="icButton" value="'.gettext("Back to Menu").'" '."onclick=\"javascript:document.location='Menu.php';\"></p>\n";

} else {

        // Create array with Classification Information (lst_ID = 1)
        $sClassSQL  = "SELECT * FROM list_lst WHERE lst_ID=1 ORDER BY lst_OptionSequence";
        $rsClassification = RunQuery($sClassSQL);
        unset($aClassificationName);
        $aClassificationName[0] = "Unassigned";
        while ($aRow = mysql_fetch_array($rsClassification))
        {
            extract($aRow);
            $aClassificationName[intval($lst_OptionID)]=$lst_OptionName;
        }

        // Create array with Family Role Information (lst_ID = 2)
        $sFamRoleSQL  = "SELECT * FROM list_lst WHERE lst_ID=2 ORDER BY lst_OptionSequence";
        $rsFamilyRole = RunQuery($sFamRoleSQL);
        unset($aFamilyRoleName);
        $aFamilyRoleName[0] = "Unassigned";
        while ($aRow = mysql_fetch_array($rsFamilyRole))
        {
            extract($aRow);
            $aFamilyRoleName[intval($lst_OptionID)]=$lst_OptionName;
        }


        $sSQL = "SELECT * FROM person_per LEFT JOIN family_fam ON person_per.per_fam_ID = family_fam.fam_ID WHERE per_ID IN (" . ConvertCartToString($_SESSION['aPeopleCart']) . ") ORDER BY per_LastName";
        $rsCartItems = RunQuery($sSQL);
        $iNumPersons = mysql_num_rows($rsCartItems);

        $sSQL = "SELECT distinct per_fam_ID FROM person_per LEFT JOIN family_fam ON person_per.per_fam_ID = family_fam.fam_ID WHERE per_ID IN (" . ConvertCartToString($_SESSION['aPeopleCart']) . ") ORDER BY per_fam_ID";
        $iNumFamilies = mysql_num_rows(RunQuery($sSQL));

        if ($iNumPersons > 16)
        {
        ?>
        <center>
        <form method="get" action="CartView.php#GenerateLabels">
        <input type="submit" class="icButton" name="gotolabels" 
        value="<?php echo gettext("Go To Labels");?>">
        </form></center>
        <?php
        }

        echo '<p align="center">' . gettext("Your cart contains") . ' ' . $iNumPersons . ' ' . gettext("persons from") . ' ' . $iNumFamilies . ' ' . gettext("families.") . '</p>';

        echo '<table align="center" width="70%" cellpadding="4" cellspacing="0">';
        echo '<tr class="TableHeader">';
        echo '<td><b>' . gettext("Name") . '</b></td>';
        echo '<td align="center"><b>' . gettext("Address?") . '</b></td>';
        echo '<td align="center"><b>' . gettext("Email?") . '</b></td>';
        echo '<td><b>' . gettext("Remove") . '</b></td>';
        echo '<td align="center"><b>' . gettext("Classification") . '</b></td>';
        echo '<td align="center"><b>' . gettext("Family Role") . '</b></td>';

        $sEmailLink = "";
        $iEmailNum = 0;

        while ($aRow = mysql_fetch_array($rsCartItems))
        {
                $sRowClass = AlternateRowStyle($sRowClass);

                extract($aRow);

                $sEmail = SelectWhichInfo($per_Email, $fam_Email, False);
                if (strlen($sEmail))
                {
                        $sValidEmail = gettext("Yes");
                        if (!stristr($sEmailLink, $sEmail))
                        {
                                $email_array[] = $sEmail;

                                if ($iEmailNum == 0)
                                {       // Comma is not needed before first email address
                                        $sEmailLink .= $sEmail;
                                        $iEmailNum++;
                                }
                                else
                                        $sEmailLink .= $sMailtoDelimiter . $sEmail;
                        }
                }
                else
                {
                        $sValidEmail = gettext("No");
                }

                $sAddress1 = SelectWhichInfo($per_Address1, $fam_Address1, False);
                $sAddress2 = SelectWhichInfo($per_Address2, $fam_Address2, False);

                if (strlen($sAddress1) > 0 || strlen($sAddress2) > 0)
                        $sValidAddy = gettext("Yes");
                else
                        $sValidAddy = gettext("No");

                echo '<tr class="' . $sRowClass . '">';
                echo '<td><a href="PersonView.php?PersonID=' . $per_ID . '">' . FormatFullName($per_Title, $per_FirstName, $per_MiddleName, $per_LastName, $per_Suffix, 1) . '</a></td>';

                echo '<td align="center">' . $sValidAddy . '</td>';
                echo '<td align="center">' . $sValidEmail . '</td>';
                echo '<td><a onclick="saveScrollCoordinates()" 
                        href="CartView.php?RemoveFromPeopleCart=' . 
                        $per_ID . '">' . gettext("Remove") . '</a></td>';
                echo '<td align="center">' . $aClassificationName[$per_cls_ID] . '</td>';
                echo '<td align="center">' . $aFamilyRoleName[$per_fmr_ID] . '</td>';

                echo "</tr>";
        }

        echo "</table>";
}

if (count($_SESSION['aPeopleCart']) != 0)
{
        echo "<br><table align=\"center\" cellpadding=\"15\"><tr><td valign=\"top\">";
        echo "<p align=\"center\" class=\"MediumText\">";
        echo "<b>" . gettext("Cart Functions") . "</b><br>";
        echo "<br>";
        echo "<a href=\"CartView.php?Action=EmptyCart\">" . gettext("Empty Cart") . "</a>";

        if ($_SESSION['bManageGroups']) {
                echo "<br>";
                echo "<a href=\"CartToGroup.php\">" . gettext("Empty Cart to Group") . "</a>";
        }
        if ($_SESSION['bAddRecords']) {
                echo "<br>";
                echo "<a href=\"CartToFamily.php\">" . gettext("Empty Cart to Family") . "</a>";
        }
        echo "<br>";
        echo "<a href=\"CartToEvent.php\">" . gettext("Empty Cart to Event") . "</a>";

        // Only show CSV export link if user is allowed to CSV export.
        if ($bExportCSV) 
        {
            /* Link to CSV export */
            echo "<br>";
            echo "<a href=\"CSVExport.php?Source=cart\">" . gettext("CSV Export") . "</a>";
        }

        if ($iEmailNum > 0) {
                // Add default email if default email has been set and is not already in string
                if ($sToEmailAddress != "" && $sToEmailAddress != "myReceiveEmailAddress" && !stristr($sEmailLink, $sToEmailAddress))
                        $sEmailLink .= $sMailtoDelimiter . $sToEmailAddress;
                $sEmailLink = urlencode($sEmailLink);  // Mailto should comply with RFC 2368
                if ($bEmailMailto) { // Does user have permission to email groups with mailto
                echo "<br><a href=\"mailto:" . $sEmailLink ."\">". gettext("Email Cart") . "</a>";
                echo "<br><a href=\"mailto:?bcc=".$sEmailLink."\">".gettext("Email (BCC)")."</a>";
                }
        }
        echo "<br><a href=\"MapUsingGoogle.php?GroupID=0\">" . gettext("Map Cart") . "</a>";

        echo "</p></td>";
?>
        <td>
        <a name="GenerateLabels"></a>

        <script language="JavaScript" type="text/javascript"><!--
        function codename() 
        {
            if(document.labelform.bulkmailpresort.checked)
            {
                document.labelform.bulkmailquiet.disabled=false;
            }
            else
            {
                document.labelform.bulkmailquiet.disabled=true;
                document.labelform.bulkmailquiet.checked=false;
            }
        }
    
        //-->
        </SCRIPT>



    <form method="get" action="Reports/PDFLabel.php" name="labelform">
        <table cellpadding="4" align="center">
                <?php
				LabelGroupSelect("groupbymode");

                echo '  <tr><td class="LabelColumn">' . gettext("Bulk Mail Presort") . '</td>';
                echo '  <td class="TextColumn">';
                echo '  <input name="bulkmailpresort" type="checkbox" onclick="codename()"';
                echo '  id="BulkMailPresort" value="1" ';
                if ($_COOKIE["bulkmailpresort"])
                    echo "checked";
                echo '  ><br></td></tr>';

                echo '  <tr><td class="LabelColumn">' . gettext("Quiet Presort") . '</td>';
                echo '  <td class="TextColumn">';
                echo '  <input ';
                if (!$_COOKIE["bulkmailpresort"])
                    echo 'disabled ';   // This would be better with $_SESSION variable
                                        // instead of cookie ... (save $_SESSION in MySQL)
                echo 'name="bulkmailquiet" type="checkbox" onclick="codename()"';
                echo '  id="QuietBulkMail" value="1" ';
                if ($_COOKIE["bulkmailquiet"] && $_COOKIE["bulkmailpresort"])
                    echo "checked";
                echo '  ><br></td></tr>';

				ToParentsOfCheckBox("toparents");
				LabelSelect("labeltype");
				FontSelect("labelfont");
				FontSizeSelect("labelfontsize");
				StartRowStartColumn();
				IgnoreIncompleteAddresses();
				LabelFileType();
                ?> 	                           

    			<tr>
						<td></td>
						<td><input type="submit" class="icButton" value="<?php echo gettext("Generate Labels");?>" name="Submit"></td>
				</tr>
    </table></form></td></tr></table>

<?php
// Only show CSV export link if user is allowed to CSV export.
if ($bExportCSV) 
{
    ?>
    <div align="center">
    <form method="post" action="CartView.php">
    <?php echo "<br><h2>" . gettext("Export Cart to CSV File") . "</h2>"; ?>
    <input type="submit" class="icButton" name="cartcsv" 
            value="<?php echo gettext("Create CSV File");?>">
    </form>
    </div>
    <?php
} 

// Only show create directory link if user is allowed to create directories
if ($bCreateDirectory)
{
?>
<div align="center"><form method="get" action="DirectoryReports.php">
<?php echo "<br><h2>" . gettext("Create Directory From Cart") . "</h2>"; ?>
<input type="submit" class="icButton" name="cartdir" 
       value="<?php echo gettext("Cart Directory");?>">
</form></div>
<?php
}


    echo '<a name="email"></a>'; // anchor used by EmailEditor.php

    if (($bEmailSend) && ($bSendPHPMail))
    {

        if (isset($email_array)) {
            $bcc_list = "";
            foreach ($email_array as $email_address) {
                // Add all address except the default
                // avoid sending to this address twice
                if ($email_address != $sToEmailAddress) {
                    $bcc_list .= $email_address . ",";
                }
            }
            $bcc_list .= $sToEmailAddress;
        }

        $sEmailForm = ""; // Initialize to empty

        ?><div align="center"><table><tr><td align="center"><?php
        echo "<br><h2>" . gettext("Send Email To People in Cart") . "</h2>";
 
        // Check if there are pending emails that have not been delivered
        // A user cannot send a new email until the previous email has been sent
    
        $sSQL  = "SELECT COUNT(emp_usr_id) as count "
               . "FROM email_message_pending_emp "
               . "WHERE emp_usr_id='".$_SESSION['iUserID']."'";

        $rsPendingEmail = RunQuery($sSQL);
        $aRow = mysql_fetch_array($rsPendingEmail);
        extract($aRow);

        if ($count) {
            // There is already a message composed in MySQL
            // Let's check and make sure it has not been sent.
            $sSQL = "SELECT * FROM email_message_pending_emp "
                  . "WHERE emp_usr_id='".$_SESSION['iUserID']."'";

            $rsPendingEmail = RunQuery($sSQL);
            $aRow = mysql_fetch_array($rsPendingEmail);
            extract($aRow);

            if (!($emp_num_sent || $emp_num_left)) {
                // if both are zero the email job has not started.  In this
                // case the user may edit the email and/or change the distribution

                // This user has no email messages stored MySQL

                $sEmailSubject = $_POST['emailsubject'];
                $sEmailMessage = $_POST['emailmessage'];

                if (strlen($sEmailSubject.$sEmailMessage)) {

                    // User has edited a message.  Update MySQL.                
                    $sSQLu = "UPDATE email_message_pending_emp ".
                             "SET emp_subject='".addslashes($sEmailSubject)."',".
                             "    emp_message='".addslashes($sEmailMessage)."' ".
                             "WHERE emp_usr_id='".$_SESSION['iUserID']."'";

                    RunQuery($sSQLu);

                }

                // Retrieve subject and message from MySQL (it might not be in POST)

                $rsPendingEmail = RunQuery($sSQL);
                $aRow = mysql_fetch_array($rsPendingEmail);
                extract($aRow);

                $sEmailSubject = stripslashes($emp_subject);
                $sEmailMessage = stripslashes($emp_message);

                $sEmailForm = "sendoredit";                

            } else {
                // This job has already started.  The user may not change the message
                // or the distribution once emails have actually been sent.
                $sSQL = "SELECT emp_last_sent_time FROM email_message_pending_emp ".
                        "WHERE emp_usr_id='".$_SESSION['iUserID']."'";

                $rsTime = RunQuery($sSQL);
                $aRow = mysql_fetch_array($rsTime);
                extract($aRow);
                
                $tLastAttempt = strtotime($emp_last_attempt_time);
                $tTimeSinceLastAttempt = time() - $tLastAttempt;
                $iWaitTime = 120;   // Number of seconds to wait before you may
                                    // send a new Email

                if ($tTimeSinceLastAttempt > $iWaitTime) {
                    // If it's been more than 2 minutes since the last email
                    // attempt it is resonable to assume that the job failed
                    // due to an error.  
                    if (!($emp_num_sent)) {
                        // No messages have been sent.
                        // In this case the user has two choices.
                        // 1) Start Over (use message and distribution)
                        // 2) Resume (use message and distribution in MySQL)
                        // 3) Abort (wipe this from MySQL)
                        $sEmailForm = 'startoverresumeabort';
                    } else {
                        // Some messages have been sent.
                        // The user may resume or abort
                        $sEmailForm = 'resumeorabort';
                    }
                } else {
                    // The user may be running the email job in another browser window
                    // In this case display statistics on the job. 
                    $sEmailForm = 'viewjobstatus';
                }
            }


        } elseif (isset($email_array)) {

            // This user has no email messages stored MySQL

            $sEmailSubject = $_POST['emailsubject'];
            $sEmailMessage = $_POST['emailmessage'];

            if (strlen($sEmailSubject.$sEmailMessage)) {

                // User has written a message.  Store it in MySQL.
                // Since this is the first time use INSERT instead of UPDATE                
                $sSQL = "INSERT INTO email_message_pending_emp ".
                        "VALUES ('" .$_SESSION['iUserID']. "','0','0','',".
                        "'2000-01-01 00:00:00','','2001-01-01 00:00:00','".
                        addslashes($sEmailSubject). "','".
                        addslashes($sEmailMessage). "')";

                RunQuery($sSQL);

                $sEmailForm = 'sendoredit';


            } else {

                // There is no pending message.  User may compose a new message.
                $sEmailForm = 'compose';

            }
        }

        if ($sEmailForm == 'compose') {

            echo '<form method="post" action="EmailEditor.php">'."\n";

            foreach ($email_array as $email_address) {
                // Add all address except the default
                // avoid sending to this address twice
                if ($email_address != $sToEmailAddress) {
                    echo '<input type="hidden" name="emaillist[]" value="' .
                                                            $email_address . '">';
                }
            }
            // The default address gets the last email
            echo '<input type="hidden" name="emaillist[]" value="'.$sToEmailAddress.'">'."\n";

            echo '<input type="submit" class="icButton" name="submit" '.
                 'value ="'.gettext("Compose Email").'">'."\n</form>";

        } elseif ($sEmailForm == 'sendoredit') {

            //Print the From, To, and Email List with the Subject and Message

            echo "\n</td></tr></table></div>\n";

            echo "<hr>\r\n";
            echo '<p class="MediumText"><b>'.gettext("From:").'</b> "'.$sFromName.'"';
            echo ' &lt;'.$sFromEmailAddress.'&gt;<br>'."\n";
            echo '<b>'.gettext("To (blind):").'</b> '.$bcc_list.'<br>'."\n";

            echo '<b>'.gettext("Subject:").'</b> '.htmlspecialchars($sEmailSubject).'<br>';

            echo '</p><hr><textarea cols="72" rows="20" readonly class="MediumText" ';
            echo 'style="border:0px;">'. htmlspecialchars($sEmailMessage) . '</textarea><br>';
            echo "<hr>\n";

            // Create button to edit this message.
            echo '<div align="center"><table><tr><td>'."\n";
            echo '<form method="post" action="EmailEditor.php">'."\n";

            foreach ($email_array as $email_address) {
                // Add all address except the default
                // avoid sending to this address twice
                if ($email_address != $sToEmailAddress) {
                    echo '<input type="hidden" name="emaillist[]" value="' .
                                                            $email_address . '">';
                }
            }
            // The default address gets the last email
            echo '<input type="hidden" name="emaillist[]" value="'.$sToEmailAddress.'">'."\n";
            echo '<input type="hidden" name="mysql" value="true">'."\n";

            echo '<input type="submit" class="icButton" name="submit" '.
                     'value ="'.gettext("Edit Email").'">'."\n</form>";

            // Create button to send this message
            echo "</td>\n<td>";

            echo '<form method="post" action="EmailSend.php">'."\n";

            foreach ($email_array as $email_address) {
                // Add all address except the default
                // avoid sending to this address twice
                if ($email_address != $sToEmailAddress) {
                    echo '<input type="hidden" name="emaillist[]" value="' .
                                                            $email_address . '">';
                }
            }
            // The default address gets the last email
            echo '<input type="hidden" name="emaillist[]" value="'.$sToEmailAddress.'">'."\n";
            echo '<input type="hidden" name="mysql" value="true">'."\n";

            echo '<input type="submit" class="icButton" name="submit" '.
                     'value ="'.gettext("Send Email").'">'."\n</form>";

        } elseif ($sEmailForm == 'startoverresumeabort') {

            // The user has three choices
            echo "<table>\n<tr><td>";
            echo 'The previous email was not sent to any recipients. You may';
            echo "</td></tr>\n<tr><td>";
            echo '1 Resume (try again using same subject, message, and distribution)';
            echo "</td></tr>\n<tr><td>";
            echo '2 Start Over (abort, but save subject and message)';
            echo "</td></tr>\n<tr><td>";
            echo '3 Abort (discard everything)';
            echo "</td></tr>\n</table>";

            // Create button to resume this job.
            echo '<div align="center"><table><tr><td>'."\n";
            echo '<form method="post" action="EmailSend.php">'."\n";

            echo '<input type="hidden" name="resume" value="true">'."\n";

            echo '<input type="submit" class="icButton" name="submit" '.
                     'value ="'.gettext("Resume").'">'."\n</form>";

            // Create button to start over
            echo "</td>\n<td>";

            echo '<form method="post" action="EmailEditor.php">'."\n";

            foreach ($email_array as $email_address) {
                // Add all address except the default
                // avoid sending to this address twice
                if ($email_address != $sToEmailAddress) {
                    echo '<input type="hidden" name="emaillist[]" value="' .
                                                            $email_address . '">';
                }
            }
            // The default address gets the last email
            echo '<input type="hidden" name="emaillist[]" value="'.$sToEmailAddress.'">'."\n";
            echo '<input type="hidden" name="startover" value="true">'."\n";

            echo '<input type="submit" class="icButton" name="submit" '.
                     'value ="'.gettext("Start Over").'">'."\n</form>";


            // Create button to abort
            echo "</td>\n<td>";

            echo '<form method="post" action="EmailSend.php">'."\n";

            // The default address gets the last email
            echo '<input type="hidden" name="abort" value="true">'."\n";

            echo '<input type="submit" class="icButton" name="submit" '.
                     'value ="'.gettext("Abort").'">'."\n</form>";

        } elseif ($sEmailForm == 'resumeorabort') {

            // The user has three choices
            echo "<table>\n<tr><td>";
            echo 'The previous email was not sent to any recipients. You may';
            echo "</td></tr>\n<tr><td>";
            echo '1 View Log';
            echo "</td></tr>\n<tr><td>";
            echo '2 Resume (try again using same subject, message, and distribution)';
            echo "</td></tr>\n<tr><td>";
            echo '3 Abort (discard everything)';
            echo "</td></tr>\n</table>";

            // Create button to resume this job.
            echo '<div align="center"><table><tr><td>'."\n";
            echo '<form method="post" action="EmailSend.php">'."\n";

            echo '<input type="hidden" name="resume" value="true">'."\n";

            echo '<input type="submit" class="icButton" name="submit" '.
                     'value ="'.gettext("Resume").'">'."\n</form>";

            // Create button to abort
            echo "</td>\n<td>";

            echo '<form method="post" action="EmailSend.php">'."\n";

            // The default address gets the last email
            echo '<input type="hidden" name="abort" value="true">'."\n";

            echo '<input type="submit" class="icButton" name="submit" '.
                     'value ="'.gettext("Abort").'">'."\n</form>";


        } else  { // ($sEmailForm == 'viewjobstatus')
            //echo '<br>job status form goes here<br>';
            echo "<br><br>";
            echo "It has been $tTimeSinceLastAttempt seconds since the last email ";
            echo "was attempted<br>\n";
            echo "$iWaitTime seconds must elapse before sending another email.<br>\n";

            $iComeBack = $iWaitTime - $tTimeSinceLastAttempt;

            echo "Refresh this page in $iComeBack seconds.<br>\n";

        }
 
        echo "</td></tr></table></div>\n";
    }
}

require "Include/Footer.php";
?>
