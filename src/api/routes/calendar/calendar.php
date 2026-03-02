<?php

use ChurchCRM\Dashboard\EventsMenuItems;
use ChurchCRM\dto\FullCalendarEvent;
use ChurchCRM\dto\SystemCalendars;
use ChurchCRM\model\ChurchCRM\Calendar;
use ChurchCRM\model\ChurchCRM\CalendarQuery;
use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\Slim\Middleware\Request\Auth\AddEventsRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\InputUtils;
use Propel\Runtime\Collection\ObjectCollection;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;
use Slim\Routing\RouteCollectorProxy;

$app->group('/calendar', function (RouteCollectorProxy $group): void {
    $group->get('/events-counters', 'getEventsCounters');
});

$app->group('/calendars', function (RouteCollectorProxy $group): void {
    $group->get('', 'getUserCalendars');
    $group->post('', 'NewCalendar')->add(AddEventsRoleAuthMiddleware::class);
    $group->get('/', 'getUserCalendars');
    $group->post('/', 'NewCalendar')->add(AddEventsRoleAuthMiddleware::class);
    $group->get('/{id}', 'getUserCalendars');
    $group->delete('/{id}', 'deleteUserCalendar');
    $group->get('/{id}/events', 'getUserCalendarEvents');
    $group->get('/{id}/fullcalendar', 'getUserCalendarFullCalendarEvents');
    $group->post('/{id}/NewAccessToken', 'NewAccessToken')->add(AddEventsRoleAuthMiddleware::class);
    $group->delete('/{id}/AccessToken', 'DeleteAccessToken')->add(AddEventsRoleAuthMiddleware::class);
});

$app->group('/systemcalendars', function (RouteCollectorProxy $group): void {
    $group->get('', 'getSystemCalendars');
    $group->get('/', 'getSystemCalendars');
    $group->get('/{id}/events', 'getSystemCalendarEvents');
    $group->get('/{id}/events/{eventid}', 'getSystemCalendarEventById');
    $group->get('/{id}/fullcalendar', 'getSystemCalendarFullCalendarEvents');
});

/**
 * @OA\Get(
 *     path="/systemcalendars",
 *     operationId="getSystemCalendars",
 *     summary="List all system calendars",
 *     description="Returns built-in system calendars (birthdays, anniversaries, events).",
 *     tags={"Calendar"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(response=200, description="Array of system calendar objects"),
 *     @OA\Response(response=401, description="Unauthorized")
 * )
 */
function getSystemCalendars(Request $request, Response $response, array $args): Response
{
    return SlimUtils::renderStringJSON($response, SystemCalendars::getCalendarList()->toJSON());
}

/**
 * @OA\Get(
 *     path="/systemcalendars/{id}/events",
 *     operationId="getSystemCalendarEvents",
 *     summary="Get events from a system calendar",
 *     tags={"Calendar"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, description="System calendar ID", @OA\Schema(type="integer", example=1)),
 *     @OA\Parameter(name="start", in="query", required=false, description="Filter start date (ISO 8601)", @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="end", in="query", required=false, description="Filter end date (ISO 8601)", @OA\Schema(type="string", format="date")),
 *     @OA\Response(response=200, description="Array of event objects"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=404, description="System calendar not found")
 * )
 */
function getSystemCalendarEvents(Request $request, Response $response, array $args): Response
{
    $Calendar = SystemCalendars::getCalendarById($args['id']);
    if (!$Calendar) {
        throw new HttpNotFoundException($request, 'Calendar ID not found!');
    }

    $start = $request->getQueryParams()['start'] ?? '';
    $end = $request->getQueryParams()['end'] ?? '';

    $events = $Calendar->getEvents($start, $end);

    return SlimUtils::renderJSON($response, $events);
}

/**
 * @OA\Get(
 *     path="/systemcalendars/{id}/events/{eventid}",
 *     operationId="getSystemCalendarEventById",
 *     summary="Get a single event from a system calendar",
 *     tags={"Calendar"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="eventid", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Event object"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=404, description="Calendar or event not found")
 * )
 */
function getSystemCalendarEventById(Request $request, Response $response, array $args): Response
{
    $Calendar = SystemCalendars::getCalendarById($args['id']);
    if (!$Calendar) {
        throw new HttpNotFoundException($request, 'Calendar ID not found!');
    }

    $event = $Calendar->getEventById($args['eventid']);

    return SlimUtils::renderJSON($response, $event);
}

/**
 * @OA\Get(
 *     path="/systemcalendars/{id}/fullcalendar",
 *     operationId="getSystemCalendarFullCalendarEvents",
 *     summary="Get system calendar events in FullCalendar format",
 *     tags={"Calendar"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="start", in="query", required=false, @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="end", in="query", required=false, @OA\Schema(type="string", format="date")),
 *     @OA\Response(response=200, description="FullCalendar-compatible event array"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=404, description="Calendar or events not found")
 * )
 */
