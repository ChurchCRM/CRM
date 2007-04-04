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
*  2006-2007 Ed Davis
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

// The log files are useful when debugging email problems.  In particular, problems
// with SMTP servers.
$bEmailLog = FALSE;

// Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

$iUserID = $_SESSION['iUserID']; // Read into local variable for faster access

// Security: Both global and user permissions needed to send email.
// Otherwise, re-direct them to the main menu.
if (!($bEmailSend && $bSendPHPMail))
{
	Redirect('Menu.php');
	exit;
}

// Keep a detailed log of events in MySQL.
function ClearEmailLog()
{
    $iUserID = $_SESSION['iUserID'];

    // Drop the table and create new empty table
    $sSQL = 'DROP TABLE IF EXISTS email_job_log_'.$iUserID;
    RunQuery($sSQL);

    $sMessage = 'Log Created at '.date('Y-m-d H:i:s');

    $tSystem = gettimeofday();

    $tSec = $tSystem['sec'];
    $tUsec = str_pad($tSystem['usec'], 6, '0');

    $sSQL = "CREATE TABLE IF NOT EXISTS email_job_log_$iUserID ( ".
            " ejl_id mediumint(9) unsigned NOT NULL auto_increment, ".
            " ejl_time varchar(20) NOT NULL DEFAULT '', ".
            " ejl_usec varchar(6) NOT NULL DEFAULT '', ".
            " ejl_text text NOT NULL DEFAULT '', PRIMARY KEY (ejl_id) ".
            ") TYPE=MyISAM";
    RunQuery($sSQL);

    $sSQL = "INSERT INTO email_job_log_$iUserID ". 
            "SET ejl_text='".mysql_real_escape_string($sMessage)."', ". 
            "    ejl_time='$tSec', ".
            "    ejl_usec='$tUsec'";

    RunQuery($sSQL);
}

function AddToEmailLog($sMessage, $iUserID)
{
    $tSystem = gettimeofday();

    $tSec = $tSystem['sec'];
    $tUsec = str_pad($tSystem['usec'], 6, '0');

    $sSQL = "INSERT INTO email_job_log_$iUserID ". 
            "SET ejl_text='".mysql_real_escape_string($sMessage)."', ". 
            "    ejl_time='$tSec', ".
            "    ejl_usec='$tUsec'";

    RunQuery($sSQL);
}

