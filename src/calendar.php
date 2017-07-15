<?php
require 'Include/Config.php';
require 'Include/Functions.php';
use ChurchCRM\Service\CalendarService;

$calenderService = new CalendarService();
use ChurchCRM\dto\SystemURLs;

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
    $(document).ready(function () {
        /* initialize the calendar
         -----------------------------------------------------------------*/
        $('#calendar').fullCalendar({
            header: {
                left: 'prev,next today',
                center: 'title',
                right: 'month,basicDay,listMonth'
            },
            height: 500,
            locale: '<?= $localeInfo->getLanguageCode() ?>',
            events: window.CRM.root + '/api/calendar/events',
            eventRender: function (event, element, view) {
                var evStart = moment(view.intervalStart).subtract(1, 'days');
                var evEnd = moment(view.intervalEnd).subtract(1, 'days');
                if (!event.start.isAfter(evStart) || event.start.isAfter(evEnd)) {
                    return false;
                }
            }
        });
    });
</script>

<?php require 'Include/Footer.php'; ?>
