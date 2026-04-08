<?php

use ChurchCRM\dto\Photo;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Base\EventQuery;
use ChurchCRM\model\ChurchCRM\Base\EventTypeQuery;
use ChurchCRM\model\ChurchCRM\CalendarQuery;
use ChurchCRM\model\ChurchCRM\Event;
use ChurchCRM\model\ChurchCRM\EventAudience;
use ChurchCRM\model\ChurchCRM\EventAttendQuery;
use ChurchCRM\model\ChurchCRM\EventCounts;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\Map\ListOptionTableMap;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Service\EventService;
use ChurchCRM\Slim\Middleware\EventsMiddleware;
use ChurchCRM\Slim\Middleware\InputSanitizationMiddleware;
use ChurchCRM\Slim\Middleware\Request\Auth\AddEventsRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\InputUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Routing\RouteCollectorProxy;

$app->group('/events', function (RouteCollectorProxy $group): void {
    $group->get('/', 'getAllEvents');
    $group->get('', 'getAllEvents');
    $group->get('/types', 'getEventTypes');
    $group->get('/today', 'getTodaysEvents');
    $group->get('/{id}', 'getEvent')->add(new EventsMiddleware());
    $group->get('/{id}/', 'getEvent')->add(new EventsMiddleware());
    $group->get('/{id}/primarycontact', 'getEventPrimaryContact')->add(new EventsMiddleware());
    $group->get('/{id}/secondarycontact', 'getEventSecondaryContact')->add(new EventsMiddleware());
    $group->get('/{id}/location', 'getEventLocation')->add(new EventsMiddleware());
    $group->get('/{id}/audience', 'getEventAudience')->add(new EventsMiddleware());
    $group->get('/{id}/roster', 'getEventRoster')->add(new EventsMiddleware());

    $group->post('/quick-create', 'quickCreateEvent')->add(new AddEventsRoleAuthMiddleware());
    $group->post('/generate-recurring', 'generateRecurringEvents')->add(new AddEventsRoleAuthMiddleware());
    $group->post('/', 'newEvent')->add(new InputSanitizationMiddleware(['Title' => 'text', 'Desc' => 'html', 'Text' => 'html']))->add(new AddEventsRoleAuthMiddleware());
    $group->post('', 'newEvent')->add(new InputSanitizationMiddleware(['Title' => 'text', 'Desc' => 'html', 'Text' => 'html']))->add(new AddEventsRoleAuthMiddleware());
    $group->post('/repeat', 'createRepeatEvents')->add(new InputSanitizationMiddleware(['Title' => 'text', 'Desc' => 'html', 'Text' => 'html']))->add(new AddEventsRoleAuthMiddleware());
    $group->post('/{id}', 'updateEvent')->add(new InputSanitizationMiddleware(['Title' => 'text', 'Desc' => 'html', 'Text' => 'html']))->add(new AddEventsRoleAuthMiddleware())->add(new EventsMiddleware());
    $group->post('/{id}/time', 'setEventTime')->add(new AddEventsRoleAuthMiddleware())->add(new EventsMiddleware());
    $group->post('/{id}/checkin', 'checkinPerson')->add(new AddEventsRoleAuthMiddleware())->add(new EventsMiddleware());
    $group->post('/{id}/checkout', 'checkoutPerson')->add(new AddEventsRoleAuthMiddleware())->add(new EventsMiddleware());
    $group->post('/{id}/checkin-all', 'checkinAll')->add(new AddEventsRoleAuthMiddleware())->add(new EventsMiddleware());
    $group->post('/{id}/checkout-all', 'checkoutAll')->add(new AddEventsRoleAuthMiddleware())->add(new EventsMiddleware());
    $group->post('/{id}/checkin-people', 'checkinPeople')->add(new AddEventsRoleAuthMiddleware())->add(new EventsMiddleware());

    $group->delete('/{id}', 'deleteEvent')->add(new AddEventsRoleAuthMiddleware())->add(new EventsMiddleware());
    $group->delete('/{id}/attendance/{personId}', 'deleteAttendance')->add(new AddEventsRoleAuthMiddleware())->add(new EventsMiddleware());

    $group->post('/{id}/status', 'setEventStatus')->add(new AddEventsRoleAuthMiddleware())->add(new EventsMiddleware());

    // Audit endpoints — find and clean up "stuck" events
    $group->get('/audit/stuck', 'getStuckEvents');
    $group->post('/audit/close', 'closeStuckEvents')->add(new AddEventsRoleAuthMiddleware());
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
        return SlimUtils::renderErrorJSON($response, gettext('invalid event type id'), [], 400);
    }

    $calendars = CalendarQuery::create()
        ->filterById($input['PinnedCalendars'])
        ->find();
    if (count($calendars) !== count($input['PinnedCalendars'])) {
        return SlimUtils::renderErrorJSON($response, gettext('invalid calendar pinning'), [], 400);
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
        return SlimUtils::renderErrorJSON($response, gettext('invalid recurrence type'), [], 400);
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
        return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], 400);
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

