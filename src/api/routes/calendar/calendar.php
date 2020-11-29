<?php

use ChurchCRM\Calendar;
use ChurchCRM\CalendarQuery;
use ChurchCRM\dto\FullCalendarEvent;
use ChurchCRM\dto\SystemCalendars;
use ChurchCRM\EventQuery;
use ChurchCRM\Slim\Middleware\Request\Auth\AddEventsRoleAuthMiddleware;
use Propel\Runtime\Collection\ObjectCollection;
use Slim\Http\Request;
use Slim\Http\Response;

$app->group('/calendars', function () {
    $this->get('', 'getUserCalendars');
    $this->post('', 'NewCalendar')->add(new AddEventsRoleAuthMiddleware());
    $this->get('/', 'getUserCalendars');
    $this->post('/', 'NewCalendar')->add(new AddEventsRoleAuthMiddleware());
    $this->get('/{id}', 'getUserCalendars');
    $this->delete('/{id}', 'deleteUserCalendar');
    $this->get('/{id}/events', 'UserCalendar');
    $this->get('/{id}/fullcalendar', 'getUserCalendarFullCalendarEvents');
    $this->post('/{id}/NewAccessToken', 'NewAccessToken')->add(new AddEventsRoleAuthMiddleware());
    $this->delete('/{id}/AccessToken', 'DeleteAccessToken')->add(new AddEventsRoleAuthMiddleware());

});


$app->group('/systemcalendars', function () {
    $this->get('', 'getSystemCalendars');
    $this->get('/', 'getSystemCalendars');
    $this->get('/{id}/events', 'getSystemCalendarEvents');
    $this->get('/{id}/events/{eventid}', 'getSystemCalendarEventById');
    $this->get('/{id}/fullcalendar', 'getSystemCalendarFullCalendarEvents');
});


function getSystemCalendars(Request $request, Response $response, array $args)
{
    return $response->write(SystemCalendars::getCalendarList()->toJSON());
}

function getSystemCalendarEvents(Request $request, Response $response, array $args)
{
    $Calendar = SystemCalendars::getCalendarById($args['id']);
    $start = $request->getQueryParam("start", "");
    $end = $request->getQueryParam("end", "");
    if ($Calendar) {
        $events = $Calendar->getEvents($start,$end);
        return $response->withJson($events->toJSON());
    }
}

function getSystemCalendarEventById(Request $request, Response $response, array $args)
{
    $Calendar = SystemCalendars::getCalendarById($args['id']);

    if ($Calendar) {
        $event = $Calendar->getEventById($args['eventid']);
        return $response->withJson($event->toJSON());
    }
}


function getSystemCalendarFullCalendarEvents($request, Response $response, $args)
{
    $Calendar = SystemCalendars::getCalendarById($args['id']);
    $start = $request->getQueryParam("start", "");
    $end = $request->getQueryParam("end", "");
    if (!$Calendar) {
        return $response->withStatus(404);
    }
    $Events = $Calendar->getEvents($start,$end);
    if (!$Events) {
        return $response->withStatus(404);
    }
    return $response->write(json_encode(EventsObjectCollectionToFullCalendar($Events, SystemCalendars::toPropelCalendar($Calendar))));
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
            return $response->withJson($Events->toJSON());
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
    $start = $request->getQueryParam("start", "");
    $end = $request->getQueryParam("end", "");
    $Events = EventQuery::create()
        ->filterByStart(array("min" => $start))
        ->filterByEnd(array("max" => $end))
        ->filterByCalendar($calendar)
        ->find();
    if (!$Events) {
        return $response->withStatus(404);
    }
    return $response->write(json_encode(EventsObjectCollectionToFullCalendar($Events, $calendar)));
}

function EventsObjectCollectionToFullCalendar(ObjectCollection $Events, Calendar $Calendar)
{
    $formattedEvents = [];
    foreach ($Events as $event) {
        $fce = new FullCalendarEvent();
        $fce->createFromEvent($event, $Calendar);
        array_push($formattedEvents, $fce);
    }
    return $formattedEvents;
}

function NewAccessToken($request, Response $response, $args)
{

    if (!isset($args['id'])) {
        return $response->withStatus(400, gettext("Invalid request: Missing calendar id"));
    }
    $Calendar = CalendarQuery::create()
        ->findOneById($args['id']);
    if (!$Calendar) {
        return $response->withStatus(404, gettext("Not Found: Unknown calendar id") . ": " . $args['id']);
    }
    $Calendar->setAccessToken(ChurchCRM\Utils\MiscUtils::randomToken());
    $Calendar->save();
    return $Calendar->toJSON();
}

function DeleteAccessToken($request, Response $response, $args)
{
  if (!isset($args['id'])) {
    return $response->withStatus(400, gettext("Invalid request: Missing calendar id"));
  }
  $Calendar = CalendarQuery::create()
      ->findOneById($args['id']);
  if (!$Calendar) {
    return $response->withStatus(404, gettext("Not Found: Unknown calendar id") . ": " . $args['id']);
  }
  $Calendar->setAccessToken(null);
  $Calendar->save();
  return $Calendar->toJSON();
}

function NewCalendar(Request $request, Response $response, $args)
{
  $input = (object)$request->getParsedBody();
  $Calendar = new Calendar();
  $Calendar->setName($input->Name);
  $Calendar->setForegroundColor($input->ForegroundColor);
  $Calendar->setBackgroundColor($input->BackgroundColor);
  $Calendar->save();
  return $response->withJson($Calendar->toArray());

}

function deleteUserCalendar(Request $request, Response $response, $args)
{
   if (!isset($args['id'])) {
        return $response->withStatus(400, gettext("Invalid request: Missing calendar id"));
    }
    $Calendar = CalendarQuery::create()
        ->findOneById($args['id']);
    if (!$Calendar) {
        return $response->withStatus(404, gettext("Not Found: Unknown calendar id") . ": " . $args['id']);
    }
    $Calendar->delete();
    return $response->withJson(array("status"=>"success"));
}