function SendEmail($sSubject, $sMessage, $sRecipient)
{

    global $sSendType;
    global $sFromEmailAddress;
    global $sFromName;
    global $sLangCode;
    global $sLanguagePath;
    global $sSMTPAuth;
    global $sSMTPUser;
    global $sSMTPPass;
    global $sSMTPHost;
    global $sSERVERNAME;
    global $sUSER;
    global $sPASSWORD;
    global $sDATABASE;
	global $sSQL_ERP;
	global $sSQL_EMP;

    $iUserID = $_SESSION['iUserID']; // Retrieve UserID for faster access

    // Store these queries in variables. (called on every loop iteration)
    $sSQLGetEmail = 'SELECT * FROM email_recipient_pending_erp '.
                    "WHERE erp_usr_id='$iUserID' ".
                    'ORDER BY erp_num_attempt, erp_id LIMIT 1';

	// Just run this one ahead of time to get the message subject and body
    $sSQL = 'SELECT * FROM email_message_pending_emp';
    extract(mysql_fetch_array(RunQuery($sSQL)));

    // Keep track of how long this script has been running.  To avoid server 
    // and browser timeouts break out of loop every $sLoopTimeout seconds and 
    // redirect back to EmailSend.php with meta refresh until finished.
    $tStartTime = time();

    $mail = new PHPMailer();

    // Set the language for PHPMailer
    $mail->SetLanguage($sLangCode, $sLanguagePath);
    if($mail->IsError())
        echo 'PHPMailer Error with SetLanguage().  Other errors (if any) may not report.<br>';

    $mail->From = $sFromEmailAddress;	// From email address (User Settings)
    $mail->FromName = $sFromName;		// From name (User Settings)

    if (strtolower($sSendType)=='smtp') {

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
    } else {
        $mail->IsSendmail();                // tell the class to use Sendmail
    }

    $bContinue = TRUE;
    $sLoopTimeout = 30; // Break out of loop if this time is exceeded
    $iMaxAttempts = 3;  // Error out if an email address fails 3 times 
    while ($bContinue) 
    {   // Three ways to get out of this loop
        // 1.  We're finished sending email
        // 2.  Time exceeds $sLoopTimeout
        // 3.  Something strange happens 
        //        (maybe user tries to send from multiple sessions
        //         causing counts and timestamps to 'misbehave' )

        $tTimeStamp = date('Y-m-d H:i:s');

        $mail->Subject = $sSubject;
        $mail->Body = $sMessage;

        if ($sRecipient == 'get_recipients_from_mysql') {
            $rsEmailAddress = RunQuery($sSQLGetEmail); // This query has limit one to pick up one recipient
            $aRow = mysql_fetch_array($rsEmailAddress);
            extract($aRow);
            $mail->AddAddress($erp_email_address);
        } else {
            $erp_email_address = $sRecipient;
            $mail->AddAddress($erp_email_address);
            $bContinue = FALSE; // Just sending one email
        }

        if(!$mail->Send()) {

			// failed- make a note in the log and the recipient record
            if ($sRecipient == 'get_recipients_from_mysql') {

                $sMsg = "Failed sending to: $erp_email_address ";
                $sMsg .= $mail->ErrorInfo;
                echo "$sMsg<br>\n";
                AddToEmailLog($sMsg, $iUserID);

                // Increment the number of attempts for this message
                $erp_num_attempt++;
                $sSQL = 'UPDATE email_recipient_pending_erp '.
                        "SET erp_num_attempt='$erp_num_attempt' ,".
                        "    erp_failed_time='$tTimeStamp' ".
                        "WHERE erp_id='$erp_id'";
                RunQuery($sSQL);

                // Check if we've maxed out retry attempts
                if ($erp_num_attempt < $iMaxAttempts) {
                    echo "Pausing 15 seconds after failure<br>\n";
                    AddToEmailLog('Pausing 15 seconds after failure', $iUserID);
                    sleep(15);  // Delay 15 seconds on failure
                                // The mail server may be having a temporary problem
                } else {
                    $_SESSION['sEmailState'] = 'error';
                    $bContinue = FALSE;
                    $sMsg = 'Too many failures. Giving up. You may try to resume later.';
                    AddToEmailLog($sMsg, $iUserID);
                }

            } else {

                $sMsg = "Failed sending to: $sRecipient ";
                $sMsg .= $mail->ErrorInfo;
                echo "$sMsg<br>\n";
                AddToEmailLog($sMsg, $iUserID);
            }
        } else {

            if ($sRecipient == 'get_recipients_from_mysql') {

                echo "<b>$erp_email_address</b> Sent! <br>\n";

                $sMsg = "Email sent to: $erp_email_address";
                AddToEmailLog($sMsg, $iUserID);

                // Delete this record from the recipient list
                $sSQL = 'DELETE FROM email_recipient_pending_erp '.
                        "WHERE erp_email_address='$erp_email_address'";
                RunQuery($sSQL);
            } else {

                echo "<b>$sRecipient</b> Sent! <br>\n";

                $sMsg = "Email sent to: $erp_email_address";
                AddToEmailLog($sMsg, $iUserID);

            }
        }
        $mail->ClearAddresses();
        $mail->ClearBCCs();

        // Are we done?
        extract(mysql_fetch_array(RunQuery($sSQL_ERP))); // this query counts remaining recipient records

        if (($sRecipient == 'get_recipients_from_mysql') && ($countrecipients == 0)) {
            $bContinue = FALSE;
            $_SESSION['sEmailState'] = 'finish';
            AddToEmailLog('Job Finished', $iUserID);
        }

        if ((time() - $tStartTime) > $sLoopTimeout) {
            // bail out of this loop if we've taken more than $sLoopTimeout seconds.
            // The meta refresh will reload this page so we can pick up where
            // we left off
            $bContinue = FALSE;
        }
    }

    if (strtolower($sSendType) == 'smtp')
        $mail->SmtpClose();

} // end of function SendEmail()


// Create log table if it does not already exist
$bTableExists = FALSE;
if(mysql_num_rows(mysql_query("SHOW TABLES LIKE 'email_job_log_$iUserID'")) == 1 ) {
    $bTableExists = TRUE;
}

if (!$bTableExists) {
    // Create a new empty log, this might be cruft
    ClearEmailLog();  
}

if ($_POST['resume'] == 'true') {
    // If we are resuming skip the 'start' state and go straight to 'continue'
    $_SESSION['sEmailState'] = 'continue';

    $sMsg = 'Email job resumed at '.date('Y-m-d H:i:s');
    AddToEmailLog($sMsg, $iUserID);

    // Clear the number of attempts
    $sSQL = 'UPDATE email_recipient_pending_erp '.
            "SET erp_num_attempt='0' ".
            "WHERE erp_usr_id='$iUserID'";

    RunQuery($sSQL);
}

