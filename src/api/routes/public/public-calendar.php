<?php

// Routes

use ChurchCRM\Utils\InputUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use ChurchCRM\dto\iCal;
use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\Calendar;
use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\Slim\Middleware\PublicCalendarAPIMiddleware;

$app->group('/public/calendar', function () {
    $this->get('/{CalendarAccessToken}/events', 'getJSON');
    $this->get('/{CalendarAccessToken}/ics','getICal');
})->add(new PublicCalendarAPIMiddleware());

function getJSON ($request, $response) {
  $calendar = getCalendar($request);
  $events = getEvents($request, $calendar);
  return $response->withJson($events->toArray());
}

function getICal ($request, $response, $args) {
  $calendar = getCalendar($request);
  $calendarName = ChurchMetaData::getChurchName().": ".$calendar->getName();
  $events = getEvents($request, $calendar);
  $CalendarICS = new iCal($events, $calendarName);
  $body = $response->getBody();
  $body->write($CalendarICS->toString());

  return $response->withHeader('Content-type','text/calendar; charset=utf-8')
     ->withHeader('Content-Disposition','attachment; filename=calendar.ics');;
}

function getCalendar(Request $request) {
  $CAT = $request->getAttribute("route")->getArgument("CalendarAccessToken");
  if(empty(trim($CAT)))
  {
    throw new \Exception("Missing calendar access token");
  }
  $calendar = ChurchCRM\CalendarQuery::create()
          ->filterByAccessToken($CAT)
          ->findOne();
  if (empty($calendar)) {
    throw new \Exception(gettext("Invalid calendar access token"));
  }
  return $calendar;
}

function getEvents(Request $request, Calendar $calendar){
  $params = $request->getQueryParams();
  if (isset($params['start']))
  {
    $start_date = DateTime::createFromFormat("Y-m-d",$params['start']);
  }
  else
  {
    $start_date = new DateTime();
  }
  $start_date->setTime(0,0,0);
  $max_events = InputUtils::FilterInt($params['max']);
  
  $events = ChurchCRM\EventQuery::create()
          ->joinCalendarEvent()
          ->useCalendarEventQuery()
            ->filterByCalendar($calendar)
          ->endUse();

  if($start_date) {
    $events->filterByStart($start_date,  Criteria::GREATER_EQUAL);
  }

  if ($max_events) {
    $events->limit($max_events);
  }

  return $events->find();
}