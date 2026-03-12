<?php

use ChurchCRM\model\ChurchCRM\Base\EventQuery;
use ChurchCRM\model\ChurchCRM\Base\EventTypeQuery;
use ChurchCRM\model\ChurchCRM\CalendarQuery;
use ChurchCRM\model\ChurchCRM\Event;
use ChurchCRM\model\ChurchCRM\EventCounts;
use ChurchCRM\Service\EventService;
use ChurchCRM\Slim\Middleware\EventsMiddleware;
use ChurchCRM\Slim\Middleware\InputSanitizationMiddleware;
use ChurchCRM\Slim\Middleware\Request\Auth\AddEventsRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;
use Slim\Routing\RouteCollectorProxy;

$app->group('/events', function (RouteCollectorProxy $group): void {
    $group->get('/', 'getAllEvents');
    $group->get('', 'getAllEvents');
    $group->get('/types', 'getEventTypes');
    $group->get('/{id}', 'getEvent')->add(new EventsMiddleware());
    $group->get('/{id}/', 'getEvent')->add(new EventsMiddleware());
    $group->get('/{id}/primarycontact', 'getEventPrimaryContact')->add(new EventsMiddleware());
    $group->get('/{id}/secondarycontact', 'getEventSecondaryContact')->add(new EventsMiddleware());
    $group->get('/{id}/location', 'getEventLocation')->add(new EventsMiddleware());
    $group->get('/{id}/audience', 'getEventAudience')->add(new EventsMiddleware());

    $group->post('/', 'newEvent')->add(new InputSanitizationMiddleware(['Title' => 'text', 'Desc' => 'html', 'Text' => 'html']))->add(new AddEventsRoleAuthMiddleware());
    $group->post('', 'newEvent')->add(new InputSanitizationMiddleware(['Title' => 'text', 'Desc' => 'html', 'Text' => 'html']))->add(new AddEventsRoleAuthMiddleware());
    $group->post('/repeat', 'createRepeatEvents')->add(new InputSanitizationMiddleware(['Title' => 'text', 'Desc' => 'html', 'Text' => 'html']))->add(new AddEventsRoleAuthMiddleware());
    $group->post('/{id}', 'updateEvent')->add(new InputSanitizationMiddleware(['Title' => 'text', 'Desc' => 'html', 'Text' => 'html']))->add(new AddEventsRoleAuthMiddleware())->add(new EventsMiddleware());
    $group->post('/{id}/time', 'setEventTime')->add(new AddEventsRoleAuthMiddleware())->add(new EventsMiddleware());

    $group->delete('/{id}', 'deleteEvent')->add(new AddEventsRoleAuthMiddleware())->add(new EventsMiddleware());
});

/**
 * @OA\Get(
 *     path="/events",
 *     operationId="getAllEvents",
 *     summary="List all events",
 *     description="Returns all calendar events with their linked group associations.",
 *     tags={"Calendar"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="List of events",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="Events", type="array",
 *                 @OA\Items(type="object",
 *                     @OA\Property(property="Id", type="integer", example=1),
 *                     @OA\Property(property="Title", type="string", example="Sunday Service"),
 *                     @OA\Property(property="Desc", type="string", nullable=true),
 *                     @OA\Property(property="Start", type="string", format="date-time"),
 *                     @OA\Property(property="End", type="string", format="date-time"),
 *                     @OA\Property(property="Groups", type="array",
 *                         @OA\Items(type="object",
 *                             @OA\Property(property="Id", type="integer"),
 *                             @OA\Property(property="Name", type="string")
 *                         )
 *                     )
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=404, description="No events found")
 * )
 */
function getAllEvents(Request $request, Response $response, array $args): Response
{
    $Events = EventQuery::create()
        ->find();
    if (empty($Events)) {
        throw new HttpNotFoundException($request);
    }

    // Build response with linked groups included
    $eventsArray = [];
    foreach ($Events as $event) {
        $eventData = $event->toArray();
        // Add linked groups
        $groups = $event->getGroups();
        $groupsArray = [];
        foreach ($groups as $group) {
            $groupsArray[] = [
                'Id' => $group->getId(),
                'Name' => $group->getName()
            ];
        }
        $eventData['Groups'] = $groupsArray;
        $eventsArray[] = $eventData;
    }

    return SlimUtils::renderJSON($response, ['Events' => $eventsArray]);
}

