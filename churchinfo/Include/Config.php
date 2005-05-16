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
$sUSER = "churchinfo";
$sPASSWORD = "churchinfo";
$sDATABASE = "churchinfo";

// Establish the database connection
$cnInfoCentral = mysql_connect($sSERVERNAME,$sUSER,$sPASSWORD);
mysql_select_db($sDATABASE);


// Read values from config table into local variables
// **************************************************
$sSQL = "SELECT cfg_name, IFNULL(cfg_value, cfg_default) AS value FROM config_cfg";
$rsConfig = mysql_query($sSQL);			// Can't use RunQuery -- not defined yet
if ($rsConfig) {
	while (list($cfg_name, $cfg_value) = mysql_fetch_row($rsConfig)) {
		$$cfg_name = $cfg_value;
	}
}
	

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