/**
 * @OA\Post(
 *     path="/events/{id}/status",
 *     operationId="setEventStatus",
 *     summary="Activate or deactivate an event",
 *     tags={"Calendar"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         required={"active"},
 *         @OA\Property(property="active", type="boolean", example=true, description="true to activate, false to deactivate")
 *     )),
 *     @OA\Response(response=200, description="Event status updated"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="AddEvents role required"),
 *     @OA\Response(response=404, description="Event not found")
 * )
 */
function setEventStatus(Request $request, Response $response, array $args): Response
{
    $input = $request->getParsedBody();
    $active = !empty($input['active']);

    /** @var Event $event */
    $event = $request->getAttribute('event');
    $event->setInActive($active ? 0 : 1);
    $event->save();

    return SlimUtils::renderSuccessJSON($response);
}

/**
 * @OA\Post(
 *     path="/events/quick-create",
 *     operationId="quickCreateEvent",
 *     summary="Quick-create an event from EventType defaults",
 *     description="Creates an event with minimal input, using EventType defaults for title, time, and group. If an event of the same type already exists for the given date, returns the existing event instead.",
 *     tags={"Calendar"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         required={"eventTypeId"},
 *         @OA\Property(property="eventTypeId", type="integer", example=5, description="Event type ID"),
 *         @OA\Property(property="date", type="string", format="date", example="2026-04-05", description="Event date (defaults to today)"),
 *         @OA\Property(property="groupId", type="integer", example=12, description="Override group ID (defaults to EventType's linked group)")
 *     )),
 *     @OA\Response(response=200, description="Event created or existing event found",
 *         @OA\JsonContent(
 *             @OA\Property(property="eventId", type="integer", example=42),
 *             @OA\Property(property="created", type="boolean", example=true),
 *             @OA\Property(property="title", type="string", example="Youth Sunday School — Apr 5, 2026")
 *         )
 *     ),
 *     @OA\Response(response=400, description="Invalid event type ID"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="AddEvents role required")
 * )
 */
function quickCreateEvent(Request $request, Response $response, array $args): Response
{
    $input = $request->getParsedBody();
    $eventTypeId = InputUtils::filterInt($input['eventTypeId'] ?? 0);
    $groupId = InputUtils::filterInt($input['groupId'] ?? 0);

    // Validate date input (defaults to today)
    $date = date('Y-m-d');
    if (!empty($input['date'])) {
        $parsedDate = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $input['date']);
        if ($parsedDate === false || $parsedDate->format('Y-m-d') !== (string) $input['date']) {
            return SlimUtils::renderErrorJSON($response, gettext('Invalid date; expected format YYYY-MM-DD'), [], 400);
        }
        $date = $parsedDate->format('Y-m-d');
    }

    $eventType = null;
    if ($eventTypeId > 0) {
        $eventType = EventTypeQuery::create()->findOneById($eventTypeId);
        if ($eventType === null) {
            return SlimUtils::renderErrorJSON($response, gettext('Invalid event type ID'), [], 400);
        }
        if ($groupId <= 0) {
            $groupId = (int) $eventType->getGroupId();
        }
    }

    // If no event type but we have a group, try to find an event type linked to this group
    if ($eventType === null && $groupId > 0) {
        $eventType = EventTypeQuery::create()
            ->filterByGroupId($groupId)
            ->filterByActive(1)
            ->findOne();
        if ($eventType !== null) {
            $eventTypeId = $eventType->getId();
        }
    }

    if ($eventType === null && $groupId <= 0) {
        return SlimUtils::renderErrorJSON($response, gettext('Event type ID or group ID is required'), [], 400);
    }

    // Check for existing event of this type/group on this date
    $existingQuery = EventQuery::create()
        ->filterByStart($date . ' 00:00:00', Criteria::GREATER_EQUAL)
        ->filterByStart($date . ' 23:59:59', Criteria::LESS_EQUAL)
        ->filterByInActive(0);
    if ($eventTypeId > 0) {
        $existingQuery->filterByType($eventTypeId);
    }
    if ($groupId > 0) {
        $existingQuery
            ->useEventAudienceQuery()
                ->filterByGroupId($groupId)
            ->endUse();
    }
    $existing = $existingQuery->findOne();

    if ($existing !== null) {
        // Ensure the existing event is linked to the group
        if ($groupId > 0) {
            $hasGroup = $existing->getGroups()->toKeyIndex('Id');
            if (!isset($hasGroup[$groupId])) {
                $audience = new EventAudience();
                $audience->setEventId($existing->getId());
                $audience->setGroupId($groupId);
                $audience->save();
            }
        }
        return SlimUtils::renderJSON($response, [
            'eventId' => $existing->getId(),
            'created' => false,
            'title' => $existing->getTitle(),
        ]);
    }

    // Build title
    $formattedDate = date('M j, Y', strtotime($date));
    if ($eventType !== null) {
        $title = $eventType->getName() . ' — ' . $formattedDate;
    } else {
        // Use group name as fallback title
        $group = GroupQuery::create()->findOneById($groupId);
        $groupName = $group !== null ? $group->getName() : gettext('Event');
        $title = $groupName . ' — ' . $formattedDate;
    }

    // Calculate start/end times from type defaults
    $startTimeStr = '09:00:00';
    if ($eventType !== null) {
        $defStartTime = $eventType->getDefStartTime();
        if ($defStartTime !== null) {
            $startTimeStr = $defStartTime->format('H:i:s');
        }
    }
    $start = $date . ' ' . $startTimeStr;
    $endDateTime = new \DateTime($start);
    $endDateTime->modify('+1 hour');
    $end = $endDateTime->format('Y-m-d H:i:s');

    $event = new Event();
    $event->setTitle($title);
    $event->setType($eventTypeId);
    $event->setStart($start);
    $event->setEnd($end);
    $event->setInActive(0);
    $event->save();

    // Link to group via event_audience if groupId is set
    if ($groupId > 0) {
        $audience = new EventAudience();
        $audience->setEventId($event->getId());
        $audience->setGroupId($groupId);
        $audience->save();
    }

    return SlimUtils::renderJSON($response, [
        'eventId' => $event->getId(),
        'created' => true,
        'title' => $title,
    ]);
}