/**
 * @OA\Get(
 *     path="/events/types",
 *     operationId="getEventTypes",
 *     summary="List all event types",
 *     tags={"Calendar"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Ordered list of event types",
 *         @OA\JsonContent(type="array",
 *             @OA\Items(type="object",
 *                 @OA\Property(property="Id", type="integer", example=1),
 *                 @OA\Property(property="Name", type="string", example="Worship Service")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=404, description="No event types found")
 * )
 */
function getEventTypes(Request $request, Response $response, array $args): Response
{
    $EventTypes = EventTypeQuery::create()
        ->orderByName()
        ->find();
    if (empty($EventTypes)) {
        throw new HttpNotFoundException($request);
    }
    return SlimUtils::renderStringJSON($response, $EventTypes->toJSON());
}

/**
 * @OA\Get(
 *     path="/events/{id}",
 *     operationId="getEvent",
 *     summary="Get an event by ID",
 *     tags={"Calendar"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
 *     @OA\Response(response=200, description="Event object", @OA\JsonContent(type="object",
 *         @OA\Property(property="Id", type="integer"),
 *         @OA\Property(property="Title", type="string"),
 *         @OA\Property(property="Start", type="string", format="date-time"),
 *         @OA\Property(property="End", type="string", format="date-time")
 *     )),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=404, description="Event not found")
 * )
 */
function getEvent(Request $request, Response $response, $args): Response
{
    $Event = $request->getAttribute('event');

    if (empty($Event)) {
        throw new HttpNotFoundException($request);
    }
    return SlimUtils::renderStringJSON($response, $Event->toJSON());
}

/**
 * @OA\Get(
 *     path="/events/{id}/primarycontact",
 *     operationId="getEventPrimaryContact",
 *     summary="Get an event's primary contact person",
 *     tags={"Calendar"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
 *     @OA\Response(response=200, description="Person object"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=404, description="Event or primary contact not found")
 * )
 */
function getEventPrimaryContact(Request $request, Response $response, array $args): Response
{
    /** @var Event $Event */
    $Event = $request->getAttribute('event');
    $Contact = $Event->getPersonRelatedByPrimaryContactPersonId();
    if ($Contact) {
        return SlimUtils::renderStringJSON($response, $Contact->toJSON());
    }
    throw new HttpNotFoundException($request);
}

/**
 * @OA\Get(
 *     path="/events/{id}/secondarycontact",
 *     operationId="getEventSecondaryContact",
 *     summary="Get an event's secondary contact person",
 *     tags={"Calendar"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
 *     @OA\Response(response=200, description="Person object"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=404, description="Event or secondary contact not found")
 * )
 */
function getEventSecondaryContact(Request $request, Response $response, array $args): Response
{
    $Contact = $request->getAttribute('event')->getPersonRelatedBySecondaryContactPersonId();
    if (empty($Contact)) {
        throw new HttpNotFoundException($request);
    }
    return SlimUtils::renderStringJSON($response, $Contact->toJSON());
}

/**
 * @OA\Get(
 *     path="/events/{id}/location",
 *     operationId="getEventLocation",
 *     summary="Get an event's location",
 *     tags={"Calendar"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
 *     @OA\Response(response=200, description="Location object"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=404, description="Event or location not found")
 * )
 */
function getEventLocation(Request $request, Response $response, array $args): Response
{
    $Location = $request->getAttribute('event')->getLocation();
    if (empty($Location)) {
        throw new HttpNotFoundException($request);
    }

    return SlimUtils::renderStringJSON($response, $Location->toJSON());
}

/**
 * @OA\Get(
 *     path="/events/{id}/audience",
 *     operationId="getEventAudience",
 *     summary="Get an event's audience (linked groups)",
 *     tags={"Calendar"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
 *     @OA\Response(response=200, description="Array of audience/group objects"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=404, description="Event or audience not found")
 * )
 */
function getEventAudience(Request $request, Response $response, array $args): Response
{
    $Audience = $request->getAttribute('event')->getEventAudiencesJoinGroup();
    if (empty($Audience)) {
        throw new HttpNotFoundException($request);
    }

    return SlimUtils::renderStringJSON($response, $Audience->toJSON());
}

