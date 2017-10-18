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