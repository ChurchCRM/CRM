<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Portal\PortalCalendarService;
use ChurchCRM\Portal\PortalNav;
use ChurchCRM\Portal\PortalTwig;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\InputUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Twig\Markup;

// GET /portal/calendar — the church calendar a member may see (design §5.3).
//
// The page itself holds no events: FullCalendar asks
// GET /api/portal/calendar/events for the window it is showing, so paging
// through months costs one small request each and the first paint is not
// waiting on a query. What the page does carry is the legend — the calendars
// that are switched on, with their colours — because that is what tells a
// member what the colours mean before any event has loaded.
$calendarHandler = function (Request $request, Response $response): Response {
    if (!SystemConfig::getBooleanValue('bPortalShowCalendar')) {
        // The section is off for this church, so the page does not exist for
        // this church either — the same answer a member gets for any other
        // address the portal does not serve.
        throw new HttpNotFoundException($request);
    }

    $calendars = [];
    foreach (PortalCalendarService::listChoices() as $choice) {
        if ($choice['visible']) {
            $calendars[] = [
                'name' => $choice['name'],
                'color' => $choice['colors']['background'],
            ];
        }
    }

    return PortalTwig::render(
        $response,
        'calendar/index.html.twig',
        [
            'pageTitle' => gettext('Calendar'),
            'calendars' => $calendars,
            'hasCalendars' => $calendars !== [],
            // Everything the page's bundle needs, as one JSON blob the layout
            // prints verbatim rather than a scatter of data- attributes. It is
            // Markup so autoescape leaves it alone, and it is encoded with
            // InputUtils::jsonEncodeForScript() exactly as the layout's own
            // blobs are.
            'calendarConfigJson' => new Markup(InputUtils::jsonEncodeForScript([
                'eventsUrl' => SystemURLs::getRootPath() . '/api/portal/calendar/events',
                // The church's own zone, so FullCalendar renders the stored
                // wall-clock times as the church means them no matter where
                // the member is reading from (timezone-handling.md).
                'timeZone' => DateTimeUtils::getConfiguredTimezone()->getName(),
                'maxWindowDays' => PortalCalendarService::MAX_WINDOW_DAYS,
            ]), 'UTF-8'),
        ],
        PortalNav::CALENDAR
    );
};

$group->get('/calendar', $calendarHandler);
$group->get('/calendar/', $calendarHandler);
