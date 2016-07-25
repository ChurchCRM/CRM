<?php
/*******************************************************************************
*
*  filename    : CartView.php
*  website     : http://www.churchcrm.io
*
*  Copyright 2001-2003 Phillip Hullquist, Deane Barker, Chris Gebhardt
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
require "Include/LabelFunctions.php";
require "Include/PersonFunctions.php";

if (isset($_POST["rmEmail"]))
{
     rmEmail();
}

// Set the page title and include HTML header
$sPageTitle = gettext("View Your Cart");
require "Include/Header.php"; ?>
<div class="box box-body">
<?php
// Confirmation message that people where added to Event from Cart
if (array_key_exists('aPeopleCart', $_SESSION) && count($_SESSION['aPeopleCart']) == 0) {
        if (!array_key_exists("Message", $_GET)) { ?>
             <p class="text-center callout callout-warning"><?= gettext("You have no items in your cart.") ?> </p>
        <?php } else {
            switch ($_GET["Message"]) {
                case "aMessage": ?>
                    <p class="text-center callout callout-info"><?= $_GET["iCount"].' '.($_GET["iCount"] == 1 ? "Record":"Records").' Emptied into Event ID:'.$_GET["iEID"]  ?> </p>
                <?php break;
            }
        }
        echo '<p align="center"><input type="button" name="Exit" class="btn btn-primary" value="'.gettext("Back to Menu").'" '."onclick=\"javascript:document.location='Menu.php';\"></p>\n";
        echo '</div>';
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

        if ($iNumPersons > 16) { ?>
        <form method="get" action="CartView.php#GenerateLabels">
        <input type="submit" class="btn" name="gotolabels"
        value="<?= gettext("Go To Labels") ?>">
        </form>
        <?php } ?>
                    
     <!-- BEGIN CART FUNCTIONS -->          
     
     
<?php if (count($_SESSION['aPeopleCart']) > 0) { ?>
<div class="box">
    <div class="box-header with-border">
        <h3 class="box-title">Cart Functions</h3>
    </div>
    <div class="box-body">
        <a href="CartView.php?Action=EmptyCart" class="btn btn-app"><i class="fa fa-trash"></i><?= gettext("Empty Cart") ?></a>
        <?php if ($_SESSION['bManageGroups']) { ?>
            <a href="CartToGroup.php" class="btn btn-app"><i class="fa fa-object-ungroup"></i><?= gettext("Empty Cart to Group") ?></a>
        <?php } ?>
        <?php if ($_SESSION['bAddRecords']) { ?>
            <a href="CartToFamily.php" class="btn btn-app"><i class="fa fa-users"></i><?= gettext("Empty Cart to Family") ?></a>
        <?php } ?>
        <a href="CartToEvent.php" class="btn btn-app"><i class="fa fa-ticket"></i><?=  gettext("Empty Cart to Event") ?></a>

        <?php  if ($bExportCSV) { ?>
            <a href="CSVExport.php?Source=cart" class="btn btn-app"><i class="fa fa-file-excel-o"></i><?=  gettext("CSV Export") ?></a>
        <?php } ?>
        <a href="MapUsingGoogle.php?GroupID=0" class="btn btn-app"><i class="fa fa-map-marker"></i><?= gettext("Map Cart") ?></a>
        <a href="Reports/NameTags.php?labeltype=74536&labelfont=times&labelfontsize=36" class="btn btn-app"><i class="fa fa-file-pdf-o"></i><?= gettext("Name Tags") ?></a>
        <?
        if (count($_SESSION['aPeopleCart']) != 0) {


            // Email Cart links
            // Note: This will email entire group, even if a specific role is currently selected.
            $sSQL = "SELECT per_Email, fam_Email
                        FROM person_per
                        LEFT JOIN person2group2role_p2g2r ON per_ID = p2g2r_per_ID
                        LEFT JOIN group_grp ON grp_ID = p2g2r_grp_ID
                        LEFT JOIN family_fam ON per_fam_ID = family_fam.fam_ID
                    WHERE per_ID NOT IN (SELECT per_ID FROM person_per INNER JOIN record2property_r2p ON r2p_record_ID = per_ID INNER JOIN property_pro ON r2p_pro_ID = pro_ID AND pro_Name = 'Do Not Email') AND per_ID IN (" . ConvertCartToString($_SESSION['aPeopleCart']) . ")";
            $rsEmailList = RunQuery($sSQL);
            $sEmailLink = '';
            while (list ($per_Email, $fam_Email) = mysql_fetch_row($rsEmailList))
            {
                $sEmail = SelectWhichInfo($per_Email, $fam_Email, False);
                if ($sEmail)
                {
                    /* if ($sEmailLink) // Don't put delimiter before first email
                        $sEmailLink .= $sMailtoDelimiter; */
                    // Add email only if email address is not already in string
                    if (!stristr($sEmailLink, $sEmail))
                        $sEmailLink .= $sEmail .= $sMailtoDelimiter;
                }
            }
            if ($sEmailLink)
            {
                // Add default email if default email has been set and is not already in string
                if ($sToEmailAddress != '' && $sToEmailAddress != 'myReceiveEmailAddress'
                                           && !stristr($sEmailLink, $sToEmailAddress))
                    $sEmailLink .= $sMailtoDelimiter . $sToEmailAddress;
                $sEmailLink = urlencode($sEmailLink);  // Mailto should comply with RFC 2368

                if ($bEmailMailto) { // Does user have permission to email groups
                // Display link
                echo "<a href='mailto:" . mb_substr($sEmailLink,0,-3) . "' class='btn btn-app'><i class='fa fa-send-o'></i>". gettext("Email Cart") . "</a>";
                echo "<a href='mailto:?bcc=". mb_substr($sEmailLink,0,-3) ."' class='btn btn-app'><i class='fa fa-send'></i>".gettext("Email (BCC)")."</a>";
                }
            }

            //Text Cart Link
            $sSQL = "SELECT per_CellPhone, fam_CellPhone FROM person_per LEFT JOIN family_fam ON person_per.per_fam_ID = family_fam.fam_ID WHERE per_ID NOT IN (SELECT per_ID FROM person_per INNER JOIN record2property_r2p ON r2p_record_ID = per_ID INNER JOIN property_pro ON r2p_pro_ID = pro_ID AND pro_Name = 'Do Not SMS') AND per_ID IN (" . ConvertCartToString($_SESSION['aPeopleCart']) . ")";
            $rsPhoneList = RunQuery($sSQL);
            $sPhoneLink = "";
            $sCommaDelimiter = ', ';

            while (list ($per_CellPhone, $fam_CellPhone) = mysql_fetch_row($rsPhoneList))
            {
                $sPhone = SelectWhichInfo($per_CellPhone, $fam_CellPhone, False);
                if ($sPhone)
                {
                    /* if ($sPhoneLink) // Don't put delimiter before first phone
                        $sPhoneLink .= $sCommaDelimiter;  */
                    // Add phone only if phone is not already in string
                    if (!stristr($sPhoneLink, $sPhone))
                        $sPhoneLink .= $sPhone .= $sCommaDelimiter;
                }
            }
            if ($sPhoneLink)
            {
                if ($bEmailMailto) { // Does user have permission to email groups

                // Display link
                echo '<a href="javascript:void(0)" onclick="allPhonesCommaD()" class="btn btn-app"><i class="fa fa-mobile-phone"></i>Text Cart';
                echo '<script>function allPhonesCommaD() {prompt("Press CTRL + C to copy all group members\' phone numbers", "'. mb_substr($sPhoneLink,0,-2) .'")};</script>';
                }
            }