/**
 * @OA\Get(
 *     path="/events/today",
 *     operationId="getTodaysEvents",
 *     summary="Get today's events with attendance stats",
 *     tags={"Calendar"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(response=200, description="Today's events",
 *         @OA\JsonContent(type="object",
 *             @OA\Property(property="events", type="array",
 *                 @OA\Items(type="object",
 *                     @OA\Property(property="id", type="integer"),
 *                     @OA\Property(property="title", type="string"),
 *                     @OA\Property(property="typeName", type="string"),
 *                     @OA\Property(property="start", type="string", format="date-time"),
 *                     @OA\Property(property="end", type="string", format="date-time"),
 *                     @OA\Property(property="checkedIn", type="integer"),
 *                     @OA\Property(property="totalAttendees", type="integer")
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(response=401, description="Unauthorized")
 * )
 */
function getTodaysEvents(Request $request, Response $response, array $args): Response
{
    $today = date('Y-m-d');
    $events = EventQuery::create()
        ->filterByStart($today . ' 00:00:00', Criteria::GREATER_EQUAL)
        ->filterByStart($today . ' 23:59:59', Criteria::LESS_EQUAL)
        ->filterByInActive(0)
        ->orderByStart()
        ->find();

    $result = [];
    foreach ($events as $event) {
        $checkedIn = EventAttendQuery::create()
            ->filterByEventId($event->getId())
            ->filterByCheckinDate(null, Criteria::ISNOTNULL)
            ->filterByCheckoutDate(null, Criteria::ISNULL)
            ->count();

        $totalAttendees = EventAttendQuery::create()
            ->filterByEventId($event->getId())
            ->count();

        $groups = $event->getGroups();
        $groupsArray = [];
        foreach ($groups as $group) {
            $groupsArray[] = ['id' => $group->getId(), 'name' => $group->getName()];
        }

        $typeName = '';
        $eventType = $event->getEventType();
        if ($eventType !== null) {
            $typeName = $eventType->getName();
        }

        $result[] = [
            'id' => $event->getId(),
            'title' => $event->getTitle(),
            'typeName' => $typeName,
            'start' => $event->getStart('Y-m-d H:i:s'),
            'end' => $event->getEnd('Y-m-d H:i:s'),
            'checkedIn' => $checkedIn,
            'totalAttendees' => $totalAttendees,
            'groups' => $groupsArray,
        ];
    }

    return SlimUtils::renderJSON($response, ['events' => $result]);
}

/**
 * @OA\Get(
 *     path="/events/{id}/roster",
 *     operationId="getEventRoster",
 *     summary="Get group members with attendance status for an event",
 *     description="Returns all members of groups linked to this event, along with their check-in/check-out status.",
 *     tags={"Calendar"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Roster with attendance status"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=404, description="Event not found")
 * )
 */
