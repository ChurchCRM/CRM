<?php
/*******************************************************************************
*
*  filename     : CheckVersion.php
*  website      : http://www.churchcrm.io
*  description  : This file checks that the ChurchInfo MySQL database is in
*                   sync with the PHP code.  
*
*
*  Contributors:
*  2006-2007 Ed Davis
*
*
*  Copyright Contributors
*
*  ChurchCRM is free software; you can redistribute it and/or modify
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

//Set the page title
$sPageTitle = gettext("Software Version Check");

// Set the current version of this PHP file
// Important!  These must be updated before every software release.

$_SESSION['sSoftwareInstalledVersion'] = '2.0.0';

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

    if ($ver_version == $_SESSION['sSoftwareInstalledVersion']) {
        Redirect('Menu.php');
        exit;
    }
} else {
    $ver_version = "unknown";
}


// Turn ON output buffering
ob_start();

// Set the page title
$sPageTitle = gettext('ChurchCRM: Database Version Check');

require ("Include/HeaderNotLoggedIn.php");

if($bVersionTableExists) {

    // This code will automatically update from 1.2.14 (last good churchinfo build to 2.0.0 for ChurchCRM
    if (strncmp($ver_version, "1.2.14", 6) == 0) {

        $old_ver_version = $ver_version;
        $sError = 'Initialize';  // Initialize error string

        //TODO upgrade script
    }
}
?>

<!-- Main content -->
<section class="content">
    <div class="row">
        <div class="error-page">
            <h2 class="headline text-red">500</h2>

            <div class="error-content">
                <h3><i class="fa fa-warning text-red"></i> Oops! Something went wrong.</h3>
                <p>
                    We will work on fixing that right away.
                    Meanwhile, you may <a href="http://docs.churchcrm.io" target="_blank">return to docs or try using the search form. </a>
                </p>

            </div>
        </div>
    </div>
    <div class="row">
        <div class="error-page">
        <!-- /.error-page -->
        <div class="box box-danger">
            <div class="box-body">
                <p>
                There is an incompatibility between database schema and installed software. You are seeing this message because there is a software bug or an incomplete upgrade.
                </p>
                <p>
                Please post to the our github <a href="https://github.com/ChurchCRM/CRM/issues" target="_blank"> github issues</a> for assistance.
                </p>
            </div>
            <div class="box-footer">
                <p>
                    Software Database Version = <?= $ver_version; ?> <br/>
                    Software Version = <?=  $_SESSION['sSoftwareInstalledVersion']; ?>
                </p>
            </div>
        </div>
        </div>
    </div>
</section>
<!-- /.content -->


<?

require 'Include/FooterNotLoggedIn.php';

?>