function getSystemCalendarFullCalendarEvents(Request $request, Response $response, array $args): Response
{
    if (!is_numeric($args['id'])) {
        throw new InvalidArgumentException('Calendar ID must be an integer!');
    }

    $calendarId = (int) $args['id'];
    $Calendar = SystemCalendars::getCalendarById($calendarId);
    if (!$Calendar) {
        throw new HttpNotFoundException($request, 'Calendar ID not found!');
    }

    $start = $request->getQueryParams()['start'] ?? '';
    $end = $request->getQueryParams()['end'] ?? '';
    $Events = $Calendar->getEvents($start, $end);
    if (!$Events) {
        throw new HttpNotFoundException($request, 'Events not found!');
    }

    return SlimUtils::renderJSON($response, EventsObjectCollectionToFullCalendar($Events, SystemCalendars::toPropelCalendar($Calendar)));
}

/**
 * @OA\Get(
 *     path="/calendars",
 *     operationId="getUserCalendars",
 *     summary="List user-created calendars",
 *     description="Returns all user calendars. Optionally filter by ID using GET /calendars/{id}.",
 *     tags={"Calendar"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(response=200, description="Array of calendar objects"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=404, description="No calendars found")
 * )
 * @OA\Get(
 *     path="/calendars/{id}",
 *     operationId="getUserCalendarById",
 *     summary="Get a specific user calendar",
 *     tags={"Calendar"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Calendar object"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=404, description="Calendar not found")
 * )
 */
function getUserCalendars(Request $request, Response $response, array $args): Response
{
    $CalendarQuery = CalendarQuery::create();
    if (isset($args['id'])) {
        $CalendarQuery->filterById($args['id']);
    }
    $Calendars = $CalendarQuery->find();
    if (!$Calendars) {
        throw new HttpNotFoundException($request, 'No calendars returned');
    }

    // Prevent caching of calendar API responses
    $response = $response
        ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
        ->withHeader('Pragma', 'no-cache');

    return SlimUtils::renderStringJSON($response, $Calendars->toJSON());
}

/**
 * @OA\Get(
 *     path="/calendars/{id}/fullcalendar",
 *     operationId="getUserCalendarFullCalendarEvents",
 *     summary="Get user calendar events in FullCalendar format",
 *     tags={"Calendar"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="start", in="query", required=true, @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="end", in="query", required=true, @OA\Schema(type="string", format="date")),
 *     @OA\Response(response=200, description="FullCalendar-compatible event array"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=404, description="Calendar or events not found")
 * )
 */
function getUserCalendarFullCalendarEvents(Request $request, Response $response, array $args): Response
{
    $CalendarID = $args['id'];
    $calendar = CalendarQuery::create()
        ->findOneById($CalendarID);
    if (!$calendar) {
        throw new HttpNotFoundException($request, 'Calendar ID not found!');
    }
    $start = $request->getQueryParams()['start'];
    $end = $request->getQueryParams()['end'];
    $Events = EventQuery::create()
        ->filterByStart(['min' => $start])
        ->filterByEnd(['max' => $end])
        ->filterByCalendar($calendar)
        ->find();
    if (!$Events) {
        throw new HttpNotFoundException($request, 'Events not found!');
    }

    return SlimUtils::renderJSON($response, EventsObjectCollectionToFullCalendar($Events, $calendar));
}

/**
 * @OA\Get(
 *     path="/calendars/{id}/events",
 *     operationId="getUserCalendarEvents",
 *     summary="Get events for a user calendar",
 *     tags={"Calendar"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="start", in="query", required=false, @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="end", in="query", required=false, @OA\Schema(type="string", format="date")),
 *     @OA\Response(response=200, description="Array of event objects"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=404, description="Calendar not found")
 * )
 */
function getUserCalendarEvents(Request $request, Response $response, array $args): Response
{
    $CalendarID = $args['id'];
    $calendar = CalendarQuery::create()
        ->findOneById($CalendarID);
    if (!$calendar) {
        throw new HttpNotFoundException($request, 'Calendar ID not found!');
    }
    $start = $request->getQueryParams()['start'] ?? '';
    $end = $request->getQueryParams()['end'] ?? '';

    $events = $calendar->getEvents($start, $end);

    return SlimUtils::renderJSON($response, $events);
}

function EventsObjectCollectionToFullCalendar(ObjectCollection $events, Calendar $calendar): array
{
    $formattedEvents = [];
    foreach ($events as $event) {
        $fce = FullCalendarEvent::createFromEvent($event, $calendar);
        $formattedEvents[] = $fce;
    }

    return $formattedEvents;
}

