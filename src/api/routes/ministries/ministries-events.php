<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\Event;
use ChurchCRM\model\ChurchCRM\VolunteerMinistry;
use ChurchCRM\model\ChurchCRM\VolunteerOccurrenceQuery;
use ChurchCRM\model\ChurchCRM\VolunteerPosition;
use ChurchCRM\model\ChurchCRM\VolunteerTeam;
use ChurchCRM\Slim\Middleware\InputSanitizationMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Volunteer\Middleware\VolunteerCoordinatorRoleAuthMiddleware;
use ChurchCRM\Volunteer\Middleware\VolunteerMinistryMiddleware;
use ChurchCRM\Volunteer\Middleware\VolunteerPositionMiddleware;
use ChurchCRM\Volunteer\Middleware\VolunteerTeamMiddleware;
use ChurchCRM\Volunteer\Middleware\VolunteerV2EnabledMiddleware;
use ChurchCRM\Volunteer\Service\VolunteerAssignmentService;
use ChurchCRM\Volunteer\Service\VolunteerEventService;
use ChurchCRM\Volunteer\VolunteerException;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

/**
 * Volunteer Management V2 API — a ministry's own calendar events (D24, design §3.3.2).
 *
 * The events are core's: they are created through `EventService` with `event_ministry_id` set
 * and are edited and deleted in the core event editor. These routes add the ministry page's
 * Calendar tab: create one event or a series (optionally staffed in the same transaction) and
 * list what the ministry owns with each event's staffing and headcount.
 *
 * Gated like the other V2 groups — rollout, then the coordinator role, then the ministry (or
 * position) entity middleware — and `VolunteerEventService` asks again from the resolved rows.
 */
$app->group('/ministries', function (RouteCollectorProxy $group): void {
    $group->get('/ministries/{ministryId:[0-9]+}/events', 'listVolunteerMinistryEvents')
        ->add(new VolunteerMinistryMiddleware());

    // `recurrence` and `staff` are nested objects the sanitizer has no type for; the service
    // validates them.
    $group->post('/ministries/{ministryId:[0-9]+}/events', 'createVolunteerMinistryEvents')
        ->add(new InputSanitizationMiddleware([
            'title' => 'text',
            'description' => 'html',
            'date' => 'date?',
            'rangeStart' => 'date?',
            'rangeEnd' => 'date?',
        ]))
        ->add(new VolunteerMinistryMiddleware());

    // D28: the Calendar tab's Delete events — owned events only, through core's delete path.
    $group->delete('/ministries/{ministryId:[0-9]+}/events', 'deleteVolunteerMinistryEvents')
        ->add(new VolunteerMinistryMiddleware());

    // D28: the ministry's own events of the team's class, which the team dialog asks about.
    $group->get('/teams/{teamId:[0-9]+}/class-events', 'getVolunteerTeamClassEvents')
        ->add(new VolunteerTeamMiddleware());

    // The new-event dialog's "Fill by default with": the schedule it would ask does not exist yet.
    $group->get('/positions/{positionId:[0-9]+}/eligible', 'listVolunteerPositionEligiblePeople')
        ->add(new VolunteerPositionMiddleware());
})->add(VolunteerCoordinatorRoleAuthMiddleware::class)->add(new VolunteerV2EnabledMiddleware());