if ($_POST['abort'] == 'true') {
    // If user chooses to abort the print job be sure to erase all evidence and
    // Redirect to main menu

    $sSQL = 'DROP TABLE IF EXISTS email_job_log_'.$iUserID;
    RunQuery($sSQL);

    // Delete message from emp
    $sSQL = 'DELETE FROM email_message_pending_emp '.
            "WHERE emp_usr_id='$iUserID'";
    RunQuery($sSQL);

    // Delete recipients from erp
    $sSQL = 'DELETE FROM email_recipient_pending_erp '.
            "WHERE erp_usr_id='$iUserID'";
    RunQuery($sSQL);

    Redirect('Menu.php?abortemail=true');
}

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

$bMetaRefresh = FALSE; // Assume page does not need refreshing


$sSQL = 'SELECT COUNT(emp_usr_id) as emp_count FROM email_message_pending_emp '.
        "WHERE emp_usr_id='$iUserID'";

extract(mysql_fetch_array(RunQuery($sSQL)));
if (!$emp_count) {
    // Can't load this page unless user has a pending message
    Redirect('Menu.php');
}

// Check if this is the first time we are attempting to send this email.
$sSQL_ERP = 'SELECT COUNT(erp_id) as countrecipients FROM email_recipient_pending_erp '.
            "WHERE erp_usr_id='$iUserID'";
extract(mysql_fetch_array(RunQuery($sSQL_ERP))); // this query counts remaining recipient records

$sSQL_EMP = 'SELECT * FROM email_message_pending_emp '.
            "WHERE emp_usr_id='$iUserID'";
extract(mysql_fetch_array(RunQuery($sSQL_EMP)));

if ($emp_to_send==0 && $countrecipients==0) {
    // If both are zero the email job has not started yet.  
    // Begin by loading the list of recipients into MySQL.
    ClearEmailLog();  // Initialize Log
    $_SESSION['sEmailState'] = 'start';
    $iEmailNum = 0;
    $email_array = $_POST['emaillist'];

    if ( !is_array($email_array) ) {

        $sMsg = 'Error, cannot start. email_array is not an array';
        echo "<br>$sMsg<br>";
        AddToEmailLog($sMsg, $iUserID); 
        $_SESSION['sEmailState'] = 'error';
    }

    if ( !count($email_array) ) {

        $sMsg = 'Error, cannot start. email_array is empty';
        echo "<br>$sMsg<br>";
        AddToEmailLog($sMsg, $iUserID); 
        $_SESSION['sEmailState'] = 'error'; 
    }

    if ($_SESSION['sEmailState'] == 'start') {

        foreach($email_array as $email_address) {
    
            $iEmailNum++;
            // Load MySQL with the list of addresses to be sent
            $sSQL = 'INSERT INTO email_recipient_pending_erp '.
                    "SET erp_id='$iEmailNum',erp_usr_id='$iUserID',erp_num_attempt='0',erp_email_address='".
                    mysql_real_escape_string($email_address)."'";

            RunQuery($sSQL);
        }

        // Since no emails have been sent the number remaining to be
        // sent is the total just counted

        $sSQL = 'UPDATE email_message_pending_emp '.
                "SET emp_to_send='$iEmailNum' ".
                "WHERE emp_usr_id='$iUserID'";
        RunQuery($sSQL);
		$countrecipients = $iEmailNum;
        AddToEmailLog('Job Started', $iUserID); // Initialize the log
    }

} elseif ($countrecipients) {

    if (!($_POST['viewlog'] == 'true')) {

        $sMsg = 'Job continuing after page reload at '.date('Y-m-d H:i:s');
        AddToEmailLog($sMsg, $iUserID);

        if ($_SESSION['sEmailState'] != 'continue') {

            if ($_SESSION['sEmailState'] != 'error') {
                $sMsg = 'Error on line '.__LINE__.' of file '.__FILE__;
                AddToEmailLog($sMsg, $iUserID);
                $_SESSION['sEmailState'] = 'error';
            }
        }
    }

} else {
    
    // Should only get here if we are about to finish by sending the final email
    if ($_SESSION['sEmailState'] != 'finish') {
        $sMsg = 'Error on line '.__LINE__.' of file '.__FILE__;
        AddToEmailLog($sMsg, $iUserID);
        $_SESSION['sEmailState'] = 'error';
    }
}

