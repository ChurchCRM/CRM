<?php

namespace ChurchCRM\Volunteer\Service;

use ChurchCRM\model\ChurchCRM\CalendarEventQuery;
use ChurchCRM\model\ChurchCRM\CalendarQuery;
use ChurchCRM\model\ChurchCRM\Event;
use ChurchCRM\model\ChurchCRM\EventAttendQuery;
use ChurchCRM\model\ChurchCRM\EventAudienceQuery;
use ChurchCRM\model\ChurchCRM\EventCountNameQuery;
use ChurchCRM\model\ChurchCRM\EventCountsQuery;
use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\model\ChurchCRM\EventTypeQuery;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\Map\EventTableMap;
use ChurchCRM\model\ChurchCRM\Person2group2roleP2g2rQuery;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\VolunteerMinistry;
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
 * that column (§2.16). What V2 adds is staffing the new events in the SAME transaction, through
 * `VolunteerScheduleService`, so a refused staffing plan leaves no event behind.
 *
 * Headcounts (`eventcounts_evtcnt`) and check-ins (`event_attend`) are core's and are only read.
 */
class VolunteerEventService
{
    /** Hard cap on one Calendar tab listing, the occurrence list's number (design M9). */
    public const MAX_EVENT_LIST = VolunteerScheduleService::MAX_OCCURRENCE_LIST;

    private const WEEKDAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

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
     * Create one event or a series for this ministry through core, pinned to calendars the
     * pin rule allows (D25), and — when `staff` is given — the schedule that staffs them with
     * its occurrences and default assignments, all in one transaction.
     *
     * A series with a Linked Group is staffed in `class` mode, one without in `ministry` mode
     * narrowed to the title; a single event through Staff this event (`event` mode, D22).
     *
     * @param array<string, mixed> $input the request body (design §3.3.2)
     *
     * @return array{events: Event[], schedule: ?VolunteerSchedule, occurrenceIds: int[], assigned: int, skipped: int}
     *
     * @throws VolunteerException        403 for a caller who is not a coordinator of the ministry or
     *                                   a refused calendar, 400 for a malformed request
     * @throws \RuntimeException         400: a staffing plan the schedule service refuses
     * @throws \InvalidArgumentException 400: a series the repeat engine refuses (the cap)
     */
    public function createMinistryEvents(VolunteerMinistry $ministry, array $input, User $actor): array
    {
        $ministryId = (int) $ministry->getId();

        // A self-service login never writes church events, whatever scope it holds (§4.7).
        if ($actor->isEditSelfExclusive() || !$this->authz->canManageMinistry($actor, $ministryId)) {
            throw VolunteerException::forbidden(gettext('Only a coordinator of this ministry may create its events'));
        }

        $plan = $this->readEventPlan($ministryId, $input, $actor);
        $staff = $this->readStaffing($input);

        $con = Propel::getWriteConnection(EventTableMap::DATABASE_NAME);
        $con->beginTransaction();

        try {
            $events = $plan['series'] === null
                ? [$this->createOneEvent($plan, $ministryId)]
                : $this->createSeries($plan, $ministryId);

            $staffed = $staff === null
                ? ['schedule' => null, 'occurrenceIds' => [], 'assigned' => 0, 'skipped' => 0]
                : $this->staffNewEvents($ministry, $plan, $staff, $events, $actor);

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
            'eventIds' => array_map(static fn (Event $event): int => (int) $event->getId(), $events),
            'scheduleId' => $staffed['schedule']?->getId(),
            'actorPersonId' => $actor->getId(),
        ]);

