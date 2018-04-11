<?php

use ChurchCRM\Base\EventQuery;
use ChurchCRM\Base\EventTypeQuery;
use ChurchCRM\CalendarQuery;
use ChurchCRM\Event;
use ChurchCRM\EventCounts;
use ChurchCRM\Slim\Middleware\Request\Auth\AddEventsRoleAuthMiddleware;
use ChurchCRM\Slim\Middleware\EventsMiddleware;
use ChurchCRM\Utils\InputUtils;
use Slim\Http\Response;
use Slim\Http\Request;

$app->group('/events', function () {

    $this->get('/', 'getAllEvents');
    $this->get('', 'getAllEvents');
    $this->get("/types", "getEventTypes");
    $this->get('/{id}', 'getEvent')->add(new EventsMiddleware);
    $this->get('/{id}/', 'getEvent')->add(new EventsMiddleware);
    $this->get('/{id}/primarycontact', 'getEventPrimaryContact');
    $this->get('/{id}/secondarycontact', 'getEventSecondaryContact');
    $this->get('/{id}/location', 'getEventLocation');
    $this->get('/{id}/audience', 'getEventAudience');

    $this->post('/', 'newEvent')->add(new AddEventsRoleAuthMiddleware());
    $this->post('', 'newEvent')->add(new AddEventsRoleAuthMiddleware());
    $this->post('/{id}', 'updateEvent')->add(new AddEventsRoleAuthMiddleware())->add(new EventsMiddleware);
    $this->post('/{id}/time', 'setEventTime')->add(new AddEventsRoleAuthMiddleware());

    $this->delete("/{id}", 'deleteEvent')->add(new AddEventsRoleAuthMiddleware());

});

function getAllEvents($request, Response $response, $args)
{
    $Events = EventQuery::create()
        ->find();
    if ($Events) {
        return $response->write($Events->toJSON());
    }
    return $response->withStatus(404);
}

function getEventTypes($request, Response $response, $args)
{
    $EventTypes = EventTypeQuery::Create()
        ->orderByName()
        ->find();
    if ($EventTypes) {
        return $response->write($EventTypes->toJSON());
    }
    return $response->withStatus(404);
}

function getEvent(Request $request, Response $response, $args)
{
    $Event = $request->getAttribute("event");
    return $response->write($Event->toJSON());
}

function getEventPrimaryContact($request, $response, $args)
{
    $Event = EventQuery::create()
        ->findOneById($args['id']);
    if ($Event) {
        $Contact = $Event->getPersonRelatedByPrimaryContactPersonId();
        if ($Contact) {
            return $response->write($Contact->toJSON());
        }
    }
    return $response->withStatus(404);
}

function getEventSecondaryContact($request, $response, $args)
{
    $Contact = EventQuery::create()
        ->findOneById($args['id'])
        ->getPersonRelatedBySecondaryContactPersonId();
    if ($Contact) {
        return $response->write($Contact->toJSON());
    }
    return $response->withStatus(404);
}

function getEventLocation($request, $response, $args)
{
    $Location = EventQuery::create()
        ->findOneById($args['id'])
        ->getLocation();
    if ($Location) {
        return $response->write($Location->toJSON());
    }
    return $response->withStatus(404);
}

function getEventAudience($request, Response $response, $args)
{
    $Audience = EventQuery::create()
        ->findOneById($args['id'])
        ->getEventAudiencesJoinGroup();
    if ($Audience) {
        return $response->write($Audience->toJSON());
    }
    return $response->withStatus(404);
}

function newEvent($request, $response, $args)
{
    $input = (object)$request->getParsedBody();
    $eventTypeName = "";

    //fetch all related event objects before committing this event.
    $type = EventTypeQuery::Create()
        ->findOneById($input->eventTypeID);
    if (!$type) {
        return $response->withStatus(400, gettext("invalid event type id"));
    }

    $calendars = CalendarQuery::create()
        ->filterById($input->eventCalendars)
        ->find();
    if (count($calendars) != count($input->eventCalendars)) {
        return $response->withStatus(400, gettext("invalid calendar pinning"));
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

    return $response->withJSON(array("status" => "success"));
}

function updateEvent($request, $response, $args)
{
    
   
    $e=new Event();
    //$e->getId();
    $input = $request->getParsedBody();
    $Event = $request->getAttribute("event");
    $id = $Event->getId();
    $Event->fromArray($input);
    $Event->setId($id);
   
    $Event->save();    
}

function setEventTime($request, Response $response, $args)
{
    $input = (object)$request->getParsedBody();

    $event = EventQuery::Create()
        ->findOneById($args['id']);
    if (!$event) {
        return $response->withStatus(404);
    }
    $event->setStart($input->startTime);
    $event->setEnd($input->endTime);
    $event->save();
    return $response->withJson(array("status" => "success"));

}


function unusedSetEventAttendance()
{
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

function deleteEvent($request, $response, $args)
{
    $input = (object)$request->getParsedBody();

    $event = EventQuery::Create()
        ->findOneById($args['id']);
    if (!$event) {
        return $response->withStatus(404);
    }
    $event->delete();
    return $response->withJson(array("status" => "success"));
}
