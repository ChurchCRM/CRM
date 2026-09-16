<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\Photo;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Base\EventQuery;
use ChurchCRM\model\ChurchCRM\Base\EventTypeQuery;
use ChurchCRM\model\ChurchCRM\Calendar;
use ChurchCRM\model\ChurchCRM\CalendarQuery;
use ChurchCRM\model\ChurchCRM\Event;
use ChurchCRM\model\ChurchCRM\KioskAssignmentQuery;
use ChurchCRM\model\ChurchCRM\EventAudience;
use ChurchCRM\model\ChurchCRM\EventAudienceQuery;
use ChurchCRM\model\ChurchCRM\EventAttendQuery;
use ChurchCRM\model\ChurchCRM\EventCounts;
use ChurchCRM\model\ChurchCRM\EventCountsQuery;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\Map\ListOptionTableMap;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\VolunteerMinistryQuery;
use ChurchCRM\Plugin\Hook\HookManager;
use ChurchCRM\Plugin\Hooks;
use ChurchCRM\Service\EventService;
use ChurchCRM\Service\VolunteerAuthorizationService;
use ChurchCRM\Slim\Middleware\EventsMiddleware;
use ChurchCRM\Slim\Middleware\InputSanitizationMiddleware;
use ChurchCRM\Slim\Middleware\Request\Auth\AddEventsOrMinistryRoleAuthMiddleware;
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

    $group->post('/quick-create', 'quickCreateEvent')->add(new InputSanitizationMiddleware(['date' => 'date?']))->add(new AddEventsRoleAuthMiddleware());
    $group->post('/generate-recurring', 'generateRecurringEvents')->add(new AddEventsRoleAuthMiddleware());
    $group->post('/', 'newEvent')->add(new InputSanitizationMiddleware(['Title' => 'text', 'Desc' => 'html', 'Text' => 'html']))->add(new AddEventsOrMinistryRoleAuthMiddleware());
    $group->post('', 'newEvent')->add(new InputSanitizationMiddleware(['Title' => 'text', 'Desc' => 'html', 'Text' => 'html']))->add(new AddEventsOrMinistryRoleAuthMiddleware());
    $group->post('/repeat', 'createRepeatEvents')->add(new InputSanitizationMiddleware([
        'Title'      => 'text',
        'Desc'       => 'html',
        'Text'       => 'html',
        // Declared optional so an absent field still reaches the handler's own
        // "Missing required field" check with its existing message (#9821).
        'RecurType'  => 'enum?:weekly,monthly,yearly',
        'RangeStart' => 'date?',
        'RangeEnd'   => 'date?',
    ]))->add(new AddEventsRoleAuthMiddleware());
    $group->post('/{id}', 'updateEvent')->add(new InputSanitizationMiddleware(['Title' => 'text', 'Desc' => 'html', 'Text' => 'html']))->add(new AddEventsOrMinistryRoleAuthMiddleware())->add(new EventsMiddleware());
    $group->post('/{id}/time', 'setEventTime')->add(new AddEventsOrMinistryRoleAuthMiddleware())->add(new EventsMiddleware());
    $group->post('/{id}/checkin', 'checkinPerson')->add(new AddEventsRoleAuthMiddleware())->add(new EventsMiddleware());
    $group->post('/{id}/checkout', 'checkoutPerson')->add(new AddEventsRoleAuthMiddleware())->add(new EventsMiddleware());
    $group->post('/{id}/checkin-all', 'checkinAll')->add(new AddEventsRoleAuthMiddleware())->add(new EventsMiddleware());
    $group->post('/{id}/checkout-all', 'checkoutAll')->add(new AddEventsRoleAuthMiddleware())->add(new EventsMiddleware());
    $group->post('/{id}/checkin-people', 'checkinPeople')->add(new AddEventsRoleAuthMiddleware())->add(new EventsMiddleware());

    $group->delete('/{id}', 'deleteEvent')->add(new AddEventsOrMinistryRoleAuthMiddleware())->add(new EventsMiddleware());
    $group->delete('/{id}/attendance/{personId}', 'deleteAttendance')->add(new AddEventsRoleAuthMiddleware())->add(new EventsMiddleware());

    $group->post('/{id}/status', 'setEventStatus')->add(new AddEventsOrMinistryRoleAuthMiddleware())->add(new EventsMiddleware());

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
 *         @OA\Property(property="End", type="string", format="date-time"),
 *         @OA\Property(property="MinistryId", type="integer", nullable=true, description="Owning volunteer ministry (Volunteer v2), or null"),
 *         @OA\Property(property="MinistryName", type="string", nullable=true, description="Name of the owning volunteer ministry, or null")
 *     )),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=404, description="Event not found")
 * )
 */
