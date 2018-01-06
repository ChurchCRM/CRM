<?php

// Routes

use ChurchCRM\Utils\InputUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\Slim\Middleware\PublicCalendarAPIMiddleware;

$app->group('/calendar', function () {
    $this->get('/events', 'getJSON');
    $this->get('/ics','getICal');
})->add(new \ChurchCRM\Slim\Middleware\PublicCalendarAPIMiddleware());

function getJSON ($request, $response, $args) {
  $events = getEvents($request);
  return $response->withJson($events->toArray());
}

function getICal ($request, $response, $args) {
  $events = getEvents($request);

  $CalendarICS = "BEGIN:VCALENDAR\r\n".
                 "VERSION:2.0\r\n".
                 "PRODID:-//ChurchCRM/CRM//NONSGML v".$_SESSION['sSoftwareInstalledVersion']."//EN\r\n".
                 "CALSCALE:GREGORIAN\r\n".
                 "METHOD:PUBLISH\r\n".
                 "X-WR-CALNAME:".ChurchMetaData::getChurchName()."\r\n".
                 "X-WR-CALDESC:\r\n";

  foreach($events as $event)
  {
    $CalendarICS .= $event->toVEVENT();
  }
  $CalendarICS .="END:VCALENDAR";

  $body = $response->getBody();
  $body->write($CalendarICS);

  return $response->withHeader('Content-type','text/calendar; charset=utf-8')
     ->withHeader('Content-Disposition','attachment; filename=calendar.ics');;
}

function getEvents($request){
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
          ->filterByPubliclyVisible(true)
          ->orderByStart(Criteria::ASC);

  if($start_date) {
    $events->filterByStart($start_date,  Criteria::GREATER_EQUAL);
  }

  if ($max_events) {
    $events->limit($max_events);
  }

  return $events->find();
}