        return ['events' => $events] + $staffed;
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
     * The `staff` block's shape. What it names — the team, the positions, the people — is
     * judged by the schedule and assignment services that write it.
     *
     * @param array<string, mixed> $input
     *
     * @return array{fields: array<string, mixed>, defaults: array<int, mixed>}|null
     *
     * @throws VolunteerException
     */
    private function readStaffing(array $input): ?array
    {
        if (!array_key_exists('staff', $input) || $input['staff'] === null) {
            return null;
        }

        $staff = $input['staff'];
        if (!is_array($staff)) {
            throw VolunteerException::invalid(gettext('The staffing section is malformed'));
        }

        $teamId = $this->positiveInt($staff['teamId'] ?? null);
        if ($teamId === null) {
            throw VolunteerException::invalid(gettext('Choose the team that staffs these events'));
        }

        $fields = ['teamId' => $teamId];
        foreach (['requirements', 'startOffsetMinutes', 'endOffsetMinutes'] as $key) {
            if (array_key_exists($key, $staff)) {
                $fields[$key] = $staff[$key];
            }
        }
        if (array_key_exists('requirements', $fields) && !is_array($fields['requirements'])) {
            throw VolunteerException::invalid(gettext('The staffing needs must be a list'));
        }

        $defaults = $staff['defaults'] ?? [];
        if (!is_array($defaults)) {
            throw VolunteerException::invalid(gettext('The default volunteers must be a list'));
        }

        return ['fields' => $fields, 'defaults' => array_values($defaults)];
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

    /**
     * @param array<string, mixed>                                         $plan
     * @param array{fields: array<string, mixed>, defaults: array<int, mixed>} $staff
     * @param Event[]                                                      $events
     *
     * @return array{schedule: VolunteerSchedule, occurrenceIds: int[], assigned: int, skipped: int}
     */
    private function staffNewEvents(VolunteerMinistry $ministry, array $plan, array $staff, array $events, User $actor): array
    {
        $fields = $staff['fields'];

        if ($plan['series'] === null) {
            $occurrence = $this->schedules->staffEvent($ministry, $fields + ['eventId' => (int) $events[0]->getId()], $actor);
            $schedule = $this->schedules->requireSchedule($occurrence);
            $occurrenceIds = [(int) $occurrence->getId()];
        } else {
            $fields += [
                'name' => mb_substr($plan['title'], 0, 100),
                'windowStart' => $plan['series']['rangeStart'],
                'windowEnd' => $plan['series']['rangeEnd'],
                'active' => true,
            ];
            if ($plan['linkedGroupId'] > 0) {
                $fields['linkMode'] = VolunteerSchedule::LINK_MODE_CLASS;
                $fields['groupId'] = $plan['linkedGroupId'];
            } else {
                $fields['linkMode'] = VolunteerSchedule::LINK_MODE_MINISTRY;
                $fields['titleFilter'] = $plan['title'];
            }

            $result = $this->schedules->createScheduleAndGenerate(
                $ministry,
                $fields,
                $actor,
                DateTimeUtils::createDateTime($plan['series']['rangeEnd'])
            );
            $schedule = $result['schedule'];
            $occurrenceIds = $result['createdIds'];
        }

        $staffed = $staff['defaults'] === []
            ? ['assigned' => 0, 'skipped' => 0]
            : $this->assignments()->assignDefaults($schedule, $occurrenceIds, $staff['defaults'], $actor);

        return ['schedule' => $schedule, 'occurrenceIds' => $occurrenceIds] + $staffed;
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
     * or `filled`.
     *
     * @param int[] $eventIds
     *
     * @return array<int, array<int, array{teamId: int, teamName: string, occurrenceIds: int[], needed: int, filled: int, pending: int, gap: int, requirementCount: int, status: string}>>
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
                'requirementCount' => 0,
            ];
            $entry['occurrenceIds'][] = $occurrenceId;
            $entry['needed'] += (int) ($summary['requiredCount'] ?? 0);
            $entry['filled'] += (int) ($summary['liveCount'] ?? 0);
            $entry['pending'] += (int) ($summary['pendingCount'] ?? 0);
            $entry['gap'] += (int) ($summary['gapCount'] ?? 0);
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