function getEvent(Request $request, Response $response, $args): Response
{
    /** @var Event $Event */
    $Event = $request->getAttribute('event');

    if (empty($Event)) {
        throw new HttpNotFoundException($request);
    }

    // Enrich the default Event JSON with the fields the unified editor UI
    // needs: first linked group (EventAudience) and attendance counts per
    // the type's categories. Keeps the UI's fetch payload to one call.
    $eventId = (int) $Event->getId();
    $data = json_decode($Event->toJSON(), true) ?: [];

    $audience = EventAudienceQuery::create()->filterByEventId($eventId)->findOne();
    $data['LinkedGroupId'] = $audience ? (int) $audience->getGroupId() : 0;

    // Volunteer v2 (#9713, §2.16 item 8). `MinistryId` is already in the Event JSON as a
    // raw column; the name is resolved here so the editor and the event view do not each
    // have to fetch the ministry list to render one label. Both stay null when the event
    // has no ministry owner — never 0, because 0 is not a ministry.
    $ministryId = $Event->getMinistryId() === null ? null : (int) $Event->getMinistryId();
    $data['MinistryId'] = $ministryId;
    $data['MinistryName'] = null;
    if ($ministryId !== null) {
        $ministry = VolunteerMinistryQuery::create()->findPk($ministryId);
        $data['MinistryName'] = $ministry === null ? null : $ministry->getName();
    }

    $counts = [];
    foreach (EventCountsQuery::create()->filterByEvtcntEventid($eventId)->orderByEvtcntCountid()->find() as $ec) {
        $counts[] = [
            'id'    => (int) $ec->getEvtcntCountid(),
            'name'  => $ec->getEvtcntCountname(),
            'count' => (int) $ec->getEvtcntCountcount(),
            'notes' => (string) $ec->getEvtcntNotes(),
        ];
    }
    // Fall back to the type's defined count categories when the event has
    // none saved yet, so the editor can show empty inputs for each
    // category rather than an empty section.
    if (empty($counts) && $Event->getType()) {
        foreach (\ChurchCRM\model\ChurchCRM\EventCountNameQuery::create()
            ->filterByTypeId((int) $Event->getType())
            ->orderById()
            ->find() as $cn) {
            $counts[] = [
                'id'    => (int) $cn->getId(),
                'name'  => $cn->getName(),
                'count' => 0,
                'notes' => '',
            ];
        }
    }
    $data['AttendanceCounts'] = $counts;

    return SlimUtils::renderJSON($response, $data);
}

// ─── Volunteer v2: per-row event authorization (#9713, design §4.6, §2.16) ───────────
//
// There is no per-row event authorization in ChurchCRM. D9 adds exactly one rule, and these
// three functions are its entire implementation — nothing else may re-derive it.
//
//     allowed = canManageEvents()                                  // the global right, unchanged
//            || (rollout on && event.MinistryId !== null
//                           && canManageMinistry(user, event.MinistryId))
//
// `AddEventsOrMinistryRoleAuthMiddleware` has already let the caller in the door (§4.5 layer
// one); these decide the row (§4.5 layer three). They run BEFORE the event is saved, so a
// refused create leaves no row behind.

/**
 * Per-request memo. `VolunteerAuthorizationService` caches the caller's scope rows, so the
 * five handlers must share one instance rather than each triggering the same query.
 */
function eventVolunteerAuthz(): VolunteerAuthorizationService
{
    static $authz = null;

    if ($authz === null) {
        $authz = new VolunteerAuthorizationService();
    }

    return $authz;
}

/**
 * The ministry id a write payload asks for.
 *
 * `''`, `0` and `null` all mean "no ministry" — the editor's select uses `0` for its
 * "No ministry" option, the API accepts an explicit `null`, and neither may be confused
 * with "the key was absent", which means "leave the current value alone".
 *
 * @return array{0: bool, 1: ?int} [the key was present, the requested ministry id]
 */
