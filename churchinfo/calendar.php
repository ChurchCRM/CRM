<?php
require "Include/Config.php";
require "Include/Functions.php";

$events = array();
$birthDays = $personService->getBirthDays();
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


// Set the page title and include HTML header
$sPageTitle = gettext("Church Calendar");
require "Include/Header.php"; ?>

<link rel="stylesheet" href="<?= $sRootPath ?>/skin/adminlte/plugins/fullcalendar/fullcalendar.min.css">
<link rel="stylesheet" href="<?= $sRootPath ?>/skin/adminlte/plugins/fullcalendar/fullcalendar.print.css" media="print">

<div class="col-lg-12">
  <div class="box box-primary">
    <div class="box-body">
      <div class="fc-event-container fc-day-grid-event" style="background-color:#f56954;border-color:#f56954;color: white; width: 100px">
          <div class="fc-title">Birthdays</div>
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.10.2/moment.min.js"></script>
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
        today: 'today',
        month: 'month',
        week: 'week',
        day: 'day'
      },
      //Random default events
      events: <?= json_encode($events) ?>
    });
 });
</script>

<?php require "Include/Footer.php"; ?>