/**
 * @OA\Post(
 *     path="/ministries/ministries/{ministryId}/events",
 *     operationId="createVolunteerMinistryEvents",
 *     summary="Create a ministry's event or event series through core, optionally staffed (D24)",
 *     description="One event (date, startTime, endTime) through the same code as POST /events, or a series (recurrence, rangeStart, rangeEnd, startTime, endTime) through the repeat engine, capped at 366 events. Every event carries this ministry's id and the Linked Group when given, and is pinned to calendarIds (default: the ministry's own calendar), each of which must be the ministry's own calendar or a church calendar opened to it unless the caller holds Add Events (D25). With staff, the schedule is created in the same transaction: class mode for a series with a Linked Group, ministry mode narrowed to the title for one without, Staff this event for a single event; its occurrences are generated and the defaults assigned. When the team already has an active schedule following the events (class mode on the same class, or ministry mode with exactly this title), the events go to that schedule instead (D30): its window is widened to take them in, it is generated, the defaults go on the occurrences this run created, and its own staffing needs and offsets are kept. Nothing is written when any part is refused. Fires event.created for each event after the commit.",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="ministryId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         required={"title","eventTypeId","startTime","endTime"},
 *         @OA\Property(property="title", type="string", example="Workday"),
 *         @OA\Property(property="eventTypeId", type="integer", example=1),
 *         @OA\Property(property="description", type="string", nullable=true),
 *         @OA\Property(property="linkedGroupId", type="integer", nullable=true, description="The Linked Group, usually a Sunday School class"),
 *         @OA\Property(property="calendarIds", type="array", @OA\Items(type="integer"), description="Omit for the ministry's own calendar"),
 *         @OA\Property(property="date", type="string", format="date", description="One event"),
 *         @OA\Property(property="startTime", type="string", example="09:00"),
 *         @OA\Property(property="endTime", type="string", example="12:00"),
 *         @OA\Property(property="recurrence", type="object", description="A series instead of one event",
 *             @OA\Property(property="type", type="string", enum={"weekly","monthly","yearly"}),
 *             @OA\Property(property="dow", type="string", example="Sunday"),
 *             @OA\Property(property="dom", type="integer", example=15),
 *             @OA\Property(property="doy", type="string", example="04-12")
 *         ),
 *         @OA\Property(property="rangeStart", type="string", format="date"),
 *         @OA\Property(property="rangeEnd", type="string", format="date"),
 *         @OA\Property(property="staff", type="object", nullable=true,
 *             @OA\Property(property="teamId", type="integer"),
 *             @OA\Property(property="requirements", type="array", @OA\Items(type="object",
 *                 @OA\Property(property="positionId", type="integer"),
 *                 @OA\Property(property="minCount", type="integer"),
 *                 @OA\Property(property="maxCount", type="integer", nullable=true)
 *             )),
 *             @OA\Property(property="startOffsetMinutes", type="integer"),
 *             @OA\Property(property="endOffsetMinutes", type="integer"),
 *             @OA\Property(property="defaults", type="array", @OA\Items(type="object",
 *                 @OA\Property(property="positionId", type="integer"),
 *                 @OA\Property(property="personId", type="integer"),
 *                 @OA\Property(property="accepted", type="boolean")
 *             ))
 *         )
 *     )),
 *     @OA\Response(response=201, description="Created",
 *         @OA\JsonContent(
 *             @OA\Property(property="events", type="array", @OA\Items(type="object",
 *                 @OA\Property(property="id", type="integer"),
 *                 @OA\Property(property="title", type="string"),
 *                 @OA\Property(property="start", type="string"),
 *                 @OA\Property(property="end", type="string")
 *             )),
 *             @OA\Property(property="schedule", type="object", description="Present when staff was given"),
 *             @OA\Property(property="reusedSchedule", type="boolean", description="Present when staff was given: the events were added to the team's existing schedule (D30)"),
 *             @OA\Property(property="occurrences", type="array", @OA\Items(type="object",
 *                 @OA\Property(property="id", type="integer"),
 *                 @OA\Property(property="eventId", type="integer"),
 *                 @OA\Property(property="occurrenceDate", type="string", format="date")
 *             )),
 *             @OA\Property(property="assigned", type="integer"),
 *             @OA\Property(property="skipped", type="integer")
 *         )
 *     ),
 *     @OA\Response(response=400, description="A missing or malformed field, an unknown type, class or calendar, a recurrence with no date in the range or over the cap, or a staffing plan the schedule refuses"),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not a coordinator of this ministry (team leaders and self-service logins included), a calendar the ministry may not pin to, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such ministry")
 * )
 */
