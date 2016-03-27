<?php
/*******************************************************************************
 *
 *  filename    : CSVExport.php
 *  description : options for creating csv file
 *
 *  http://www.churchcrm.io/
 *  Copyright 2001-2002 Phillip Hullquist, Deane Barker
 *
 *  LICENSE:
 *  (C) Free Software Foundation, Inc.
 *
 *  ChurchCRM is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful, but
 *  WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 *  General Public License for mote details.
 *
 *  http://www.gnu.org/licenses
 *
 ******************************************************************************/

// Include the function library
require "Include/Config.php";
require "Include/Functions.php";


// Set the page title and include HTML header
$sPageTitle = gettext("System Settings");
require "Include/Header.php";

?>
<div class="row">
  <div class="col-lg-12">
    <div class="box box-body">
      <ol>
        <li><a href="EditSettings.php?Cat=Step1">Church Information</a></li>
        <li><a href="EditSettings.php?Cat=Step2">User setup</a></li>
        <li><a href="EditSettings.php?Cat=Step3">Email Setup</a></li>
        <li><a href="EditSettings.php?Cat=Step4">Member Setup</a></li>
        <li><a href="EditSettings.php?Cat=Step5">System Settings</a></li>
        <li><a href="EditSettings.php?Cat=Step6">Map Settings</a></li>
        <li><a href="EditSettings.php?Cat=Step7">Report Settings</a></li>
        <li><a href="EditSettings.php?Cat=Step8">Other Settings</a></li>
      </ol>
    </div>
  </div>
</div>
<?php require "Include/Footer.php" ?>