function eventMinistryFromInput(array $input): array
{
    if (!array_key_exists('MinistryId', $input)) {
        return [false, null];
    }

    $raw = $input['MinistryId'];
    if ($raw === null || $raw === '' || (int) $raw === 0) {
        return [true, null];
    }

    return [true, (int) $raw];
}

/**
 * May this caller write an event row whose ministry is `$eventMinistryId`?
 *
 * `null` (no ministry owner) means the event behaves exactly as it always has: only the
 * global AddEvent right opens it.
 */
function eventWriteAllowed(User $user, ?int $eventMinistryId): bool
{
    if ($user->canManageEvents()) {
        return true;
    }

    if (!User::isVolunteerV2Enabled() || $eventMinistryId === null) {
        return false;
    }

    return eventVolunteerAuthz()->canManageMinistry($user, $eventMinistryId);
}

/**
 * May this caller pin an event to `$calendar`?
 *
 * The global Add Events right opens every calendar, as it always has. A ministry
 * coordinator who does NOT hold it may pin to exactly one kind of calendar: one their
 * own ministry owns (`calendars.ministry_id`, Member Portal design §5.3). That is the
 * whole of the exception — the same shape the Group hooks got for the ministry's pool
 * (D19), and the reason every ministry is created with a calendar of its own.
 *
 * A coordinator therefore cannot quietly write onto "Public Calendar", which is what a
 * church would notice; and "pin to my own ministry's calendar" needs no new permission,
 * which is what makes a coordinator useful without Add Events.
 */
function eventCalendarPinAllowed(User $user, Calendar $calendar): bool
{
    if ($user->canManageEvents()) {
        return true;
    }

    if (!User::isVolunteerV2Enabled()) {
        return false;
    }

    $ministryId = $calendar->getMinistryId();
    if ($ministryId === null) {
        return false;
    }

    return eventVolunteerAuthz()->canManageMinistry($user, (int) $ministryId);
}

/**
 * Guard for the pinned-calendar list on a create or an update. Returns a 403 naming the
 * calendar that was refused, or null when every one of them is allowed.
 *
 * Runs BEFORE the event is saved, like `eventWriteGuard()`, so a refused pin leaves no row
 * and no half-pinned event behind.
 *
 * @param iterable<Calendar> $calendars
 */
function eventCalendarPinGuard(Response $response, iterable $calendars): ?Response
{
    $user = AuthenticationManager::getCurrentUser();

    foreach ($calendars as $calendar) {
        if (!eventCalendarPinAllowed($user, $calendar)) {
            return SlimUtils::renderErrorJSON(
                $response,
                sprintf(
                    /* Translators: %s is the name of a calendar the user may not write to. */
                    gettext('Not authorized to pin events to the calendar "%s"'),
                    (string) $calendar->getName()
                ),
                [],
                403
            );
        }
    }

    return null;
}

/**
 * Guard for the four handlers that write an EXISTING event (update, time, status, delete).
 * Returns a 403 response when the caller may not touch this row, null when they may.
 */
function eventWriteGuard(Request $request, Response $response, Event $event): ?Response
{
    $ministryId = $event->getMinistryId() === null ? null : (int) $event->getMinistryId();

    if (eventWriteAllowed(AuthenticationManager::getCurrentUser(), $ministryId)) {
        return null;
    }

    return SlimUtils::renderErrorJSON(
        $response,
        gettext('Not authorized to modify this event'),
        [],
        403
    );
}

/**
 * Decide what `event_ministry_id` a write should end up with, and whether the caller is
 * allowed to put it there (§2.16 item 9).
 *
 * Setting a ministry requires authority over the NEW value; replacing or clearing one
 * requires authority over the CURRENT value as well. Clearing to "no ministry" additionally
 * requires the global AddEvent right, because nobody has ministry authority over an event
 * that has no ministry — a coordinator clearing the field would put the event permanently
 * out of their own reach, and §4.6 asks for authority over the value being set.
 *
 * Returns the id to store, or a Response to return instead (400 unknown ministry, 403 not
 * authorized for it).
 */
