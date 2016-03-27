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
require 'Service/SystemService.php';
$systemService = new SystemService();

//Set the page title
$sPageTitle = gettext("Software Version Check");

if ($systemService->checkDatabaseVersion())  //either the DB is good, or the upgrade was successful.
{
    Redirect('Menu.php');
    exit;
}
else        //the upgrade failed!
{
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
                    Software Database Version = <?= $systemService->getDatabaseVersion(); ?> <br/>
                    Software Version = <?=  $_SESSION['sSoftwareInstalledVersion'] ?>
                </p>
            </div>
        </div>
        </div>
    </div>
</section>
<!-- /.content -->


<?php
}

require 'Include/FooterNotLoggedIn.php';

?>
