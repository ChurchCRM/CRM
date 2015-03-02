<?php
/*******************************************************************************
 *
 *  filename    : Default.php
 *  description : login page that checks for correct username and password
 *
 *  http://www.churchdb.org/
 *  Copyright 2001-2002 Phillip Hullquist, Deane Barker,
 *
 *  Updated 2005-03-19 by Everette L Mills: Removed dropdown login box and
 *  added user entered login box
 *
 *  
 *  LICENSE:
 *  (C) Free Software Foundation, Inc.
 *
 *  ChurchInfo is free software; you can redistribute it and/or modify
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
 ******************************************************************************/

// Show disable message if register_globals are turned on.
if (ini_get('register_globals'))
{
    echo "<h3>ChurchInfo will not operate with PHP's register_globals option turned on.<br>";
    echo 'This is for your own protection as the use of this setting could entirely undermine <br>';
    echo 'all security.  You need to either turn off register_globals in your php.ini or else<br>';
    echo 'configure your web server to turn off register_globals for the ChurchInfo directory.</h3>';
    exit;
}

// Include the function library
require 'Include/Config.php';
$bSuppressSessionTests = TRUE;
require 'Include/Functions.php';
// Initialize the variables
$sErrorText = '';

// Is the user requesting to logoff or timed out?
if (isset($_GET["Logoff"]) || isset($_GET['timeout'])) {
    if (!isset($_SESSION['sshowPledges']) || ($_SESSION['sshowPledges'] == ''))
        $_SESSION['sshowPledges'] = 0;
    if (!isset($_SESSION['sshowPayments']) || ($_SESSION['sshowPayments'] == ''))
        $_SESSION['sshowPayments'] = 0;
    if (!isset($_SESSION['bSearchFamily']) || ($_SESSION['bSearchFamily'] == ''))
        $_SESSION['bSearchFamily'] = 0;

   if (!empty($_SESSION['iUserID'])) {
       $sSQL = "UPDATE user_usr SET usr_showPledges = " . $_SESSION['sshowPledges'] .
                     ", usr_showPayments = " . $_SESSION['sshowPayments'] .
                     ", usr_showSince = '" . $_SESSION['sshowSince'] . "'" .
                     ", usr_defaultFY = '" . $_SESSION['idefaultFY'] . "'" .
                     ", usr_currentDeposit = '" . $_SESSION['iCurrentDeposit'] . "'";
        if ($_SESSION['dCalStart'] != '')
            $sSQL .= ", usr_CalStart = '" . $_SESSION['dCalStart'] . "'";
        if ($_SESSION['dCalEnd'] != '')
            $sSQL .= ", usr_CalEnd = '" . $_SESSION['dCalEnd'] . "'";
        if ($_SESSION['dCalNoSchool1'] != '')
            $sSQL .= ", usr_CalNoSchool1 = '" . $_SESSION['dCalNoSchool1'] . "'";
        if ($_SESSION['dCalNoSchool2'] != '')
            $sSQL .= ", usr_CalNoSchool1 = '" . $_SESSION['dCalNoSchool2'] . "'";
        if ($_SESSION['dCalNoSchool3'] != '')
            $sSQL .= ", usr_CalNoSchool1 = '" . $_SESSION['dCalNoSchool3'] . "'";
        if ($_SESSION['dCalNoSchool4'] != '')
            $sSQL .= ", usr_CalNoSchool1 = '" . $_SESSION['dCalNoSchool4'] . "'";
        if ($_SESSION['dCalNoSchool5'] != '')
            $sSQL .= ", usr_CalNoSchool1 = '" . $_SESSION['dCalNoSchool5'] . "'";
        if ($_SESSION['dCalNoSchool6'] != '')
            $sSQL .= ", usr_CalNoSchool1 = '" . $_SESSION['dCalNoSchool6'] . "'";
        if ($_SESSION['dCalNoSchool7'] != '')
            $sSQL .= ", usr_CalNoSchool1 = '" . $_SESSION['dCalNoSchool7'] . "'";
        if ($_SESSION['dCalNoSchool8'] != '')
            $sSQL .= ", usr_CalNoSchool1 = '" . $_SESSION['dCalNoSchool8'] . "'";
        $sSQL .= ", usr_SearchFamily = '" . $_SESSION['bSearchFamily'] . "'" .
                     " WHERE usr_per_ID = " . $_SESSION['iUserID'];
       RunQuery($sSQL);
   }
}