/**
 * @OA\Post(
 *     path="/calendars/{id}/NewAccessToken",
 *     operationId="newCalendarAccessToken",
 *     summary="Generate a new public access token for a calendar",
 *     tags={"Calendar"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Calendar object with new access token"),
 *     @OA\Response(response=400, description="Missing or invalid calendar ID"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="AddEvents role required")
 * )
 */
function NewAccessToken(Request $request, Response $response, array $args): Response
{
    if (!isset($args['id'])) {
        throw new HttpBadRequestException($request, gettext('Invalid request: Missing calendar id'));
    }
    $Calendar = CalendarQuery::create()
        ->findOneById($args['id']);
    if (!$Calendar) {
        throw new HttpBadRequestException($request, gettext('Not Found: Unknown calendar id') . ': ' . $args['id']);
    }
    $Calendar->setAccessToken(ChurchCRM\Utils\MiscUtils::randomToken());
    $Calendar->save();

    return SlimUtils::renderJSON($response, $Calendar->toArray());
}

/**
 * @OA\Delete(
 *     path="/calendars/{id}/AccessToken",
 *     operationId="deleteCalendarAccessToken",
 *     summary="Remove the public access token from a calendar",
 *     tags={"Calendar"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Calendar object with access token cleared"),
 *     @OA\Response(response=400, description="Missing or invalid calendar ID"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="AddEvents role required")
 * )
 */
function DeleteAccessToken(Request $request, Response $response, array $args): Response
{
    if (!isset($args['id'])) {
        throw new HttpBadRequestException($request, gettext('Invalid request: Missing calendar id'));
    }
    $Calendar = CalendarQuery::create()
        ->findOneById($args['id']);
    if (!$Calendar) {
        throw new HttpBadRequestException($request, gettext('Not Found: Unknown calendar id') . ': ' . $args['id']);
    }
    $Calendar->setAccessToken(null);
    $Calendar->save();

    return SlimUtils::renderJSON($response, $Calendar->toArray());
}

/**
 * @OA\Post(
 *     path="/calendars",
 *     operationId="newCalendar",
 *     summary="Create a new calendar",
 *     tags={"Calendar"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         required={"Name","ForegroundColor","BackgroundColor"},
 *         @OA\Property(property="Name", type="string", example="Youth Group"),
 *         @OA\Property(property="ForegroundColor", type="string", example="#ffffff"),
 *         @OA\Property(property="BackgroundColor", type="string", example="#3788d8")
 *     )),
 *     @OA\Response(response=200, description="New calendar object"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="AddEvents role required")
 * )
 */
function NewCalendar(Request $request, Response $response, $args): Response
{
    $input = $request->getParsedBody();
    $Calendar = new Calendar();
    $Calendar->setName(InputUtils::sanitizeText($input['Name']));
    $Calendar->setForegroundColor($input['ForegroundColor']);
    $Calendar->setBackgroundColor($input['BackgroundColor']);
    $Calendar->save();

    return SlimUtils::renderJSON($response, $Calendar->toArray());
}

/**
 * @OA\Delete(
 *     path="/calendars/{id}",
 *     operationId="deleteUserCalendar",
 *     summary="Delete a user calendar",
 *     tags={"Calendar"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Calendar deleted",
 *         @OA\JsonContent(@OA\Property(property="success", type="boolean", example=true))
 *     ),
 *     @OA\Response(response=400, description="Missing or invalid calendar ID"),
 *     @OA\Response(response=401, description="Unauthorized")
 * )
 */
function deleteUserCalendar(Request $request, Response $response, array $args): Response
{
    if (!isset($args['id'])) {
        throw new HttpBadRequestException($request, gettext('Invalid request: Missing calendar id'));
    }
    $Calendar = CalendarQuery::create()
        ->findOneById($args['id']);
    if (!$Calendar) {
        throw new HttpBadRequestException($request, gettext('Not Found: Unknown calendar id') . ': ' . $args['id']);
    }
    $Calendar->delete();

    return SlimUtils::renderSuccessJSON($response);
}

/**
 * Get today's event counters (birthdays, anniversaries, events)
 * Called once on page load to populate menu badges
 */
/**
 * @OA\Get(
 *     path="/calendar/events-counters",
 *     operationId="getEventsCounters",
 *     summary="Get today's event counters for the dashboard menu badges",
 *     description="Returns counts of upcoming birthdays, anniversaries, and events used to populate navigation badge counts.",
 *     tags={"Calendar"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(response=200, description="Counter object",
 *         @OA\JsonContent(type="object",
 *             @OA\Property(property="birthdays", type="integer", example=2),
 *             @OA\Property(property="anniversaries", type="integer", example=1),
 *             @OA\Property(property="events", type="integer", example=3)
 *         )
 *     ),
 *     @OA\Response(response=401, description="Unauthorized")
 * )
 */
function getEventsCounters(Request $request, Response $response, array $args): Response
{
    return SlimUtils::renderJSON($response, EventsMenuItems::getDashboardItemValue());
}
