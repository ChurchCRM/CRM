<?php
/*******************************************************************************
 *
 *  filename    : Default.php
 *  last change : 2003-05-29
 *  description : login page that checks for correct username and password
 *
 *  http://www.infocentral.org/
 *  Copyright 2001-2002 Phillip Hullquist, Deane Barker
 *
 *  InfoCentral is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

// Show disable message if register_globals are turned on.
if (ini_get('register_globals'))
{
	echo "<h3>InfoCentral will not operate with PHP's register_globals option turned on.<BR>";
	echo "This is for your own protection as the use of this setting could entirely undermine <BR>";
	echo "all security.  You need to either turn off register_globals in your php.ini or else<BR>";
	echo "configure your web server to turn off register_globals for the InfoCentral directory.</h3>";
	exit;
}

// Include the function library
require "Include/Config.php";
$bSuppressSessionTests = true;
require "Include/Functions.php";

// Get the UserID out of the form results
if (isset($_POST["User"])) {
	$iUserID = FilterInput($_POST["User"],'int');
} else {
	$iUserID = 0;
}

// Is the user requesting to logoff or timed out?
if (isset($_GET["Logoff"]) || isset($_GET['timeout'])) {
	if ($_SESSION['sshowPledges'] == "")
		$_SESSION['sshowPledges'] = 0;
	if ($_SESSION['sshowPayments'] == "")
		$_SESSION['sshowPayments'] = 0;

	$sSQL = "UPDATE user_usr SET usr_showPledges = " . $_SESSION['sshowPledges'] . 
	              ", usr_showPayments = " . $_SESSION['sshowPayments'] .
				  ", usr_showSince = '" . $_SESSION['sshowSince'] . "'" .
				  ", usr_defaultFY = '" . $_SESSION['idefaultFY'] . "'" .
				  ", usr_currentDeposit = '" . $_SESSION['iCurrentDeposit'] . "'" .
				  " WHERE usr_per_ID = " . $_SESSION['iUserID'];
	RunQuery($sSQL);

	$_COOKIE = array();
	$_SESSION = array();
	session_destroy();
}

// Initialize the variables
$sErrorText = "";

// Has the form been submitted?
if ($iUserID > 0)
{
	// Get the information for the selected user
	$sSQL = "SELECT * FROM user_usr INNER JOIN person_per ON usr_per_ID = per_ID WHERE usr_per_ID = " . $iUserID;
	$rsQueryResult = RunQuery($sSQL);
	extract(mysql_fetch_array($rsQueryResult));

	// Get the user's family id in case edit self is turned on
	$sSQL = "SELECT per_fam_ID FROM person_per WHERE per_ID = " . $iUserID;
	$rsQueryResult = RunQuery($sSQL);
	extract(mysql_fetch_array($rsQueryResult));

	// Block the login if a maximum login failure count has been reached
	if ($iMaxFailedLogins > 0 && $usr_FailedLogins >= $iMaxFailedLogins) {

		$sErrorText = "<BR>" . gettext("Too many failed logins: your account has been locked.  Please contact an administrator.");
	}

	// Does the password match?
	elseif ($usr_Password != md5(strtolower($_POST["Password"]))) {

		// Increment the FailedLogins
		$sSQL = "UPDATE user_usr SET usr_FailedLogins = usr_FailedLogins + 1 WHERE usr_per_ID = " . $iUserID;
		RunQuery($sSQL);

		// Set the error text
		$sErrorText = "&nbsp;" . gettext("Invalid password");

	} else {

		// Set the LastLogin and Increment the LoginCount
		$sSQL = "UPDATE user_usr SET usr_LastLogin = '" . date("Y-m-d H:i:s") . "', usr_LoginCount = usr_LoginCount + 1, usr_FailedLogins = 0 WHERE usr_per_ID = " . $iUserID;
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

			// Set the Admin permission
			$_SESSION['bAdmin'] = false;
		}

		// Set the FailedLogins
		$_SESSION['iFailedLogins'] = $usr_FailedLogins;

		// Set the LoginCount
		$_SESSION['iLoginCount'] = $usr_LoginCount;

		// Set the Last Login
		$_SESSION['dLastLogin'] = ConvertMySQLDate($usr_LastLogin);

		// Set the Workspace Width
		$_SESSION['iWorkspaceWidth'] = $usr_WorkspaceWidth;

		// Set the Base Font Size
		$_SESSION['iBaseFontSize'] = $usr_BaseFontSize;

		// Set the Style Sheet
		$_SESSION['sStyle'] = $usr_Style;

		// Create the Cart
		$_SESSION['aPeopleCart'] = array();

		// Create the variable for the Global Message
		$_SESSION['sGlobalMessage'] = "";

		// Set whether or not we need a password change
		$_SESSION['bNeedPasswordChange'] = $usr_NeedPasswordChange;

		// Initialize the last operation time
		$_SESSION['tLastOperation'] = time();

		$aHTTPports = explode(',', $aHTTPports);
		$aHTTPSports = explode(',', $aHTTPSports);

		if (in_array($_SERVER['SERVER_PORT'],$aHTTPports))
		{
			$_SESSION['bSecureServer'] = false;
		}
		elseif (in_array($_SERVER['SERVER_PORT'],$aHTTPSports))
		{
			$_SESSION['bSecureServer'] = true;
		}
		else
		{
			echo "Invalid server port number.  Check Config.php";
			exit;
		}
		$_SESSION['iServerPort'] = $_SERVER['SERVER_PORT'];

		// If PHP's magic quotes setting is turned off, we want to use a workaround to ensure security.
		$_SESSION['bHasMagicQuotes'] = get_magic_quotes_gpc();

		// Pledge and payment preferences
		$_SESSION['sshowPledges'] = $usr_showPledges;
		$_SESSION['sshowPayments'] = $usr_showPayments;
		$_SESSION['sshowSince'] = $usr_showSince;
		$_SESSION['idefaultFY'] = $usr_defaultFY;
		$_SESSION['iCurrentDeposit'] = $usr_currentDeposit;

		// Redirect to the Menu
		Redirect("Menu.php");
	}
}

// Get all the users in the system
$sSQL = "SELECT per_FirstName, per_LastName, per_Title, per_Suffix, per_MiddleName, user_usr.* FROM user_usr, person_per WHERE per_ID = usr_per_ID ORDER BY per_LastName";
$rsUsers = RunQuery($sSQL);

// Set the page title and include HTML header
?>

<html>
<head>
	<link rel="stylesheet" type="text/css" href="Include/Style.css">
	<title><?php echo gettext("InfoCentral: Login"); ?></title>
</head>
<body>
<table width="80%" border="0" cellpadding="5" cellspacing="0" align="center">
<tr>
	<td valign="top">
		<br>
		<p class="PageTitle"><?php echo gettext("Please Login"); ?></p>
		<form method="post" name="LoginForm" action="Default.php">
		<table border="0" align="center" cellpadding="5">
		<?php if (isset($_GET['timeout'])) { ?> <tr><td align="center" colspan="2"><span style="color:red; font-size:120%;">Your previous session timed out.  Please login again.</span></td></tr> <?php } ?>
		<tr>
			<td class="LabelColumn"><?php echo gettext("Select your Username:"); ?></td>
			<td class="TextColumn">
			<select style="font-family:courier,fixed; font-size:120%; color:#000000;" name="User" onChange="javascript:document.getElementById('PasswordError').innerHTML = '';">
				<option value="0"><?php echo gettext("Choose One"); ?></option>
				<option value="0">--------------------</option>
				<?php

				while ($aRow = mysql_fetch_array($rsUsers)) {

					extract($aRow);

					// Write the <option> tag
					echo "<option style=\"color:#004477;\" value=\"" . $usr_per_ID . "\"";
						if ($iUserID == $usr_per_ID) { echo " selected"; }
					echo ">";

					$userString = FormatFullName($per_Title, $per_FirstName, $per_MiddleName, $per_LastName, $per_Suffix, 0);
					echo $userString;
					for ($iCount = 25 - strlen($userString); $iCount > 0; $iCount--) echo "&nbsp;";
					echo "[" . $usr_per_ID . "]";

					echo "</option>";
				}
				?>
			</select>
			</td>
		</tr>
		<tr>
			<td class="LabelColumn"><?php echo gettext("Enter your password:"); ?></td>
			<td class="TextColumnWithBottomBorder">
				<input type="password" id="LoginBox" name="Password" size="10">
				<span style="color:red;" id="PasswordError"><?php echo $sErrorText; ?></span>
			</td>
		</tr>
		<tr>
			<td colspan="2" align="center"><input type="submit" class="icButton" name="LogonSubmit" value=<?php echo '"' . gettext("Login") . '"'; ?>></td>
		</tr>
		</table>
		</form>
	</td>
</tr>
</table>

<script language="JavaScript">
	document.LoginForm.Password.focus();
</script>

</body>
</html>