$iUserID = 0;
// Get the UserID out of user name submitted in form results
if (isset($_POST['User']) && $sErrorText == '') {

    // Get the information for the selected user
    $UserName = FilterInput($_POST['User'],'string',32);
    $uSQL = "SELECT usr_per_id FROM user_usr WHERE usr_UserName like '$UserName'";
    $usQueryResult = RunQuery($uSQL);
    $usQueryResultSet = mysql_fetch_array($usQueryResult);
    if ($usQueryResultSet == Null){
        // Set the error text
        $sErrorText = '&nbsp;' . gettext('Invalid login or password');
    }else{
        //Set user Id based on login name provided
        $iUserID = $usQueryResultSet['usr_per_id'];
    }
} else {
    // Nothing submitted yet, must be the first time loading this page.
    // Clear out any old session
    $iUserID = 0;
    $_COOKIE = array();
    $_SESSION = array();
    session_destroy();
}


// Has the form been submitted?
if ($iUserID > 0)
{
    // Get the information for the selected user
    $sSQL = "SELECT * FROM user_usr WHERE usr_per_ID ='$iUserID'";
    extract(mysql_fetch_array(RunQuery($sSQL)));

    $sSQL = "SELECT * FROM person_per WHERE per_ID ='$iUserID'";
    extract(mysql_fetch_array(RunQuery($sSQL)));

    $bPasswordMatch = FALSE;
    // Check the user password
    
    // Note that there are several possible encodings for the password in the database
    $tmp = $_POST['Password'];
    $sPasswordHashMd5 = md5($tmp);
    
    $tmp = $_POST['Password'].$iUserID;
    $sPasswordHash40 = sha1(sha1($tmp).$tmp);
    
    $tmp = $_POST['Password'].$iUserID;
    $sPasswordHashSha256 = hash ("sha256", $tmp);
    
    $bPasswordMatch = ($usr_Password == $sPasswordHashMd5 || $usr_Password == $sPasswordHash40 || $usr_Password == $sPasswordHashSha256);

    if ($bPasswordMatch && $usr_Password != $sPasswordHashSha256) {
    	// Need to make sure this field can handle the additional length before updating the password
    	$sSQL = "ALTER IGNORE TABLE user_usr MODIFY `usr_Password` text NOT NULL default ''";
    	RunQuery($sSQL, TRUE); // TRUE means stop on error
    	
        $sSQL = "UPDATE user_usr SET usr_Password='".$sPasswordHashSha256."' ".
                "WHERE usr_per_ID ='".$iUserID."'";
        RunQuery($sSQL);
    }

    // Block the login if a maximum login failure count has been reached
    if ($iMaxFailedLogins > 0 && $usr_FailedLogins >= $iMaxFailedLogins) {

        $sErrorText = '<br>' . gettext('Too many failed logins: your account has been locked.  Please contact an administrator.');
    }

    // Does the password match?
    elseif (!$bPasswordMatch) {

        // Increment the FailedLogins
        $sSQL = 'UPDATE user_usr SET usr_FailedLogins = usr_FailedLogins + 1 '.
                "WHERE usr_per_ID ='$iUserID'";
        RunQuery($sSQL);

        // Set the error text
        $sErrorText = '&nbsp;' . gettext('Invalid login or password');
    } else {

        // Set the LastLogin and Increment the LoginCount
        $sSQL = "UPDATE user_usr SET usr_LastLogin = NOW(), usr_LoginCount = usr_LoginCount + 1, usr_FailedLogins = 0 WHERE usr_per_ID ='$iUserID'";
        RunQuery($sSQL);

        // Set the User's family id in case EditSelf is enabled
        $_SESSION['iFamID'] = $per_fam_ID;

        // Set the UserID
        $_SESSION['iUserID'] = $usr_per_ID;

        // Set the Actual Name for use in the sidebar
        $_SESSION['UserFirstName'] = $per_FirstName;

        // Set the Actual Name for use in the sidebar
        $_SESSION['UserLastName'] = $per_LastName;

        // Set the pagination Search Limit
        $_SESSION['SearchLimit'] = $usr_SearchLimit;

        // Set the User's email address
        $_SESSION['sEmailAddress'] = $per_Email;

        // If user has administrator privilege, override other settings and enable all permissions.
        if ($usr_Admin) {
            $_SESSION['bAddRecords'] = true;
            $_SESSION['bEditRecords'] = true;
            $_SESSION['bDeleteRecords'] = true;
            $_SESSION['bMenuOptions'] = true;
            $_SESSION['bManageGroups'] = true;
            $_SESSION['bFinance'] = true;
            $_SESSION['bNotes'] = true;
            $_SESSION['bCommunication'] = true;
            $_SESSION['bCanvasser'] = true;
            $_SESSION['bAdmin'] = true;
        }

        // Otherwise, set the individual permissions.
        else {
            // Set the Add permission
            $_SESSION['bAddRecords'] = $usr_AddRecords;

            // Set the Edit permission
            $_SESSION['bEditRecords'] = $usr_EditRecords;

            // Set the Delete permission
            $_SESSION['bDeleteRecords'] = $usr_DeleteRecords;

            // Set the Menu Option permission
            $_SESSION['bMenuOptions'] = $usr_MenuOptions;

            // Set the ManageGroups permission
            $_SESSION['bManageGroups'] = $usr_ManageGroups;

            // Set the Donations and Finance permission
            $_SESSION['bFinance'] = $usr_Finance;

            // Set the Notes permission
            $_SESSION['bNotes'] = $usr_Notes;

            // Set the Communications permission
            $_SESSION['bCommunication'] = $usr_Communication;

            // Set the EditSelf permission
            $_SESSION['bEditSelf'] = $usr_EditSelf;

            // Set the Canvasser permission
            $_SESSION['bCanvasser'] = $usr_Canvasser;

            // Set the Admin permission
            $_SESSION['bAdmin'] = false;
        }

        // Set the FailedLogins
        $_SESSION['iFailedLogins'] = $usr_FailedLogins;

        // Set the LoginCount
        $_SESSION['iLoginCount'] = $usr_LoginCount;

        // Set the Last Login
        $_SESSION['dLastLogin'] = $usr_LastLogin;

        // Set the Workspace Width
        $_SESSION['iWorkspaceWidth'] = $usr_Workspacewidth;

        // Set the Base Font Size
        $_SESSION['iBaseFontSize'] = $usr_BaseFontSize;

        // Set the Style Sheet
        $_SESSION['sStyle'] = $usr_Style;

        // Create the Cart
        $_SESSION['aPeopleCart'] = array();

        // Create the variable for the Global Message
        $_SESSION['sGlobalMessage'] = '';

        // Set whether or not we need a password change
        $_SESSION['bNeedPasswordChange'] = $usr_NeedPasswordChange;

        // Initialize the last operation time
        $_SESSION['tLastOperation'] = time();

        // Set the Root Path ... used in basic security check
        $_SESSION['sRootPath'] = $sRootPath;

        // Set the URL Path
        $_SESSION['sURLPath'] = $_POST['sURLPath'];

        // If PHP's magic quotes setting is turned off, we want to use a workaround to ensure security.
        if (function_exists('get_magic_quotes_gpc'))
            $_SESSION['bHasMagicQuotes'] = get_magic_quotes_gpc();
        else
            $_SESSION['bHasMagicQuotes'] = 0;


        // Pledge and payment preferences
        $_SESSION['sshowPledges'] = $usr_showPledges;
        $_SESSION['sshowPayments'] = $usr_showPayments;
        $_SESSION['sshowSince'] = $usr_showSince;
        $_SESSION['idefaultFY'] = CurrentFY(); // Improve the chance of getting the correct fiscal year assigned to new transactions
        $_SESSION['iCurrentDeposit'] = $usr_currentDeposit;

        // Church school calendar preferences
        $_SESSION['dCalStart'] = $usr_CalStart;
        $_SESSION['dCalEnd'] = $usr_CalEnd;
        $_SESSION['dCalNoSchool1'] = $usr_CalNoSchool1;
        $_SESSION['dCalNoSchool2'] = $usr_CalNoSchool2;
        $_SESSION['dCalNoSchool3'] = $usr_CalNoSchool3;
        $_SESSION['dCalNoSchool4'] = $usr_CalNoSchool4;
        $_SESSION['dCalNoSchool5'] = $usr_CalNoSchool5;
        $_SESSION['dCalNoSchool6'] = $usr_CalNoSchool6;
        $_SESSION['dCalNoSchool7'] = $usr_CalNoSchool7;
        $_SESSION['dCalNoSchool8'] = $usr_CalNoSchool8;

        // Search preference
        $_SESSION['bSearchFamily'] = $usr_SearchFamily;

        if (isset($bEnableMRBS) && $bEnableMRBS) {
		   	$_SESSION["UserName"] = $UserName; // set the session variable recognized by MRBS
		   	// Update the MRBS user record to match this ChurchInfo user
		   	$iMRBSLevel = 0;
		   	if ($usr_AddRecords)
		   		$iMRBSLevel = 1;
        	if ($usr_Admin)
		   		$iMRBSLevel = 2;
            $sSQL = "INSERT INTO mrbs_users (id, level, name, email) VALUES ('$iUserID', '$iMRBSLevel', '$UserName', '$per_Email') ON DUPLICATE KEY UPDATE level='$iMRBSLevel', name='$UserName',email='$per_Email'";
        	RunQuery($sSQL);
        }
        
        if (isset($bEnableWebCalendar) && $bEnableWebCalendar) {
        	$sAdmin = ($usr_Admin ? 'Y' : 'N');
		    $GLOBALS['login'] = $UserName;
		    $GLOBALS['firstname'] = $per_FirstName;
    		$GLOBALS['lastname'] = $per_LastName;
    		$GLOBALS['is_admin'] = $sAdmin;
    		$GLOBALS['email'] = $per_Email;
		    $GLOBALS['fullname'] = "$per_FirstName $per_LastName";
		    $GLOBALS['enabled'] = 1;
		    
		    $_SESSION['webcal_login'] = $UserName;
		    
        	$sSQL = "INSERT INTO webcal_user (cal_login, cal_firstname, cal_lastname, cal_is_admin, cal_email) VALUES ('$UserName', '". mysql_real_escape_string ($per_FirstName)." ', '".mysql_real_escape_string ($per_LastName)."', '$sAdmin', '$per_Email') ON DUPLICATE KEY UPDATE cal_login='$UserName', cal_firstname='".mysql_real_escape_string ($per_FirstName)."', cal_lastname='".mysql_real_escape_string ($per_LastName)."',cal_is_admin='$sAdmin', cal_email='$per_Email'";
        	RunQuery($sSQL);
        }
        
        // Redirect to the Menu
        Redirect('CheckVersion.php');
        exit;
    }
}
// Turn ON output buffering
ob_start();

