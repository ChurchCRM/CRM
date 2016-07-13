<?php
/*******************************************************************************
 *
 *  filename    : ReportList.php
 *  last change : 2003-03-20
 *  website     : http://www.churchcrm.io
 *  copyright   : Copyright 2003 Chris Gebhardt
 *
 *  ChurchCRM is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

require 'Include/Config.php';
require 'Include/Functions.php';

//Set the page title
$sPageTitle = gettext('Report Menu');

$today = getdate();
$year = $today['year'];

require 'Include/Header.php';
?>
  <!-- ./col -->
  <?php if ($_SESSION['bFinance'])
  {
    ?>
<div class="row">
    <div class="col-lg-12">
      <div class="box">
        <div class="box-header with-border">
          <h3 class="box-title"><?= gettext("Financial Reports") ?></h3>
        </div>
        <div class="box-body">
          <p>
            <a class="MediumText" href="FinancialReports.php">
          </p>
          <?php
          if ($_SESSION['bAdmin'])
          {
            echo '<p>';
            echo '<a class="MediumText" href="CanvassAutomation.php">';
            echo gettext('Canvass Automation') . "</a><br>";
            echo gettext('Automated support for conducting an every-member canvass.');
          }
          ?>
        </div>
      </div>
    </div><!-- ./col -->
    <?php
  }

#Conditionally Display the Event Reports, only if there are actually events in the database.  Otherwise, Don't render the Event reports section.
//$sSQL = "SELECT * FROM event_types";
  $sSQL = "SELECT DISTINCT event_types.* FROM event_types RIGHT JOIN events_event ON event_types.type_id=events_event.event_type ORDER BY type_id ";
  $rsOpps = RunQuery($sSQL);
  $numRows = mysql_num_rows($rsOpps);
  if ($numRows > 0)
  {
    ?>
    <div class="col-lg-12">
      <div class="box">
        <div class="box-header with-border">
          <h3 class="box-title"><?= gettext("Event Attendance Reports") ?></h3>
        </div>
        <div class="box-body">
          <?php
          // List all events
          for ($row = 1; $row <= $numRows; $row++)
          {
            $aRow = mysql_fetch_array($rsOpps);
            extract($aRow);
            echo '&nbsp;&nbsp;&nbsp;<a href="EventAttendance.php?Action=List&Event=' .
            $type_id . '&Type=' . gettext($type_name) . '" title="List All ' .
            gettext($type_name) . ' Events"><strong>' . gettext($type_name) .
            '</strong></a>' . "<br>\n";
          }
          ?>
        </div>
      </div>
    </div>
    <?php
  }
  ?>
</div>


<?php require 'Include/Footer.php' ?>
