<?php
/*******************************************************************************
 *
 *  filename    : /Include/Initialize.php
 *  last change : 2003-01-07
 *  website     : http://www.infocentral.org
 *  copyright   : Copyright 2001-2003 Deane Barker, Chris Gebhardt
 *  description : This is a minimal script initialization common to InfoCentral
 *              : Scripts which do not need Functions.php can use this instead
 *
 *  InfoCentral is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

// Set error reporting
if ($debug == true)
	Error_reporting ( E_ALL ^ E_NOTICE);
else
	error_reporting(0);

// Establish the database connection
$cnInfoCentral = mysql_connect($sSERVERNAME,$sUSER,$sPASSWORD);
mysql_select_db($sDATABASE);

// Basic security: If the UserID isn't set (no session), redirect to the login page
if (!isset($_SESSION['iUserID']))
{
	Redirect("Default.php");
	exit;
}

// Check for login timeout.  If login has expired, redirect to login page
if ($sSessionTimeout > 0)
{
	if ((time() - $_SESSION['tLastOperation']) > $sSessionTimeout) {
		Redirect("Default.php?timeout");
		exit;
	} else {
		$_SESSION['tLastOperation'] = time();
	}
}

// If this user needs to change password, send to that page
if ($_SESSION['bNeedPasswordChange'] && !isset($bNoPasswordRedirect))
{
	Redirect("UserPasswordChange.php?PersonID=" . $_SESSION['iUserID']);
	exit;
}

// Convert a relative URL into an absolute URL and redirect the browser there.
function Redirect($sRelativeURL)
{
	global $sRootPath;

	if (!$_SESSION['bSecureServer'])
	{
		$sProtocol = "http://";
		if ($_SESSION['iServerPort'] != 80)
			$sPort = ":" . $_SESSION['iServerPort'];
		else
			$sPort = "";
	}
	else
	{
		$sProtocol = "https://";
		if ($_SESSION['iServerPort'] != 443)
			$sPort = ":" . $_SESSION['iServerPort'];
		else
			$sPort = "";
	}

	header("Location: " . $sProtocol . $_SERVER['HTTP_HOST'] . $sPort . $sRootPath . "/" . $sRelativeURL);
}

function RunQuery($sSQL, $bStopOnError = true)
{
	global $cnInfoCentral;
	global $debug;

	if ($result = mysql_query($sSQL, $cnInfoCentral))
		return $result;
	elseif ($bStopOnError)
	{
		if ($debug)
			die(gettext("Cannot execute query.") . "<p>$sSQL<p>" . mysql_error());
		else
			die("Database error or invalid data");
	}
}

?>