// Decide if we want this page to reload again
$sEmailState = $_SESSION['sEmailState'];
if ($sEmailState == 'start') {
    $bMetaRefresh = TRUE;
} elseif ($sEmailState == 'continue') {
    $bMetaRefresh = TRUE;
} elseif ($sEmailState == 'finish') {
    // Send the final email (don't load page again)
    $bMetaRefresh = FALSE;
} else {
    // Don't load page again.
    // We are either in the error state or some other unknown state.
    $_SESSION['sEmailState'] = 'error';
    $bMetaRefresh = FALSE;
}

// Set a Meta Refresh in the header so this page automatically reloads
if ($bMetaRefresh) { 
    $sMetaRefresh = '<meta http-equiv="refresh" content="2;URL=EmailSend.php">'."\n";
}

// Set the page title and include HTML header
$sPageTitle = gettext('Email Sent');
require 'Include/Header.php';

if(!$bPHPMAILER_Installed) {
    echo    '<br>' . gettext('ERROR: PHPMailer is not properly installed on this server.')
    .       '<br>' . gettext('PHPMailer is required in order to send emails from this server.');

    echo '<br><br>include_path = ' . ini_get('include_path');

    if ($bHavePHPMailerClass)
        echo '<br><br>Found: ' . $sFoundPHPMailerClass;
    else
        echo '<br><br>Unable to find file: class.phpmailer.php';


    if ($bHaveSMTPClass)
        echo '<br>Found: ' . $sFoundSMTPClass;
    else
        echo '<br>Unable to find file: class.smtp.php';

    if ($bHavePHPMailerLanguage)
        echo '<br>Found: ' . $sFoundLanguageFile;
    else
        echo "<br>Unable to find file: phpmailer.lang-$sLangCode.php";

    exit;
}

$tTimeStamp = date('m/d H:i:s');

$sEmailState = $_SESSION['sEmailState'];

