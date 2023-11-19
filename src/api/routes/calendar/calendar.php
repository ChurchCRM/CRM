<?php

use ChurchCRM\dto\FullCalendarEvent;
use ChurchCRM\dto\SystemCalendars;
use ChurchCRM\model\ChurchCRM\Calendar;
use ChurchCRM\model\ChurchCRM\CalendarQuery;
use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\Slim\Middleware\Request\Auth\AddEventsRoleAuthMiddleware;
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
    return $response->write(SystemCalendars::getCalendarList()->toJSON());
}

function getSystemCalendarEvents(Request $request, Response $response, array $args)
{
    $Calendar = SystemCalendars::getCalendarById($args['id']);
    $start = $request->getQueryParam('start', '');
    $end = $request->getQueryParam('end', '');
    if ($Calendar) {
        $events = $Calendar->getEvents($start, $end);
        $response->getBody()->write(json_encode($events));

        return $response->withHeader('Content-Type', 'application/json');
    }
}

function getSystemCalendarEventById(Request $request, Response $response, array $args)
{
    $Calendar = SystemCalendars::getCalendarById($args['id']);

    if ($Calendar) {
        $event = $Calendar->getEventById($args['eventid']);
        $response->getBody()->write(json_encode($event));

        return $response->withHeader('Content-Type', 'application/json');
    }
}

function getSystemCalendarFullCalendarEvents($request, Response $response, $args)
{
    $Calendar = SystemCalendars::getCalendarById($args['id']);
    $start = $request->getQueryParam('start', '');
    $end = $request->getQueryParam('end', '');
    if (!$Calendar) {
        return $response->withStatus(404);
    }
    $Events = $Calendar->getEvents($start, $end);
    if (!$Events) {
        return $response->withStatus(404);
    }

    return $response->write(json_encode(EventsObjectCollectionToFullCalendar($Events, SystemCalendars::toPropelCalendar($Calendar)), JSON_THROW_ON_ERROR));
}

function getUserCalendars(Request $request, Response $response, array $args)
{
    $CalendarQuery = CalendarQuery::create();
    if (isset($args['id'])) {
        $CalendarQuery->filterById($args['id']);
    }
    $Calendars = $CalendarQuery->find();
    if ($Calendars) {
        return $response->write($Calendars->toJSON());
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
            $response->getBody()->write(json_encode($Events));

            return $response->withHeader('Content-Type', 'application/json');
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
    $start = $request->getQueryParam('start', '');
    $end = $request->getQueryParam('end', '');
    $Events = EventQuery::create()
        ->filterByStart(['min' => $start])
        ->filterByEnd(['max' => $end])
        ->filterByCalendar($calendar)
        ->find();
    if (!$Events) {
        return $response->withStatus(404);
    }

    return $response->write(
        json_encode(
            EventsObjectCollectionToFullCalendar($Events, $calendar),
            JSON_THROW_ON_ERROR
        )
    );
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
        return $response->withStatus(404, gettext('Not Found: Unknown calendar id').': '.$args['id']);
    }
    $Calendar->setAccessToken(ChurchCRM\Utils\MiscUtils::randomToken());
    $Calendar->save();

    return $Calendar->toJSON();
}

function DeleteAccessToken($request, Response $response, $args)
{
    if (!isset($args['id'])) {
        return $response->withStatus(400, gettext('Invalid request: Missing calendar id'));
    }
    $Calendar = CalendarQuery::create()
      ->findOneById($args['id']);
    if (!$Calendar) {
        return $response->withStatus(404, gettext('Not Found: Unknown calendar id').': '.$args['id']);
    }
    $Calendar->setAccessToken(null);
    $Calendar->save();

    return $Calendar->toJSON();
}

function NewCalendar(Request $request, Response $response, $args)
{
    $input = (object) $request->getParsedBody();
    $Calendar = new Calendar();
    $Calendar->setName($input->Name);
    $Calendar->setForegroundColor($input->ForegroundColor);
    $Calendar->setBackgroundColor($input->BackgroundColor);
    $Calendar->save();
    $response->getBody()->write(json_encode($Calendar));

    return $response->withHeader('Content-Type', 'application/json');
}

function deleteUserCalendar(Request $request, Response $response, $args)
{
    if (!isset($args['id'])) {
        return $response->withStatus(400, gettext('Invalid request: Missing calendar id'));
    }
    $Calendar = CalendarQuery::create()
        ->findOneById($args['id']);
    if (!$Calendar) {
        return $response->withStatus(404, gettext('Not Found: Unknown calendar id').': '.$args['id']);
    }
    $Calendar->delete();

    $response->getBody()->write(json_encode(['status' => 'success']));

    return $response->withHeader('Content-Type', 'application/json');
}