function getEventRoster(Request $request, Response $response, array $args): Response
{
    /** @var Event $event */
    $event = $request->getAttribute('event');
    $groups = $event->getGroups();

    if ($groups->count() === 0) {
        return SlimUtils::renderJSON($response, [
            'event' => ['id' => $event->getId(), 'title' => $event->getTitle()],
            'groups' => [],
            'stats' => ['total' => 0, 'checkedIn' => 0, 'checkedOut' => 0],
            'members' => [],
        ]);
    }

    $firstGroup = $groups->getFirst();

    // Build query matching KioskAssignment::getActiveGroupMembers() pattern
    $groupTypeJoin = new Join();
    $groupTypeJoin->addCondition('Person2group2roleP2g2r.RoleId', 'list_lst.lst_OptionId', Join::EQUAL);
    $groupTypeJoin->addForeignValueCondition('list_lst', 'lst_ID', '', $firstGroup->getRoleListId(), Join::EQUAL);
    $groupTypeJoin->setJoinType(Criteria::LEFT_JOIN);

    $members = PersonQuery::create()
        ->joinWithPerson2group2roleP2g2r()
        ->usePerson2group2roleP2g2rQuery()
            ->filterByGroup($groups)
            ->joinGroup()
            ->addJoinObject($groupTypeJoin)
            ->withColumn(ListOptionTableMap::COL_LST_OPTIONNAME, 'RoleName')
        ->endUse()
        ->leftJoinEventAttend()
        ->addJoinCondition('EventAttend', 'event_attend.event_id = ?', $event->getId())
        ->withColumn('event_attend.checkin_date', 'CheckinDate')
        ->withColumn('event_attend.checkout_date', 'CheckoutDate')
        ->withColumn('(CASE WHEN event_attend.event_id IS NOT NULL AND event_attend.checkout_date IS NULL AND event_attend.checkin_date IS NOT NULL THEN \'checked_in\' WHEN event_attend.checkout_date IS NOT NULL THEN \'checked_out\' ELSE \'not_checked_in\' END)', 'AttendStatus')
        ->find();

    $membersArray = [];
    $checkedInCount = 0;
    $checkedOutCount = 0;

    foreach ($members as $person) {
        $photo = new Photo('Person', $person->getId());
        $status = $person->getVirtualColumn('AttendStatus');
        if ($status === 'checked_in') {
            $checkedInCount++;
        } elseif ($status === 'checked_out') {
            $checkedOutCount++;
        }

        $membersArray[] = [
            'personId' => $person->getId(),
            'firstName' => $person->getFirstName(),
            'lastName' => $person->getLastName(),
            'role' => $person->getVirtualColumn('RoleName'),
            'gender' => $person->getGender(),
            'hasPhoto' => $photo->hasUploadedPhoto(),
            'status' => $status,
            'checkinTime' => $person->getVirtualColumn('CheckinDate'),
        ];
    }

    $groupsArray = [];
    foreach ($groups as $group) {
        $groupsArray[] = ['id' => $group->getId(), 'name' => $group->getName()];
    }

    return SlimUtils::renderJSON($response, [
        'event' => ['id' => $event->getId(), 'title' => $event->getTitle()],
        'groups' => $groupsArray,
        'stats' => [
            'total' => count($membersArray),
            'checkedIn' => $checkedInCount,
            'checkedOut' => $checkedOutCount,
        ],
        'members' => $membersArray,
    ]);
}

/**
 * @OA\Post(
 *     path="/events/{id}/checkin",
 *     operationId="checkinPerson",
 *     summary="Check in a person to an event via AJAX",
 *     tags={"Calendar"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         required={"personId"},
 *         @OA\Property(property="personId", type="integer", example=101),
 *         @OA\Property(property="checkedInById", type="integer", nullable=true, example=55, description="Person ID of whoever is checking this person in (e.g., parent dropping off child)")
 *     )),
 *     @OA\Response(response=200, description="Person checked in"),
 *     @OA\Response(response=400, description="Invalid person ID"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=404, description="Event not found")
 * )
 */
function checkinPerson(Request $request, Response $response, array $args): Response
{
    $input = $request->getParsedBody();
    $personId = InputUtils::filterInt($input['personId'] ?? 0);
    if ($personId <= 0) {
        return SlimUtils::renderErrorJSON($response, gettext('Invalid person ID'), [], 400);
    }
    $checkedInById = InputUtils::filterInt($input['checkedInById'] ?? 0) ?: null;

    /** @var Event $event */
    $event = $request->getAttribute('event');
    if ((int) $event->getInActive() === 1) {
        return SlimUtils::renderErrorJSON($response, gettext('Cannot check in to an inactive event. Activate the event first.'), [], 409);
    }

    $event->checkInPerson($personId, $checkedInById);

    return SlimUtils::renderJSON($response, [
        'success' => true,
        'status' => 'checked_in',
        'checkinTime' => date(SystemConfig::getValue('sDateTimeFormat')),
    ]);
}