function resolveEventMinistryId(Response $response, array $input, ?int $currentMinistryId): int|Response|null
{
    [$present, $requested] = eventMinistryFromInput($input);

    if (!$present || $requested === $currentMinistryId) {
        return $currentMinistryId;
    }

    $user = AuthenticationManager::getCurrentUser();
    $authz = eventVolunteerAuthz();

    if ($requested !== null) {
        if (VolunteerMinistryQuery::create()->findPk($requested) === null) {
            return SlimUtils::renderErrorJSON($response, gettext('Unknown volunteer ministry'), [], 400);
        }
        if (!$authz->canManageMinistry($user, $requested)) {
            return SlimUtils::renderErrorJSON(
                $response,
                gettext('Not authorized to assign events to that volunteer ministry'),
                [],
                403
            );
        }
    } elseif (!$user->canManageEvents()) {
        return SlimUtils::renderErrorJSON(
            $response,
            gettext('Not authorized to remove the volunteer ministry from this event'),
            [],
            403
        );
    }

    if ($currentMinistryId !== null && !$authz->canManageMinistry($user, $currentMinistryId)) {
        return SlimUtils::renderErrorJSON(
            $response,
            gettext('Not authorized to change the volunteer ministry of this event'),
            [],
            403
        );
    }

    return $requested;
}

/**
 * Shared helper: apply LinkedGroupId + AttendanceCounts[] from the unified
 * editor payload to an existing Event row. Idempotent — call after each
 * newEvent / updateEvent save.
 *
 * `MinistryId` is deliberately NOT handled here even though it arrives in the same payload:
 * it is a column on `events_event` and it carries an authorization decision, so it is
 * resolved by `resolveEventMinistryId()` and written BEFORE the row is saved. A refused
 * ministry must leave no event behind, which a post-save hook could not guarantee. See the
 * explicit `setMinistryId()` calls in newEvent/updateEvent — and note that `updateEvent`'s
 * `fromArray($input)` would otherwise copy `MinistryId` straight through unchecked (§2.16
 * item 7), which is exactly why that call is followed by an authoritative overwrite.
 */
function applyEventExtendedFields(Event $event, array $input): void
{
    $eventId = (int) $event->getId();

    if (array_key_exists('LinkedGroupId', $input)) {
        $linkedGroupId = (int) $input['LinkedGroupId'];
        EventAudienceQuery::create()->filterByEventId($eventId)->delete();
        if ($linkedGroupId > 0) {
            $audience = new EventAudience();
            $audience->setEventId($eventId);
            $audience->setGroupId($linkedGroupId);
            $audience->save();
        }
    }

    if (array_key_exists('AttendanceCounts', $input) && is_array($input['AttendanceCounts'])) {
        // Whitelist count IDs by the event's type and source the canonical
        // category name from EventCountName — never trust the client-supplied
        // `name`. Notes are plain text: strip tags so a rogue payload can't
        // land raw HTML/script in a field that later shows in the UI.
        $typeId = (int) $event->getType();
        $allowedNames = [];
        foreach (\ChurchCRM\model\ChurchCRM\EventCountNameQuery::create()
            ->filterByTypeId($typeId)
            ->find() as $cn) {
            $allowedNames[(int) $cn->getId()] = (string) $cn->getName();
        }

        foreach ($input['AttendanceCounts'] as $row) {
            if (!is_array($row) || !isset($row['id'])) {
                continue;
            }
            $countId = (int) $row['id'];
            if ($countId <= 0 || !array_key_exists($countId, $allowedNames)) {
                continue; // unknown category for this event's type
            }
            $count = EventCountsQuery::create()->findPk([$eventId, $countId]);
            if ($count === null) {
                $count = new EventCounts();
                $count->setEvtcntEventid($eventId);
                $count->setEvtcntCountid($countId);
            }
            $count->setEvtcntCountname($allowedNames[$countId]);
            $count->setEvtcntCountcount((int) ($row['count'] ?? 0));
            $count->setEvtcntNotes(InputUtils::sanitizeText((string) ($row['notes'] ?? '')));
            $count->save();
        }
    }
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
 *         @OA\Property(property="PinnedCalendars", type="array", @OA\Items(type="integer"), example={1}),
 *         @OA\Property(property="MinistryId", type="integer", nullable=true, description="Volunteer v2: owning volunteer ministry. Only an administrator, a global volunteer manager or a coordinator of that ministry may set it. A volunteer coordinator without the global AddEvent right MUST supply one they manage.")
 *     )),
 *     @OA\Response(response=200, description="Event created",
 *         @OA\JsonContent(@OA\Property(property="success", type="boolean", example=true))
 *     ),
 *     @OA\Response(response=400, description="Invalid event type, calendar ID or volunteer ministry"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="AddEvents role required, or the caller may not assign the event to that volunteer ministry")
 * )
 */