// Set the page title and include HTML header
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
   <meta name="viewport" content="width=device-width, initial-scale=1.0, minimal-ui"/>
   <meta http-equiv="pragma" content="no-cache">
     <meta http-equiv="Content-Type" content="text/html;charset=utf-8">
   <link rel="stylesheet" type="text/css" href="Include/Style.css">
   <title><?php echo gettext('ChurchInfo: Login'); ?></title>
</head>
<body>
<?php
// Show the login screen if the URL protocol and path have been
// returned by the browser in a query string

    if (empty($_GET['Proto']) || empty($_GET['Path'])) {
        echo '

<script language="javascript" type="text/javascript">
    error_page1="http://www.churchdb.org";
    error_page2="http://www.churchdb.org";
    if(window.location.href.indexOf(":") == 5) {
        v_Proto="https";
        v_Path=window.location.href.substring(8);
    } else if (window.location.href.indexOf(":") == 4) {
        v_Proto="http";
        v_Path=window.location.href.substring(7);
    } else {
        window.location=error_page1;
    }
    v_index=v_Path.toLowerCase().indexOf("default.php")-1;
    if(v_index < 0) {
        window.location=error_page2;
    }
    v_Path=v_Path.substring(0,v_index);
    v_Path=encodeURIComponent(v_Path);
    v_QueryString="Proto="+v_Proto+"&Path="+v_Path;
    if(window.location.href.indexOf("?") < 0 ) {
        window.location=window.location.href+"?"+v_QueryString;
    } else {
        window.location=window.location.href+"&"+v_QueryString;
    }
        
    </script>';

    }