/**
 * @OA\Post(
 *     path="/events/{id}/checkout",
 *     operationId="checkoutPerson",
 *     summary="Check out a person from an event via AJAX",
 *     tags={"Calendar"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         required={"personId"},
 *         @OA\Property(property="personId", type="integer", example=101),
 *         @OA\Property(property="checkedOutById", type="integer", nullable=true, example=55, description="Person ID of whoever is checking this person out (e.g., parent picking up child)")
 *     )),
 *     @OA\Response(response=200, description="Person checked out"),
 *     @OA\Response(response=400, description="Invalid person ID"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=404, description="Event not found")
 * )
 */
function checkoutPerson(Request $request, Response $response, array $args): Response
{
    $input = $request->getParsedBody();
    $personId = InputUtils::filterInt($input['personId'] ?? 0);
    if ($personId <= 0) {
        return SlimUtils::renderErrorJSON($response, gettext('Invalid person ID'), [], 400);
    }
    $checkedOutById = InputUtils::filterInt($input['checkedOutById'] ?? 0) ?: null;

    /** @var Event $event */
    $event = $request->getAttribute('event');
    $result = $event->checkOutPerson($personId, $checkedOutById);

    if ($result['status'] === 'not_checked_in') {
        return SlimUtils::renderErrorJSON($response, gettext('Person is not checked in'), [], 400);
    }

    return SlimUtils::renderJSON($response, [
        'success' => true,
        'status' => 'checked_out',
        'checkoutTime' => date(SystemConfig::getValue('sDateTimeFormat')),
    ]);
}

/**
 * @OA\Post(
 *     path="/events/{id}/checkin-all",
 *     operationId="checkinAll",
 *     summary="Batch check-in all group members for an event",
 *     tags={"Calendar"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="All members checked in",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean"),
 *             @OA\Property(property="checkedIn", type="integer")
 *         )
 *     ),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="AddEvents role required"),
 *     @OA\Response(response=404, description="Event not found")
 * )
 */
function checkinAll(Request $request, Response $response, array $args): Response
{
    /** @var Event $event */
    $event = $request->getAttribute('event');
    if ((int) $event->getInActive() === 1) {
        return SlimUtils::renderErrorJSON($response, gettext('Cannot check in to an inactive event. Activate the event first.'), [], 409);
    }

    $groups = $event->getGroups();

    // Deduplicate: a person may belong to multiple linked groups
    $uniquePersonIds = [];
    foreach ($groups as $group) {
        $members = $group->getPerson2group2roleP2g2rs();
        foreach ($members as $member) {
            $personId = (int) $member->getPersonId();
            if ($personId > 0) {
                $uniquePersonIds[$personId] = true;
            }
        }
    }

    $checkedInCount = 0;
    foreach (array_keys($uniquePersonIds) as $personId) {
        $event->checkInPerson($personId);
        $checkedInCount++;
    }

    return SlimUtils::renderJSON($response, [
        'success' => true,
        'checkedIn' => $checkedInCount,
    ]);
}

/**
 * @OA\Post(
 *     path="/events/{id}/checkout-all",
 *     operationId="checkoutAll",
 *     summary="Batch check-out all checked-in people from an event",
 *     tags={"Calendar"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="All people checked out",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean"),
 *             @OA\Property(property="checkedOut", type="integer")
 *         )
 *     ),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="AddEvents role required"),
 *     @OA\Response(response=404, description="Event not found")
 * )
 */
function checkoutAll(Request $request, Response $response, array $args): Response
{
    /** @var Event $event */
    $event = $request->getAttribute('event');
    $attendees = $event->getEventAttends();

    $checkedOutCount = 0;
    foreach ($attendees as $attendance) {
        if ($attendance->getCheckoutDate() === null && $attendance->getCheckinDate() !== null) {
            $event->checkOutPerson($attendance->getPersonId());
            $checkedOutCount++;
        }
    }

    return SlimUtils::renderJSON($response, [
        'success' => true,
        'checkedOut' => $checkedOutCount,
    ]);
}

/**
 * @OA\Post(
 *     path="/events/{id}/checkin-people",
 *     operationId="checkinPeople",
 *     summary="Batch check-in a list of people for an event",
 *     description="Used by Family View 'Check In Family' (#6838) and any other UI that needs to check in a known set of people. Each ID is processed via Event::checkInPerson() which creates timeline notes and timestamps.",
 *     tags={"Calendar"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         required={"personIds"},
 *         @OA\Property(property="personIds", type="array", @OA\Items(type="integer"), example={101,102,103}),
 *         @OA\Property(property="checkedInById", type="integer", nullable=true, example=55, description="Person ID of whoever is checking these people in")
 *     )),
 *     @OA\Response(response=200, description="People checked in",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean"),
 *             @OA\Property(property="checkedIn", type="integer")
 *         )
 *     ),
 *     @OA\Response(response=400, description="Invalid input"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="AddEvents role required"),
 *     @OA\Response(response=404, description="Event not found")
 * )
 */