function newEvent(Request $request, Response $response, array $args): Response
{
    $input = $request->getParsedBody();

    // Volunteer v2 (#9713, §4.6): the role middleware let this caller in either because they
    // hold the global AddEvent right or because they coordinate a ministry. A coordinator may
    // only create events that carry one of THEIR ministries, so the ownership decision is made
    // — and refused — before any row is written.
    $ministryId = resolveEventMinistryId($response, $input, null);
    if ($ministryId instanceof Response) {
        return $ministryId;
    }
    if (!eventWriteAllowed(AuthenticationManager::getCurrentUser(), $ministryId)) {
        return SlimUtils::renderErrorJSON(
            $response,
            gettext('Not authorized to create an event without a volunteer ministry you manage'),
            [],
            403
        );
    }

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

    // Existence was the only check this had. A coordinator without Add Events may pin to
    // their own ministry's calendar and to nothing else (§5.3).
    $pinRefusal = eventCalendarPinGuard($response, $calendars);
    if ($pinRefusal !== null) {
        return $pinRefusal;
    }

    // we have event type and pined calendars.  now create the event.
    $event = new Event();
    $event->setTitle($input['Title']);
    $event->setEventType($type);
    // InputSanitizationMiddleware already sanitizes these HTML fields; just ensure they're set
    $desc = isset($input['Desc']) ? $input['Desc'] : '';
    $text = isset($input['Text']) ? $input['Text'] : '';
    $event->setDesc($desc);
    $event->setText($text);
    $event->setStart(str_replace('T', ' ', $input['Start']));
    $event->setEnd(str_replace('T', ' ', $input['End']));
    if (array_key_exists('InActive', $input)) {
        $event->setInActive((int) $input['InActive']);
    }
    $event->setMinistryId($ministryId);
    $event->setCalendars($calendars);
    $event->save();
    HookManager::doAction(Hooks::EVENT_CREATED, $event);

    applyEventExtendedFields($event, $input);

    return SlimUtils::renderSuccessJSON($response);
}

/**
 * @OA\Post(
 *     path="/events/repeat",
 *     operationId="createRepeatEvents",
 *     summary="Create a series of repeat events",
 *     description="Generates individual event records for each occurrence of a recurring event within a date range. Each created event is independently editable. Shares one recurrence engine with POST /events/generate-recurring: the same recurrence and date range produce the same occurrence dates on both endpoints, and a single call creates at most 366 events.",
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
 *         @OA\Property(property="Inactive", type="integer", enum={0,1}, nullable=true),
 *         @OA\Property(property="SkipExisting", type="boolean", default=false, description="Skip occurrence dates that already have an active event of this type, making the call idempotent. Same semantics as POST /events/generate-recurring.")
 *     )),
 *     @OA\Response(response=200, description="Repeat events created",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="count", type="integer", example=52),
 *             @OA\Property(property="skipped", type="integer", example=0, description="Occurrences skipped because an event of this type already existed on that date (only non-zero when SkipExisting is true)"),
 *             @OA\Property(property="eventIds", type="array", @OA\Items(type="integer"))
 *         )
 *     ),
 *     @OA\Response(response=400, description="Invalid input (bad event type, invalid dates, unknown recurrence type, or more than 366 occurrences)"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="AddEvents role required")
 * )
 */
