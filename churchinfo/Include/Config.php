<?php
/*******************************************************************************
 *
 *  filename    : Include/Config.php
 *  last change : 2003-09-30
 *  description : global configuration
 *
 *  http://www.churchinfo.org/
 *  Copyright 2001-2003 Phillip Hullquist, Deane Barker, Chris Gebhardt
 *
 *  InfoCentral is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

// Database connection constants
$sSERVERNAME = "localhost";
$sUSER = "mikewilt_demo";
$sPASSWORD = "solstice";
$sDATABASE = "mikewilt_demo";

$sWEBCALENDARDB = "mikewilt_cal";

// Ports on which the web server may run.  Defaults are fine for most people.
$aHTTPports = "80,8000,8080";
$aHTTPSports = "443";

// Root path of your InfoCentral installation ( THIS MUST BE SET CORRECTLY! )
// For example, if you will be accessing from http://www.yourdomain.com/web/churchinfo
//  then you would enter "/web/churchinfo" here.  This path SHOULD NOT end with slash.
$sRootPath = "/churchinfo";

// Set debug mode
// This may be helpful for when you're first setting up InfoCentral, but you should
// probably turn it off for maximum security otherwise.  If you are having trouble,
// please enable this so that you'll know what the errors are.  This is especially
// important if you need to report a problem on the help forums.
$debug = true;

//
// Location of libraries that InfoCentral needs for certain features:
//
// If these libraries are already in your PHP include_path, you can ignore these
// settings.  The defaults are relative paths from your InfoCentral directory,
// however, absolute paths may also be specified.  Paths SHOULD NOT end with slash.

// JPGraph library
$sJPGRAPH_PATH = "Include/jpgraph-1.13/src";

// FPDF library
$sFPDF_PATH = "Include/fpdf";

// phpmailer library
$sPHPMAILER_PATH = "Include/phpmailer";


// Directory report configuration
// Include only these classifications in the directory, comma seperated
$sDirClassifications = "1,2,4,5";
// These are the family role numbers designated as head of house
$sDirRoleHead = "1,7";
// These are the family role numbers designated as spouse
$sDirRoleSpouse = "2";
// These are the family role numbers designated as child
$sDirRoleChild = "3";


//
// Session parameters
//

// Session timeout length in seconds.  Default 3600 = 1 hour
// Set to zero to disable session timeouts.
$sSessionTimeout = 3600;

// Restricted queries
// This is a temporary solution until more comprehensive user permissions are developed

// Queries for which user must have finance permissions to use:
$aFinanceQueries = "28";

// Should only administrators have access to the CSV export system and directory report?
// NOTE: While this does not provide any true security, it may discourage casual users from
// exporting and downloading the whole person info database if they are not allowed to.
$bCSVAdminOnly = true;

//
// Password-related settings.
// NOTE: InfoCentral passwords are case insensitive
//

// Default password for new users and those with reset passwords
$sDefault_Pass = "defaultpassword";

// Minimum length a user may set their password to.
$sMinPasswordLength = 6;

// Minimum amount that a new password must differ from the old one (# of characters changed)
// Set to zero to disable this feature.
$sMinPasswordChange = 4;

// A comma-seperated list of disallowed (too obvious) passwords.
$sDisallowedPasswords = "churchinfo,password,god,jesus,church,christian";

// Maximum number of failed logins to allow before a user account is locked.
// Once the maximum has been reached, an administrator must re-enable the account.
// This feature helps to protect against automated password guessing attacks.
// Set to zero to disable this feature.
$iMaxFailedLogins = 50;

//
// Interface Options
//

// Turn on or off guided help (Tool Tips).
// This feature is not complete.  Leave off for now.
$bToolTipsOn = false;

// Interface navigation method
// 1 = Javascript MenuBar (default),  2 = Flat Sidebar (alternative for buggy browsers)
$iNavMethod = 1;

// Show family member firstnames in Family Listing?
$bFamListFirstNames = true;

// PDF handling mode.  1 = Save File dialog, 2 = Open in current browser window
$iPDFOutputType = 1;

//
// Menu defaults
// NOTE: sDefaultState must be your state's 2-letter abbreviation, not name!
//

$sDefaultCity = "";
$sDefaultState = "PA";
$sDefaultCountry = "United States";

// Email information. Only set these if $bEmailSend is true
//
// If you wish to be able to send emails from within InfoCentral. This requires
// either an SMTP server address to send from or sendmail installed in PHP
$bEmailSend = true;
// The method for sending email. Put "smtp" for SMTP server, "sendmail" for
// Sendmail.
$sSendType = "smtp";

// The email address that shows up in the "From:" field
$sFromEmailAddress = "myFromEmailAddress";
// The name that shows up on email address
$sFromName = "Mailing List";
// Default account for receiving a copy of all emails
$sToEmailAddress  = "myReceiveEmailAddress";
// SMTP Address
$sSMTPHost = "mySMTPHostName";
// Does your SMTP server require auththentication (username/password)?
$sSMTPAuth = true;
// SMTP Username
$sSMTPUser = "mySMTPUserName";
// SMTP Password
$sSMTPPass = "mySMTPPassword";
// Word Wrap point. Default for most email programs is 72
$sWordWrap = "72";

// Are you using any non-standards-compliant "broken" browsers at this installation?
// If so, enabling this will turn off the CSS tags that make the menubar stay
// at the top of the screen instead of scrolling with the rest of the page.
// It will also turn off the use of nice, alpha-blended PNG images, which IE still
// does not properly handle.
//
// NOTICE: MS Internet Explorer is currently not standards-compliant enough for
// these purposes.  Please use a quality web browser such as Netscape 7, Mozilla,
// or KDE's Konqueror instead.  This option will disappear if MS ever fixes their
// browser.
//
$bDefectiveBrowser = true;

// Unavailable person info inherited from assigned family for display?
//
// This option causes certain info from a person's assigned family record to be
// displayed IF the corresponding info has NOT been entered for that person.  For
// example, if the family has a home phone number entered but a family member does
// not, that family member's person view page will show the family home phone number.
// This makes sense for most churches, but not some other non-profit orgs.
//
$bShowFamilyData = true;

// Change which version of the vCard standard is used.  By default vCard 3.0 is used.
// Some software does not support the newer 3.0 version, in which case you can set
// this to true to use vCard 2.1 instead.  (Palm Desktop is one such application)
//
$bOldVCardVersion = false;

//
// Backup system settings.
//
// NOTES:
// 1.) "sPROGRAMname" variables are the names of these programs on your system.
// Include the full path to these executables if they are not in your system
// path.  If you do not have one of these programs installed, comment out the
// appropriate line.
//
// 2.) This backup system only works on "UNIX-style" operating systems such as
// GNU/Linux, OSX and the BSD variants.  If you are still using Windows, you should
// probably disable this feature until you wise up and make the switch. (-:
// Of course, remember that only your web server needs to running a UNIX-style
// OS for this feature to work.
//
$bEnableBackupUtility = true;
$sGZIPname = "gzip";
$sZIPname = "zip";
$sPGPname = "gpg";

// Internationalization (I18n) support
// Right now, InfoCentral supports US English (en_US), Italian (it_IT), French (fr_FR), and German (de_DE)
$sLanguage = 'en_US';

$iFYMonth = 6; // Fiscal year runs June-May.  Pledges, payments, and canvass data are associated with the fiscal year

//
// SETTINGS END HERE.  DO NOT MODIFY BELOW THIS LINE
//

putenv("LANG=$sLanguage");
setlocale(LC_ALL, $sLanguage);

// Get numeric and monetary locale settings.
$aLocaleInfo = localeconv();

// This is needed to avoid some bugs in various libraries like fpdf.
setlocale(LC_NUMERIC, 'C');

// patch some missing data for Italian.  This shouldn't be necessary!
if ($sLanguage == "it_IT")
{
	$aLocaleInfo["thousands_sep"] = ".";
	$aLocaleInfo["frac_digits"] = "2";
}

if (function_exists('bindtextdomain'))
{
	$domain = 'messages';

	$sLocaleDir = "locale";
	if (!is_dir($sLocaleDir))
		$sLocaleDir = "../" . $sLocaleDir;

	bindtextdomain($domain, $sLocaleDir);
	textdomain($domain);
}
else
{
	if ($sLanguage != 'en_US')
	{
		// PHP array version of the l18n strings
		$sLocaleMessages = "locale/" . $sLanguage . "/LC_MESSAGES/messages.php";

		if (!is_readable($sLocaleMessages))
			$sLocaleMessages = "../" . $sLocaleMessages;

		require ($sLocaleMessages);

		// replacement implementation of gettext for broken installs
		function gettext($text)
		{
			global $locale;

			if (!empty($locale[$text]))
				return $locale[$text];
			else
				return $text;
		}
	}
	else
	{
		// dummy gettext function
		function gettext($text)
		{
			return $text;
		}
	}

	function _($text) { return gettext($text); }
}

?>