function checkinPeople(Request $request, Response $response, array $args): Response
{
    $input = $request->getParsedBody();
    $personIds = $input['personIds'] ?? [];

    if (!is_array($personIds) || empty($personIds)) {
        return SlimUtils::renderErrorJSON($response, gettext('personIds must be a non-empty array'), [], 400);
    }

    $checkedInById = InputUtils::filterInt($input['checkedInById'] ?? 0) ?: null;

    /** @var Event $event */
    $event = $request->getAttribute('event');
    if ((int) $event->getInActive() === 1) {
        return SlimUtils::renderErrorJSON($response, gettext('Cannot check in to an inactive event. Activate the event first.'), [], 409);
    }

    $checkedInCount = 0;
    foreach ($personIds as $rawId) {
        $personId = (int) $rawId;
        if ($personId > 0) {
            $event->checkInPerson($personId, $checkedInById);
            $checkedInCount++;
        }
    }

    return SlimUtils::renderJSON($response, [
        'success' => true,
        'checkedIn' => $checkedInCount,
    ]);
}

/**
 * @OA\Delete(
 *     path="/events/{id}/attendance/{personId}",
 *     operationId="deleteAttendance",
 *     summary="Delete a person's attendance record from an event",
 *     tags={"Calendar"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="personId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Attendance deleted"),
 *     @OA\Response(response=404, description="Event or attendance record not found"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="AddEvents role required")
 * )
 */
function deleteAttendance(Request $request, Response $response, array $args): Response
{
    $personId = (int) $args['personId'];

    /** @var Event $event */
    $event = $request->getAttribute('event');

    $attendance = EventAttendQuery::create()
        ->filterByEvent($event)
        ->filterByPersonId($personId)
        ->findOne();

    if ($attendance === null) {
        throw new HttpNotFoundException($request);
    }

    $attendance->delete();

    return SlimUtils::renderJSON($response, [
        'success' => true,
        'status' => 'deleted',
    ]);
}

/**
 * @OA\Post(
 *     path="/events/generate-recurring",
 *     operationId="generateRecurringEvents",
 *     summary="Generate recurring events from an EventType's recurrence settings",
 *     description="Creates multiple events between startDate and endDate based on the EventType's recurrence pattern (weekly, monthly, or yearly). Optionally skips dates where an event of the same type already exists.",
 *     tags={"Calendar"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         required={"eventTypeId","startDate","endDate"},
 *         @OA\Property(property="eventTypeId", type="integer", example=5),
 *         @OA\Property(property="startDate", type="string", format="date", example="2026-04-05"),
 *         @OA\Property(property="endDate", type="string", format="date", example="2026-06-28"),
 *         @OA\Property(property="skipExisting", type="boolean", example=true, description="Skip dates that already have an event of this type"),
 *         @OA\Property(property="pinnedCalendars", type="array", @OA\Items(type="integer"), example={1}, description="Calendar IDs to pin generated events to")
 *     )),
 *     @OA\Response(response=200, description="Events generated",
 *         @OA\JsonContent(
 *             @OA\Property(property="created", type="integer", example=12),
 *             @OA\Property(property="skipped", type="integer", example=1),
 *             @OA\Property(property="events", type="array", @OA\Items(type="object",
 *                 @OA\Property(property="id", type="integer"),
 *                 @OA\Property(property="title", type="string"),
 *                 @OA\Property(property="date", type="string", format="date")
 *             ))
 *         )
 *     ),
 *     @OA\Response(response=400, description="Invalid input"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="AddEvents role required")
 * )
 */
