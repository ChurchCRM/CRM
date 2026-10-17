<?php

namespace ChurchCRM\Volunteer\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Exceptions\EventDeleteRefusedException;
use ChurchCRM\model\ChurchCRM\CalendarEventQuery;
use ChurchCRM\model\ChurchCRM\CalendarQuery;
use ChurchCRM\model\ChurchCRM\Event;
use ChurchCRM\model\ChurchCRM\EventAttendQuery;
use ChurchCRM\model\ChurchCRM\EventAudience;
use ChurchCRM\model\ChurchCRM\EventAudienceQuery;
use ChurchCRM\model\ChurchCRM\EventCountNameQuery;
use ChurchCRM\model\ChurchCRM\EventCountsQuery;
use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\model\ChurchCRM\EventType;
use ChurchCRM\model\ChurchCRM\EventTypeQuery;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\Map\EventTableMap;
use ChurchCRM\model\ChurchCRM\Person2group2roleP2g2rQuery;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\VolunteerAssignmentQuery;
use ChurchCRM\model\ChurchCRM\VolunteerMinistry;
use ChurchCRM\model\ChurchCRM\VolunteerMinistryQuery;
use ChurchCRM\model\ChurchCRM\VolunteerOccurrence;
use ChurchCRM\model\ChurchCRM\VolunteerOccurrenceQuery;
use ChurchCRM\model\ChurchCRM\VolunteerSchedule;
use ChurchCRM\model\ChurchCRM\VolunteerScheduleQuery;
use ChurchCRM\model\ChurchCRM\VolunteerTeamQuery;
use ChurchCRM\Plugin\Hook\HookManager;
use ChurchCRM\Plugin\Hooks;
use ChurchCRM\Service\EventService;
use ChurchCRM\Service\RecurrenceDateGenerator;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Volunteer\VolunteerException;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Propel;
use Psr\Log\LoggerInterface;

/**
 * A ministry's own calendar events (D24) and the event facts V2 shows beside staffing (D26).
 *
 * Core owns the events. They are created by `EventService` — the single-event path behind
 * `POST /api/events` and the repeat engine behind the repeat editor — with `event_ministry_id`
 * set, and they are edited and deleted only in the core event editor, which authorizes by
 * that column (§2.16). Creating events and staffing them are separate steps (D33): what V2 adds
 * after the create is "Staff them" — the new events go to the schedules that already follow
 * them, through `VolunteerScheduleService`.
 *
 * Headcounts (`eventcounts_evtcnt`) and check-ins (`event_attend`) are core's and are only read.
 */
class VolunteerEventService
{
    /** Hard cap on one Calendar tab listing, the occurrence list's number (design M9). */
    public const MAX_EVENT_LIST = VolunteerScheduleService::MAX_OCCURRENCE_LIST;

    private const WEEKDAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    /** The event type added for a ministry's own events when V2 is turned on (D31, #10357). */
    public const OTHER_EVENT_TYPE_NAME = 'Other';

    /** D28: what happens to a ministry's own events of a class its team stops staffing. */
    public const CLASS_EVENTS_KEEP = 'keep';
    public const CLASS_EVENTS_REMOVE = 'remove';
    public const CLASS_EVENTS_MOVE = 'move';
    public const CLASS_EVENTS_DELETE = 'delete';

    private LoggerInterface $logger;

    private VolunteerAuthorizationService $authz;

    private ?VolunteerAssignmentService $assignments = null;

    public function __construct(
        ?VolunteerAuthorizationService $authz = null,
        private readonly EventService $events = new EventService(),
        private readonly VolunteerScheduleService $schedules = new VolunteerScheduleService(),
    ) {
        $this->authz = $authz ?? new VolunteerAuthorizationService();
        $this->logger = LoggerUtils::getAppLogger();
    }

    // ── Creating a ministry's events (D24) ─────────────────────────────────

    /**
     * The event type a ministry's new event starts with (D31): the one Admin → Ministry
     * Settings names, else the type called "Other", else none. Only an active type counts,
     * so renaming, retiring or deleting the chosen one falls back instead of preselecting
     * nothing.
     */
    public static function defaultEventTypeId(): ?int
    {
        $configured = SystemConfig::getIntValue('iVolunteerDefaultEventTypeId');
        $type = $configured > 0
            ? EventTypeQuery::create()->filterById($configured)->filterByActive(1)->findOne()
            : null;
        $type ??= EventTypeQuery::create()
            ->filterByName(self::OTHER_EVENT_TYPE_NAME)
            ->filterByActive(1)
            ->orderById()
            ->findOne();

        return $type === null ? null : (int) $type->getId();
    }

    /**
     * Add the "Other" type when V2 is turned on (#10357), unless the church already has a type
     * of that name, active or not. Never on an upgrade, so a church that stays on V1 does not
     * see a new type.
     */
    public static function addOtherEventType(): void
    {
        if (EventTypeQuery::create()->filterByName(self::OTHER_EVENT_TYPE_NAME)->exists()) {
            return;
        }

        $type = new EventType();
        $type->setName(self::OTHER_EVENT_TYPE_NAME);
        $type->setDefRecurType('none');
        $type->setDefRecurDOM('');
        $type->setActive(1);
        $type->save();

        LoggerUtils::getAppLogger()->info('Volunteer v2 added the "Other" event type', ['typeId' => (int) $type->getId()]);
    }

