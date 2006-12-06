<?php
/*******************************************************************************
*
*  filename    : Include/LoadConfigs.php
*  website     : http://www.churchdb.org
*  description : global configuration 
*                   The code in this file used to be part of part of Config.php
*
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

// Establish the database connection
$cnInfoCentral = mysql_connect($sSERVERNAME,$sUSER,$sPASSWORD) 
        or die ('Cannot connect to the MySQL server because: ' . mysql_error());

mysql_select_db($sDATABASE) 
        or die ('Cannot select the MySQL database because: ' . mysql_error());

// Verify that $sDocumentRoot and $sRootPath are correct
$sTestPath = $sDocumentRoot.$sRootPath.'/Include/Config.php';
$aSeparators = array('\\', '/');
$sTestPath = str_replace ( $aSeparators, DIRECTORY_SEPARATOR, $sTestPath  );
if (!(file_exists($sTestPath) && is_readable($sTestPath))) {
    $sErrorMessage  = "Unable to open file: $sTestPath<br><br>\n";
    $sErrorMessage .= 'Please verify that the following ';
    $sErrorMessage .= "variables are correct in Include/Config.php<br>\n";
    $sErrorMessage .= "\$sDocumentRoot = $sDocumentRoot<br>\n\$sRootPath = $sRootPath";
    $sErrorMessage .= "<br><br>\n";

    $sCorrectPath = dirname(__FILE__).DIRECTORY_SEPARATOR.'Config.php';;

    $sErrorMessage .= "Canonical file path = $sCorrectPath<br><br>\n";

    // we know path ends in /Include/Config.php
    // strip this off since not useful for debugging
    
    $sTestPath = dirname(dirname($sTestPath));
    $sCorrectPath = dirname(dirname($sCorrectPath));

    if ($sTestPath != $sCorrectPath) {
        
        // Try and determine what is wrong and advise the user how to fix this problem.

        // First, check if the $sTestPath ends with $sRootPath or starts with $sDocumentRoot

        $iRootPathLength = strlen($sRootPath);
        $iDocumentRootLength = (strlen($sDocumentRoot));
        $sEndOfTestPath = substr($sCorrectPath,-1*$iRootPathLength,$iRootPathLength);
        $sStartOfTestPath = (substr($sCorrectPath,0,$iDocumentRootLength));

        $bDocumentRoot = ($sStartOfTestPath == $sDocumentRoot);
        $bRootPath = ($sEndOfTestPath == $sRootPath);

        if ($bDocumentRoot && $bRootPath) {
            // Both $bDocumentRoot and 
            // Somehow the *correct* path begins with $sDocumentRoot and ends
            // with $sRootPath.  Hard to imagine this as a failure possibility
            
            $sErrorMessage .= "Path begins with \$sDocumentRoot = $sDocumentRoot<br>\n";
            $sErrorMessage .= "Path ends with \$sRootPath = $sRootPath<br>\n";
            $sErrorMessage .= "Is it even possible to ever see this error message?<br><br>\n";

        } elseif (!$bDocumentRoot && !$bRootPath) {
            // Neither match.  Can't help the user in this situation.
            // Their web host may be accessing the file via a different path
            // using symbolic links.

            $sErrorMessage .= "Your webhost might be accessing Config.php using a ";
            $sErrorMessage .= "symbolic link rather than using the canonical file path.";
            $sErrorMessage .= "<br><br>\n";

        } elseif ($bDocumentRoot && !$bRootPath) {
            // In this situation it appears $sDocumentRoot is correct but $sRootPath is wrong.
            // Advise user of the possible correct value for $sRootPath

            // Since $sDocumentRoot appears to be correct let's strip it from the start of
            // $sCorrectPath

            $sAltRootPath = substr($sCorrectPath,$iDocumentRootLength);

            $sErrorMessage .= "\$sDocumentRoot appears correct<br>\n";
            $sErrorMessage .= "\$sRootPath appears incorrect<br>\n";
            $sErrorMessage .= "Incorrect: \$sRootPath = $sRootPath<br>\n";
            $sErrorMessage .= "Try this: \$sRootPath = $sAltRootPath<br>\n";

        } else {
            // In this situation it appears $sRootPath is correct but $sDocumentRoot is wrong.
            // Advise user of the possible correct value for $sDocumentRoot

            // Since $sRootPath appears to be correct let's strip it from the end of
            // $sCorrectPath

            $iLength = strlen($sCorrectPath)-strlen($sRootPath);
            $sAltDocumentRoot = substr($sCorrectPath,0,$iLength);

            $sErrorMessage .= "\$sRootPath appears correct<br>\n";
            $sErrorMessage .= "\$sDocumentRoot appears incorrect<br>\n";
            $sErrorMessage .= "Incorrect: \$sDocumentRoot = $sDocumentRoot<br>\n";
            $sErrorMessage .= "Try this: \$sDocumentRoot = $sAltDocumentRoot<br>\n";

        }


    } else {
        $sErrorMessage .= "It appears you entered the correct information.<br>\n";
        $sErrorMessage .= "You should not be seeing this error message.<br>\n";

    }


    die ($sErrorMessage);
}

// Initialize the session
session_start();

// Read values from config table into local variables
// **************************************************
$sSQL = "SELECT cfg_name, IFNULL(cfg_value, cfg_default) AS value "
      . "FROM config_cfg WHERE cfg_section='General'";
$rsConfig = mysql_query($sSQL);			// Can't use RunQuery -- not defined yet
if ($rsConfig) {
    while (list($cfg_name, $value) = mysql_fetch_row($rsConfig)) {
        $$cfg_name = $value;
    }
}

if (isset($_SESSION['iUserID'])) {      // Not set on Default.php
    // Load user variables from user config table.
    // **************************************************
    $sSQL = "SELECT ucfg_name, ucfg_value AS value "
          . "FROM userconfig_ucfg WHERE ucfg_per_ID='".$_SESSION['iUserID']."'";
    $rsConfig = mysql_query($sSQL);     // Can't use RunQuery -- not defined yet
    if ($rsConfig) {
        while (list($ucfg_name, $value) = mysql_fetch_row($rsConfig)) {
            $$ucfg_name = $value;
        }
    }
}

$sMetaRefresh = '';  // Initialize to empty

putenv("LANG=$sLanguage");
setlocale(LC_ALL, $sLanguage);

// Get numeric and monetary locale settings.
$aLocaleInfo = localeconv();

// This is needed to avoid some bugs in various libraries like fpdf.
setlocale(LC_NUMERIC, 'C');

// patch some missing data for Italian.  This shouldn't be necessary!
if ($sLanguage == 'it_IT')
{
    $aLocaleInfo['thousands_sep'] = '.';
    $aLocaleInfo['frac_digits'] = '2';
}

if (function_exists('bindtextdomain'))
{
    $domain = 'messages';

    $sLocaleDir = 'locale';
    if (!is_dir($sLocaleDir))
        $sLocaleDir = '../' . $sLocaleDir;

    bindtextdomain($domain, $sLocaleDir);
    textdomain($domain);
}
else
{
    if ($sLanguage != 'en_US')
    {
        // PHP array version of the l18n strings
        $sLocaleMessages = "locale/$sLanguage/LC_MESSAGES/messages.php";

        if (!is_readable($sLocaleMessages))
            $sLocaleMessages = "../$sLocaleMessages";

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
