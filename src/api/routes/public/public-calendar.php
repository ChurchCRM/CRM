<?php

// Routes

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\iCal;
use ChurchCRM\Slim\Middleware\Request\PublicCalendarAPIMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/public/calendar', function (RouteCollectorProxy $group) {
    $group->get('/{CalendarAccessToken}/events', 'getJSON');
    $group->get('/{CalendarAccessToken}/ics', 'getICal');
    $group->get('/{CalendarAccessToken}/fullcalendar', 'getPublicCalendarFullCalendarEvents');
})->add(PublicCalendarAPIMiddleware::class);

function getJSON(Request $request, Response $response)
{
    $events = $request->getAttribute('events');

    return $response->withJson($events->toArray());
}

function getICal($request, $response)
{
    $calendar = $request->getAttribute('calendar');
    $events = $request->getAttribute('events');
    $calendarName = $calendar->getName().': '.ChurchMetaData::getChurchName();
    $CalendarICS = new iCal($events, $calendarName);
    $body = $response->getBody();
    $body->write($CalendarICS->toString());

    return $response->withHeader('Content-type', 'text/calendar; charset=utf-8')
      ->withHeader('Content-Disposition', 'attachment; filename=calendar.ics');
}

function getPublicCalendarFullCalendarEvents($request, Response $response)
{
    $calendar = $request->getAttribute('calendar');
    $events = $request->getAttribute('events');

    return $response->write(json_encode(EventsObjectCollectionToFullCalendar($events, $calendar), JSON_THROW_ON_ERROR));
}