function generateRecurringEvents(Request $request, Response $response, array $args): Response
{
    $input = $request->getParsedBody();
    $eventTypeId = InputUtils::filterInt($input['eventTypeId'] ?? 0);
    $startDate = $input['startDate'] ?? '';
    $endDate = $input['endDate'] ?? '';
    $skipExisting = (bool) ($input['skipExisting'] ?? true);
    $pinnedCalendarIds = $input['pinnedCalendars'] ?? [];

    if ($eventTypeId <= 0) {
        return SlimUtils::renderErrorJSON($response, gettext('Event type ID is required'), [], 400);
    }

    $eventType = EventTypeQuery::create()->findOneById($eventTypeId);
    if ($eventType === null) {
        return SlimUtils::renderErrorJSON($response, gettext('Invalid event type ID'), [], 400);
    }

    if (empty($startDate) || empty($endDate) || strtotime($startDate) === false || strtotime($endDate) === false) {
        return SlimUtils::renderErrorJSON($response, gettext('Valid startDate and endDate are required'), [], 400);
    }

    if (strtotime($endDate) < strtotime($startDate)) {
        return SlimUtils::renderErrorJSON($response, gettext('endDate must be after startDate'), [], 400);
    }

    // Limit range to 1 year max to prevent accidental mass-creation
    $maxEnd = (new \DateTime($startDate))->modify('+1 year')->format('Y-m-d');
    if ($endDate > $maxEnd) {
        return SlimUtils::renderErrorJSON($response, gettext('Date range cannot exceed 1 year'), [], 400);
    }

    $recurType = $eventType->getDefRecurType() ?: 'none';
    if ($recurType === 'none') {
        return SlimUtils::renderErrorJSON($response, gettext('Event type has no recurrence pattern configured'), [], 400);
    }

    // Calculate occurrence dates
    $dates = [];
    $start = new \DateTime($startDate);
    $end = new \DateTime($endDate);

    switch ($recurType) {
        case 'weekly':
            $dow = $eventType->getDefRecurDow();
            if (empty($dow)) {
                return SlimUtils::renderErrorJSON($response, gettext('Event type has no day-of-week configured for weekly recurrence'), [], 400);
            }
            // Move to the first occurrence of the target day on or after startDate
            $current = clone $start;
            $targetDay = ucfirst(strtolower(trim($dow)));
            if ($current->format('l') !== $targetDay) {
                $current->modify("next {$targetDay}");
            }
            while ($current <= $end) {
                $dates[] = $current->format('Y-m-d');
                $current->modify('+1 week');
            }
            break;

        case 'monthly':
            $dom = (int) $eventType->getDefRecurDom();
            if ($dom < 1 || $dom > 31) {
                return SlimUtils::renderErrorJSON($response, gettext('Event type has no valid day-of-month configured for monthly recurrence'), [], 400);
            }
            $current = clone $start;
            // Move to the target DOM in the current month
            $current->setDate((int) $current->format('Y'), (int) $current->format('m'), min($dom, (int) $current->format('t')));
            if ($current < $start) {
                $current->modify('+1 month');
                $current->setDate((int) $current->format('Y'), (int) $current->format('m'), min($dom, (int) $current->format('t')));
            }
            while ($current <= $end) {
                $dates[] = $current->format('Y-m-d');
                $current->modify('+1 month');
                // Handle months with fewer days (e.g., DOM=31 in a 30-day month)
                $current->setDate((int) $current->format('Y'), (int) $current->format('m'), min($dom, (int) $current->format('t')));
            }
            break;

        case 'yearly':
            $doy = $eventType->getDefRecurDoy();
            if ($doy === null) {
                return SlimUtils::renderErrorJSON($response, gettext('Event type has no date configured for yearly recurrence'), [], 400);
            }
            $doyDate = $doy instanceof \DateTime ? $doy : new \DateTime($doy);
            $month = (int) $doyDate->format('m');
            $day = (int) $doyDate->format('d');
            $currentYear = (int) $start->format('Y');
            $endYear = (int) $end->format('Y');
            for ($y = $currentYear; $y <= $endYear; $y++) {
                $candidate = new \DateTime("{$y}-{$month}-{$day}");
                if ($candidate >= $start && $candidate <= $end) {
                    $dates[] = $candidate->format('Y-m-d');
                }
            }
            break;
    }

    if (empty($dates)) {
        return SlimUtils::renderJSON($response, ['created' => 0, 'skipped' => 0, 'events' => []]);
    }

    $groupId = (int) $eventType->getGroupId();
    $startTimeStr = '09:00:00';
    $defStartTime = $eventType->getDefStartTime();
    if ($defStartTime !== null) {
        $startTimeStr = $defStartTime->format('H:i:s');
    }

    // Resolve pinned calendars if provided
    $calendars = null;
    if (!empty($pinnedCalendarIds) && is_array($pinnedCalendarIds)) {
        $calendars = CalendarQuery::create()
            ->filterById($pinnedCalendarIds, Criteria::IN)
            ->find();
    }

    $createdEvents = [];
    $skippedCount = 0;

    foreach ($dates as $date) {
        // Check for existing event on this date
        if ($skipExisting) {
            $existing = EventQuery::create()
                ->filterByType($eventTypeId)
                ->filterByStart($date . ' 00:00:00', Criteria::GREATER_EQUAL)
                ->filterByStart($date . ' 23:59:59', Criteria::LESS_EQUAL)
                ->filterByInActive(0)
                ->findOne();

            if ($existing !== null) {
                $skippedCount++;
                continue;
            }
        }

        $formattedDate = date('M j, Y', strtotime($date));
        $title = $eventType->getName() . ' — ' . $formattedDate;

        $eventStart = $date . ' ' . $startTimeStr;
        $eventEnd = (new \DateTime($eventStart))->modify('+1 hour')->format('Y-m-d H:i:s');

        $event = new Event();
        $event->setTitle($title);
        $event->setType($eventTypeId);
        $event->setStart($eventStart);
        $event->setEnd($eventEnd);
        $event->setInActive(0);
        if ($calendars !== null) {
            $event->setCalendars($calendars);
        }
        $event->save();

        if ($groupId > 0) {
            $audience = new EventAudience();
            $audience->setEventId($event->getId());
            $audience->setGroupId($groupId);
            $audience->save();
        }

        $createdEvents[] = [
            'id' => $event->getId(),
            'title' => $title,
            'date' => $date,
        ];
    }

    return SlimUtils::renderJSON($response, [
        'created' => count($createdEvents),
        'skipped' => $skippedCount,
        'events' => $createdEvents,
    ]);
}

