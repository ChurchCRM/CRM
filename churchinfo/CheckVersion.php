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
require 'Include/Config.php';
require 'Include/Functions.php';

// Set the current version of this PHP file
// Important!  These must be updated before every software release.

$_SESSION['sChurchInfoPHPVersion'] = '1.2.7';
$_SESSION['sChurchInfoPHPDate'] = '2007-01-01';

// Check if the table version_ver exists.  If the table does not exist then
// SQL scripts must be manually run to get the database up to version 1.2.7
$bVersionTableExists = FALSE;
if(mysql_num_rows(RunQuery("SHOW TABLES LIKE 'version_ver'")) == 1) {
    $bVersionTableExists = TRUE;
}

// Let's see if the MySQL version matches the PHP version.  If we have a match then
// proceed to Menu.php.  Otherwise further error checking is needed.
if ($bVersionTableExists) {
    $sSQL = 'SELECT * FROM version_ver ORDER BY ver_ID DESC';
    $aRow = mysql_fetch_array(RunQuery($sSQL));
    extract($aRow);

    if ($ver_version == $_SESSION['sChurchInfoPHPVersion']) {
        Redirect('Menu.php');
        exit;
    }
}

// Turn ON output buffering
ob_start();

// Set the page title
$sPageTitle = gettext('ChurchInfo: Database Version Check');

?><!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">

<head>
	<meta http-equiv="pragma" content="no-cache">
    <meta http-equiv="Content-Type" content="text/html;charset=utf-8">
	<link rel="stylesheet" type="text/css" href="Include/Style.css">
	<title><?php echo $sPageTitle; ?></title>
</head>
<body>
<table>
    <tr>
        <td>
            <table>
                <tr>
                    <td>
<?php


if(!$bVersionTableExists) {
    // Display message indicating that the ChurchInfo database must be updated to version
    // 1.2.7 using SQL scripts

    echo    'Error: Please update your ChurchInfo MySQL database to version 1.2.7 '
    .       'before using version 1.2.7 (or later) of PHP code.<br>';
    echo    'Your database and PHP code are out of sync.  ChurchInfo is in an untested '
    .       'state and may not be stable. ';

    require 'Include/Footer.php';
    exit;
}

// This code is ready to go for automatically updating from 1.2.7 to 1.3.0
// whenever that may happen
if ($ver_version == '1.2.7') {
    $sUpdateFile = 'AutoSQL'.DIRECTORY_SEPARATOR.'Update1.2.7To1.3.0.sql';

    if ((file_exists($sUpdateFile) && is_readable($sUpdateFile))) {
        $sSQL = file_get_contents($sUpdateFile);
        RunQuery($sSQL, FALSE); // FALSE means do not stop on error
        $sError = mysql_error();
    } else {
        $sSQL = 'Could not access file '.$sUpdateFile;
        $sError = $sSQL;
    }

    if ($sError) {
        echo '<br>MySQL error while upgrading database:<br>'.$sError."<br><br>\n";

        echo '<br><br>You are seeing this message because you have encountered software a bug.'
        .    '<br>Please post to the ChurchInfo '
        .       '<a href="http://sourceforge.net/forum/forum.php?forum_id=401180"> help forum</a> '
        .       'for assistance. The complete query is shown below.<br>'."\n";

        echo "<br>$sSQL<br>\n";

        echo '<br>ChurchInfo MySQL Version = ' . $ver_version;
        echo '<br>ChurchInfo PHP Version = ' . $_SESSION['sChurchInfoPHPVersion'];

    } else {

        echo '<br>Database schema has been updated from 1.2.7 to 1.3.0.<br>'
        .    '<BR>Please <a href="CheckVersion.php">click here</a> to continue.';

    }

    require 'Include/Footer.php';
    exit;
}


// We should not get to the bottom of this file.  We only get here if there is a bug.

echo    'There is an incompatibility between database schema and PHP script.  You are seeing '
.       'this message because there is a software bug.'
.       '<BR>Please post to the ChurchInfo '
.       '<a href="http://sourceforge.net/forum/forum.php?forum_id=401180"> Help forum</a> '
.       'for assistance. ';

echo    '<BR>ChurchInfo MySQL Version = ' . $ver_version;
echo    '<BR>ChurchInfo PHP Version = ' . $_SESSION['sChurchInfoPHPVersion'];

require 'Include/Footer.php';

?>