function createRepeatEvents(Request $request, Response $response, array $args): Response
{
    $input = $request->getParsedBody() ?? [];

    // Validate required fields up-front so a malformed request returns a
    // clean 400 instead of triggering PHP notices / exceptions deeper in
    // the service.
    $required = ['Title', 'Type', 'StartTime', 'EndTime', 'RecurType', 'RangeStart', 'RangeEnd'];
    foreach ($required as $key) {
        if (!isset($input[$key]) || $input[$key] === '' || $input[$key] === null) {
            return SlimUtils::renderErrorJSON($response, sprintf(gettext('Missing required field: %s'), $key), [], 400);
        }
    }

    // RecurType (enum) and RangeStart / RangeEnd (strict YYYY-MM-DD, no silent
    // roll-over to "now") are validated and normalised declaratively by the
    // InputSanitizationMiddleware on this route — see the route table above.
    $recurType = $input['RecurType'];

    if ($input['RangeEnd'] < $input['RangeStart']) {
        return SlimUtils::renderErrorJSON($response, gettext('RangeEnd must be on or after RangeStart'), [], 400);
    }

    try {
        $service = new EventService();
        $result = $service->createRecurringEvents([
            'title'          => $input['Title'],
            'typeId'         => (int) $input['Type'],
            'desc'           => $input['Desc'] ?? '',
            'text'           => $input['Text'] ?? '',
            'startTime'      => $input['StartTime'],
            'endTime'        => $input['EndTime'],
            'recurType'      => $recurType,
            'recurDOW'       => $input['RecurDOW'] ?? null,
            'recurDOM'       => isset($input['RecurDOM']) ? (int) $input['RecurDOM'] : null,
            'recurDOY'       => $input['RecurDOY'] ?? null,
            'rangeStart'     => $input['RangeStart'],
            'rangeEnd'       => $input['RangeEnd'],
            'pinnedCalendars' => array_map('intval', is_array($input['PinnedCalendars'] ?? null) ? $input['PinnedCalendars'] : []),
            'linkedGroupId'  => (int) ($input['LinkedGroupId'] ?? 0),
            'inactive'       => (int) ($input['Inactive'] ?? 0),
            // Off by default so the historical "create them all again" behaviour
            // is unchanged; opt in for an idempotent re-run (#9735).
            'skipExisting'   => filter_var($input['SkipExisting'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ]);
    } catch (\InvalidArgumentException $e) {
        return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], 400);
    }

    $createdIds = array_column($result['events'], 'id');

    return SlimUtils::renderJSON($response, [
        'success'  => true,
        'count'    => count($createdIds),
        'skipped'  => $result['skipped'],
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
 *         @OA\Property(property="PinnedCalendars", type="array", @OA\Items(type="integer")),
 *         @OA\Property(property="MinistryId", type="integer", nullable=true, description="Volunteer v2: owning volunteer ministry. Omit the key to leave it unchanged; null or 0 clears it (global AddEvent right required to clear).")
 *     )),
 *     @OA\Response(response=200, description="Event updated",
 *         @OA\JsonContent(@OA\Property(property="success", type="boolean", example=true))
 *     ),
 *     @OA\Response(response=400, description="Unknown volunteer ministry"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="AddEvents role required, or the event belongs to a volunteer ministry the caller does not manage"),
 *     @OA\Response(response=404, description="Event not found")
 * )
 */
