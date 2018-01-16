<?php

/* * *****************************************************************************
 *
 *  filename    : events.php
 *  last change : 2017-11-16
 *  description : manage the full calendar with events
 *
 *  http://www.churchcrm.io/
 *  Copyright 2017 Logel Philippe
 *
 * **************************************************************************** */

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Base\EventQuery;
use ChurchCRM\Base\EventTypeQuery;
use ChurchCRM\Event;
use ChurchCRM\EventCountsQuery;
use ChurchCRM\EventCounts;
use ChurchCRM\Service\CalendarService;
use ChurchCRM\dto\MenuEventsCount;
use ChurchCRM\Utils\InputUtils;
use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\CalendarQuery;

$app->group('/events', function () {

  $this->get('/', 'getAllEvents');
  $this->get('', 'getAllEvents');
  $this->get("/types","getEventTypes");  
  $this->get('/{id}', 'getEvent');
  $this->get('/{id}/', 'getEvent');
  $this->get('/{id}/primarycontact', 'getEventPrimaryContact');
  $this->get('/{id}/secondarycontact', 'getEventSecondaryContact');
  $this->get('/{id}/location', 'getEventLocation');
  $this->get('/{id}/audience', 'getEventAudience');
  
  $this->post('/', 'newEvent');
  $this->post('', 'newEvent');
  $this->post('/{id}', 'updateEvent');
  $this->post('/{id}/time', 'setEventTime');
  
});

function getAllEvents($request, Response $response, $args) {
  $Events = EventQuery::create()
          ->find();
  if ($Events) {
    return $response->write($Events->toJSON());
  }
  return $response->withStatus(404);
}

function getEventTypes($request, Response $response, $args) {
  $EventTypes = EventTypeQuery::Create()
          ->orderByName()
          ->find();
  if ($EventTypes) {
    return $response->write($EventTypes->toJSON());
  }
  return $response->withStatus(404);
}

function getEvent($request, Response $response, $args) {
  $Event = EventQuery::Create()
          ->findOneById($args['id']);
  if ($Event) {
    return $response->write($Event->toJSON());
  }
  return $response->withStatus(404);
}

function getEventPrimaryContact($request, $response, $args) {
  $Event = EventQuery::create()
          ->findOneById($args['id']);
  if ($Event) {
    $Contact = $Event->getPersonRelatedByPrimaryContactPersonId();
    if($Contact) { 
      return $response->write($Contact->toJSON());
    }
  }
  return $response->withStatus(404);
}

function getEventSecondaryContact($request, $response, $args) {
  $Contact = EventQuery::create()
          ->findOneById($args['id'])
          ->getPersonRelatedBySecondaryContactPersonId();
  if ($Contact) {
    return $response->write($Contact->toJSON());
  }
  return $response->withStatus(404);
}

function getEventLocation($request, $response, $args) {
  $Location = EventQuery::create()
          ->findOneById($args['id'])
          ->getLocation();
  if ($Location) {
    return $response->write($Location->toJSON());
  }
  return $response->withStatus(404);
}

function getEventAudience($request, Response $response, $args) {
  $Audience = EventQuery::create()
          ->findOneById($args['id'])
          ->getEventAudiencesJoinGroup();
  if ($Audience) {
    return $response->write($Audience->toJSON());
  }
  return $response->withStatus(404);
}

function newEvent($request, $response, $args) {
  $input = (object) $request->getParsedBody();
  $eventTypeName = "";
  
  //fetch all related event objects before committing this event.
  $type = EventTypeQuery::Create()
          ->findOneById($input->eventTypeID);
  if (!$type)
  {
    return $response->withStatus(400)->withJSON(array("status"=>"invalid event type id"));
  }

  $calendars = CalendarQuery::create()
          ->filterById($input->eventCalendars)
          ->find();
  if (count($calendars) != count($input->eventCalendars)){
    return $response->withStatus(400)->withJSON(array("status"=>"invalid calendar pinning"));
  }
  
  // we have event type and pined calendars.  now create the event.
  $event = new Event;
  $event->setTitle($input->EventTitle);
  $event->setType($type);
  $event->setDesc($input->EventDesc);
  $event->setStart(str_replace("T", " ", $input->start));
  $event->setEnd(str_replace("T", " ", $input->end));
  $event->setText(InputUtils::FilterHTML($input->eventPredication));
  $event->setCalendars($calendars);
  $event->save();

  return $response->withJSON(array("status"=>"success"));
}

function updateEvent($request, $response, $args) {
  $input = (object) $request->getParsedBody();
  
}
function setEventTime ($request, Response $response, $args) {
  $input = (object) $request->getParsedBody();

  $event = EventQuery::Create()
    ->findOneById($args['id']);
  if(!$event) {
    return $response->withStatus(404);
  }
  $event->setStart($input->startTime);
  $event->setEnd($input->endTime);
  $event->save();
  return $response->withJson(array("status"=>"success"));
  
}


function unusedSetEventAttendance() {
  if ($input->Total > 0 || $input->Visitors || $input->Members) {
    $eventCount = new EventCounts;
    $eventCount->setEvtcntEventid($event->getID());
    $eventCount->setEvtcntCountid(1);
    $eventCount->setEvtcntCountname('Total');
    $eventCount->setEvtcntCountcount($input->Total);
    $eventCount->setEvtcntNotes($input->EventCountNotes);
    $eventCount->save();

    $eventCount = new EventCounts;
    $eventCount->setEvtcntEventid($event->getID());
    $eventCount->setEvtcntCountid(2);
    $eventCount->setEvtcntCountname('Members');
    $eventCount->setEvtcntCountcount($input->Members);
    $eventCount->setEvtcntNotes($input->EventCountNotes);
    $eventCount->save();

    $eventCount = new EventCounts;
    $eventCount->setEvtcntEventid($event->getID());
    $eventCount->setEvtcntCountid(3);
    $eventCount->setEvtcntCountname('Visitors');
    $eventCount->setEvtcntCountcount($input->Visitors);
    $eventCount->setEvtcntNotes($input->EventCountNotes);
    $eventCount->save();
  }
}