/**
 * @OA\Post(
 *     path="/events",
 *     operationId="newEvent",
 *     summary="Create a new event",
 *     tags={"Calendar"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         required={"Title","Type","Start","End","PinnedCalendars"},
 *         @OA\Property(property="Title", type="string", example="Easter Service"),
 *         @OA\Property(property="Type", type="integer", example=1, description="Event type ID from GET /events/types"),
 *         @OA\Property(property="Desc", type="string", nullable=true),
 *         @OA\Property(property="Start", type="string", format="date-time", example="2026-04-05T09:00:00"),
 *         @OA\Property(property="End", type="string", format="date-time", example="2026-04-05T11:00:00"),
 *         @OA\Property(property="Text", type="string", nullable=true, description="Rich text body (HTML allowed)"),
 *         @OA\Property(property="PinnedCalendars", type="array", @OA\Items(type="integer"), example={1})
 *     )),
 *     @OA\Response(response=200, description="Event created",
 *         @OA\JsonContent(@OA\Property(property="success", type="boolean", example=true))
 *     ),
 *     @OA\Response(response=400, description="Invalid event type or calendar ID"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="AddEvents role required")
 * )
 */
function newEvent(Request $request, Response $response, array $args): Response
{
    $input = $request->getParsedBody();

    //fetch all related event objects before committing this event.
    $type = EventTypeQuery::create()
        ->findOneById($input['Type']);
    if (empty($type)) {
        throw new HttpBadRequestException($request, gettext('invalid event type id'));
    }

    $calendars = CalendarQuery::create()
        ->filterById($input['PinnedCalendars'])
        ->find();
    if (count($calendars) !== count($input['PinnedCalendars'])) {
        throw new HttpBadRequestException($request, gettext('invalid calendar pinning'));
    }

    // we have event type and pined calendars.  now create the event.
    $event = new Event();
    $event->setTitle($input['Title']);
    $event->setEventType($type);
    $event->setDesc($input['Desc']);
    $event->setStart(str_replace('T', ' ', $input['Start']));
    $event->setEnd(str_replace('T', ' ', $input['End']));
    $event->setText($input['Text']);
    $event->setCalendars($calendars);
    $event->save();

    return SlimUtils::renderSuccessJSON($response);
}

/**
 * @OA\Post(
 *     path="/events/repeat",
 *     operationId="createRepeatEvents",
 *     summary="Create a series of repeat events",
 *     description="Generates individual event records for each occurrence of a recurring event within a date range. Each created event is independently editable.",
 *     tags={"Calendar"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         required={"Title","Type","StartTime","EndTime","RecurType","RangeStart","RangeEnd"},
 *         @OA\Property(property="Title", type="string", example="Sunday Worship Service"),
 *         @OA\Property(property="Type", type="integer", example=1, description="Event type ID from GET /events/types"),
 *         @OA\Property(property="Desc", type="string", nullable=true),
 *         @OA\Property(property="Text", type="string", nullable=true, description="Rich text body (HTML allowed)"),
 *         @OA\Property(property="StartTime", type="string", example="09:00", description="Event start time in HH:MM format"),
 *         @OA\Property(property="EndTime", type="string", example="10:30", description="Event end time in HH:MM format"),
 *         @OA\Property(property="RecurType", type="string", enum={"weekly","monthly","yearly"}, example="weekly"),
 *         @OA\Property(property="RecurDOW", type="string", example="Sunday", description="Day of week for weekly recurrence (e.g. Sunday)"),
 *         @OA\Property(property="RecurDOM", type="integer", example=1, description="Day of month (1-31) for monthly recurrence"),
 *         @OA\Property(property="RecurDOY", type="string", example="04-12", description="Month-day in MM-DD format for yearly recurrence"),
 *         @OA\Property(property="RangeStart", type="string", format="date", example="2026-01-01", description="First date of the repetition range (inclusive)"),
 *         @OA\Property(property="RangeEnd", type="string", format="date", example="2026-12-31", description="Last date of the repetition range (inclusive)"),
 *         @OA\Property(property="PinnedCalendars", type="array", @OA\Items(type="integer"), nullable=true),
 *         @OA\Property(property="LinkedGroupId", type="integer", nullable=true),
 *         @OA\Property(property="Inactive", type="integer", enum={0,1}, nullable=true)
 *     )),
 *     @OA\Response(response=200, description="Repeat events created",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="count", type="integer", example=52),
 *             @OA\Property(property="eventIds", type="array", @OA\Items(type="integer"))
 *         )
 *     ),
 *     @OA\Response(response=400, description="Invalid input (bad event type, invalid dates, unknown recurrence type)"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="AddEvents role required")
 * )
 */