function updateEvent(Request $request, Response $response, array $args): Response
{
    $input = $request->getParsedBody();
    /** @var Event $Event */
    $Event = $request->getAttribute('event');
    $id = $Event->getId();

    $guard = eventWriteGuard($request, $response, $Event);
    if ($guard !== null) {
        return $guard;
    }

    $currentMinistryId = $Event->getMinistryId() === null ? null : (int) $Event->getMinistryId();
    $ministryId = resolveEventMinistryId($response, $input, $currentMinistryId);
    if ($ministryId instanceof Response) {
        return $ministryId;
    }

    // fromArray copies matching property names (Title, Desc, Start, End,
    // Text, InActive) onto the Event. LinkedGroupId + AttendanceCounts
    // aren't Event columns; applyEventExtendedFields handles those.
    $Event->fromArray($input);
    $Event->setId($id);
    // MinistryId IS an Event column, so fromArray() above has just copied whatever the
    // payload said with no authorization at all (§2.16 item 7). Overwrite it with the value
    // resolveEventMinistryId() authorized — this line is the whole reason that is safe.
    $Event->setMinistryId($ministryId);
    $PinnedCalendars = CalendarQuery::create()
        ->filterById($input['PinnedCalendars'], Criteria::IN)
        ->find();

    // Same rule as newEvent(): the pins a caller may set are the calendars they may write
    // to (§5.3). Checked before the save, so a refused pin leaves the event as it was.
    $pinRefusal = eventCalendarPinGuard($response, $PinnedCalendars);
    if ($pinRefusal !== null) {
        return $pinRefusal;
    }

    $Event->setCalendars($PinnedCalendars);

    $Event->save();

    applyEventExtendedFields($Event, $input);

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

    $guard = eventWriteGuard($request, $response, $event);
    if ($guard !== null) {
        return $guard;
    }

    $event->setStart($input['startTime']);
    $event->setEnd($input['endTime']);
    $event->save();

    return SlimUtils::renderSuccessJSON($response);
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
    $event = $request->getAttribute('event');
    $eventId = (int) $event->getId();

    $guard = eventWriteGuard($request, $response, $event);
    if ($guard !== null) {
        return $guard;
    }

    // Block if event is still open and people are currently checked in.
    if (!$event->getInActive()) {
        $checkedInCount = EventAttendQuery::create()
            ->filterByEventId($eventId)
            ->filterByCheckinDate(null, Criteria::NOT_EQUAL)
            ->filterByCheckoutDate(null, Criteria::EQUAL)
            ->count();
        if ($checkedInCount > 0) {
            return SlimUtils::renderErrorJSON(
                $response,
                sprintf(gettext('Cannot delete event: %d people are currently checked in.'), $checkedInCount),
                [],
                409
            );
        }
    }

    // Block if the event is currently assigned to a kiosk.
    if (KioskAssignmentQuery::create()->filterByEventId($eventId)->exists()) {
        return SlimUtils::renderErrorJSON(
            $response,
            gettext('Cannot delete event: event is currently assigned to a kiosk.'),
            [],
            409
        );
    }

    $event->delete();

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

    $guard = eventWriteGuard($request, $response, $event);
    if ($guard !== null) {
        return $guard;
    }

    $event->setInActive($active ? 0 : 1);
    $event->save();

    return SlimUtils::renderSuccessJSON($response);
}

/**
 * @OA\Post(
 *     path="/events/quick-create",
 *     operationId="quickCreateEvent",
 *     summary="Quick-create an event from EventType defaults",
 *     description="Creates an event with minimal input, using EventType defaults for title, time, and group. **At least one of eventTypeId or groupId is required.** If only groupId is provided, the event type is auto-detected from the group's linked EventType. If an event of the same type already exists for the given date, returns the existing event instead.",
 *     tags={"Calendar"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         @OA\Property(property="eventTypeId", type="integer", example=5, description="Event type ID. Either eventTypeId OR groupId is required."),
 *         @OA\Property(property="groupId", type="integer", example=12, description="Group ID — when provided without eventTypeId, the event type is auto-detected from the group's linked EventType."),
 *         @OA\Property(property="date", type="string", format="date", example="2026-04-05", description="Event date (defaults to today)")
 *     )),
 *     @OA\Response(response=200, description="Event created or existing event found",
 *         @OA\JsonContent(
 *             @OA\Property(property="eventId", type="integer", example=42),
 *             @OA\Property(property="created", type="boolean", example=true),
 *             @OA\Property(property="title", type="string", example="Youth Sunday School — Apr 5, 2026")
 *         )
 *     ),
 *     @OA\Response(response=400, description="Invalid event type ID, or a date that is not a valid YYYY-MM-DD"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="AddEvents role required")
 * )
 */
