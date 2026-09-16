<?php

use ChurchCRM\Portal\PortalApiMiddleware;
use ChurchCRM\Portal\PortalCalendarService;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

/*
 * `/api/portal/calendar/*` — what the Member Portal's calendar page reads
 * (design §5.3, issue #9866).
 *
 * Three properties hold here, and they are why the portal does not simply call
 * `/api/calendars/{id}/fullcalendar` per calendar:
 *
 *  - **Session only.** `PortalApiMiddleware` refuses an API key outright, so
 *    this is not a second, key-shaped way into church events.
 *  - **The administrator chooses the calendars, not the caller.** There is no
 *    calendar id in the URL: the answer is the union of whatever
 *    Admin → Member Portal → Calendars has switched on. Asking for a calendar
 *    that is not shared is not a permission failure, it is unexpressible.
 *  - **A bounded window.** `from` and `to` are plain `YYYY-MM-DD` days, and the
 *    window is clamped to PortalCalendarService::MAX_WINDOW_DAYS, because the
 *    system calendars expand a row per person per year and an unbounded range
 *    would be a cheap way to make the server work hard.
 */
$app->group('/portal/calendar', function (RouteCollectorProxy $group): void {
    $group->get('/events', 'getPortalCalendarEventsAPI');
})->add(PortalApiMiddleware::class);

/**
 * GET /api/portal/calendar/events?from=YYYY-MM-DD&to=YYYY-MM-DD
 *
 * Answers `{from, to, events}`: the effective window (which is not necessarily
 * the one that was asked for — see the clamp) and the events on it, already
 * shaped for FullCalendar in the church's configured timezone.
 *
 *   400 — `from` or `to` is not a calendar day, or `to` is before `from`
 */
function getPortalCalendarEventsAPI(Request $request, Response $response): Response
{
    $params = $request->getQueryParams();

    // `today` is midnight in the configured sTimeZone: Bootstrapper makes it
    // PHP's default timezone, so "today" means today at the church.
    $from = new DateTimeImmutable('today');
    if (isset($params['from'])) {
        $from = portalCalendarDay($params['from']);
        if ($from === null) {
            return portalCalendarBadRequest($request, $response, gettext('The start of the range is not a date.'));
        }
    }

    $to = $from->modify('+31 days');
    if (isset($params['to'])) {
        $to = portalCalendarDay($params['to']);
        if ($to === null) {
            return portalCalendarBadRequest($request, $response, gettext('The end of the range is not a date.'));
        }
    }

    if ($to < $from) {
        return portalCalendarBadRequest($request, $response, gettext('The end of the range is before its start.'));
    }

    // Clamp rather than refuse: a client asking for a year still gets a useful
    // answer, and the response says which window it actually describes.
    $latest = $from->modify('+' . PortalCalendarService::MAX_WINDOW_DAYS . ' days');
    if ($to > $latest) {
        $to = $latest;
    }

    return SlimUtils::renderJSON($response, [
        'from' => $from->format('Y-m-d'),
        'to' => $to->format('Y-m-d'),
        'hasVisibleCalendars' => PortalCalendarService::hasVisibleCalendars(),
        'events' => PortalCalendarService::eventsBetween($from, $to),
    ]);
}

/**
 * A `YYYY-MM-DD` query parameter as a day, or null when it is absent or is not
 * a real calendar day. Strict on purpose: the portal calendar sends days, and
 * anything looser would let a caller smuggle a time zone in.
 */
function portalCalendarDay(mixed $value): ?DateTimeImmutable
{
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return null;
    }

    $day = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

    return $day === false || $day->format('Y-m-d') !== $value ? null : $day;
}

function portalCalendarBadRequest(Request $request, Response $response, string $message): Response
{
    return SlimUtils::renderErrorJSON($response, $message, [], 400, null, $request);
}