    /**
     * Create one event or a series for this ministry through core, pinned to calendars the
     * pin rule allows (D25), in one transaction. Staffing comes after, as its own step (D33):
     * {@see self::staffOnFollowingSchedules()} or Staff this event.
     *
     * @param array<string, mixed> $input the request body (design §3.3.2)
     *
     * @return Event[]
     *
     * @throws VolunteerException        403 for a caller who is not a coordinator of the ministry or
     *                                   a refused calendar, 400 for a malformed request or a `staff` key
     * @throws \InvalidArgumentException 400: a series the repeat engine refuses (the cap)
     */
    public function createMinistryEvents(VolunteerMinistry $ministry, array $input, User $actor): array
    {
        $ministryId = (int) $ministry->getId();

        $this->assertMayManageEvents($ministryId, $actor, gettext('Only a coordinator of this ministry may create its events'));

        if (array_key_exists('staff', $input)) {
            throw VolunteerException::invalid(gettext('Staffing is set up after the events are created'));
        }

        $plan = $this->readEventPlan($ministryId, $input, $actor);

        $con = Propel::getWriteConnection(EventTableMap::DATABASE_NAME);
        $con->beginTransaction();

        try {
            $events = $plan['series'] === null
                ? [$this->createOneEvent($plan, $ministryId)]
                : $this->createSeries($plan, $ministryId);

            $con->commit();
        } catch (\Throwable $e) {
            $con->rollBack();

            throw $e;
        }

        // After the commit, so a plugin never hears of an event that was rolled back.
        foreach ($events as $event) {
            HookManager::doAction(Hooks::EVENT_CREATED, $event);
        }

        $this->logger->info('Volunteer ministry events created', [
            'ministryId' => $ministryId,
            'eventIds' => $this->eventIds($events),
            'actorPersonId' => $actor->getId(),
        ]);

        return $events;
    }

    /**
     * A coordinator of the ministry or a global manager; a self-service login never writes
     * church events, whatever scope it holds (§4.7).
     *
     * @throws VolunteerException 403
     */
    private function assertMayManageEvents(int $ministryId, User $actor, string $message): void
    {
        if ($actor->isEditSelfExclusive() || !$this->authz->canManageMinistry($actor, $ministryId)) {
            throw VolunteerException::forbidden($message);
        }
    }

    /**
     * Everything about the events the request asks for, validated, before anything is written.
     *
     * @param array<string, mixed> $input
     *
     * @return array{title: string, type: \ChurchCRM\model\ChurchCRM\EventType, description: string, linkedGroupId: int, calendars: \Propel\Runtime\Collection\Collection, startTime: string, endTime: string, date: ?string, series: ?array{type: string, dow: ?string, dom: ?int, doy: ?string, rangeStart: string, rangeEnd: string}}
     *
     * @throws VolunteerException
     */
    private function readEventPlan(int $ministryId, array $input, User $actor): array
    {
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw VolunteerException::invalid(gettext('An event title is required'));
        }
        if (mb_strlen($title) > 255) {
            throw VolunteerException::invalid(gettext('The event title is too long'));
        }

        $typeId = $this->positiveInt($input['eventTypeId'] ?? null);
        $type = $typeId === null ? null : EventTypeQuery::create()->findPk($typeId);
        if ($type === null) {
            throw VolunteerException::invalid(gettext('Choose an existing event type'));
        }

        $description = trim((string) ($input['description'] ?? ''));
        if (mb_strlen($description) > 255) {
            throw VolunteerException::invalid(gettext('The description is too long'));
        }

        $linkedGroupId = 0;
        $rawGroup = $input['linkedGroupId'] ?? null;
        if (!in_array($rawGroup, [null, '', 0, '0'], true)) {
            VolunteerClassLinkService::assertMinistryTeaches($ministryId);
            $linkedGroupId = $this->positiveInt($rawGroup) ?? 0;
            if ($linkedGroupId === 0 || GroupQuery::create()->findPk($linkedGroupId) === null) {
                throw VolunteerException::invalid(gettext('The class does not exist'));
            }
        }

        $startTime = $this->requireTime($input['startTime'] ?? null);
        $endTime = $this->requireTime($input['endTime'] ?? null);
        if ($endTime <= $startTime) {
            throw VolunteerException::invalid(gettext('The event must end after it starts'));
        }

        $series = null;
        $date = null;
        if (array_key_exists('recurrence', $input) && $input['recurrence'] !== null) {
            $series = $this->readRecurrence($input);
        } else {
            $date = $this->requireDate($input['date'] ?? null, gettext('Choose the date of the event'));
        }