?>

<table width="80%" border="0" cellpadding="5" cellspacing="0" align="center">
<tr>
    <td valign="top">
        <br>
        <p class="PageTitle"><?php echo gettext('Please Login'); ?></p>
<?php
    if (isset($_GET['Proto']) && isset($_GET['Path'])) {
?>
        <form method="post" name="LoginForm" 
        <?php echo "action=\"Default.php?Proto=".$_GET['Proto'].
        "&amp;Path=".rawurlencode($_GET['Path'])."\"" ?> >
        <table border="0" align="center" cellpadding="5">
        <?php if (isset($_GET['timeout'])) { ?>
        <tr>
            <td align="center" colspan="2">
            <span style="color:red; font-size:120%;">Your previous session timed out.  Please login again.</span>
            </td>
        </tr> <?php } ?>

        <?php if (isset($sErrorText) <> '') { ?>
        <tr>
            <td align="center" colspan="2">
            <span style="color:red;" id="PasswordError"><?php echo $sErrorText; ?></span>
            </td>
        </tr><?php } ?>
        <tr>
            <td class="LabelColumn"><?php echo gettext('Enter your user name:'); ?></td>
            <td class="TextColumnWithBottomBorder">
                <input type="text" id="UserBox" name="User" size="10">

            </td>
        </tr>
        <tr>
            <td class="LabelColumn"><?php echo gettext('Enter your password:'); ?></td>
            <td class="TextColumnWithBottomBorder">
                <input type="password" id="PasswordBox" name="Password" size="10">
            </td>
        </tr>
        <tr>
            <td colspan="2" align="center">
            <input type="submit" class="icButton" name="LogonSubmit" 
             value="<?php echo gettext('Login'); ?>"></td><td>
            <input type="hidden" name="sURLPath" 
             value="<?php echo $_GET['Proto'] . "://" . $_GET['Path'] ?>"></td>
        </tr>
        </table>
        </form>
<?php
    }
?>
    </td>
</tr>
</table>

<script language="JavaScript" type="text/JavaScript">
    document.LoginForm.User.focus();
</script>
<?php
//
// Basic sercurity checks:
//
// Check if https is required:
// Verify that page has an authorized URL in the browser address bar.
// Otherwise redirect to login page.
// An array of authorized URL's is specified in Config.php ... $URL
    if (isset($bLockURL) && ($bLockURL === TRUE)) {
        echo '
    <script language="javascript" type="text/javascript">
        v_test="FAIL"'; // Set "FAIL" to assume the URL is not allowed
                        // Set "PASS" if we learn it is allowed
        foreach ($URL as $value) { // Default.php is 11 characters
            $value = substr($value, 0, -11);
            echo '
        if(window.location.href.indexOf("'.$value.'") == 0) v_test="PASS";';
        }
        echo '
        if (v_test == "FAIL") window.location="'.$URL[0].'";
    </script>';
    }
// End of basic security checks

?>

</body>
</html>

<?php
// Turn OFF output buffering
ob_end_flush();
?>
