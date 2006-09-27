<?php
/*******************************************************************************
*
*  filename     : CheckVersion.php
*  website      : http://www.churchdb.org
*  description  : This file checks that the ChurchInfo MySQL database is in
*                   sync with the PHP code.  
*
*
*  Contributors:
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


// Include the function library
require "Include/Config.php";
require "Include/Functions.php";

// Set the page title
//$sPageTitle = gettext("ChurchInfo Database Version Check");

//require "Include/Header.php";

// Set the current version of this PHP file
// Important!  These must be updated before every software release.
$_SESSION['sChurchInfoPHPVersion'] = "1.2.7";
$_SESSION['sChurchInfoPHPDate'] = "2006-11-01";

// First check if the table version_ver exists.  If the table does not exist then
// old style SQL scripts must be run to get the database up to version 1.2.7

$bTableExists=FALSE;
if(mysql_num_rows(mysql_query("SHOW TABLES LIKE 'version_ver'"))==1)
    $bTableExists=TRUE;

if(!$bTableExists) {
    // Display message indicating that the ChurchInfo database must be updated to version
    // 1.2.7 using SQL scripts

    echo    "Error: Please update your ChurchInfo MySQL database to version 1.2.7 "
    .       "before using version 1.2.7 (or later) of PHP code.<BR>";
    echo    "Your database and PHP code are out of sync.  ChurchInfo is in an untested "
    .       "state and may not be stable. ";

//    require "Include/Footer.php";
    exit;
}

// Let's see if the MySQL version matches the PHP version.  If we have a match then
// proceed to Menu.php.  Otherwise further error checking is needed.

$sSQL  = "SELECT * FROM version_ver ORDER BY ver_ID DESC";
$rsVersion = mysql_query($sSQL);

$aRow = mysql_fetch_array($rsVersion);
extract($aRow);
if ($ver_version == $_SESSION['sChurchInfoPHPVersion']) {
    Redirect("Menu.php");
    exit;
}

// This code will be added when version 1.2.8 is released
//if ($ver_version == "1.2.7") {
//    Redirect("Updates/From1.2.7To1.2.8.php");
//    exit;
//}


// We should not get to the bottom of this file.  We only get here if there is a bug.

echo    'There is an incompatibility between database schema and PHP script.  You are seeing '
.       'this message because there is a bug.'
.       '<BR>Please post to the ChurchInfo '
.       '<a href="http://sourceforge.net/forum/forum.php?forum_id=401180"> Help forum</a> '
.       'for assistance. ';

echo    '<BR>ChurchInfo MySQL Version = ' . $ver_version;
echo    '<BR>ChurchInfo PHP Version = ' . $_SESSION['sChurchInfoPHPVersion'];

?>