if ($sEmailState == 'continue') {

    // continue sending email
    $sSubject = $emp_subject;
    $sMessage = $emp_message;

    // There must be more than one recipient
    if ($countrecipients) {

        echo '<br>Please be patient. Job is not finished.<br><br>';
        echo '<b>Please allow up to 60 seconds for page to reload.</b><br><br>';

        SendEmail($sSubject, $sMessage, 'get_recipients_from_mysql');

    } else {
        $_SESSION['sEmailState'] = 'finish';
    }

} elseif ($sEmailState == 'start') {

    // send start message

    if ($countrecipients) {

        $sSubject = "Email job started at $tTimeStamp";

        $sMessage = "Email job issued by ";
        $sMessage .= $_SESSION['UserFirstName'].' '.$_SESSION['UserLastName'];
        $sMessage .= " using:\n";
        $sMessage .= "From Name = $sFromName\n";
        $sMessage .= "From Address = $sFromEmailAddress\n";

        $sMessage .= "Email job started at $tTimeStamp\n\n";
        $sMessage .= "Upon successful completion a log will be sent to $sFromEmailAddress";
        $sMessage .= "\n\n";

        $sMessage .= "Job will attempt to send email to the following $countrecipients addresses ";
        $sMessage .= "in this order";
        $sMessage .= "\n\n";

        $sSQL = "SELECT * FROM email_recipient_pending_erp ".
                "WHERE erp_usr_id='$iUserID' ".
                "ORDER BY erp_id";

        $rsERP = RunQuery($sSQL);
        while ($aRow = mysql_fetch_array($rsERP)) {
            extract($aRow);
            $sMessage .= $erp_email_address."\n";
        }

        $sMessage .= "\n\nEnd of Listing\n\n";

        if ($bEmailLog) {
            $sMsg = "Attempting to email job start notification to $sFromEmailAddress";
            echo $sMsg.'<br><br>';
            AddToEmailLog($sMsg, $iUserID);

            SendEmail($sSubject, $sMessage, $sFromEmailAddress);
        }

        $_SESSION['sEmailState'] = 'continue';

        echo '<br><br>Please be patient. Job will begin when page reloads.<br><br>';
        echo '<b>Please allow up to 60 seconds for page to reload.</b><br>';

    } else {

        $_SESSION['sEmailState'] = 'error';
        $sSubject = "Email job aborted at $tTimeStamp";
        AddToEmailLog('Error: Attempted to send to empty distribution list', $iUserID);

    }

} elseif ($sEmailState == 'finish') {

    $sSubject = "Email job finished at $tTimeStamp";

    $sMessage = "Email job issued by ";
    $sMessage .= $_SESSION['UserFirstName'].' '.$_SESSION['UserLastName'];
    $sMessage .= " using:\n";
    $sMessage .= "From Name = $sFromName\n";
    $sMessage .= "From Address = $sFromEmailAddress\n";

    $sSQL = "SELECT * FROM email_message_pending_emp ".
            "WHERE emp_usr_id='$iUserID'";
    extract(mysql_fetch_array(RunQuery($sSQL)));

    $sMessage .= "Email sent to $emp_num_sent email addresses.\n";
    $sMessage .= "Email job finished at $tTimeStamp\n\n";
    $sMessage .= "Email job log:\n\n";

    $sSQL = "SELECT * FROM email_job_log_$iUserID ".
            "ORDER BY ejl_id";

    $sHTMLLog = '<br><br><div align="center"><table>';

    $rsEJL = RunQuery($sSQL);
    while ($aRow = mysql_fetch_array($rsEJL)) {
        extract($aRow);

        $sTime = date('i:s', intval($ejl_time)).'.';
        $sTime .= substr($ejl_usec,0,3);
        $sMsg = $ejl_text;
        $sMessage .= $sTime.' '.$sMsg."\n";
        $sHTMLLog .= "<tr><td>$sTime</td><td>$sMsg</td></tr>\n";
    }
    $sHTMLLog .= '</table></div>';

    if ($bEmailLog) {
        $sMsg = "Attempting to email log to $sFromEmailAddress\n";
        echo $sMsg.'<br>';
        AddToEmailLog($sMsg, $iUserID);

        // Send end message
        SendEmail($sSubject, $sMessage, $sFromEmailAddress);
    }
    echo "<br><b>The job is finished!</b><br>\n";

    echo $sHTMLLog;

    // Delete message from emp
    $sSQL = "DELETE FROM email_message_pending_emp ".
            "WHERE emp_usr_id='$iUserID'";
    RunQuery($sSQL);

    // Delete recipients from erp (not really needed, this should have already happened)
    // (no harm in trying again)
    $sSQL = "DELETE FROM email_recipient_pending_erp ".
            "WHERE erp_usr_id='$iUserID'";
    RunQuery($sSQL);

    // Drop the table that was used to store the log
    // $sSQL = "DROP TABLE IF EXISTS email_job_log_".$iUserID;
    // RunQuery($sSQL);
    // Don't do this, it will be dropped before this person sends the next email

} elseif ($sEmailState == 'error') {

    // we're having trouble sending email.  Terminate, but leave
    // message and distribution list in MySQL

    if (!($_POST['viewlog'] == 'true')) { // Don't add log entry if user is viewing the log
        $sMsg = 'Job terminating due to error. You may try to resume later.';
        AddToEmailLog($sMsg, $iUserID);
    }

    echo "Job terminated due to error.  Please review log for further information.<br>\n";

    $sSubject = "Email job terminated due to error at $tTimeStamp";

    $sMessage = "Email job issued by ";
    $sMessage .= $_SESSION['UserFirstName'].' '.$_SESSION['UserLastName'];
    $sMessage .= " using:\n";
    $sMessage .= "From Name = $sFromName\n";
    $sMessage .= "From Address = $sFromEmailAddress\n";

    $sSQL = "SELECT * FROM email_message_pending_emp ".
            "WHERE emp_usr_id='$iUserID'";
    extract(mysql_fetch_array(RunQuery($sSQL)));

    $sMessage .= "Email sent to $emp_num_sent email addresses.\n";
    $sMessage .= "Email job terminated at $tTimeStamp\n\n";
    $sMessage .= "Email job log:\n\n";


    $sSQL = "SELECT * FROM email_job_log_$iUserID ORDER BY ejl_id";

    $sHTMLLog = '<br><br><div align="center"><table>';

    $rsEJL = RunQuery($sSQL);
    while ($aRow = mysql_fetch_array($rsEJL)) {
        extract($aRow);

        $sTime = date('i:s', intval($ejl_time)).'.';
        $sTime .= substr($ejl_usec,0,3);
        $sMsg = $ejl_text;
        $sMessage .= $sTime.' '.$sMsg."\n";
        $sHTMLLog .= "<tr><td>$sTime</td><td>$sMsg</td></tr>\n";
    }

    $sHTMLLog .= '</table></div>';

    echo $sHTMLLog;

    $sMsg = "Attempting to email log to $sFromEmailAddress\n";
    echo $sMsg.'<br>';
    SendEmail($sSubject, $sMessage, $sFromEmailAddress);


} else {

    // we're in an undefined state
    // exit this with an error

    $_SESSION['sEmailState'] = 'error';
    AddToEmailLog('Job in undefined state, attempt to save data and exit', $iUserID);

}

require 'Include/Footer.php';
?>
