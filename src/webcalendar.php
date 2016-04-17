<?php
/*******************************************************************************
*
*  filename    : webcalendar.php
*  description : shell page for the WebCalendar system
*
*  http://www.churchcrm.io/
*  Copyright 2012 Michael Wilt
*
*  Copyright Contributors
*
*  ChurchCRM is free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  This file best viewed in a text editor with tabs stops set to 4 characters
*
******************************************************************************/

// Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

// Set the page title
$sPageTitle = gettext('ChurchCRM WebCalendar Portal');

require 'Include/Header.php';

echo "<object data=$sWebCalendarPath/month.php width=100% height=1024> <embed src=$sWebCalendarPath/month.php width=100% height=1024> </embed> Error: Embedded data could not be displayed. </object>";

require 'Include/Footer.php';
?>