function createVolunteerMinistryEvents(Request $request, Response $response): Response
{
    $ministry = $request->getAttribute('volunteerMinistry');
    $input = (array) $request->getParsedBody();

    try {
        $result = (new VolunteerEventService())->createMinistryEvents(
            $ministry,
            $input,
            AuthenticationManager::getCurrentUser()
        );
    } catch (VolunteerException $e) {
        return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], $e->getStatusCode(), null, $request);
    } catch (\RuntimeException | \InvalidArgumentException $e) {
        return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], 400, null, $request);
    }

    $payload = [
        'events' => array_map(static fn (Event $event): array => [
            'id' => (int) $event->getId(),
            'title' => (string) $event->getTitle(),
            'start' => $event->getStart('Y-m-d H:i:s'),
            'end' => $event->getEnd('Y-m-d H:i:s'),
        ], $result['events']),
    ];

    if ($result['schedule'] !== null) {
        $occurrences = VolunteerOccurrenceQuery::create()
            ->filterById($result['occurrenceIds'], Criteria::IN)
            ->orderByOccurrenceDate()
            ->find();

        $payload['schedule'] = volunteerScheduleToArray($result['schedule']);
        $payload['reusedSchedule'] = $result['reusedSchedule'];
        $payload['occurrences'] = [];
        foreach ($occurrences as $occurrence) {
            $payload['occurrences'][] = [
                'id' => (int) $occurrence->getId(),
                'eventId' => (int) $occurrence->getEventId(),
                'occurrenceDate' => $occurrence->getOccurrenceDate('Y-m-d'),
            ];
        }
        $payload['assigned'] = $result['assigned'];
        $payload['skipped'] = $result['skipped'];
    }

    return SlimUtils::renderJSON($response, $payload, 201);
}

/**
 * @OA\Get(
 *     path="/ministries/ministries/{ministryId}/events",
 *     operationId="listVolunteerMinistryEvents",
 *     summary="The events a ministry owns, with their staffing and headcount (D24, D26)",
 *     description="Events whose event_ministry_id is this ministry, inactive ones included. Upcoming by default (from today, a year ahead, soonest first); past=1 lists the year before today, newest first. from/to override either bound. Each event carries its type, pinned calendars, Linked Groups, headcount total and, per team of this ministry, the staffing of its scheduled occurrences anchored to the event, from the same gap derivation as the occurrence list.",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="ministryId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="from", in="query", required=false, @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="to", in="query", required=false, @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="past", in="query", required=false, @OA\Schema(type="boolean")),
 *     @OA\Response(response=200, description="OK",
 *         @OA\JsonContent(
 *             @OA\Property(property="events", type="array", @OA\Items(type="object",
 *                 @OA\Property(property="id", type="integer"),
 *                 @OA\Property(property="title", type="string"),
 *                 @OA\Property(property="start", type="string"),
 *                 @OA\Property(property="end", type="string"),
 *                 @OA\Property(property="eventTypeId", type="integer"),
 *                 @OA\Property(property="eventTypeName", type="string", nullable=true),
 *                 @OA\Property(property="inactive", type="boolean"),
 *                 @OA\Property(property="calendars", type="array", @OA\Items(type="object")),
 *                 @OA\Property(property="linkedGroups", type="array", @OA\Items(type="object")),
 *                 @OA\Property(property="headcount", type="object",
 *                     @OA\Property(property="recorded", type="boolean"),
 *                     @OA\Property(property="total", type="integer")
 *                 ),
 *                 @OA\Property(property="otherStaffing", type="array", description="D28: other ministries with an occurrence on the event and how many of their volunteers are assigned (pending or accepted)", @OA\Items(type="object",
 *                     @OA\Property(property="ministryId", type="integer"),
 *                     @OA\Property(property="ministryName", type="string"),
 *                     @OA\Property(property="assigned", type="integer")
 *                 )),
 *                 @OA\Property(property="staffing", type="array", @OA\Items(type="object",
 *                     @OA\Property(property="teamId", type="integer"),
 *                     @OA\Property(property="teamName", type="string"),
 *                     @OA\Property(property="occurrenceIds", type="array", @OA\Items(type="integer")),
 *                     @OA\Property(property="needed", type="integer"),
 *                     @OA\Property(property="filled", type="integer"),
 *                     @OA\Property(property="pending", type="integer"),
 *                     @OA\Property(property="gap", type="integer"),
 *                     @OA\Property(property="requirementCount", type="integer"),
 *                     @OA\Property(property="status", type="string", enum={"unplanned","gap","pending","filled"})
 *                 ))
 *             )),
 *             @OA\Property(property="from", type="string", format="date"),
 *             @OA\Property(property="to", type="string", format="date"),
 *             @OA\Property(property="past", type="boolean"),
 *             @OA\Property(property="limit", type="integer"),
 *             @OA\Property(property="capped", type="boolean")
 *         )
 *     ),
 *     @OA\Response(response=400, description="A malformed or inverted window"),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not a coordinator of this ministry, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such ministry")
 * )
 */