?>
        <a href="DirectoryReports.php?cartdir=Cart+Directory" class="btn btn-app"><i class="fa fa-book"></i><?= gettext("Create Directory From Cart") ?></a>

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
    </div>
    <!-- /.box-body -->
</div>
<!-- /.box -->
<?php } ?>
<!-- Default box -->
<div class="box">
    <div class="box-header with-border">
        <h3 class="box-title"><?= gettext("Generate Labels") ?></h3>
    </div>
    <div class="box-body">
    <form method="get" action="Reports/PDFLabel.php" name="labelform">
        <table class="table table-responsive">
                <?php
                LabelGroupSelect("groupbymode");

                echo '  <tr><td>' . gettext("Bulk Mail Presort") . '</td>';
                echo '  <td>';
                echo '  <input name="bulkmailpresort" type="checkbox" onclick="codename()"';
                echo '  id="BulkMailPresort" value="1" ';
                if (array_key_exists("buildmailpresort", $_COOKIE) && $_COOKIE["bulkmailpresort"])
                    echo "checked";
                echo '  ><br></td></tr>';

                echo '  <tr><td>' . gettext("Quiet Presort") . '</td>';
                echo '  <td>';
                echo '  <input ';
                if (array_key_exists("buildmailpresort", $_COOKIE) && !$_COOKIE["bulkmailpresort"])
                    echo 'disabled ';   // This would be better with $_SESSION variable
                                        // instead of cookie ... (save $_SESSION in MySQL)
                echo 'name="bulkmailquiet" type="checkbox" onclick="codename()"';
                echo '  id="QuietBulkMail" value="1" ';
                if (array_key_exists("bulkmailquiet", $_COOKIE) && $_COOKIE["bulkmailquiet"] && array_key_exists("buildmailpresort", $_COOKIE) && $_COOKIE["bulkmailpresort"])
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
                        <td><input type="submit" class="btn btn-primary" value="<?= gettext("Generate Labels") ?>" name="Submit"></td>
                </tr>
    </table></form></td></tr></table>
    </div>
    <!-- /.box-body -->