/**
 * @OA\Get(
 *     path="/events/audit/stuck",
 *     operationId="getStuckEvents",
 *     summary="List past events that are still marked active",
 *     description="Returns every event whose end date has passed but which is still marked Active. Each row includes the count of attendees still checked in (no checkout). This is the audit set — anything in this list represents an event that was never properly closed, regardless of whether anyone forgot to check out.",
 *     tags={"Calendar"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(response=200, description="List of stuck events with stats")
 * )
 */
function getStuckEvents(Request $request, Response $response, array $args): Response
{
    $now = date('Y-m-d H:i:s');

    // Find all active events whose end is in the past. We don't filter by
    // "still checked in" — a 2016 event marked Active with zero un-checked-out
    // people is still a stale row that should be flagged for cleanup.
    $candidates = EventQuery::create()
        ->filterByInActive(0)
        ->filterByEnd($now, Criteria::LESS_THAN)
        ->orderByEnd(Criteria::DESC)
        ->limit(500)
        ->find();

    $stuck = [];
    foreach ($candidates as $event) {
        $eventId = (int) $event->getId();

        // Count attendees who checked in but never checked out (informational
        // — does not affect inclusion in the result set).
        $stillCheckedIn = EventAttendQuery::create()
            ->filterByEventId($eventId)
            ->filterByCheckinDate(null, Criteria::ISNOTNULL)
            ->filterByCheckoutDate(null, Criteria::ISNULL)
            ->count();

        $stuck[] = [
            'id'             => $eventId,
            'title'          => $event->getTitle(),
            'typeName'       => $event->getEventType() ? $event->getEventType()->getName() : '',
            'start'          => $event->getStart('Y-m-d H:i:s'),
            'end'            => $event->getEnd('Y-m-d H:i:s'),
            'stillCheckedIn' => $stillCheckedIn,
        ];
    }

    return SlimUtils::renderJSON($response, [
        'count'  => count($stuck),
        'events' => $stuck,
    ]);
}

/**
 * @OA\Post(
 *     path="/events/audit/close",
 *     operationId="closeStuckEvents",
 *     summary="Batch close stuck events",
 *     description="For each event id provided, checks out anyone still checked in and marks the event inactive. Idempotent — events that are already closed/empty are skipped.",
 *     tags={"Calendar"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         required={"eventIds"},
 *         @OA\Property(property="eventIds", type="array", @OA\Items(type="integer"), example={42,43,44}),
 *         @OA\Property(property="checkoutPeople", type="boolean", example=true, description="Auto check-out anyone still checked in (defaults to true)"),
 *         @OA\Property(property="deactivate", type="boolean", example=true, description="Mark the event inactive after closing (defaults to true)")
 *     )),
 *     @OA\Response(response=200, description="Summary of close operation")
 * )
 */
function closeStuckEvents(Request $request, Response $response, array $args): Response
{
    $input = $request->getParsedBody();
    $eventIds = $input['eventIds'] ?? [];
    if (!is_array($eventIds) || empty($eventIds)) {
        return SlimUtils::renderErrorJSON($response, gettext('eventIds must be a non-empty array'), [], 400);
    }
    $checkoutPeople = !isset($input['checkoutPeople']) || $input['checkoutPeople'];
    $deactivate = !isset($input['deactivate']) || $input['deactivate'];

    $eventsClosed = 0;
    $peopleCheckedOut = 0;

    foreach ($eventIds as $rawId) {
        $eventId = (int) $rawId;
        if ($eventId <= 0) {
            continue;
        }

        $event = EventQuery::create()->findOneById($eventId);
        if ($event === null) {
            continue;
        }

        if ($checkoutPeople) {
            $stillIn = EventAttendQuery::create()
                ->filterByEventId($eventId)
                ->filterByCheckinDate(null, Criteria::ISNOTNULL)
                ->filterByCheckoutDate(null, Criteria::ISNULL)
                ->find();

            foreach ($stillIn as $att) {
                $event->checkOutPerson($att->getPersonId());
                $peopleCheckedOut++;
            }
        }

        if ($deactivate && (int) $event->getInActive() === 0) {
            $event->setInActive(1);
            $event->save();
            $eventsClosed++;
        }
    }

    return SlimUtils::renderJSON($response, [
        'success'          => true,
        'eventsClosed'     => $eventsClosed,
        'peopleCheckedOut' => $peopleCheckedOut,
    ]);
}
