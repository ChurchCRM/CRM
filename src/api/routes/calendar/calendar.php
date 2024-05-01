<?php

use ChurchCRM\dto\FullCalendarEvent;
use ChurchCRM\dto\SystemCalendars;
use ChurchCRM\model\ChurchCRM\Calendar;
use ChurchCRM\model\ChurchCRM\CalendarQuery;
use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\Slim\Middleware\Request\Auth\AddEventsRoleAuthMiddleware;
use ChurchCRM\Slim\Request\SlimUtils;
use Propel\Runtime\Collection\ObjectCollection;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;
use Slim\Routing\RouteCollectorProxy;

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

function getSystemCalendars(Request $request, Response $response, array $args): Response
{
    return SlimUtils::renderStringJSON($response, SystemCalendars::getCalendarList()->toJSON());
}

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

function getSystemCalendarEventById(Request $request, Response $response, array $args): Response
{
    $Calendar = SystemCalendars::getCalendarById($args['id']);
    if (!$Calendar) {
        throw new HttpNotFoundException($request, 'Calendar ID not found!');
    }

    $event = $Calendar->getEventById($args['eventid']);

    return SlimUtils::renderJSON($response, $event);
}

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

    return SlimUtils::renderStringJSON($response, $Calendars->toJSON());
}

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

function NewCalendar(Request $request, Response $response, $args): Response
{
    $input = $request->getParsedBody();
    $Calendar = new Calendar();
    $Calendar->setName($input['Name']);
    $Calendar->setForegroundColor($input['ForegroundColor']);
    $Calendar->setBackgroundColor($input['BackgroundColor']);
    $Calendar->save();

    return SlimUtils::renderJSON($response, $Calendar->toArray());
}

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