</div>


<?php
    if (($bEmailSend) && ($bSendPHPMail)) {
        if (isset($email_array)) {
            $bcc_list = "";
            foreach ($email_array as $email_address) {
                // Add all address except the default
                // avoid sending to this address twice
                if ($email_address != $sToEmailAddress) {
                    $bcc_list .= $email_address . ", ";
                }
            }
            if ($sToEmailAddress) {
                // append $sToEmailAddress
                $bcc_list .= $sToEmailAddress;
            } else {
                // remove the last ", "
                $bcc_list = substr($bcc_list, 0, strlen($bcc_list) - 2 );
            }
        }

        $sEmailForm = ""; // Initialize to empty

        ?><div align="center"><table class="table"><tr><td align="center"><?php
        echo "<br><h2>" . gettext("Send Email To People in Cart") . "</h2>";
 
        // Check if there are pending emails that have not been delivered
        // A user cannot send a new email until the previous email has been sent
    
        $sSQL  = "SELECT COUNT(emp_usr_id) as countjobs "
               . "FROM email_message_pending_emp "
               . "WHERE emp_usr_id='".$_SESSION['iUserID']."'";

        $rsPendingEmail = RunQuery($sSQL);
        $aRow = mysql_fetch_array($rsPendingEmail);
        extract($aRow);

        $sSQL  = "SELECT COUNT(erp_usr_id) as countrecipients "
               . "FROM email_recipient_pending_erp "
               . "WHERE erp_usr_id='".$_SESSION['iUserID']."'";
        $rsCountRecipients = RunQuery($sSQL);
        $aRow = mysql_fetch_array($rsCountRecipients);
        extract($aRow);

        if ($countjobs) {
            // There is already a message composed in MySQL
            // Let's check and make sure it has not been sent.
            $sSQL = "SELECT * FROM email_message_pending_emp "
                  . "WHERE emp_usr_id='".$_SESSION['iUserID']."'";

            $rsPendingEmail = RunQuery($sSQL);
            $aRow = mysql_fetch_array($rsPendingEmail);
            extract($aRow);

            if ($emp_to_send == 0 && $countrecipients == 0) {
                // if both are zero the email job has not started.  In this
                // case the user may edit the email and/or change the distribution

                // This user has no email messages stored MySQL

                $sEmailSubject = "";
                if (array_key_exists ('emailsubject', $_POST))
                	$sEmailSubject = stripslashes($_POST['emailsubject']);
                $sEmailMessage = "";
                if (array_key_exists ('emailmessage', $_POST))
	                $sEmailMessage = stripslashes($_POST['emailmessage']);
                $hasAttach = 0;
                $attachName = "";

                if (array_key_exists ('Attach', $_FILES)) {
	        		$attachName = $_FILES['Attach']['name'];
	        		$hasAttach = 1;
                	move_uploaded_file ($_FILES['Attach']['tmp_name'], "tmp_attach/".$attachName);
		        }
                
                if (strlen($sEmailSubject.$sEmailMessage)) {

                    // User has edited a message.  Update MySQL.                
                    $sSQLu = "UPDATE email_message_pending_emp ".
                             "SET emp_subject='".mysql_real_escape_string($sEmailSubject)."',".
                             "    emp_message='".mysql_real_escape_string($sEmailMessage)."', ".
                             "    emp_attach_name='".$attachName."',".
                             "    emp_attach='".$hasAttach."' ".
                             "WHERE emp_usr_id='".$_SESSION['iUserID']."'";

                    RunQuery($sSQLu);

                } else {

                    // Retrieve subject and message from MySQL

                    $rsPendingEmail = RunQuery($sSQL);
                    $aRow = mysql_fetch_array($rsPendingEmail);
                    extract($aRow);

                    $sEmailSubject = $emp_subject;
                    $sEmailMessage = $emp_message;
                    $attachName = $emp_attach_name;
                }

                $sEmailForm = "sendoredit";                

            } else { 
                // This job has already started.  The user may not change the message
                // or the distribution once emails have actually been sent.
                $sEmailForm = 'resumeorabort';
            }


        } elseif (isset($email_array)) {

            // This user has no email messages stored MySQL
			$sEmailSubject = "";
			$sEmailMessage = "";
			$hasAttach = 0;
			$attachName = "";
        	
			if (array_key_exists ('emailsubject', $_POST))
	            $sEmailSubject = stripslashes($_POST['emailsubject']);
			if (array_key_exists ('emailmessage', $_POST))
	            $sEmailMessage = stripslashes($_POST['emailmessage']);	            
            if (array_key_exists ('Attach', $_FILES)) {
	        	$attachName = $_FILES['Attach']['name'];
	        	$hasAttach = 1;
                move_uploaded_file ($_FILES['Attach']['tmp_name'], "tmp_attach/".$attachName);
	        }

            if (strlen($sEmailSubject.$sEmailMessage)) {

                // User has written a message.  Store it in MySQL.
                // Since this is the first time use INSERT instead of UPDATE                
                $sSQL = "INSERT INTO email_message_pending_emp ".
                        "SET " . 
                            "emp_usr_id='" .$_SESSION['iUserID']. "',".
                            "emp_to_send='0'," .
                            "emp_subject='" . mysql_real_escape_string($sEmailSubject). "',".
                            "emp_message='" . mysql_real_escape_string($sEmailMessage). "',".
                            "emp_attach_name='" .$attachName . "',".
                			"emp_attach='".$hasAttach."'";

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
            if ($sToEmailAddress) { // The default address gets the last email
            echo '<input type="hidden" name="emaillist[]" value="'.$sToEmailAddress.'">'."\n";
            }

            echo '<input type="submit" class="btn" name="submit" '.
                 'value ="'.gettext("Compose Email").'">'."\n</form>";
            
        } elseif ($sEmailForm == 'sendoredit') {

            //Print the From, To, and Email List with the Subject and Message

            echo "\n</td></tr></table></div>\n";

            echo "<hr>\r\n";
            echo '<p class="MediumText"><b>'.gettext("From:").'</b> "'.$sFromName.'"';
            echo ' &lt;'.$sFromEmailAddress.'&gt;<br>'."\n";
            echo '<b>'.gettext("To (blind):").'</b> '.$bcc_list.'<br>'."\n";

            echo '<b>'.gettext("Subject:").'</b> '.htmlspecialchars($sEmailSubject).'<br>';
            
            if (strlen ($attachName) > 0) {
            	echo '<b>'.gettext("Attach file:").'</b> '.htmlspecialchars($attachName).'<br>';
            }

            echo '</p><hr><textarea cols="72" rows="20" readonly class="MediumText" ';
            echo 'style="border:0px;">'. htmlspecialchars($sEmailMessage) . '</textarea><br>';
            echo "<hr>\n";

            // Create button to edit this message.
            echo '<div align="center"><table class="table"><tr><td>'."\n";
            echo '<form method="post" action="EmailEditor.php">'."\n";

            foreach ($email_array as $email_address) {
                // Add all address except the default
                // avoid sending to this address twice
                if ($email_address != $sToEmailAddress) {
                    echo '<input type="hidden" name="emaillist[]" value="' .
                                                            $email_address . '">';
                }
            }
            if ($sToEmailAddress) {  // The default address gets the last email
            echo '<input type="hidden" name="emaillist[]" value="'.$sToEmailAddress.'">'."\n";
            }

            echo '<input type="hidden" name="mysql" value="true">'."\n";

            echo '<input type="submit" class="btn" name="submit" '.
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
            if ($sToEmailAddress) { // The default address gets the last email
            echo '<input type="hidden" name="emaillist[]" value="'.$sToEmailAddress.'">'."\n";
            }

            echo '<input type="hidden" name="mysql" value="true">'."\n";
            echo '<input type="submit" class="btn" name="submit" '.
                     'value ="'.gettext("Send Email").'">'."\n</form>";
            // Create button to Delete this message
            echo "</td>\n<td>";
            echo '<form method="post" action="CartView.php">'."\n";
            echo '<input type="hidden" name="rmEmail" value="true">'."\n";
            echo '<input type="submit" class="btn" name="rmEail" '.
                 'value ="'.gettext("Delete Email").'">'."\n</form>";

        } elseif ($sEmailForm == 'resumeorabort') {

            // The user has two choices
            echo "<table>\n<tr><td>";
            echo 'The previous email did not succesfully complete. You may';
            echo "</td></tr>\n<tr><td>";
            echo '1 Resume at point of failure (no duplicates will be sent)';
            echo "</td></tr>\n<tr><td>";
            echo '2 Abort (discard everything)';
            echo "</td></tr>\n<tr><td>";
            echo '3 View Log';
            echo "</td></tr>\n</table>";

            // Create button to resume this job.
            echo '<div align="center"><table><tr><td>'."\n";
            echo '<form method="post" action="EmailSend.php">'."\n";

            echo '<input type="hidden" name="resume" value="true">'."\n";

            echo '<input type="submit" class="btn" name="submit" '.
                     'value ="'.gettext("Resume").'">'."\n</form>";


            // Create button to abort
            echo "</td>\n<td>";

            echo '<form method="post" action="EmailSend.php">'."\n";

            // The default address gets the last email
            echo '<input type="hidden" name="abort" value="true">'."\n";

            echo '<input type="submit" class="btn" name="submit" '.
                     'value ="'.gettext("Abort").'">'."\n</form>";

            // Create button to view log
            echo "</td>\n<td>";

            echo '<form method="post" action="EmailSend.php">'."\n";

            // The default address gets the last email
            echo '<input type="hidden" name="viewlog" value="true">'."\n";

            echo '<input type="submit" class="btn" name="submit" '.
                     'value ="'.gettext("View Log").'">'."\n</form>";



        } else  { // ($sEmailForm == 'viewjobstatus')
            //echo '<br>job status form goes here<br>';
            echo "<br><br>";
            echo "It has been $tTimeSinceLastAttempt seconds since the last email ";
            echo "was attempted<br>\n";
            echo "$iWaitTime seconds must elapse before sending another email.<br>\n";

            $iComeBack = $iWaitTime - $tTimeSinceLastAttempt;
            echo "Refresh this page in $iComeBack seconds.<br>\n";

            $sSQL = 'SELECT * FROM email_job_log_'.$_SESSION['iUserID'].' '.
                    'ORDER BY ejl_id';

            $rsEJL = RunQuery($sSQL, FALSE); // FALSE means do not stop on error
            $sError = mysql_error();

            if ($sError) {
                echo '<br>'.$sError;
                echo '<br>'.$sSQL;

            } else {
                $sHTMLLog = '<br><br><div align="center"><table>';            
                while ($aRow = mysql_fetch_array($rsEJL)) {
                    extract($aRow);

                    $sTime = date('i:s', intval($ejl_time)).'.';
                    $sTime .= substr($ejl_usec,0,3);
                    $sMsg = stripslashes($ejl_text);
                    $sHTMLLog .= '<tr><td>'.$sTime.'</td><td>'.$sMsg.'</td></tr>'."\n";
                }
                $sHTMLLog .= '</table></div>';
                echo $sHTMLLog;
            }

        }
        echo '<a name="email"></a>'; // anchor used by EmailEditor.php
        echo "</td></tr></table></div>\n";
    }
}


