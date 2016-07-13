<?php
require "Include/Config.php";
require "Include/Functions.php";
require "Service/EventService.php";

$eventService = new EventService();

$birthDays = $personService->getBirthDays();
$crmEvents = $eventService->getEvents();

$events = array();
$year = date("Y");

foreach ($birthDays as $birthDay) {
  $event = array(
             "title" => $birthDay["firstName"] . " " . $birthDay["lastName"],
             "start" => $year . "-" . $birthDay["birthMonth"] . "-" . $birthDay["birthDay"],
             "url"   => $birthDay["uri"],
             "backgroundColor" => '#f56954', //red
             "borderColor"     => '#f56954', //red
             "allDay" => true
           );

  array_push($events, $event);
}

foreach ($crmEvents as $evnt) {
  $event = array(
    "title" => $evnt["title"],
    "start" => $evnt["start"],
    "end" => $evnt["end"],
    "backgroundColor" => '#f39c12', //red
    "borderColor"     => '#f39c12', //red
    "allDay" => true
  );
  array_push($events, $event);
}


// Set the page title and include HTML header
$sPageTitle = gettext("Church Calendar");
require "Include/Header.php"; ?>

<link rel="stylesheet" href="<?= $sRootPath ?>/skin/adminlte/plugins/fullcalendar/fullcalendar.min.css">
<link rel="stylesheet" href="<?= $sRootPath ?>/skin/adminlte/plugins/fullcalendar/fullcalendar.print.css" media="print">
<style>
  @media print {
    a[href]:after {
      content: none !important;
    }
  }
</style>
<div class="col-lg-12">
  <div class="box box-primary">
    <div class="box-body">
      <div class="fc-event-container fc-day-grid-event" style="background-color:#f56954;border-color:#f56954;color: white; width: 100px">
          <div class="fc-title"><?= gettext("Birthdays") ?></div>
      </div>
      <div class="fc-event-container fc-day-grid-event" style="background-color:#f39c12;border-color:#f39c12;color: white; width: 100px">
          <div class="fc-title"><?= gettext("Events") ?></div>
      </div>
    </div>
  </div>
</div>

<div class="col-lg-12">
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
<script src="<?= $sRootPath ?>/skin/adminlte/plugins/daterangepicker/moment.min.js"></script>
<script src="<?= $sRootPath ?>/skin/adminlte/plugins/fullcalendar/fullcalendar.min.js"></script>
<script>
  $(function () {
    /* initialize the calendar
     -----------------------------------------------------------------*/
    $('#calendar').fullCalendar({
      header: {
        left: 'prev,next today',
        center: 'title',
        right: 'month,agendaWeek,agendaDay'
      },
      buttonText: {
        today: '<?= gettext("Today") ?>',
        month: '<?= gettext("Month") ?>',
        week: '<?= gettext("Week") ?>',
        day: '<?= gettext("Day") ?>'
      },
      //Random default events
      events: <?= json_encode($events) ?>
    });
 });
</script>

<?php require "Include/Footer.php"; ?>