function listVolunteerMinistryEvents(Request $request, Response $response): Response
{
    $ministry = $request->getAttribute('volunteerMinistry');
    $params = $request->getQueryParams();
    $past = isset($params['past']) && filter_var($params['past'], FILTER_VALIDATE_BOOLEAN);

    $from = volunteerParseDateParam($params['from'] ?? null);
    $to = volunteerParseDateParam($params['to'] ?? null);
    if ((($params['from'] ?? '') !== '' && $from === null) || (($params['to'] ?? '') !== '' && $to === null)) {
        return SlimUtils::renderErrorJSON($response, gettext('Dates must be in YYYY-MM-DD form'), [], 400, null, $request);
    }

    $today = DateTimeUtils::getTodayDate();
    if ($past) {
        $to ??= DateTimeUtils::getDateRelativeTo($today, '-1 day');
        $from ??= DateTimeUtils::getDateRelativeTo($to, '-1 year');
    } else {
        $from ??= $today;
        $to ??= DateTimeUtils::getDateRelativeTo($from, '+1 year');
    }
    if ($to < $from) {
        return SlimUtils::renderErrorJSON($response, gettext('The window ends before it starts'), [], 400, null, $request);
    }

    $result = (new VolunteerEventService())->listMinistryEvents($ministry, $from, $to, $past);

    return SlimUtils::renderJSON($response, [
        'events' => $result['events'],
        'from' => $from,
        'to' => $to,
        'past' => $past,
        'limit' => VolunteerEventService::MAX_EVENT_LIST,
        'capped' => $result['capped'],
    ]);
}

/**
 * @OA\Get(
 *     path="/ministries/positions/{positionId}/eligible",
 *     operationId="listVolunteerPositionEligiblePeople",
 *     summary="Who may fill a position on a schedule that is about to be created",
 *     description="The new-event dialog's Fill by default with picker (D24): the same rotation-ordered list as /schedules/{id}/eligible, for the position's own team.",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="positionId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="q", in="query", required=false, @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="OK",
 *         @OA\JsonContent(@OA\Property(property="people", type="array", @OA\Items(type="object")))
 *     ),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized for this position, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such position")
 * )
 */
function listVolunteerPositionEligiblePeople(Request $request, Response $response): Response
{
    /** @var VolunteerPosition $position */
    $position = $request->getAttribute('volunteerPosition');
    $params = $request->getQueryParams();
    $query = isset($params['q']) && $params['q'] !== '' ? InputUtils::sanitizeText((string) $params['q']) : null;

    return SlimUtils::renderJSON($response, [
        'people' => (new VolunteerAssignmentService())->getEligiblePeopleForPosition($position, $query),
    ]);
}