?>
                    
                    
    <!-- END CART FUNCTIONS -->
                    
<!-- BEGIN CART LISTING -->
<div class="box box-primary">
    <div class="box-header with-border">
        <h3 class="box-title"><?= gettext("Your cart contains") . ' ' . $iNumPersons . ' ' . gettext("persons from") . ' ' . $iNumFamilies . ' ' . gettext("families.") ?></h3>
    </div>
    <div class="box-body">
        <table class="table table-hover">
        <tr>
            <th><?= gettext("Name") ?></th>
            <th><?=  gettext("Address?") ?></th>
            <th><?=  gettext("Email?") ?></th>
            <th><?= gettext("Remove") ?></th>
            <th><?=  gettext("Classification") ?></th>
            <th><?=  gettext("Family Role") ?></th>
        </tr>
        <?php
        $sEmailLink = "";
        $iEmailNum = 0;
        $sRowClass = "RowColorA";
        $email_array = array ();

        while ($aRow = mysql_fetch_array($rsCartItems))
        {
                $sRowClass = AlternateRowStyle($sRowClass);

                extract($aRow);

                $sEmail = SelectWhichInfo($per_Email, $fam_Email, False);
                if (strlen($sEmail) == 0 && strlen ($per_WorkEmail) > 0) {
                	$sEmail = $per_WorkEmail;                	
                }
                
                if (strlen($sEmail)) {
                    $sValidEmail = gettext("Yes");
                    if (!stristr($sEmailLink, $sEmail)) {
                        $email_array[] = $sEmail;

                        if ($iEmailNum == 0) {       
                        	// Comma is not needed before first email address
                            $sEmailLink .= $sEmail;
                            $iEmailNum++;
                        } else
                            $sEmailLink .= $sMailtoDelimiter . $sEmail;
                    }
                } else {
                        $sValidEmail = gettext("No");
                }

                $sAddress1 = SelectWhichInfo($per_Address1, $fam_Address1, False);
                $sAddress2 = SelectWhichInfo($per_Address2, $fam_Address2, False);

                if (strlen($sAddress1) > 0 || strlen($sAddress2) > 0)
                        $sValidAddy = gettext("Yes");
                else
                        $sValidAddy = gettext("No");

                echo '<tr class="' . $sRowClass . '">';
                echo '<td><img src="'. $personService->getPhoto($per_ID). '" class="direct-chat-img"> &nbsp <a href="PersonView.php?PersonID=' . $per_ID . '">' . FormatFullName($per_Title, $per_FirstName, $per_MiddleName, $per_LastName, $per_Suffix, 1) . '</a></td>';

                echo '<td align="center">' . $sValidAddy . '</td>';
                echo '<td align="center">' . $sValidEmail . '</td>';
                echo '<td><a href="CartView.php?RemoveFromPeopleCart=' .
                        $per_ID . '">' . gettext("Remove") . '</a></td>';
                echo '<td align="center">' . $aClassificationName[$per_cls_ID] . '</td>';
                echo '<td align="center">' . $aFamilyRoleName[$per_fmr_ID] . '</td>';

                echo "</tr>";
        }

        echo "</table>";
} ?>
<!-- END CART LISTING -->

<?php

require "Include/Footer.php";

function rmEmail()
{
        $iUserID = $_SESSION['iUserID']; // Read into local variable for faster access
        // Delete message from emp
    $sSQL = "DELETE FROM email_message_pending_emp ".
            "WHERE emp_usr_id='$iUserID'";
    RunQuery($sSQL);

    // Delete recipients from erp (not really needed, this should have already happened)
    // (no harm in trying again)
    $sSQL = "DELETE FROM email_recipient_pending_erp ".
            "WHERE erp_usr_id='$iUserID'";
    RunQuery($sSQL);
        echo '<font class="SmallError">Deleted Email message succesfuly</font>';
}
?>
