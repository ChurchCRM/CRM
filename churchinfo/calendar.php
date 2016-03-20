<?php
require "Include/Config.php";
require "Include/Functions.php";

$events = array();
$birthDays = $personService->getBirthDays();
$year = date("Y");

foreach ($birthDays as $birthDay) {
  $event = "{ title: '". $birthDay["firstName"] . " " . $birthDay["lastName"]  ."',
              start: new Date(". $year. ", ". $birthDay["birthMonth"]. ", ". $birthDay["birthDay"]. "),
              url: '".$birthDay["uri"]."',
              backgroundColor: '#f56954', //red
              borderColor: '#f56954' //red

            }";

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

    /* initialize the external events
     -----------------------------------------------------------------*/
    function ini_events(ele) {
      ele.each(function () {

        // create an Event Object (http://arshaw.com/fullcalendar/docs/event_data/Event_Object/)
        // it doesn't need to have a start or end
        var eventObject = {
          title: $.trim($(this).text()) // use the element's text as the event title
        };

        // store the Event Object in the DOM element so we can get to it later
        $(this).data('eventObject', eventObject);

        // make the event draggable using jQuery UI
        $(this).draggable({
          zIndex: 1070,
          revert: true, // will cause the event to go back to its
          revertDuration: 0  //  original position after the drag
        });

      });
    }

    ini_events($('#external-events div.external-event'));

    /* initialize the calendar
     -----------------------------------------------------------------*/
    //Date for the calendar events (dummy data)
    var date = new Date();
    var d = date.getDate(),
      m = date.getMonth(),
      y = date.getFullYear();
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
      events: [
        <?= implode(",", $events) ?>
      ],
      editable: true,
      droppable: true, // this allows things to be dropped onto the calendar !!!
      drop: function (date, allDay) { // this function is called when something is dropped

        // retrieve the dropped element's stored Event Object
        var originalEventObject = $(this).data('eventObject');

        // we need to copy it, so that multiple events don't have a reference to the same object
        var copiedEventObject = $.extend({}, originalEventObject);

        // assign it the date that was reported
        copiedEventObject.start = date;
        copiedEventObject.allDay = allDay;
        copiedEventObject.backgroundColor = $(this).css("background-color");
        copiedEventObject.borderColor = $(this).css("border-color");

        // render the event on the calendar
        // the last `true` argument determines if the event "sticks" (http://arshaw.com/fullcalendar/docs/event_rendering/renderEvent/)
        $('#calendar').fullCalendar('renderEvent', copiedEventObject, true);

      }
    });
 });
</script>

<?php require "Include/Footer.php"; ?>
