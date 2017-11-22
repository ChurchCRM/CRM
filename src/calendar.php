<?php

/*******************************************************************************
 *
 *  filename    : calendar.php
 *  last change : 2017-11-16
 *  description : manage the full calendar
 *
 *  http://www.churchcrm.io/
 *  Copyright 2017 Logel Philippe
  *
 ******************************************************************************/
 
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Service\CalendarService;
use ChurchCRM\GroupQuery;
use ChurchCRM\EventTypesQuery;

$calenderService = new CalendarService();
use ChurchCRM\dto\SystemURLs;

$groups = GroupQuery::Create()
      ->orderByName()
      ->find();
      

$eventTypes = EventTypesQuery::Create()
      ->orderByName()
      ->find();
  

// Set the page title and include HTML header
$sPageTitle = gettext('Church Calendar');
require 'Include/Header.php'; ?>

<style>
    @media print {
        a[href]:after {
            content: none !important;
        }
    }
    .fc-other-month .fc-day-number {
      display:none;
    }
</style>
<div class="col">
    <div class="box box-primary">
        <div class="box-body">
            <?php foreach ($calenderService->getEventTypes() as $type) {
    ?>
                <div class="col-xs-3 fc-event-container fc-day-grid-event"
                     style="background-color:<?= $type['backgroundColor'] ?>;border-color:<?= $type['backgroundColor'] ?>;color: white; ">
                    <div class="fc-title"><?= gettext($type['Name']) ?></div>
                </div>
                <?php
} ?>
        </div>
    </div>
</div>

<div class="box">
  <div class="box-header with-border">
    <h3 class="box-title"><?= gettext('Quick Settings') ?></h3>
  </div>
  <div class="box-body">
      <form>
          <div class="col-sm-4"> <b><?= gettext("Birthdate") ?>:</b> <input data-size="small" id="isBirthdateActive" type="checkbox" data-toggle="toggle" data-on="<?= gettext("Include") ?>" data-off="<?= gettext("Exclude") ?>"> </div>
          <div class="col-sm-4"> <b><?= gettext("Anniversary") ?>:</b> <input data-size="small" id="isAnniversaryActive" type="checkbox" data-toggle="toggle" data-on="<?= gettext("Include") ?>" data-off="<?= gettext("Exclude") ?>"></div>
          <div class="col-sm-4"> <b><?= gettext("With Limit") ?>:</b> <input data-size="small" id="isWithLimit" type="checkbox" data-toggle="toggle" data-on="<?= gettext("Include") ?>" data-off="<?= gettext("Exclude") ?>"></div>
      </form>
  </div>
</div>

<div class="box">
  <div class="box-header with-border">
    <h3 class="box-title"><?= gettext('Filter Settings') ?></h3>
  </div>
  <div class="box-body">
      <form>
          <div class="col-sm-3"> <b><?= gettext("Event Type Filter") ?> : </b> 
            <select type="text" id="EventTypeFilter" value="0">
              <option value='0' ><?= gettext("None") ?></option>
            <?php
                  foreach ($eventTypes as $eventType) {
                      echo "+\"<option value='".$eventType->getID()."'>".$eventType->getName()."</option>\"";
                  }
            ?>
            </select>
          </div>
          <div class="col-sm-6"> <b><?= gettext("Event Group Filter") ?>:</b> 
            <select type="text" id="EventGroupFilter" value="0">
              <option value='0' ><?= gettext("None") ?></option>
            <?php
                  foreach ($groups as $group) {
                      echo "+\"<option value='".$group->getID()."'>".$group->getName()."</option>\"";
                  }
                ?>  
            </select>
          </div>
      </form>
  </div>
</div>

<div class="col">
    <div class="box box-info">
        <div class="box-body no-padding">
            <!-- THE CALENDAR -->
            <div id="calendar"></div>
        </div>
        <!-- /.box-body -->
    </div>
    <!-- /. box -->
</div>
<!-- /.col -->

&nbsp;

<!-- fullCalendar 2.2.5 -->
<script>

 
  var isModifiable  = <?php 
    if ($_SESSION['bAddEvent']) {
        echo "true";
    } else {
        echo "false";
    }
  ?>;
  
  var eventTypes = <?php
                      foreach ($eventTypes as $eventType) {
                          echo "+\"<option value='".$eventType->getID()."'>".$eventType->getName()."</option>\"";
                      }
                    ?>;
  
  var eventGroups = <?php
                  foreach ($groups as $group) {
                      echo "+\"<option value='".$group->getID()."'>".$group->getName()."</option>\"";
                  }
                ?>;

</script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/js/Calendar.js" type="text/javascript"></script>

<?php require 'Include/Footer.php'; ?>