function createRepeatEvents(Request $request, Response $response, array $args): Response
{
    $input = $request->getParsedBody();

    $validRecurTypes = ['weekly', 'monthly', 'yearly'];
    $recurType = $input['RecurType'] ?? '';
    if (!in_array($recurType, $validRecurTypes, true)) {
        throw new HttpBadRequestException($request, gettext('invalid recurrence type'));
    }

    try {
        $service = new EventService();
        $createdIds = $service->createRepeatEvents([
            'title'          => $input['Title'],
            'typeId'         => (int) ($input['Type'] ?? 0),
            'desc'           => $input['Desc'] ?? '',
            'text'           => $input['Text'] ?? '',
            'startTime'      => $input['StartTime'] ?? '09:00',
            'endTime'        => $input['EndTime'] ?? '10:00',
            'recurType'      => $recurType,
            'recurDOW'       => $input['RecurDOW'] ?? null,
            'recurDOM'       => isset($input['RecurDOM']) ? (int) $input['RecurDOM'] : null,
            'recurDOY'       => $input['RecurDOY'] ?? null,
            'rangeStart'     => $input['RangeStart'],
            'rangeEnd'       => $input['RangeEnd'],
            'pinnedCalendars' => array_map('intval', is_array($input['PinnedCalendars'] ?? null) ? $input['PinnedCalendars'] : []),
            'linkedGroupId'  => (int) ($input['LinkedGroupId'] ?? 0),
            'inactive'       => (int) ($input['Inactive'] ?? 0),
        ]);
    } catch (\InvalidArgumentException $e) {
        throw new HttpBadRequestException($request, $e->getMessage());
    }

    return SlimUtils::renderJSON($response, [
        'success'  => true,
        'count'    => count($createdIds),
        'eventIds' => $createdIds,
    ]);
}

/**
 * @OA\Post(
 *     path="/events/{id}",
 *     operationId="updateEvent",
 *     summary="Update an existing event",
 *     tags={"Calendar"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         @OA\Property(property="Title", type="string"),
 *         @OA\Property(property="Desc", type="string", nullable=true),
 *         @OA\Property(property="Start", type="string", format="date-time"),
 *         @OA\Property(property="End", type="string", format="date-time"),
 *         @OA\Property(property="Text", type="string", nullable=true),
 *         @OA\Property(property="PinnedCalendars", type="array", @OA\Items(type="integer"))
 *     )),
 *     @OA\Response(response=200, description="Event updated",
 *         @OA\JsonContent(@OA\Property(property="success", type="boolean", example=true))
 *     ),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="AddEvents role required"),
 *     @OA\Response(response=404, description="Event not found")
 * )
 */
function updateEvent(Request $request, Response $response, array $args): Response
{
    $input = $request->getParsedBody();
    /** @var Event $Event */
    $Event = $request->getAttribute('event');
    $id = $Event->getId();

    $Event->fromArray($input);
    $Event->setId($id);
    $PinnedCalendars = CalendarQuery::create()
        ->filterById($input['PinnedCalendars'], Criteria::IN)
        ->find();
    $Event->setCalendars($PinnedCalendars);

    $Event->save();

    return SlimUtils::renderSuccessJSON($response);
}

/**
 * @OA\Post(
 *     path="/events/{id}/time",
 *     operationId="setEventTime",
 *     summary="Update an event's start and end times",
 *     tags={"Calendar"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         required={"startTime","endTime"},
 *         @OA\Property(property="startTime", type="string", format="date-time", example="2026-04-05T09:00:00"),
 *         @OA\Property(property="endTime", type="string", format="date-time", example="2026-04-05T11:00:00")
 *     )),
 *     @OA\Response(response=200, description="Time updated",
 *         @OA\JsonContent(@OA\Property(property="success", type="boolean", example=true))
 *     ),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="AddEvents role required"),
 *     @OA\Response(response=404, description="Event not found")
 * )
 */
function setEventTime(Request $request, Response $response, array $args): Response
{
    $input = $request->getParsedBody();
    $event = $request->getAttribute('event');
    $event->setStart($input['startTime']);
    $event->setEnd($input['endTime']);
    $event->save();

    return SlimUtils::renderSuccessJSON($response);
}

function unusedSetEventAttendance(): void
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

/**
 * @OA\Delete(
 *     path="/events/{id}",
 *     operationId="deleteEvent",
 *     summary="Delete an event",
 *     tags={"Calendar"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
 *     @OA\Response(response=200, description="Event deleted",
 *         @OA\JsonContent(@OA\Property(property="success", type="boolean", example=true))
 *     ),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="AddEvents role required"),
 *     @OA\Response(response=404, description="Event not found")
 * )
 */
function deleteEvent(Request $request, Response $response, array $args): Response
{
    $request->getAttribute('event')->delete();

    return SlimUtils::renderSuccessJSON($response);
}