function quickCreateEvent(Request $request, Response $response, array $args): Response
{
    $input = $request->getParsedBody();
    $eventTypeId = InputUtils::filterInt($input['eventTypeId'] ?? 0);
    $groupId = InputUtils::filterInt($input['groupId'] ?? 0);

    // 'date' is an optional, strictly validated YYYY-MM-DD field: the
    // InputSanitizationMiddleware on this route rejects anything else and
    // leaves an unsupplied value untouched, so it only has to default here.
    $date = (string) ($input['date'] ?? '');
    if ($date === '') {
        $date = date('Y-m-d');
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

    // If still no event type and the group is a Sunday School class, fall back to
    // the "Sunday School" event type by name so quick-create works out of the box
    // even before the admin explicitly links the type to a group.
    if ($eventType === null && $groupId > 0) {
        $group = GroupQuery::create()->findOneById($groupId);
        if ($group !== null && $group->isSundaySchool()) {
            $eventType = EventTypeQuery::create()
                ->filterByName('Sunday School')
                ->filterByActive(1)
                ->findOne();
            if ($eventType !== null) {
                $eventTypeId = $eventType->getId();
            }
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

    // Calculate start/end times from type defaults. getDefStartTime() can
    // return either a DateTime or a raw string depending on the Propel
    // codegen path that built the model — handle both.
    $startTimeStr = '09:00:00';
    if ($eventType !== null) {
        $defStartTime = $eventType->getDefStartTime();
        if ($defStartTime instanceof \DateTimeInterface) {
            $startTimeStr = $defStartTime->format('H:i:s');
        } elseif (is_string($defStartTime) && $defStartTime !== '') {
            $parsed = \DateTimeImmutable::createFromFormat('H:i:s', $defStartTime)
                ?: \DateTimeImmutable::createFromFormat('H:i', $defStartTime);
            if ($parsed !== false) {
                $startTimeStr = $parsed->format('H:i:s');
            }
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
    HookManager::doAction(Hooks::EVENT_CREATED, $event);

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
            ->addAsColumn('RoleName', ListOptionTableMap::COL_LST_OPTIONNAME)
        ->endUse()
        ->leftJoinEventAttend()
        ->addJoinCondition('EventAttend', 'event_attend.event_id = ?', $event->getId())
        ->addAsColumn('CheckinDate', 'event_attend.checkin_date')
        ->addAsColumn('CheckoutDate', 'event_attend.checkout_date')
        ->addAsColumn('AttendStatus', '(CASE WHEN event_attend.event_id IS NOT NULL AND event_attend.checkout_date IS NULL AND event_attend.checkin_date IS NOT NULL THEN \'checked_in\' WHEN event_attend.checkout_date IS NOT NULL THEN \'checked_out\' ELSE \'not_checked_in\' END)')
        ->filterByLiving()
        ->orderByLastName()
        ->orderByFirstName()
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
    // Filter out deceased persons in one batch query — they cannot be checked in
    if (!empty($uniquePersonIds)) {
        $livingIds = PersonQuery::create()
            ->filterById(array_keys($uniquePersonIds), Criteria::IN)
            ->filterByLiving()
            ->select(['Id'])
            ->find()
            ->toArray();
        $livingIdSet = array_flip(array_map('intval', $livingIds));
    } else {
        $livingIdSet = [];
    }
    foreach (array_keys($uniquePersonIds) as $personId) {
        if (!isset($livingIdSet[$personId])) {
            continue;
        }
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
 *     description="Creates multiple events between startDate and endDate based on the EventType's recurrence pattern (weekly, monthly, or yearly). Optionally skips dates where an event of the same type already exists. Shares one recurrence engine with POST /events/repeat: the same recurrence and date range produce the same occurrence dates on both endpoints, and a single call creates at most 366 events.",
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
 *     @OA\Response(response=400, description="Invalid input (bad event type, invalid dates, event type with no recurrence configured, or more than 366 occurrences)"),
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

    // Thin adapter over the one recurring-event generator (#9735). Everything
    // this endpoint used to do inline — occurrence dates, the generated
    // "<Type> — M j, Y" title, the event type's default start time with a
    // one-hour duration, and skipExisting dedup — is the service's documented
    // behaviour when the caller supplies no title, times or recurrence.
    try {
        $service = new EventService();
        $result = $service->createRecurringEvents([
            'typeId'          => $eventTypeId,
            'rangeStart'      => $startDate,
            'rangeEnd'        => $endDate,
            'skipExisting'    => $skipExisting,
            'pinnedCalendars' => array_map('intval', is_array($pinnedCalendarIds) ? $pinnedCalendarIds : []),
            'linkedGroupId'   => (int) $eventType->getGroupId(),
        ]);
    } catch (\InvalidArgumentException $e) {
        return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], 400);
    }

    return SlimUtils::renderJSON($response, [
        'created' => count($result['events']),
        'skipped' => $result['skipped'],
        'events' => $result['events'],
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