/**
 * @OA\Delete(
 *     path="/ministries/ministries/{ministryId}/events",
 *     operationId="deleteVolunteerMinistryEvents",
 *     summary="Delete events this ministry owns (D28)",
 *     description="The Calendar tab's Delete events. Every id must be an event whose event_ministry_id is this ministry, and each is deleted through core's event delete in one transaction - all or none. Refused while another ministry has volunteers assigned (pending or accepted) on any of them unless the caller holds Add Events, and whenever core refuses one (people checked in, a kiosk assigned).",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="ministryId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         required={"eventIds"},
 *         @OA\Property(property="eventIds", type="array", @OA\Items(type="integer"))
 *     )),
 *     @OA\Response(response=200, description="Deleted", @OA\JsonContent(@OA\Property(property="deleted", type="integer"))),
 *     @OA\Response(response=400, description="eventIds is missing, empty or not a list of ids"),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not a coordinator of this ministry, an event another ministry or nobody owns, a self-service login, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such ministry or event"),
 *     @OA\Response(response=409, description="Another ministry has volunteers on one of the events (`ministries` names them), or core refused one (the reason and `eventId`); nothing is deleted")
 * )
 */
function deleteVolunteerMinistryEvents(Request $request, Response $response): Response
{
    /** @var VolunteerMinistry $ministry */
    $ministry = $request->getAttribute('volunteerMinistry');
    $raw = ((array) $request->getParsedBody())['eventIds'] ?? null;

    $eventIds = [];
    foreach (is_array($raw) ? $raw : [null] as $value) {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            return SlimUtils::renderErrorJSON($response, gettext('eventIds must be a list of event ids'), [], 400, null, $request);
        }
        $eventIds[] = $id;
    }

    try {
        $deleted = (new VolunteerEventService())->deleteOwnedEvents($ministry, $eventIds, AuthenticationManager::getCurrentUser());
    } catch (VolunteerException $e) {
        return SlimUtils::renderErrorJSON($response, $e->getMessage(), $e->getExtra(), $e->getStatusCode(), null, $request);
    }

    return SlimUtils::renderJSON($response, ['deleted' => $deleted]);
}

/**
 * @OA\Get(
 *     path="/ministries/teams/{teamId}/class-events",
 *     operationId="getVolunteerTeamClassEvents",
 *     summary="The events this team's ministry owns for the team's class (D28)",
 *     description="What the team dialog asks about before the class changes or the team is deleted: how many events whose event_ministry_id is the team's ministry have the team's current class as Linked Group, how many of them are today or later, and which other ministries staff them. Events an administrator created are not counted. All zero for a team with no class.",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="teamId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="OK",
 *         @OA\JsonContent(
 *             @OA\Property(property="teamId", type="integer"),
 *             @OA\Property(property="ministryId", type="integer"),
 *             @OA\Property(property="ministryName", type="string"),
 *             @OA\Property(property="classGroupId", type="integer", nullable=true),
 *             @OA\Property(property="classGroupName", type="string", nullable=true),
 *             @OA\Property(property="total", type="integer"),
 *             @OA\Property(property="upcoming", type="integer"),
 *             @OA\Property(property="otherStaffing", type="array", @OA\Items(type="object",
 *                 @OA\Property(property="ministryId", type="integer"),
 *                 @OA\Property(property="ministryName", type="string"),
 *                 @OA\Property(property="assigned", type="integer"),
 *                 @OA\Property(property="eventCount", type="integer")
 *             ))
 *         )
 *     ),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized for this team, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such team")
 * )
 */
function getVolunteerTeamClassEvents(Request $request, Response $response): Response
{
    /** @var VolunteerTeam $team */
    $team = $request->getAttribute('volunteerTeam');
    $ministryId = (int) $team->getMinistryId();
    $class = $team->getClassGroup();

    $summary = $class === null
        ? ['total' => 0, 'upcoming' => 0, 'otherStaffing' => []]
        : (new VolunteerEventService())->summarizeOwnedClassEvents($ministryId, (int) $class->getId());

    return SlimUtils::renderJSON($response, [
        'teamId' => (int) $team->getId(),
        'ministryId' => $ministryId,
        'ministryName' => (string) ($team->getMinistry()?->getName() ?? ''),
        'classGroupId' => $class === null ? null : (int) $class->getId(),
        'classGroupName' => $class === null ? null : (string) $class->getName(),
    ] + $summary);
}
