<?php

// Routes

use ChurchCRM\Utils\InputUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use ChurchCRM\dto\iCal;
use ChurchCRM\Slim\Middleware\PublicCalendarAPIMiddleware;

$app->group('/public/calendar', function () {
    $this->get('/events', 'getJSON');
    $this->get('/ics','getICal');
})->add(new PublicCalendarAPIMiddleware());

function getJSON ($request, $response, $args) {
  $events = getPublicEvents($request);
  return $response->withJson($events->toArray());
}

function getICal ($request, $response, $args) {
  $CalendarICS = new iCal(getPublicEvents($request));
  $body = $response->getBody();
  $body->write($CalendarICS->toString());

  return $response->withHeader('Content-type','text/calendar; charset=utf-8')
     ->withHeader('Content-Disposition','attachment; filename=calendar.ics');;
}

function getPublicEvents($request){
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