<?php
/*******************************************************************************
*
*  filename    : Include/Config.php
*  website     : http://www.churchdb.org
*  description : global configuration
*
*  http://www.churchdb.org/
*  Copyright 2001-2005 Phillip Hullquist, Deane Barker, Chris Gebhardt, 
*                      Michael Wilt, Timothy Dearborn
*
*
*  Additional Contributors:
*  2006 Ed Davis
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


// Database connection constants
$sSERVERNAME = "localhost";
$sUSER = "churchinfo";
$sPASSWORD = "churchinfo";
$sDATABASE = "churchinfo";

// Root path of your ChurchInfo installation ( THIS MUST BE SET CORRECTLY! )
// For example, if you will be accessing from http://www.yourdomain.com/web/churchinfo
// then you would enter "/web/churchinfo" here.  This path SHOULD NOT end with slash.
$sRootPath="/churchinfo";

// If you are using a non-standard port number you may need to include the 
// port number in the URL.  Default value is fine for most installations.
$sPort="";

// You can enforce https access by setting this true.
$bHTTPSOnly=FALSE;

// When using a shared SSL certificate provided by your webhost for https access
// you may need to add the shared SSL server name to the URL.  Default value is fine
// for most installations
$sSharedSSLServer="";

// When using a shared SSL certificate your webhost may also require you to use a 
// modified version of your hostname.  Default value is fine for most installations.
$sHTTP_Host=$_SERVER['HTTP_HOST'];

// Some webhosts implement shared SSL differently.  ChurchInfo currently
// works with the following implementation of shared SSL hosting.
//
// Let's say your "normal" http access looks like this:
// http://www.mydomain.org/churchinfo/Default.php
//
// Now let's say that access via your webhosts shared SSL certificate looks like this:
// https://ssl.secureaccess.net/ssl.mydomain.org/churchinfo/Default.php
//
// Here are the settings to implement the above example
// $sSharedSSLServer="ssl.secureaccess.net";
// $sHTTP_Host="ssl.mydomain.org";
//
// If your webhost implements shared SSL differently you may need to modify the
// source code to work with another implementation of shared SSL.  The only
// code that should need to be modified is the function RedirectURL() in
// the file Include/Functions.php.   
// Please post to the sourceforge help forum.  Tell us about your changes to
// get ChurchInfo to work with another implementation of shared SSL and we'll 
// try to add it to the next release.

//
// SETTINGS END HERE.  DO NOT MODIFY BELOW THIS LINE
//

// Establish the database connection
$cnInfoCentral = mysql_connect($sSERVERNAME,$sUSER,$sPASSWORD) 
        or die ('Cannot connect to the MySQL server because: ' . mysql_error());

mysql_select_db($sDATABASE) 
        or die ('Cannot select the MySQL database because: ' . mysql_error());

// Read values from config table into local variables
// **************************************************
$sSQL = "SELECT cfg_name, IFNULL(cfg_value, cfg_default) AS value FROM config_cfg WHERE cfg_section='General'";
$rsConfig = mysql_query($sSQL);			// Can't use RunQuery -- not defined yet
if ($rsConfig) {
    while (list($cfg_name, $cfg_value) = mysql_fetch_row($rsConfig)) {
        $$cfg_name = $cfg_value;
    }
}

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
