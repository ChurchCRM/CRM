<?php

use ChurchCRM\model\ChurchCRM\Base\EventQuery;
use ChurchCRM\model\ChurchCRM\Base\EventTypeQuery;
use ChurchCRM\model\ChurchCRM\CalendarQuery;
use ChurchCRM\model\ChurchCRM\Event;
use ChurchCRM\model\ChurchCRM\EventCounts;
use ChurchCRM\Slim\Middleware\EventsMiddleware;
use ChurchCRM\Slim\Middleware\Request\Auth\AddEventsRoleAuthMiddleware;
use ChurchCRM\Utils\InputUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/events', function (RouteCollectorProxy $group) {
    $group->get('/', 'getAllEvents');
    $group->get('', 'getAllEvents');
    $group->get('/types', 'getEventTypes');
    $group->get('/{id}', 'getEvent')->add(new EventsMiddleware());
    $group->get('/{id}/', 'getEvent')->add(new EventsMiddleware());
    $group->get('/{id}/primarycontact', 'getEventPrimaryContact');
    $group->get('/{id}/secondarycontact', 'getEventSecondaryContact');
    $group->get('/{id}/location', 'getEventLocation');
    $group->get('/{id}/audience', 'getEventAudience');

    $group->post('/', 'newEvent')->add(new AddEventsRoleAuthMiddleware());
    $group->post('', 'newEvent')->add(new AddEventsRoleAuthMiddleware());
    $group->post('/{id}', 'updateEvent')->add(new AddEventsRoleAuthMiddleware())->add(new EventsMiddleware());
    $group->post('/{id}/time', 'setEventTime')->add(new AddEventsRoleAuthMiddleware());

    $group->delete('/{id}', 'deleteEvent')->add(new AddEventsRoleAuthMiddleware());
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
    $Event = $request->getAttribute('event');

    return $response->write($Event->toJSON());
}

function getEventPrimaryContact(Request $request, Response $response, array $args)
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

function getEventSecondaryContact(Request $request, Response $response, array $args)
{
    $Contact = EventQuery::create()
        ->findOneById($args['id'])
        ->getPersonRelatedBySecondaryContactPersonId();
    if ($Contact) {
        return $response->write($Contact->toJSON());
    }

    return $response->withStatus(404);
}

function getEventLocation(Request $request, Response $response, array $args)
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

function newEvent(Request $request, Response $response, array $args)
{
    $input = (object)$request->getParsedBody();

    //fetch all related event objects before committing this event.
    $type = EventTypeQuery::Create()
        ->findOneById($input->Type);
    if (!$type) {
        return $response->withStatus(400, gettext('invalid event type id'));
    }

    $calendars = CalendarQuery::create()
        ->filterById($input->PinnedCalendars)
        ->find();
    if (count($calendars) != count($input->PinnedCalendars)) {
        return $response->withStatus(400, gettext('invalid calendar pinning'));
    }

    // we have event type and pined calendars.  now create the event.
    $event = new Event();
    $event->setTitle($input->Title);
    $event->setEventType($type);
    $event->setDesc($input->Desc);
    $event->setStart(str_replace('T', ' ', $input->Start));
    $event->setEnd(str_replace('T', ' ', $input->End));
    $event->setText(InputUtils::filterHTML($input->Text));
    $event->setCalendars($calendars);
    $event->save();

    $response->getBody()->write(json_encode(['status' => 'success']));

    return $response->withHeader('Content-Type', 'application/json');
}

function updateEvent(Request $request, Response $response, array $args)
{
    $e = new Event();
    //$e->getId();
    $input = $request->getParsedBody();
    $Event = $request->getAttribute('event');
    $id = $Event->getId();
    $Event->fromArray($input);
    $Event->setId($id);
    $PinnedCalendars = CalendarQuery::Create()
        ->filterById($input['PinnedCalendars'], Criteria::IN)
        ->find();
    $Event->setCalendars($PinnedCalendars);

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

    $response->getBody()->write(json_encode(['status' => 'success']));

    return $response->withHeader('Content-Type', 'application/json');
}

function unusedSetEventAttendance()
{
    if ($input->Total > 0 || $input->Visitors || $input->Members) {
        $eventCount = new EventCounts();
        $eventCount->setEvtcntEventid($event->getID());
        $eventCount->setEvtcntCountid(1);
        $eventCount->setEvtcntCountname('Total');
        $eventCount->setEvtcntCountcount($input->Total);
        $eventCount->setEvtcntNotes($input->EventCountNotes);
        $eventCount->save();

        $eventCount = new EventCounts();
        $eventCount->setEvtcntEventid($event->getID());
        $eventCount->setEvtcntCountid(2);
        $eventCount->setEvtcntCountname('Members');
        $eventCount->setEvtcntCountcount($input->Members);
        $eventCount->setEvtcntNotes($input->EventCountNotes);
        $eventCount->save();

        $eventCount = new EventCounts();
        $eventCount->setEvtcntEventid($event->getID());
        $eventCount->setEvtcntCountid(3);
        $eventCount->setEvtcntCountname('Visitors');
        $eventCount->setEvtcntCountcount($input->Visitors);
        $eventCount->setEvtcntNotes($input->EventCountNotes);
        $eventCount->save();
    }
}

function deleteEvent(Request $request, Response $response, array $args)
{
    $input = (object)$request->getParsedBody();

    $event = EventQuery::Create()
        ->findOneById($args['id']);
    if (!$event) {
        return $response->withStatus(404);
    }
    $event->delete();

    $response->getBody()->write(json_encode(['status' => 'success']));

    return $response->withHeader('Content-Type', 'application/json');
}
