<?php

use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\Service\CalendarService;
use ChurchCRM\CalendarQuery;
use ChurchCRM\Calendar;
use ChurchCRM\EventQuery;
use ChurchCRM\dto\FullCalendarEvent;
use ChurchCRM\dto\SystemCalendars;
use Propel\Runtime\Collection\ObjectCollection;

$app->group('/calendars', function () {
    $this->get('','getUserCalendars');
    $this->get('/','getUserCalendars');
    $this->get('/{id}','getUserCalendars');
    $this->get('/{id}/events', 'UserCalendar');
    $this->get('/{id}/fullcalendar', 'getUserCalendarFullCalendarEvents');
    $this->post('/{id}/NewAccessToken', 'NewAccessToken');
});


$app->group('/systemcalendars', function () {
    $this->get('','getSystemCalendars');
    $this->get('/','getSystemCalendars');
    $this->get('/{id}/events', 'getSystemCalendarEvents');
    $this->get('/{id}/events/{eventid}', 'getSystemCalendarEventById');
    $this->get('/{id}/fullcalendar', 'getSystemCalendarFullCalendarEvents');
});


function getSystemCalendars(Request $request, Response $response, array $args ) {
  return $response->write(SystemCalendars::getCalendarList()->toJSON());
}

function getSystemCalendarEvents(Request $request, Response $response, array $args ) {
    $Calendar = SystemCalendars::getCalendarById($args['id']);

    if ($Calendar) {
      $events=  $Calendar->getEvents();
      
      echo $events->toJSON();
      die();
      return $response->withJson($Calendar->toJSON());
    }
}

function getSystemCalendarEventById(Request $request, Response $response, array $args ) {
    $Calendar = SystemCalendars::getCalendarById($args['id']);

    if ($Calendar) {
      $event=  $Calendar->getEventById($args['eventid']);
      echo $event->toJSON();
      die();
      return $response->withJson($Calendar->toJSON());
    }
}



function getSystemCalendarFullCalendarEvents($request, Response $response, $args) {
  $Calendar = SystemCalendars::getCalendarById($args['id']);
  if (!$Calendar) {
     return $response->withStatus(404);
  }
  $Events = $Calendar->getEvents();
  if (!$Events) {
    return $response->withStatus(404);
  }
  return $response->write(json_encode(EventsObjectCollectionToFullCalendar($Events,SystemCalendars::toPropelCalendar($Calendar))));
}


function getUserCalendars(Request $request, Response $response, array $args ) {
  $CalendarQuery = CalendarQuery::create();
  if (isset($args['id']))
  {
    $CalendarQuery->filterById($args['id']);
  }
  $Calendars = $CalendarQuery->find();
  if ($Calendars) 
  {
    return $response->write($Calendars->toJSON());
  }
}

function getUserCalendarEvents(Request $request, Response $response, array $p_args ) {
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

function getUserCalendarFullCalendarEvents($request, Response $response, $args) {
  $CalendarID = $args['id'];
  $calendar = CalendarQuery::create()
          ->findOneById($CalendarID);
  if (!$calendar)
  {
    return $response->withStatus(404);
  }     
  $start = $request->getQueryParam("start","");
  $end = $request->getQueryParam("end","");
  $Events = EventQuery::create()
          ->filterByStart(array("min"=>$start))
          ->filterByEnd(array("max"=>$end))
          ->filterByCalendar($calendar)
          ->find();
  if (!$Events) {
     return $response->withStatus(404);
  }
  return $response->write(json_encode(EventsObjectCollectionToFullCalendar($Events,$calendar)));
}

function EventsObjectCollectionToFullCalendar(ObjectCollection $Events, Calendar $Calendar){
  $formattedEvents = [];
  foreach ($Events as $event){
    $fce = new FullCalendarEvent();
      $fce->createFromEvent($event,$Calendar);
    array_push($formattedEvents, $fce );
  }
  return $formattedEvents;
}

function NewAccessToken($request, Response $response, $args) {
  
  if (!isset($args['id']))
  {
    throw new \Exception(gettext("Missing calendar id"));
  }
  $Calendar = CalendarQuery::create()
              ->findOneById($args['id']);
  if (!$Calendar) {
    throw new \Exception (gettext("Invalid Calendar id"));
  }
  $Calendar->setAccessToken(ChurchCRM\Utils\MiscUtils::randomToken());
  $Calendar->save();
  return $Calendar->toJSON();
}