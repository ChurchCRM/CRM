$(function () {
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
      events: window.CRM.root + '/api/calendar/events'
    });
 });