        return [
            'title' => $title,
            'type' => $type,
            'description' => $description,
            'linkedGroupId' => $linkedGroupId,
            'calendars' => $this->readCalendars($ministryId, $input, $actor),
            'startTime' => $startTime,
            'endTime' => $endTime,
            'date' => $date,
            'series' => $series,
        ];
    }

    /**
     * The calendars to pin. Omitted means the ministry's own calendar; every one asked for
     * must pass the pin rule for THIS ministry (D25), or the whole request is refused.
     *
     * @param array<string, mixed> $input
     *
     * @throws VolunteerException
     */
    private function readCalendars(int $ministryId, array $input, User $actor): \Propel\Runtime\Collection\Collection
    {
        if (!array_key_exists('calendarIds', $input)) {
            $calendars = CalendarQuery::create()->filterByMinistryId($ministryId)->find();
        } else {
            $raw = $input['calendarIds'];
            if (!is_array($raw)) {
                throw VolunteerException::invalid(gettext('calendarIds must be a list of calendar ids'));
            }
            $ids = [];
            foreach ($raw as $value) {
                $id = $this->positiveInt($value);
                if ($id === null) {
                    throw VolunteerException::invalid(gettext('calendarIds must be a list of calendar ids'));
                }
                $ids[$id] = $id;
            }
            $calendars = CalendarQuery::create()->filterById(array_values($ids), Criteria::IN)->find();
            if (count($calendars) !== count($ids)) {
                throw VolunteerException::invalid(gettext('One of the calendars does not exist'));
            }
        }

        $rule = new VolunteerCalendarService($this->authz);
        foreach ($calendars as $calendar) {
            if (!$rule->mayPin($actor, $ministryId, $calendar)) {
                throw VolunteerException::forbidden(sprintf(
                    /* Translators: %s is the name of a calendar the user may not write to. */
                    gettext('Not authorized to pin events to the calendar "%s"'),
                    (string) $calendar->getName()
                ));
            }
        }

        return $calendars;
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array{type: string, dow: ?string, dom: ?int, doy: ?string, rangeStart: string, rangeEnd: string}
     *
     * @throws VolunteerException
     */
    private function readRecurrence(array $input): array
    {
        $recurrence = $input['recurrence'];
        if (!is_array($recurrence)) {
            throw VolunteerException::invalid(gettext('The recurrence must say how the events repeat'));
        }

        $type = (string) ($recurrence['type'] ?? '');
        if (!in_array($type, RecurrenceDateGenerator::SUPPORTED_RECUR_TYPES, true)) {
            throw VolunteerException::invalid(gettext('Choose weekly, monthly or yearly'));
        }

        $dow = null;
        $dom = null;
        $doy = null;
        switch ($type) {
            case RecurrenceDateGenerator::RECUR_WEEKLY:
                foreach (self::WEEKDAYS as $weekday) {
                    if (strcasecmp($weekday, trim((string) ($recurrence['dow'] ?? ''))) === 0) {
                        $dow = $weekday;
                    }
                }
                if ($dow === null) {
                    throw VolunteerException::invalid(gettext('Choose the day of the week'));
                }
                break;

            case RecurrenceDateGenerator::RECUR_MONTHLY:
                $dom = $this->positiveInt($recurrence['dom'] ?? null);
                if ($dom === null || $dom > 31) {
                    throw VolunteerException::invalid(gettext('Choose a day of the month from 1 to 31'));
                }
                break;

            default:
                $doy = trim((string) ($recurrence['doy'] ?? ''));
                if (preg_match('/^(\d{2})-(\d{2})$/', $doy, $parts) !== 1 || !checkdate((int) $parts[1], (int) $parts[2], 2000)) {
                    throw VolunteerException::invalid(gettext('Choose the date each year as MM-DD'));
                }
        }

        $rangeStart = $this->requireDate($input['rangeStart'] ?? null, gettext('Choose the first date'));
        $rangeEnd = $this->requireDate($input['rangeEnd'] ?? null, gettext('Choose the last date'));
        if ($rangeEnd < $rangeStart) {
            throw VolunteerException::invalid(gettext('The last date is before the first date'));
        }

        return [
            'type' => $type,
            'dow' => $dow,
            'dom' => $dom,
            'doy' => $doy,
            'rangeStart' => $rangeStart,
            'rangeEnd' => $rangeEnd,
        ];
    }

    /**
     * @param array<string, mixed> $plan
     */
    private function createOneEvent(array $plan, int $ministryId): Event
    {
        return $this->events->createEvent($plan['type'], [
            'title' => $plan['title'],
            'desc' => $plan['description'],
            'start' => $plan['date'] . ' ' . $plan['startTime'],
            'end' => $plan['date'] . ' ' . $plan['endTime'],
            'ministryId' => $ministryId,
            'calendars' => $plan['calendars'],
            'linkedGroupId' => $plan['linkedGroupId'],
        ]);
    }

    /**
     * @param array<string, mixed> $plan
     *
     * @return Event[]
     *
     * @throws VolunteerException when the recurrence finds no date in the range
     */
    private function createSeries(array $plan, int $ministryId): array
    {
        $series = $plan['series'];
        $calendarIds = [];
        foreach ($plan['calendars'] as $calendar) {
            $calendarIds[] = (int) $calendar->getId();
        }

        $result = $this->events->createRecurringEvents([
            'title' => $plan['title'],
            'typeId' => (int) $plan['type']->getId(),
            'desc' => $plan['description'],
            'startTime' => $plan['startTime'],
            'endTime' => $plan['endTime'],
            'recurType' => $series['type'],
            'recurDOW' => $series['dow'],
            'recurDOM' => $series['dom'],
            'recurDOY' => $series['doy'],
            'rangeStart' => $series['rangeStart'],
            'rangeEnd' => $series['rangeEnd'],
            'pinnedCalendars' => $calendarIds,
            'linkedGroupId' => $plan['linkedGroupId'],
            'ministryId' => $ministryId,
        ]);

        if ($result['events'] === []) {
            throw VolunteerException::invalid(gettext('No date between the first and last date matches the recurrence'));
        }

        return iterator_to_array(
            EventQuery::create()
                ->filterById(array_column($result['events'], 'id'), Criteria::IN)
                ->orderByStart()
                ->find(),
            false
        );
    }

    // ── Staff them: new events on the schedules that follow them (D33) ─────

    /**
     * "Staff them" after a recurring event was created (D33). Every active, non-one-off
     * schedule of this ministry that follows these events takes them — `class` mode on their
     * class, `ministry` mode with exactly their title, `event_type` mode with their type and
     * exactly their title. Each one's window is widened to cover them (D30: the first date
     * moved back to the first event, a set last date moved on to the last one, an open end
     * left open), it is generated through the last event, never past the scheduling horizon
     * (D31), and its saved defaults are assigned on the occurrences that run created (D32).
     * Its own staffing needs and offsets apply. When none follows them, nothing is made: the
     * page offers Add schedule instead.
     *
     * @param int[] $eventIds the events just created, one series
     *
     * @return array<int, array{schedule: VolunteerSchedule, widened: bool, created: int, existing: int, from: string, through: string, assigned: int, skipped: int, unqualified: int}>
     *
     * @throws VolunteerException 400 for no ids or events that are not one series, 404 for an
     *                            unknown event, 403 for a caller who may not manage the
     *                            ministry or an event it does not own
     * @throws \RuntimeException  400: a run over the occurrence cap
     */
    public function staffOnFollowingSchedules(VolunteerMinistry $ministry, array $eventIds, User $actor): array
    {
        $ministryId = (int) $ministry->getId();
        $this->assertMayManageEvents($ministryId, $actor, gettext('Only a coordinator of this ministry may staff its events'));

        $events = $this->ownedEvents($ministry, $eventIds, gettext('Choose the events to staff'));
        $series = $this->seriesOf($events);
        $schedules = $this->schedules->findSchedulesFollowing($ministryId, $series['title'], $series['eventTypeId'], $series['groupIds']);
        if ($schedules === []) {
            return [];
        }

        $first = (string) $events[0]->getStart('Y-m-d');
        $last = (string) $events[count($events) - 1]->getStart('Y-m-d');

        $con = Propel::getWriteConnection(EventTableMap::DATABASE_NAME);
        $con->beginTransaction();

        try {
            $runs = [];
            foreach ($schedules as $schedule) {
                $runs[] = ['schedule' => $schedule, 'widened' => $this->widenToCover($schedule, $first, $last, $actor)]
                    + $this->schedules->generateOccurrences($schedule, DateTimeUtils::createDateTime($last));
            }
            $con->commit();
        } catch (\Throwable $e) {
            $con->rollBack();

            throw $e;
        }

        $results = [];
        foreach ($runs as $run) {
            $createdIds = $run['createdIds'];
            unset($run['createdIds']);
            $results[] = $run + $this->assignments()->assignScheduleDefaults($run['schedule'], $createdIds, $actor);
        }

        $this->logger->info('Volunteer ministry events staffed on the schedules that follow them', [
            'ministryId' => $ministryId,
            'eventIds' => $this->eventIds($events),
            'scheduleIds' => array_map(static fn (VolunteerSchedule $schedule): int => (int) $schedule->getId(), $schedules),
            'actorPersonId' => $actor->getId(),
        ]);

        return $results;
    }

    /**
     * What the events have in common, which is what a schedule follows: one title (ignoring
     * case, as generation compares it), one type and one set of Linked Groups.
     *
     * @param Event[] $events
     *
     * @return array{title: string, eventTypeId: int, groupIds: int[]}
     *
     * @throws VolunteerException 400 when they differ
     */
    private function seriesOf(array $events): array
    {
        $groups = $this->linkedGroupsByEvent($this->eventIds($events));
        $key = static function (Event $event) use ($groups): string {
            $groupIds = array_column($groups[(int) $event->getId()] ?? [], 'id');
            sort($groupIds);

            return json_encode([mb_strtolower(trim((string) $event->getTitle())), (int) $event->getType(), $groupIds]);
        };

        $first = $events[0];
        foreach ($events as $event) {
            if ($key($event) !== $key($first)) {
                throw VolunteerException::invalid(gettext('These events are not one series: they differ in title, type or class'));
            }
        }

        return [
            'title' => trim((string) $first->getTitle()),
            'eventTypeId' => (int) $first->getType(),
            'groupIds' => array_column($groups[(int) $first->getId()] ?? [], 'id'),
        ];
    }

    /**
     * D30: a schedule that takes new events covers their dates — its first date moved back
     * to the first event, a set last date moved on to the last one, an open end left open.
     * Answers whether anything moved.
     */
    private function widenToCover(VolunteerSchedule $schedule, string $first, string $last, User $actor): bool
    {
        $widen = [];
        if ($first < (string) $schedule->getWindowStart('Y-m-d')) {
            $widen['windowStart'] = $first;
        }
        $windowEnd = $schedule->getWindowEnd('Y-m-d');
        if ($windowEnd !== null && $last > $windowEnd) {
            $widen['windowEnd'] = $last;
        }
        if ($widen !== []) {
            $this->schedules->updateSchedule($schedule, $widen, $actor);
        }

        return $widen !== [];
    }

    /**
     * The events named, every one owned by this ministry, soonest first.
     *
     * @param int[] $eventIds
     *
     * @return Event[]
     *
     * @throws VolunteerException 400 for no ids, 404 for an unknown event, 403 for one the
     *                            ministry does not own
     */
    private function ownedEvents(VolunteerMinistry $ministry, array $eventIds, string $noneMessage): array
    {
        $eventIds = array_values(array_unique($eventIds));
        if ($eventIds === []) {
            throw VolunteerException::invalid($noneMessage);
        }

        $events = iterator_to_array(EventQuery::create()->filterById($eventIds, Criteria::IN)->orderByStart()->find(), false);
        if (count($events) !== count($eventIds)) {
            throw VolunteerException::notFound(gettext('One of the events does not exist'));
        }
        foreach ($events as $event) {
            if ((int) $event->getMinistryId() !== (int) $ministry->getId()) {
                throw VolunteerException::forbidden(sprintf(
                    gettext('%1$s is not an event of %2$s'),
                    $event->getTitle(),
                    $ministry->getName()
                ));
            }
        }

        return $events;
    }
    // ── The Calendar tab (D24) ─────────────────────────────────────────────

    /**
     * The events a ministry owns between two dates, each with its type, pins, Linked Group,
     * headcount total and — per team of this ministry — the staffing of the occurrences
     * anchored to it, from the one gap implementation (§2.11.3).
     *
     * @return array{events: array<int, array<string, mixed>>, capped: bool}
     */
    public function listMinistryEvents(VolunteerMinistry $ministry, string $from, string $to, bool $newestFirst): array
    {
        $ministryId = (int) $ministry->getId();

        $rows = iterator_to_array(
            EventQuery::create()
                ->filterByMinistryId($ministryId)
                ->filterByStart($from . ' 00:00:00', Criteria::GREATER_EQUAL)
                ->filterByStart($to . ' 23:59:59', Criteria::LESS_EQUAL)
                ->orderByStart($newestFirst ? Criteria::DESC : Criteria::ASC)
                ->orderById()
                ->limit(self::MAX_EVENT_LIST + 1)
                ->find(),
            false
        );

        $capped = count($rows) > self::MAX_EVENT_LIST;
        if ($capped) {
            $rows = array_slice($rows, 0, self::MAX_EVENT_LIST);
        }
        if ($rows === []) {
            return ['events' => [], 'capped' => false];
        }

        $eventIds = array_map(static fn (Event $event): int => (int) $event->getId(), $rows);

        $typeNames = [];
        foreach (EventTypeQuery::create()->filterById(array_unique(array_map(static fn (Event $event): int => (int) $event->getType(), $rows)), Criteria::IN)->find() as $type) {
            $typeNames[(int) $type->getId()] = (string) $type->getName();
        }

        $calendars = $this->calendarsByEvent($eventIds);
        $groups = $this->linkedGroupsByEvent($eventIds);
        $staffing = $this->staffingByEvent($ministryId, $eventIds);
        $headcounts = $this->headcountTotals($eventIds);

        $events = [];
        $otherStaffing = $this->otherMinistriesStaffing($ministryId, $eventIds);

        foreach ($rows as $event) {
            $eventId = (int) $event->getId();
            $events[] = [
                'id' => $eventId,
                'title' => (string) $event->getTitle(),
                'start' => $event->getStart('Y-m-d H:i:s'),
                'end' => $event->getEnd('Y-m-d H:i:s'),
                'eventTypeId' => (int) $event->getType(),
                'eventTypeName' => $typeNames[(int) $event->getType()] ?? null,
                'inactive' => (int) $event->getInActive() !== 0,
                'calendars' => $calendars[$eventId] ?? [],
                'linkedGroups' => $groups[$eventId] ?? [],
                'staffing' => $staffing[$eventId] ?? [],
                'otherStaffing' => $otherStaffing[$eventId] ?? [],
                'headcount' => $headcounts[$eventId] ?? ['recorded' => false, 'total' => 0],
            ];
        }

        return ['events' => $events, 'capped' => $capped];
    }

    /**
     * @param int[] $eventIds
     *
     * @return array<int, array<int, array{id: int, name: string}>>
     */
    private function calendarsByEvent(array $eventIds): array
    {
        $pins = CalendarEventQuery::create()
            ->filterByEventId($eventIds, Criteria::IN)
            ->select(['CalendarId', 'EventId'])
            ->find()
            ->toArray();
        if ($pins === []) {
            return [];
        }

        $pinsByCalendar = [];
        foreach ($pins as $pin) {
            $pinsByCalendar[(int) $pin['CalendarId']][] = (int) $pin['EventId'];
        }

        $byEvent = [];
        foreach (CalendarQuery::create()->filterById(array_keys($pinsByCalendar), Criteria::IN)->orderByName()->find() as $calendar) {
            $calendarId = (int) $calendar->getId();
            foreach ($pinsByCalendar[$calendarId] as $eventId) {
                $byEvent[$eventId][] = ['id' => $calendarId, 'name' => (string) $calendar->getName()];
            }
        }

        return $byEvent;
    }

    /**
     * @param int[] $eventIds
     *
     * @return array<int, array<int, array{id: int, name: string}>>
     */
    private function linkedGroupsByEvent(array $eventIds): array
    {
        $links = EventAudienceQuery::create()
            ->filterByEventId($eventIds, Criteria::IN)
            ->select(['EventId', 'GroupId'])
            ->find()
            ->toArray();
        if ($links === []) {
            return [];
        }

        $names = [];
        foreach (GroupQuery::create()->filterById(array_unique(array_column($links, 'GroupId')), Criteria::IN)->find() as $group) {
            $names[(int) $group->getId()] = (string) $group->getName();
        }

        $byEvent = [];
        foreach ($links as $link) {
            $groupId = (int) $link['GroupId'];
            if (isset($names[$groupId])) {
                $byEvent[(int) $link['EventId']][] = ['id' => $groupId, 'name' => $names[$groupId]];
            }
        }

        return $byEvent;
    }

    /**
     * Per event, per team of this ministry: what its scheduled occurrences anchored to the
     * event need and have. `status` is the one word the tab's badge shows: `unplanned` (no
     * staffing needs set, §2.10), `gap`, `pending` (every slot taken, not every answer in)
     * or `filled`. `openCount` is the room left and `capacity` the summed maximums, so the
     * badge can say "Covered · 1 more welcome" or "Full" (D34).
     *
     * @param int[] $eventIds
     *
     * @return array<int, array<int, array{teamId: int, teamName: string, occurrenceIds: int[], needed: int, filled: int, pending: int, gap: int, openCount: int, capacity: int, requirementCount: int, status: string}>>
     */
    private function staffingByEvent(int $ministryId, array $eventIds): array
    {
        $teamOfSchedule = [];
        foreach (VolunteerScheduleQuery::create()->filterByMinistryId($ministryId)->select(['Id', 'TeamId'])->find() as $row) {
            $teamOfSchedule[(int) $row['Id']] = (int) $row['TeamId'];
        }
        if ($teamOfSchedule === []) {
            return [];
        }

        $occurrences = VolunteerOccurrenceQuery::create()
            ->filterByScheduleId(array_keys($teamOfSchedule), Criteria::IN)
            ->filterByEventId($eventIds, Criteria::IN)
            ->filterByStatus(VolunteerOccurrence::STATUS_SCHEDULED)
            ->orderById()
            ->select(['Id', 'ScheduleId', 'EventId'])
            ->find()
            ->toArray();
        if ($occurrences === []) {
            return [];
        }

        $teamNames = [];
        foreach (VolunteerTeamQuery::create()->filterByMinistryId($ministryId)->find() as $team) {
            $teamNames[(int) $team->getId()] = (string) $team->getName();
        }

        $gaps = $this->assignments()->getGaps(array_map('intval', array_column($occurrences, 'Id')));

        $byEvent = [];
        foreach ($occurrences as $row) {
            $occurrenceId = (int) $row['Id'];
            $eventId = (int) $row['EventId'];
            $teamId = $teamOfSchedule[(int) $row['ScheduleId']];
            $summary = $gaps[$occurrenceId] ?? [];

            $entry = $byEvent[$eventId][$teamId] ?? [
                'teamId' => $teamId,
                'teamName' => $teamNames[$teamId] ?? '',
                'occurrenceIds' => [],
                'needed' => 0,
                'filled' => 0,
                'pending' => 0,
                'gap' => 0,
                'openCount' => 0,
                'capacity' => 0,
                'requirementCount' => 0,
            ];
            $entry['occurrenceIds'][] = $occurrenceId;
            $entry['needed'] += (int) ($summary['requiredCount'] ?? 0);
            $entry['filled'] += (int) ($summary['liveCount'] ?? 0);
            $entry['pending'] += (int) ($summary['pendingCount'] ?? 0);
            $entry['gap'] += (int) ($summary['gapCount'] ?? 0);
            $entry['openCount'] += (int) ($summary['openCount'] ?? 0);
            $entry['capacity'] += (int) ($summary['capacity'] ?? 0);
            $entry['requirementCount'] += (int) ($summary['requirementCount'] ?? 0);
            $byEvent[$eventId][$teamId] = $entry;
        }

        $result = [];
        foreach ($byEvent as $eventId => $teams) {
            usort($teams, static fn (array $a, array $b): int => strcasecmp($a['teamName'], $b['teamName']));
            foreach ($teams as $index => $team) {
                $teams[$index]['status'] = match (true) {
                    $team['requirementCount'] === 0 => 'unplanned',
                    $team['gap'] > 0 => 'gap',
                    $team['pending'] > 0 => 'pending',
                    default => 'filled',
                };
            }
            $result[$eventId] = $teams;
        }

        return $result;
    }

    /**
     * Per event, the OTHER ministries with an occurrence anchored to it and how many of their
     * volunteers are assigned there (pending or accepted) — what deleting the event would pull
     * from under them.
     *
     * @param int[] $eventIds
     *
     * @return array<int, array<int, array{ministryId: int, ministryName: string, assigned: int}>>
     */
    public function otherMinistriesStaffing(int $ministryId, array $eventIds): array
    {
        if ($eventIds === []) {
            return [];
        }

        $occurrences = VolunteerOccurrenceQuery::create()
            ->filterByEventId($eventIds, Criteria::IN)
            ->select(['Id', 'EventId', 'ScheduleId'])
            ->find()
            ->toArray();
        if ($occurrences === []) {
            return [];
        }

        $ministryOfSchedule = [];
        foreach (
            VolunteerScheduleQuery::create()
                ->filterById(array_unique(array_map('intval', array_column($occurrences, 'ScheduleId'))), Criteria::IN)
                ->filterByMinistryId($ministryId, Criteria::NOT_EQUAL)
                ->select(['Id', 'MinistryId'])
                ->find() as $row
        ) {
            $ministryOfSchedule[(int) $row['Id']] = (int) $row['MinistryId'];
        }
        $occurrences = array_values(array_filter(
            $occurrences,
            static fn (array $row): bool => isset($ministryOfSchedule[(int) $row['ScheduleId']])
        ));
        if ($occurrences === []) {
            return [];
        }

        $assignedPerOccurrence = array_count_values(array_map('intval', VolunteerAssignmentQuery::create()
            ->filterByOccurrenceId(array_map('intval', array_column($occurrences, 'Id')), Criteria::IN)
            ->filterByStatus(VolunteerAssignmentService::LIVE_STATUSES, Criteria::IN)
            ->select(['OccurrenceId'])
            ->find()
            ->toArray()));

        $names = [];
        foreach (VolunteerMinistryQuery::create()->filterById(array_unique(array_values($ministryOfSchedule)), Criteria::IN)->find() as $ministry) {
            $names[(int) $ministry->getId()] = (string) $ministry->getName();
        }

        $byEvent = [];
        foreach ($occurrences as $row) {
            $eventId = (int) $row['EventId'];
            $otherId = $ministryOfSchedule[(int) $row['ScheduleId']];
            $byEvent[$eventId][$otherId] ??= ['ministryId' => $otherId, 'ministryName' => $names[$otherId] ?? '', 'assigned' => 0];
            $byEvent[$eventId][$otherId]['assigned'] += $assignedPerOccurrence[(int) $row['Id']] ?? 0;
        }

        $result = [];
        foreach ($byEvent as $eventId => $ministries) {
            usort($ministries, static fn (array $a, array $b): int => strcasecmp($a['ministryName'], $b['ministryName']));
            $result[$eventId] = $ministries;
        }

        return $result;
    }

    // ── A class's events when its team changes (D28) ───────────────────────

    /**
     * The events this ministry owns whose Linked Group is the class. An event an administrator
     * created carries another ministry's id or none, so it is never among them.
     *
     * @return Event[]
     */
    public function findOwnedClassEvents(int $ministryId, int $groupId): array
    {
        return iterator_to_array(
            EventQuery::create()
                ->filterByMinistryId($ministryId)
                ->useEventAudienceQuery()
                    ->filterByGroupId($groupId)
                ->endUse()
                ->orderByStart()
                ->find(),
            false
        );
    }

    /**
     * What the team dialog asks about: how many of those events there are, how many are still
     * to come, and which other ministries staff them.
     *
     * @return array{total: int, upcoming: int, otherStaffing: array<int, array{ministryId: int, ministryName: string, assigned: int, eventCount: int}>}
     */
    public function summarizeOwnedClassEvents(int $ministryId, int $groupId): array
    {
        $events = $this->findOwnedClassEvents($ministryId, $groupId);
        $today = DateTimeUtils::getTodayDate() . ' 00:00:00';

        $others = [];
        foreach ($this->otherMinistriesStaffing($ministryId, $this->eventIds($events)) as $ministries) {
            foreach ($ministries as $other) {
                $id = $other['ministryId'];
                $others[$id] ??= ['ministryId' => $id, 'ministryName' => $other['ministryName'], 'assigned' => 0, 'eventCount' => 0];
                $others[$id]['assigned'] += $other['assigned'];
                $others[$id]['eventCount']++;
            }
        }
        $others = array_values($others);
        usort($others,static fn (array $a, array $b): int => strcasecmp($a['ministryName'], $b['ministryName']));

        return [
            'total' => count($events),
            'upcoming' => count(array_filter($events, static fn (Event $event): bool => $event->getStart('Y-m-d H:i:s') >= $today)),
            'otherStaffing' => $others,
        ];
    }

    /**
     * @param string[] $allowed the choices this caller offers
     *
     * @throws VolunteerException 400
     */
    public static function readClassEventsChoice(mixed $raw, array $allowed): string
    {
        if ($raw === null || $raw === '') {
            return self::CLASS_EVENTS_KEEP;
        }
        if (!is_string($raw) || !in_array($raw, $allowed, true)) {
            throw VolunteerException::invalid(sprintf(
                gettext('classEvents must be one of: %s'),
                implode(', ', $allowed)
            ));
        }

        return $raw;
    }

    /**
     * D28: the team → class link and an event's Linked Group are different facts, so when a
     * team stops staffing a class its ministry's own events of that class change only as the
     * coordinator chose — kept, the class removed from them, moved to the team's new class,
     * or deleted through core. Runs in the caller's transaction.
     *
     * @return int how many events were changed or deleted
     *
     * @throws VolunteerException 400 for `move` with no new class, 403 for an event the actor
     *                            may not write, 409 when a delete is refused
     */
    public function applyClassEventsChoice(int $ministryId, int $groupId, ?int $newGroupId, string $choice, User $actor): int
    {
        if ($choice === self::CLASS_EVENTS_KEEP) {
            return 0;
        }
        if ($choice === self::CLASS_EVENTS_MOVE && $newGroupId === null) {
            throw VolunteerException::invalid(gettext('The events can only be moved to a class the team is linked to'));
        }

        $events = $this->findOwnedClassEvents($ministryId, $groupId);
        if ($events === []) {
            return 0;
        }
        $this->assertMayWriteEvents($events, $actor);

        if ($choice === self::CLASS_EVENTS_DELETE) {
            $this->deleteEvents($ministryId, $events, $actor);
        } else {
            foreach ($events as $event) {
                $eventId = (int) $event->getId();
                EventAudienceQuery::create()->filterByEventId($eventId)->filterByGroupId($groupId)->delete();
                if ($choice === self::CLASS_EVENTS_MOVE
                    && !EventAudienceQuery::create()->filterByEventId($eventId)->filterByGroupId($newGroupId)->exists()) {
                    $audience = new EventAudience();
                    $audience->setEventId($eventId);
                    $audience->setGroupId($newGroupId);
                    $audience->save();
                }
            }
        }

        $this->logger->info('Volunteer class events ' . $choice, [
            'ministryId' => $ministryId,
            'groupId' => $groupId,
            'newGroupId' => $newGroupId,
            'eventIds' => $this->eventIds($events),
            'actorPersonId' => $actor->getId(),
        ]);

        return count($events);
    }

    /**
     * The Calendar tab's Delete events (D28): only events this ministry owns, through core's
     * delete path, all or none.
     *
     * @param int[] $eventIds
     *
     * @throws VolunteerException 400 for no ids, 404 for an unknown event, 403 for an event
     *                            this ministry does not own or the actor may not write, 409
     *                            when another ministry has volunteers there or core refuses
     */
    public function deleteOwnedEvents(VolunteerMinistry $ministry, array $eventIds, User $actor): int
    {
        $ministryId = (int) $ministry->getId();
        $events = $this->ownedEvents($ministry, $eventIds, gettext('Choose the events to delete'));
        $this->assertMayWriteEvents($events, $actor);

        $con = Propel::getWriteConnection(EventTableMap::DATABASE_NAME);
        $con->beginTransaction();

        try {
            $this->deleteEvents($ministryId, $events, $actor);
            $con->commit();
        } catch (\Throwable $e) {
            $con->rollBack();

            throw $e;
        }

        $this->logger->info('Volunteer ministry events deleted', [
            'ministryId' => $ministryId,
            'eventIds' => $this->eventIds($events),
            'actorPersonId' => $actor->getId(),
        ]);

        return count($events);
    }

    /**
     * Refused while another ministry has volunteers on any of the events — they would be left
     * serving at an event that no longer exists — unless the actor manages the calendar
     * outright (Add Events). Core's own refusals come back as a 409 carrying its reason; the
     * caller's transaction then leaves every event in place.
     *
     * @param Event[] $events
     *
     * @throws VolunteerException 409
     */
    private function deleteEvents(int $ministryId, array $events, User $actor): void
    {
        if (!$actor->canManageEvents()) {
            $staffed = [];
            foreach ($this->otherMinistriesStaffing($ministryId, $this->eventIds($events)) as $ministries) {
                foreach ($ministries as $other) {
                    if ($other['assigned'] > 0) {
                        $staffed[$other['ministryId']] = $other['ministryName'];
                    }
                }
            }
            if ($staffed !== []) {
                natcasesort($staffed);

                throw VolunteerException::conflict(sprintf(
                    gettext('Volunteers of %s are assigned to these events, so they cannot be deleted here. Ask that ministry to remove its staffing, or ask someone who manages the calendar.'),
                    implode(', ', $staffed)
                ))->withExtra(['ministries' => array_map(
                    static fn (int $id, string $name): array => ['ministryId' => $id, 'ministryName' => $name],
                    array_keys($staffed),
                    array_values($staffed)
                )]);
            }
        }

        foreach ($events as $event) {
            try {
                $this->events->deleteEvent($event);
            } catch (EventDeleteRefusedException $e) {
                throw VolunteerException::conflict(sprintf(
                    '%1$s (%2$s): %3$s',
                    $event->getTitle(),
                    $event->getStart('Y-m-d'),
                    $e->getMessage()
                ))->withExtra(['eventId' => (int) $event->getId()]);
            }
        }
    }

    /**
     * Every event is asked again whether this actor may write it (§4.6), and a self-service
     * login never writes church events whatever scope it holds (§4.7).
     *
     * @param Event[] $events
     *
     * @throws VolunteerException 403
     */
    private function assertMayWriteEvents(array $events, User $actor): void
    {
        foreach ($events as $event) {
            $ministryId = $event->getMinistryId() === null ? null : (int) $event->getMinistryId();
            if ($actor->isEditSelfExclusive() || !$this->authz->canWriteEvent($actor, $ministryId)) {
                throw VolunteerException::forbidden(sprintf(gettext('Not authorized to change the event %s'), $event->getTitle()));
            }
        }
    }

    /**
     * @param Event[] $events
     *
     * @return int[]
     */
    private function eventIds(array $events): array
    {
        return array_map(static fn (Event $event): int => (int) $event->getId(), $events);
    }

    // ── Headcount (D26) ────────────────────────────────────────────────────

    /**
     * The attendance-count totals of several events, the way the core event view adds them
     * up: the sum of every category. `recorded` is false until some category is above zero.
     *
     * @param int[] $eventIds
     *
     * @return array<int, array{recorded: bool, total: int}>
     */
    public function headcountTotals(array $eventIds): array
    {
        if ($eventIds === []) {
            return [];
        }

        $totals = [];
        foreach (EventCountsQuery::create()->filterByEvtcntEventid($eventIds, Criteria::IN)->find() as $row) {
            $eventId = (int) $row->getEvtcntEventid();
            $count = (int) $row->getEvtcntCountcount();
            $totals[$eventId] ??= ['recorded' => false, 'total' => 0];
            $totals[$eventId]['total'] += $count;
            $totals[$eventId]['recorded'] = $totals[$eventId]['recorded'] || $count > 0;
        }

        return $totals;
    }

    /**
     * The anchored event's headcount card on an occurrence page (D26): its type's count
     * categories with this event's values, the total, where the counts are entered (the core
     * event editor, for a viewer who may edit the event and can reach the admin shell) and,
     * for an event with a roster — a Linked Group — how many people are checked in.
     *
     * @return array{recorded: bool, total: int, counts: array<int, array{name: string, count: int}>, editPath: ?string, roster: ?array{checkedIn: int, members: int}}
     */
    public function headcount(Event $event, User $viewer): array
    {
        $eventId = (int) $event->getId();

        $stored = [];
        foreach (EventCountsQuery::create()->filterByEvtcntEventid($eventId)->orderByEvtcntCountid()->find() as $row) {
            $stored[(int) $row->getEvtcntCountid()] = [
                'name' => (string) $row->getEvtcntCountname(),
                'count' => (int) $row->getEvtcntCountcount(),
            ];
        }

        $counts = [];
        foreach (EventCountNameQuery::create()->filterByTypeId((int) $event->getType())->orderById()->find() as $category) {
            $categoryId = (int) $category->getId();
            $counts[] = ['name' => (string) $category->getName(), 'count' => $stored[$categoryId]['count'] ?? 0];
            unset($stored[$categoryId]);
        }
        // Counts entered under a category the event's type no longer has still happened.
        foreach ($stored as $row) {
            $counts[] = $row;
        }

        $total = array_sum(array_column($counts, 'count'));
        $ministryId = $event->getMinistryId() === null ? null : (int) $event->getMinistryId();
        $mayEdit = !$viewer->isEditSelfExclusive() && $this->authz->canWriteEvent($viewer, $ministryId);

        return [
            'recorded' => $total > 0,
            'total' => $total,
            'counts' => $counts,
            'editPath' => $mayEdit ? '/event/editor/' . $eventId : null,
            'roster' => $this->rosterCheckIns($eventId),
        ];
    }

    /**
     * @return array{checkedIn: int, members: int}|null null when the event has no Linked Group
     */
    private function rosterCheckIns(int $eventId): ?array
    {
        $groupIds = array_map(
            'intval',
            EventAudienceQuery::create()->filterByEventId($eventId)->select(['GroupId'])->find()->toArray()
        );
        if ($groupIds === []) {
            return null;
        }

        $members = array_unique(array_map(
            'intval',
            Person2group2roleP2g2rQuery::create()->filterByGroupId($groupIds, Criteria::IN)->select(['PersonId'])->find()->toArray()
        ));

        return [
            'checkedIn' => EventAttendQuery::create()->filterByEventId($eventId)->count(),
            'members' => count($members),
        ];
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function assignments(): VolunteerAssignmentService
    {
        return $this->assignments ??= new VolunteerAssignmentService();
    }

    private function positiveInt(mixed $raw): ?int
    {
        if (is_int($raw)) {
            return $raw > 0 ? $raw : null;
        }
        if (is_string($raw) && preg_match('/^\d+$/', trim($raw)) === 1) {
            $value = (int) trim($raw);

            return $value > 0 ? $value : null;
        }

        return null;
    }

    /**
     * `HH:MM` or `HH:MM:SS`, as `HH:MM:SS`.
     *
     * @throws VolunteerException
     */
    private function requireTime(mixed $raw): string
    {
        $value = is_string($raw) ? trim($raw) : '';
        $parsed = \DateTimeImmutable::createFromFormat('!H:i:s', $value)
            ?: \DateTimeImmutable::createFromFormat('!H:i', $value);
        if ($value === '' || $parsed === false || !in_array($value, [$parsed->format('H:i'), $parsed->format('H:i:s')], true)) {
            throw VolunteerException::invalid(gettext('Give the start and end times as HH:MM'));
        }

        return $parsed->format('H:i:s');
    }

    /**
     * @throws VolunteerException
     */
    private function requireDate(mixed $raw, string $message): string
    {
        $value = is_string($raw) ? $raw : '';
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($parsed === false || $parsed->format('Y-m-d') !== $value) {
            throw VolunteerException::invalid($message);
        }

        return $value;
    }
}
