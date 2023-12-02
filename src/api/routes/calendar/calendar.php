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
use Slim\Routing\RouteCollectorProxy;

$app->group('/calendars', function (RouteCollectorProxy $group) {
    $group->get('', 'getUserCalendars');
    $group->post('', 'NewCalendar')->add(new AddEventsRoleAuthMiddleware());
    $group->get('/', 'getUserCalendars');
    $group->post('/', 'NewCalendar')->add(new AddEventsRoleAuthMiddleware());
    $group->get('/{id}', 'getUserCalendars');
    $group->delete('/{id}', 'deleteUserCalendar');
    $group->get('/{id}/events', 'UserCalendar');
    $group->get('/{id}/fullcalendar', 'getUserCalendarFullCalendarEvents');
    $group->post('/{id}/NewAccessToken', 'NewAccessToken')->add(new AddEventsRoleAuthMiddleware());
    $group->delete('/{id}/AccessToken', 'DeleteAccessToken')->add(new AddEventsRoleAuthMiddleware());
});

$app->group('/systemcalendars', function (RouteCollectorProxy $group) {
    $group->get('', 'getSystemCalendars');
    $group->get('/', 'getSystemCalendars');
    $group->get('/{id}/events', 'getSystemCalendarEvents');
    $group->get('/{id}/events/{eventid}', 'getSystemCalendarEventById');
    $group->get('/{id}/fullcalendar', 'getSystemCalendarFullCalendarEvents');
});

function getSystemCalendars(Request $request, Response $response, array $args)
{
    return SlimUtils::renderStringJSON($response, SystemCalendars::getCalendarList()->toJSON());
}

function getSystemCalendarEvents(Request $request, Response $response, array $args)
{
    $Calendar = SystemCalendars::getCalendarById($args['id']);
    $start = $request->getQueryParams()['start'];
    $end = $request->getQueryParams()['end'];
    if ($Calendar) {
        $events = $Calendar->getEvents($start, $end);
        return SlimUtils::renderJSON($response, $events);
    }
}

function getSystemCalendarEventById(Request $request, Response $response, array $args)
{
    $Calendar = SystemCalendars::getCalendarById($args['id']);

    if ($Calendar) {
        $event = $Calendar->getEventById($args['eventid']);
        return SlimUtils::renderJSON($response, $event);
    }
}

function getSystemCalendarFullCalendarEvents($request, Response $response, $args)
{
    $Calendar = SystemCalendars::getCalendarById($args['id']);
    $start = $request->getQueryParams()['start'];
    $end = $request->getQueryParams()['end'];
    if (!$Calendar) {
        return $response->withStatus(404);
    }
    $Events = $Calendar->getEvents($start, $end);
    if (!$Events) {
        return $response->withStatus(404);
    }

    return SlimUtils::renderJSON($response, EventsObjectCollectionToFullCalendar($Events, SystemCalendars::toPropelCalendar($Calendar)));
}

function getUserCalendars(Request $request, Response $response, array $args)
{
    $CalendarQuery = CalendarQuery::create();
    if (isset($args['id'])) {
        $CalendarQuery->filterById($args['id']);
    }
    $Calendars = $CalendarQuery->find();
    if ($Calendars) {
        return SlimUtils::renderStringJSON($response, $Calendars->toJSON());
    }
}

function getUserCalendarEvents(Request $request, Response $response, array $p_args)
{
    $Calendar = CalendarQuery::create()->findOneById($p_args['id']);
    if ($Calendar) {
        $Events = EventQuery::create()
            ->filterByCalendar($Calendar)
            ->find();
        if ($Events) {
            return SlimUtils::renderStringJSON($response, $Events->toJSON());
        }
    }
}

function getUserCalendarFullCalendarEvents($request, Response $response, $args)
{
    $CalendarID = $args['id'];
    $calendar = CalendarQuery::create()
        ->findOneById($CalendarID);
    if (!$calendar) {
        return $response->withStatus(404);
    }
    $start = $request->getQueryParams()['start'];
    $end = $request->getQueryParams()['end'];
    $Events = EventQuery::create()
        ->filterByStart(['min' => $start])
        ->filterByEnd(['max' => $end])
        ->filterByCalendar($calendar)
        ->find();
    if (!$Events) {
        return $response->withStatus(404);
    }

    return SlimUtils::renderJSON($response, EventsObjectCollectionToFullCalendar($Events, $calendar));
}

function EventsObjectCollectionToFullCalendar(ObjectCollection $events, Calendar $calendar): array
{
    $formattedEvents = [];
    foreach ($events as $event) {
        $fce = FullCalendarEvent::createFromEvent($event, $calendar);
        array_push($formattedEvents, $fce);
    }

    return $formattedEvents;
}

function NewAccessToken($request, Response $response, $args)
{
    if (!isset($args['id'])) {
        return $response->withStatus(400, gettext('Invalid request: Missing calendar id'));
    }
    $Calendar = CalendarQuery::create()
        ->findOneById($args['id']);
    if (!$Calendar) {
        return $response->withStatus(404, gettext('Not Found: Unknown calendar id') . ': ' . $args['id']);
    }
    $Calendar->setAccessToken(ChurchCRM\Utils\MiscUtils::randomToken());
    $Calendar->save();

    return SlimUtils::renderJSON($response, $Calendar);
}

function DeleteAccessToken($request, Response $response, $args)
{
    if (!isset($args['id'])) {
        return $response->withStatus(400, gettext('Invalid request: Missing calendar id'));
    }
    $Calendar = CalendarQuery::create()
        ->findOneById($args['id']);
    if (!$Calendar) {
        return $response->withStatus(404, gettext('Not Found: Unknown calendar id') . ': ' . $args['id']);
    }
    $Calendar->setAccessToken(null);
    $Calendar->save();

    return SlimUtils::renderJSON($response, $Calendar);
}

function NewCalendar(Request $request, Response $response, $args)
{
    $input = $request->getParsedBody();
    $Calendar = new Calendar();
    $Calendar->setName($input['Name']);
    $Calendar->setForegroundColor($input['ForegroundColor']);
    $Calendar->setBackgroundColor($input['BackgroundColor']);
    $Calendar->save();

    return SlimUtils::renderJSON($response, $Calendar->toArray());
}

function deleteUserCalendar(Request $request, Response $response, $args)
{
    if (!isset($args['id'])) {
        return $response->withStatus(400, gettext('Invalid request: Missing calendar id'));
    }
    $Calendar = CalendarQuery::create()
        ->findOneById($args['id']);
    if (!$Calendar) {
        return $response->withStatus(404, gettext('Not Found: Unknown calendar id') . ': ' . $args['id']);
    }
    $Calendar->delete();

    return SlimUtils::renderSuccessJSON($response);
}
