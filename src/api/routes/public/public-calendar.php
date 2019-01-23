<?php

// Routes

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\iCal;
use ChurchCRM\Slim\Middleware\Request\PublicCalendarAPIMiddleware;
use Slim\Http\Request;
use Slim\Http\Response;

$app->group('/public/calendar', function () {
    $this->get('/{CalendarAccessToken}/events', 'getJSON');
    $this->get('/{CalendarAccessToken}/ics', 'getICal');
    $this->get('/{CalendarAccessToken}/fullcalendar', 'getPublicCalendarFullCalendarEvents');
})->add(new PublicCalendarAPIMiddleware());

function getJSON(Request $request, Response $response)
{
  $events = $request->getAttribute("events");
  return $response->withJson($events->toArray());
}

function getICal($request, $response)
{
  $calendar = $request->getAttribute("calendar");
  $events = $request->getAttribute("events");
  $calendarName = $calendar->getName() . ": " . ChurchMetaData::getChurchName();
  $CalendarICS = new iCal($events, $calendarName);
  $body = $response->getBody();
  $body->write($CalendarICS->toString());

  return $response->withHeader('Content-type', 'text/calendar; charset=utf-8')
      ->withHeader('Content-Disposition', 'attachment; filename=calendar.ics');;
}

function getPublicCalendarFullCalendarEvents($request, Response $response)
{
  $calendar = $request->getAttribute("calendar");
  $events = $request->getAttribute("events");
  return $response->write(json_encode(EventsObjectCollectionToFullCalendar($events, $calendar)));
}
