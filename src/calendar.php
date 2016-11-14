<?php
require "Include/Config.php";
require "Include/Functions.php";
use ChurchCRM\Service\CalendarService;

$calenderService = new CalendarService();

// Set the page title and include HTML header
$sPageTitle = gettext("Church Calendar");
require "Include/Header.php"; ?>

<link rel="stylesheet" href="<?= $sRootPath ?>/skin/fullcalendar/fullcalendar.min.css">
<link rel="stylesheet" href="<?= $sRootPath ?>/skin/fullcalendar/fullcalendar.print.css" media="print">
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
      <?php foreach ($calenderService->getEventTypes() as $type) { ?>
      <div class="fc-event-container fc-day-grid-event" style="background-color:<?= $type["backgroundColor"]?>;border-color:<?= $type["backgroundColor"]?>;color: white; width: 100px">
          <div class="fc-title"><?= gettext($type["Name"])?></div>
      </div>
      <?php }?>
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
<script src="<?= $sRootPath ?>/skin/fullcalendar/fullcalendar.min.js"></script>
<script>
  $(function () {
    /* initialize the calendar
     -----------------------------------------------------------------*/
    $('#calendar').fullCalendar({
      locale: '<?= $localeInfo->getLanguageCode() ?>',
      events: window.CRM.root + '/api/calendar/events'
    });
 });
</script>

<?php require "Include/Footer.php"; ?>
