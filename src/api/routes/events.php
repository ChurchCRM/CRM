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
use ChurchCRM\Base\EventTypesQuery;
use ChurchCRM\Event;
use ChurchCRM\EventCountsQuery;
use ChurchCRM\EventCounts;
use ChurchCRM\Service\CalendarService;
use ChurchCRM\dto\MenuEventsCount;
use ChurchCRM\Utils\InputUtils;
use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\dto\FullCalendarEvent;

$app->group('/events', function () {

  $this->get('/', 'getAllEvents');
  $this->get('', 'getAllEvents');
  $this->get('/fullcalendar', 'getFullCalendarEvents');
  $this->get('/{id}/primarycontact', 'getEventPrimaryContact');
  $this->get('/{id}/secondarycontact', 'getEventSecondaryContact');
  $this->get('/{id}/location', 'getEventLocation');
  $this->get('/{id}/audience', 'getEventAudience');
  $this->get('/notDone', 'getEventsNotDone');
  $this->get('/numbers', 'getEventsNumbers');
  $this->get('/calendars', 'getEventsCalendars');
  $this->post('/', 'newOrUpdateEvent');
});

function getAllEvents($request, Response $response, $args) {
  $Events = EventQuery::create()
          ->find();
  if ($Events) {
    return $response->write($Events->toJSON());
  }
  return $response->withStatus(404);
}

function getFullCalendarEvents($request, Response $response, $args) {
  $start = $request->getQueryParam("start","");
  $end = $request->getQueryParam("end","");
  $Events = EventQuery::create()
          ->filterByStart(array("min"=>$start))
          ->filterByEnd(array("max"=>$end))
          ->find();
  if ($Events) {
    $formattedEvents = [];
    foreach ($Events as $event){
      $fce = new FullCalendarEvent();
        $fce->createFromEvent($event);
      array_push($formattedEvents, $fce );
    }
    return $response->write(json_encode($formattedEvents));
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

function getEventAudience($request, $response, $args) {
  $Audience = EventQuery::create()
          ->findOneById($args['id'])
          ->getEventAudiencesJoinGroup();
  if ($Audience) {
    return $response->write($Audience->toJSON());
  }
  return $response->withStatus(404);
}

function getEventsNotDone($request, $response, $args) {
  $Events = EventQuery::create()
          ->filterByEnd(new DateTime(), Propel\Runtime\ActiveQuery\Criteria::GREATER_EQUAL)
          ->find();
  if ($Events) {
    return $response->write($Events->toJSON());
  }
  return $response->withStatus(404);
}

function getEventsNumbers($request, $response, $args) {
  $response->withJson(MenuEventsCount::getNumberEventsOfToday());
}

function getEventsCalendars($request, $response, $args) {
  $eventTypes = EventTypesQuery::Create()
          ->orderByName()
          ->find();

  $return = [];
  foreach ($eventTypes as $eventType) {
    $values['eventTypeID'] = $eventType->getID();
    $values['name'] = $eventType->getName();

    array_push($return, $values);
  }
  if ($return) {
    return $response->withJson($return);
  }
  return $response->withStatus(404);
}

function newOrUpdateEvent($request, $response, $args) {
  $input = (object) $request->getParsedBody();

  if (!strcmp($input->evntAction, 'createEvent')) {
    $eventTypeName = "";

    $EventGroupType = $input->EventGroupType; // for futur dev : personal or group

    if ($input->eventTypeID) {
      $type = EventTypesQuery::Create()
              ->findOneById($input->eventTypeID);
      $eventTypeName = $type->getName();
    }

    $event = new Event;
    $event->setTitle($input->EventTitle);
    $event->setType($input->eventTypeID);
    $event->setTypeName($eventTypeName);
    $event->setDesc($input->EventDesc);
    $event->setPubliclyVisible($input->EventPubliclyVisible);

    if ($input->EventGroupID > 0)
      $event->setGroupId($input->EventGroupID);

    $event->setStart(str_replace("T", " ", $input->start));
    $event->setEnd(str_replace("T", " ", $input->end));
    $event->setText(InputUtils::FilterHTML($input->eventPredication));
    $event->save();

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

    $realCalEvnt = $this->CalendarService->createCalendarItem('event', $event->getTitle(), $event->getStart('Y-m-d H:i:s'), $event->getEnd('Y-m-d H:i:s'), $event->getEventURI(), $event->getId(), $event->getType(), $event->getGroupId()); // only the event id sould be edited and moved and have custom color

    return $response->withJson(array_filter($realCalEvnt));
  } else if ($input->evntAction == 'moveEvent') {
    $event = EventQuery::Create()
            ->findOneById($input->eventID);


    $oldStart = new DateTime($event->getStart('Y-m-d H:i:s'));
    $oldEnd = new DateTime($event->getEnd('Y-m-d H:i:s'));

    $newStart = new DateTime(str_replace("T", " ", $input->start));

    if ($newStart < $oldStart) {
      $interval = $oldStart->diff($newStart);
      $newEnd = $oldEnd->add($interval);
    } else {
      $interval = $newStart->diff($oldStart);
      $newEnd = $oldEnd->sub($interval);
    }

    $event->setStart($newStart->format('Y-m-d H:i:s'));
    $event->setEnd($newEnd->format('Y-m-d H:i:s'));
    $event->save();

    $realCalEvnt = $this->CalendarService->createCalendarItem('event', $event->getTitle(), $event->getStart('Y-m-d H:i:s'), $event->getEnd('Y-m-d H:i:s'), $event->getEventURI(), $event->getId(), $event->getType(), $event->getGroupId()); // only the event id sould be edited and moved and have custom color

    return $response->withJson(array_filter($realCalEvnt));
  } else if (!strcmp($input->evntAction, 'retriveEvent')) {
    $event = EventQuery::Create()
            ->findOneById($input->eventID);

    $realCalEvnt = $this->CalendarService->createCalendarItem('event', $event->getTitle(), $event->getStart('Y-m-d H:i:s'), $event->getEnd('Y-m-d H:i:s'), $event->getEventURI(), $event->getId(), $event->getType(), $event->getGroupId()); // only the event id sould be edited and moved and have custom color

    return $response->withJson(array_filter($realCalEvnt));
  } else if (!strcmp($input->evntAction, 'resizeEvent')) {
    $event = EventQuery::Create()
            ->findOneById($input->eventID);

    $event->setEnd(str_replace("T", " ", $input->end));
    $event->save();

    $realCalEvnt = $this->CalendarService->createCalendarItem('event', $event->getTitle(), $event->getStart('Y-m-d H:i:s'), $event->getEnd('Y-m-d H:i:s'), $event->getEventURI(), $event->getId(), $event->getType(), $event->getGroupId()); // only the event id sould be edited and moved and have custom color

    return $response->withJson(array_filter($realCalEvnt));
  }
}
