<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\model\ChurchCRM\EventTypeQuery;
use ChurchCRM\model\ChurchCRM\Map\VolunteerOccurrenceTableMap;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\VolunteerAssignmentQuery;
use ChurchCRM\model\ChurchCRM\VolunteerMinistry;
use ChurchCRM\model\ChurchCRM\VolunteerOccurrence;
use ChurchCRM\model\ChurchCRM\VolunteerOccurrenceQuery;
use ChurchCRM\model\ChurchCRM\VolunteerPosition;
use ChurchCRM\model\ChurchCRM\VolunteerPositionQuery;
use ChurchCRM\model\ChurchCRM\VolunteerRequirement;
use ChurchCRM\model\ChurchCRM\VolunteerRequirementQuery;
use ChurchCRM\model\ChurchCRM\VolunteerSchedule;
use ChurchCRM\model\ChurchCRM\VolunteerScheduleQuery;
use ChurchCRM\model\ChurchCRM\VolunteerTeamQuery;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\LoggerUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Propel;
use Psr\Log\LoggerInterface;

/**
 * Volunteer Management v2 (#9708, epic #9701): schedules, occurrence generation and
 * staffing requirements.
 *
 * The one rule this class exists to implement is D4: **when a schedule is linked to a
 * ChurchCRM event type, the event rows are the source of truth.** Generation in that mode
 * only ever *selects* `events_event` rows and attaches an occurrence to each one; it never
 * creates, edits or deletes an event, and the occurrence stores no times of its own — they
 * are resolved lazily from the event by resolveOccurrenceWindow(). Only a *standalone*
 * schedule has dates of its own, and those come from the shared
 * {@see RecurrenceDateGenerator} that #9735 extracted, never from a private copy of the
 * same switch statement.
 *
 * There is no event series identifier in the schema (design F7): "repeat" bulk-inserts N
 * independent rows and discards the id list. The de-facto series key the core itself
 * trusts is **event type + a date window** (F8, `events.php` skipExisting and
 * quickCreateEvent's find-or-create), and §2.8 adds an optional title filter for the case
 * where one type carries several distinct services (UC4). That triple is the binding a
 * linked schedule resolves at generation time.
 *
 * Idempotency is a *database* property, not a code path: `vocc_schedule_event_uidx`
 * dedupes linked rows and `vocc_schedule_start_uidx` dedupes standalone ones, and
 * generation uses findOneOrCreate() inside one transaction — the same idiom
 * Event::checkInPerson() uses. That is how `skipExisting` is re-expressed here (§2.9).
 *
 * Time handling follows the storage convention: every DATETIME is naive wall-clock in
 * `sTimeZone` and every "now"/"today" comes from DateTimeUtils, never from `new \DateTime()`
 * or `date()` (F24, timezone-handling.md).
 *
 * Deliberately NOT here: authorization. The route's entity middleware has already decided
 * whether the caller may touch the schedule or occurrence (§4.5); a service that re-asked
 * would either duplicate or contradict it.
 */
class VolunteerScheduleService
{
    /**
     * The most occurrences one generation run may materialise.
     *
     * Same number and same purpose as EventService::MAX_REPEAT_OCCURRENCES — "at most a
     * daily series for a year" — so a coordinator who types a silly `through` gets the
     * same answer from the volunteer module as from the calendar.
     */
    public const MAX_GENERATED_OCCURRENCES = EventService::MAX_REPEAT_OCCURRENCES;

    /** Hard cap on a coordinator occurrence listing (design M9). */
    public const MAX_OCCURRENCE_LIST = 500;

    private RecurrenceDateGenerator $recurrenceDateGenerator;

    private LoggerInterface $logger;

    public function __construct(?RecurrenceDateGenerator $recurrenceDateGenerator = null)
    {
        $this->recurrenceDateGenerator = $recurrenceDateGenerator ?? new RecurrenceDateGenerator();
        $this->logger = LoggerUtils::getAppLogger();
    }

    // ── Schedule CRUD ──────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $fields
     *
     * @throws \RuntimeException when a §2.8 invariant is violated
     */
    public function createSchedule(VolunteerMinistry $ministry, array $fields, User $actor): VolunteerSchedule
    {
        $schedule = new VolunteerSchedule();
        $schedule->setMinistryId((int) $ministry->getId());

        $this->applyScheduleFields($schedule, $fields, true);
        $schedule->save();

        $this->logger->info('Volunteer schedule created', [
            'scheduleId' => $schedule->getId(),
            'ministryId' => $ministry->getId(),
            'linkMode' => $schedule->getLinkMode(),
            'actorPersonId' => $actor->getId(),
        ]);

        return $schedule;
    }

    /**
     * @param array<string, mixed> $fields only the keys present are applied
     *
     * @throws \RuntimeException when a §2.8 invariant is violated
     */
    public function updateSchedule(VolunteerSchedule $schedule, array $fields, User $actor): VolunteerSchedule
    {
        $this->applyScheduleFields($schedule, $fields, false);
        $schedule->save();

        $this->logger->info('Volunteer schedule updated', [
            'scheduleId' => $schedule->getId(),
            'actorPersonId' => $actor->getId(),
        ]);

        return $schedule;
    }

    /**
     * Delete a schedule and, by FK cascade, its occurrences and template requirements.
     *
     * Refuses while any occurrence still carries an assignment: those rows are service
     * history, and the cascade would take them silently (§3.3.2).
     *
     * @throws \RuntimeException when an occurrence has assignments
     */
    public function deleteSchedule(VolunteerSchedule $schedule, User $actor): void
    {
        $assignmentCount = $this->countAssignments((int) $schedule->getId());
        if ($assignmentCount > 0) {
            throw new \RuntimeException(sprintf(
                gettext('This schedule cannot be deleted: %d volunteer assignments still reference its occurrences.'),
                $assignmentCount
            ));
        }

        $this->logger->info('Volunteer schedule deleted', [
            'scheduleId' => $schedule->getId(),
            'actorPersonId' => $actor->getId(),
        ]);

        $schedule->delete();
    }

    /** Assignments hanging off any occurrence of this schedule. */
    public function countAssignments(int $scheduleId): int
    {
        $occurrenceIds = $this->getOccurrenceIds($scheduleId);
        if ($occurrenceIds === []) {
            return 0;
        }

        return VolunteerAssignmentQuery::create()
            ->filterByOccurrenceId($occurrenceIds, Criteria::IN)
            ->count();
    }

    /**
     * @return int[]
     */
    private function getOccurrenceIds(int $scheduleId): array
    {
        $ids = VolunteerOccurrenceQuery::create()
            ->filterByScheduleId($scheduleId)
            ->select(['Id'])
            ->find()
            ->toArray();

        return array_map('intval', $ids);
    }

    /**
     * Validate and apply the §2.8 field set.
     *
     * MySQL cannot express "this column is NOT NULL only when that one has this value", so
     * every invariant in §2.8 is enforced here and nowhere else. On an update the current
     * row is the baseline, so a caller may flip `linkMode` and have the now-meaningless
     * columns cleared for them rather than being told to null each one by hand.
     *
     * @param array<string, mixed> $fields
     *
     * @throws \RuntimeException
     */
    private function applyScheduleFields(VolunteerSchedule $schedule, array $fields, bool $isCreate): void
    {
        $has = static fn (string $key): bool => array_key_exists($key, $fields);
        $value = static fn (string $key) => $fields[$key] ?? null;

        if ($has('name') || $isCreate) {
            $name = trim((string) $value('name'));
            if ($name === '') {
                throw new \RuntimeException(gettext('A schedule name is required'));
            }
            $schedule->setName($name);
        }

        $linkMode = $has('linkMode') ? (string) $value('linkMode') : (string) $schedule->getLinkMode();
        if (!in_array($linkMode, VolunteerSchedule::allLinkModes(), true)) {
            throw new \RuntimeException(gettext('Unknown schedule link mode'));
        }
        $schedule->setLinkMode($linkMode);

        if ($has('teamId') || $isCreate) {
            $teamId = $value('teamId') === null || $value('teamId') === '' ? null : (int) $value('teamId');
            if ($teamId !== null) {
                $team = VolunteerTeamQuery::create()->findPk($teamId);
                if ($team === null || (int) $team->getMinistryId() !== (int) $schedule->getMinistryId()) {
                    throw new \RuntimeException(gettext('The team does not belong to this ministry'));
                }
            }
            $schedule->setTeamId($teamId);
        }

        if ($has('windowStart') || $isCreate) {
            $windowStart = $this->requireDate((string) $value('windowStart'), gettext('window start'));
            $schedule->setWindowStart($windowStart);
        }

        if ($has('windowEnd') || $isCreate) {
            $raw = $value('windowEnd');
            $schedule->setWindowEnd(
                $raw === null || $raw === '' ? null : $this->requireDate((string) $raw, gettext('window end'))
            );
        }

        $windowEnd = $schedule->getWindowEnd();
        if ($windowEnd !== null && $windowEnd < $schedule->getWindowStart()) {
            throw new \RuntimeException(gettext('The schedule window ends before it starts'));
        }

        if ($has('generateAheadDays')) {
            $ahead = (int) $value('generateAheadDays');
            if ($ahead < 1 || $ahead > 730) {
                throw new \RuntimeException(gettext('Generate-ahead days must be between 1 and 730'));
            }
            $schedule->setGenerateAheadDays($ahead);
        }

        if ($has('active')) {
            $schedule->setActive((bool) filter_var($value('active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($has('titleFilter') || $isCreate) {
            $raw = $value('titleFilter');
            $filter = $raw === null ? '' : trim((string) $raw);
            $schedule->setTitleFilter($filter === '' ? null : $filter);
        }

        if ($linkMode === VolunteerSchedule::LINK_MODE_EVENT_TYPE) {
            $this->applyLinkedFields($schedule, $fields, $isCreate);

            return;
        }

        $this->applyStandaloneFields($schedule, $fields, $isCreate);
    }

    /**
     * @param array<string, mixed> $fields
     *
     * @throws \RuntimeException
     */
    private function applyLinkedFields(VolunteerSchedule $schedule, array $fields, bool $isCreate): void
    {
        // "A linked schedule never carries its own recurrence" (D4). Rejecting the fields
        // rather than quietly dropping them is the difference between a coordinator who
        // learns the rule and one who thinks they configured something that does nothing.
        foreach (['recurDow', 'recurDom', 'startTime', 'endTime'] as $key) {
            if (array_key_exists($key, $fields) && $fields[$key] !== null && $fields[$key] !== '') {
                throw new \RuntimeException(gettext('A schedule linked to an event type carries no recurrence or times of its own; the event is the source of truth'));
            }
        }
        if (
            array_key_exists('recurType', $fields)
            && $fields['recurType'] !== null
            && $fields['recurType'] !== ''
            && $fields['recurType'] !== VolunteerSchedule::RECUR_NONE
        ) {
            throw new \RuntimeException(gettext('A schedule linked to an event type carries no recurrence or times of its own; the event is the source of truth'));
        }

        $eventTypeId = array_key_exists('eventTypeId', $fields)
            ? ($fields['eventTypeId'] === null || $fields['eventTypeId'] === '' ? null : (int) $fields['eventTypeId'])
            : ($isCreate ? null : $schedule->getEventTypeId());

        if ($eventTypeId === null) {
            throw new \RuntimeException(gettext('A schedule linked to an event type must name the event type'));
        }
        if (EventTypeQuery::create()->findPk($eventTypeId) === null) {
            throw new \RuntimeException(gettext('The event type does not exist'));
        }

        $schedule->setEventTypeId($eventTypeId);
        $schedule->setRecurType(VolunteerSchedule::RECUR_NONE);
        $schedule->setRecurDow(null);
        $schedule->setRecurDom(null);
        $schedule->setStartTime(null);
        $schedule->setEndTime(null);
    }

    /**
     * @param array<string, mixed> $fields
     *
     * @throws \RuntimeException
     */
    private function applyStandaloneFields(VolunteerSchedule $schedule, array $fields, bool $isCreate): void
    {
        if (array_key_exists('eventTypeId', $fields) && $fields['eventTypeId'] !== null && $fields['eventTypeId'] !== '') {
            throw new \RuntimeException(gettext('A standalone schedule names no event type; use the event_type link mode instead'));
        }
        $schedule->setEventTypeId(null);

        $recurType = array_key_exists('recurType', $fields)
            ? (string) ($fields['recurType'] ?? '')
            : ($isCreate ? '' : (string) $schedule->getRecurType());

        if (!in_array($recurType, VolunteerSchedule::allRecurTypes(), true)) {
            throw new \RuntimeException(gettext('Unknown recurrence type'));
        }
        if ($recurType === VolunteerSchedule::RECUR_NONE) {
            throw new \RuntimeException(gettext('A standalone schedule needs a recurrence pattern'));
        }
        $schedule->setRecurType($recurType);

        if (array_key_exists('recurDow', $fields) || $isCreate) {
            $raw = $fields['recurDow'] ?? null;
            $dow = $raw === null || $raw === '' ? null : (string) $raw;
            if ($dow !== null && !in_array($dow, VolunteerSchedule::allRecurDows(), true)) {
                throw new \RuntimeException(gettext('Unknown day of week'));
            }
            $schedule->setRecurDow($dow);
        }

        if (array_key_exists('recurDom', $fields) || $isCreate) {
            $raw = $fields['recurDom'] ?? null;
            $dom = $raw === null || $raw === '' ? null : (int) $raw;
            if ($dom !== null && ($dom < 1 || $dom > 31)) {
                throw new \RuntimeException(gettext('Day of month must be between 1 and 31'));
            }
            $schedule->setRecurDom($dom);
        }

        if ($recurType === VolunteerSchedule::RECUR_WEEKLY && $schedule->getRecurDow() === null) {
            throw new \RuntimeException(gettext('A weekly schedule needs a day of week'));
        }
        if ($recurType === VolunteerSchedule::RECUR_MONTHLY && $schedule->getRecurDom() === null) {
            throw new \RuntimeException(gettext('A monthly schedule needs a day of month'));
        }

        if (array_key_exists('startTime', $fields) || $isCreate) {
            $raw = $fields['startTime'] ?? null;
            $schedule->setStartTime($raw === null || $raw === '' ? null : $this->requireTime((string) $raw, gettext('start time')));
        }
        if (array_key_exists('endTime', $fields) || $isCreate) {
            $raw = $fields['endTime'] ?? null;
            $schedule->setEndTime($raw === null || $raw === '' ? null : $this->requireTime((string) $raw, gettext('end time')));
        }

        // §2.8: a standalone schedule must have a start time, because §2.9 requires every
        // standalone occurrence to carry one and there is deliberately no CHECK enforcing it.
        if ($schedule->getStartTime() === null) {
            throw new \RuntimeException(gettext('A standalone schedule needs a start time'));
        }
    }

    // ── Occurrence generation ──────────────────────────────────────────────

    /**
     * Materialise occurrences up to `$through`, idempotently.
     *
     * Linked mode resolves (event type + optional title filter + window) to concrete
     * `events_event` rows and attaches one occurrence to each. Standalone mode asks the
     * shared RecurrenceDateGenerator for the dates and stamps the schedule's own times on
     * them. Neither mode ever writes to `events_event`, and neither touches an occurrence
     * that already exists — the unique keys make a repeat run a no-op.
     *
     * @return array{created: int, existing: int, through: string} `through` is the date
     *                                                            actually generated to,
     *                                                            after the schedule's own
     *                                                            window has clamped it
     *
     * @throws \RuntimeException when the run would exceed MAX_GENERATED_OCCURRENCES
     */
    public function generateOccurrences(VolunteerSchedule $schedule, ?\DateTimeInterface $through = null): array
    {
        $range = $this->resolveGenerationRange($schedule, $through);
        $reportedThrough = $range['end']->format('Y-m-d');

        if ($range['start'] > $range['end']) {
            // A window entirely in the past, or one that closed before it opened: nothing
            // to materialise, and explicitly not an error.
            return ['created' => 0, 'existing' => 0, 'through' => $reportedThrough];
        }

        $result = $schedule->getLinkMode() === VolunteerSchedule::LINK_MODE_EVENT_TYPE
            ? $this->generateLinkedOccurrences($schedule, $range['start'], $range['end'])
            : $this->generateStandaloneOccurrences($schedule, $range['start'], $range['end']);

        $this->logger->info('Volunteer occurrences generated', [
            'scheduleId' => $schedule->getId(),
            'linkMode' => $schedule->getLinkMode(),
            'through' => $reportedThrough,
            'created' => $result['created'],
            'existing' => $result['existing'],
        ]);

        return $result + ['through' => $reportedThrough];
    }

    /**
     * The inclusive date range one run materialises.
     *
     * Start is the later of the schedule's window start and today: historical occurrences
     * are immutable (§2.9) and back-filling services that already happened would create
     * staffing rows nobody can act on. End is the earliest of the caller's `through`, the
     * schedule's window end, and — when the caller named nothing — today plus the
     * schedule's own GenerateAheadDays.
     *
     * @return array{start: \DateTime, end: \DateTime}
     */
    private function resolveGenerationRange(VolunteerSchedule $schedule, ?\DateTimeInterface $through): array
    {
        $today = DateTimeUtils::getStartOfToday();

        $windowStart = $schedule->getWindowStart();
        $start = $windowStart instanceof \DateTimeInterface
            ? DateTimeUtils::createDateTime($windowStart->format('Y-m-d'))
            : clone $today;
        $start->setTime(0, 0, 0);
        if ($start < $today) {
            $start = clone $today;
        }

        if ($through !== null) {
            $end = DateTimeUtils::createDateTime($through->format('Y-m-d'));
        } else {
            $end = clone $today;
            $end->modify('+' . max(1, (int) $schedule->getGenerateAheadDays()) . ' days');
        }
        $end->setTime(0, 0, 0);

        $windowEnd = $schedule->getWindowEnd();
        if ($windowEnd instanceof \DateTimeInterface) {
            $limit = DateTimeUtils::createDateTime($windowEnd->format('Y-m-d'));
            $limit->setTime(0, 0, 0);
            if ($end > $limit) {
                $end = $limit;
            }
        }

        return ['start' => $start, 'end' => $end];
    }

    /**
     * Linked mode: the events already exist, so "generation" is a SELECT plus one
     * occurrence row per event found. V2 never creates an events_event row (D4).
     *
     * @return array{created: int, existing: int}
     */
    private function generateLinkedOccurrences(VolunteerSchedule $schedule, \DateTime $start, \DateTime $end): array
    {
        $query = EventQuery::create()
            ->filterByType((int) $schedule->getEventTypeId())
            ->filterByStart($start->format('Y-m-d') . ' 00:00:00', Criteria::GREATER_EQUAL)
            ->filterByStart($end->format('Y-m-d') . ' 23:59:59', Criteria::LESS_EQUAL)
            ->filterByInActive(0);

        $titleFilter = $schedule->getTitleFilter();
        if ($titleFilter !== null && $titleFilter !== '') {
            // The optional narrowing of §2.8: one event type, several distinct services.
            $query->filterByTitle('%' . $titleFilter . '%', Criteria::LIKE);
        }

        $events = $query->orderByStart()->find();
        $this->assertWithinCap(count($events));

        $candidates = [];
        foreach ($events as $event) {
            $candidates[] = [
                'eventId' => (int) $event->getId(),
                'occurrenceDate' => $event->getStart('Y-m-d'),
                // Deliberately no denormalised copy of the event's times: they are read
                // lazily by resolveOccurrenceWindow(), so there is nothing to keep in sync
                // and no EVENT_UPDATED hook to add (§2.9, E-15).
                'startDateTime' => null,
                'endDateTime' => null,
            ];
        }

        return $this->persistOccurrences($schedule, $candidates);
    }

    /**
     * Standalone mode: the dates come from the one shared recurrence implementation
     * (#9735's RecurrenceDateGenerator), and the schedule's own times are stamped on them.
     *
     * @return array{created: int, existing: int}
     */
    private function generateStandaloneOccurrences(VolunteerSchedule $schedule, \DateTime $start, \DateTime $end): array
    {
        $dates = $this->recurrenceDateGenerator->generate(
            (string) $schedule->getRecurType(),
            $schedule->getRecurDow(),
            $schedule->getRecurDom() === null ? null : (int) $schedule->getRecurDom(),
            // The schedule table has no day-of-year column (§2.8), so a yearly standalone
            // schedule recurs on its window start's month and day — the only anniversary
            // the row actually carries.
            $this->resolveYearlyMonthDay($schedule),
            $start,
            $end
        );

        $this->assertWithinCap(count($dates));

        $startTime = $this->formatTime($schedule->getStartTime());
        $endTime = $this->formatTime($schedule->getEndTime());

        $candidates = [];
        foreach ($dates as $date) {
            $candidates[] = [
                'eventId' => null,
                'occurrenceDate' => $date->format('Y-m-d'),
                // §2.9: a standalone row ALWAYS carries its own start. There is
                // deliberately no CHECK enforcing that (MariaDB refuses one on a column an
                // FK action can change), so this is the only thing that guarantees it —
                // and it is also what vocc_schedule_start_uidx deduplicates on.
                'startDateTime' => $this->occurrenceStart($date, $startTime),
                'endDateTime' => $this->occurrenceEnd($date, $startTime, $endTime),
            ];
        }

        return $this->persistOccurrences($schedule, $candidates);
    }

    /**
     * @throws \RuntimeException when a run would materialise more rows than the cap allows
     */
    private function assertWithinCap(int $count): void
    {
        if ($count > self::MAX_GENERATED_OCCURRENCES) {
            throw new \RuntimeException(sprintf(
                gettext('Too many occurrences (%d) — narrow the date range. Maximum allowed: %d'),
                $count,
                self::MAX_GENERATED_OCCURRENCES
            ));
        }
    }

    /**
     * One transaction, findOneOrCreate per candidate: the two unique keys of §2.9 do the
     * deduplication, so a second run over the same window inserts nothing and reports every
     * candidate as `existing`. This is `skipExisting` (F8) re-expressed as a database
     * guarantee instead of a pre-flight SELECT that a concurrent run could race.
     *
     * @param array<int, array{eventId: ?int, occurrenceDate: string, startDateTime: ?string, endDateTime: ?string}> $candidates
     *
     * @return array{created: int, existing: int}
     */
    private function persistOccurrences(VolunteerSchedule $schedule, array $candidates): array
    {
        $created = 0;
        $existing = 0;
        $now = DateTimeUtils::getNowDateTime();
        $scheduleId = (int) $schedule->getId();

        $con = Propel::getWriteConnection(VolunteerOccurrenceTableMap::DATABASE_NAME);
        $con->beginTransaction();

        try {
            foreach ($candidates as $candidate) {
                $query = VolunteerOccurrenceQuery::create()->filterByScheduleId($scheduleId);
                if ($candidate['eventId'] !== null) {
                    $query->filterByEventId($candidate['eventId']);
                } else {
                    $query->filterByStartDateTime($candidate['startDateTime']);
                }

                $occurrence = $query->findOneOrCreate($con);

                if (!$occurrence->isNew()) {
                    // Historical occurrences are immutable: an existing row is never
                    // re-pointed at another event, moved, or deleted (§2.9).
                    $existing++;
                    continue;
                }

                $occurrence->setScheduleId($scheduleId);
                $occurrence->setEventId($candidate['eventId']);
                $occurrence->setOccurrenceDate($candidate['occurrenceDate']);
                $occurrence->setStartDateTime($candidate['startDateTime']);
                $occurrence->setEndDateTime($candidate['endDateTime']);
                $occurrence->setStatus(VolunteerOccurrence::STATUS_SCHEDULED);
                $occurrence->setGeneratedDate($now);
                $occurrence->save($con);
                $created++;
            }

            $con->commit();
        } catch (\Throwable $e) {
            $con->rollBack();

            throw $e;
        }

        return ['created' => $created, 'existing' => $existing];
    }

    /**
     * Cancel one occurrence without touching its schedule (§2.9). Cancelling is not
     * deleting: the row survives so a later generation run does not resurrect it.
     */
    public function setOccurrenceStatus(VolunteerOccurrence $occurrence, string $status, User $actor): VolunteerOccurrence
    {
        if (!in_array($status, VolunteerOccurrence::allStatuses(), true)) {
            throw new \RuntimeException(gettext('Unknown occurrence status'));
        }

        $occurrence->setStatus($status);
        $occurrence->save();

        $this->logger->info('Volunteer occurrence status changed', [
            'occurrenceId' => $occurrence->getId(),
            'status' => $status,
            'actorPersonId' => $actor->getId(),
        ]);

        return $occurrence;
    }

    public function cancelOccurrence(VolunteerOccurrence $occurrence, User $actor): VolunteerOccurrence
    {
        return $this->setOccurrenceStatus($occurrence, VolunteerOccurrence::STATUS_CANCELLED, $actor);
    }

    // ── Effective time and requirements ────────────────────────────────────

    /**
     * The occurrence's real start and end — read from the event row when the occurrence is
     * linked, from its own columns when it is standalone. **The single source of truth**:
     * nothing else in V2 may decide what time an occurrence happens at.
     *
     * Both may be null for a formerly-linked occurrence whose event was deleted: the FK is
     * ON DELETE SET NULL, so such a row survives on `vocc_OccurrenceDate` alone (§2.9).
     *
     * @return array{start: ?\DateTime, end: ?\DateTime}
     */
    public function resolveOccurrenceWindow(VolunteerOccurrence $occurrence): array
    {
        $eventId = $occurrence->getEventId();
        if ($eventId !== null) {
            $event = EventQuery::create()->findPk((int) $eventId);
            if ($event !== null) {
                return ['start' => $event->getStart(), 'end' => $event->getEnd()];
            }
        }

        return ['start' => $occurrence->getStartDateTime(), 'end' => $occurrence->getEndDateTime()];
    }

    /**
     * The occurrence's own requirement rows, unioned with its schedule's templates for
     * every position the occurrence does not override (§2.10). **Nothing else may
     * re-implement this merge** — #9709's gap calculation consumes exactly this list.
     *
     * @return VolunteerRequirement[] keyed by position id, ordered by position order then name
     */
    public function getEffectiveRequirements(int $occurrenceId): array
    {
        $occurrence = VolunteerOccurrenceQuery::create()->findPk($occurrenceId);
        if ($occurrence === null) {
            return [];
        }

        $effective = [];

        foreach (VolunteerRequirementQuery::create()->filterByOccurrenceId($occurrenceId)->find() as $override) {
            $effective[(int) $override->getPositionId()] = $override;
        }

        $templates = VolunteerRequirementQuery::create()
            ->filterByScheduleId((int) $occurrence->getScheduleId())
            ->find();
        foreach ($templates as $template) {
            $positionId = (int) $template->getPositionId();
            if (!isset($effective[$positionId])) {
                $effective[$positionId] = $template;
            }
        }

        if ($effective === []) {
            return [];
        }

        $order = $this->positionOrder(array_keys($effective));
        uksort($effective, static fn (int $a, int $b): int => ($order[$a] ?? [PHP_INT_MAX, '']) <=> ($order[$b] ?? [PHP_INT_MAX, '']));

        return $effective;
    }

    /**
     * @param int[] $positionIds
     *
     * @return array<int, array{0: int, 1: string}>
     */
    private function positionOrder(array $positionIds): array
    {
        $order = [];
        $positions = VolunteerPositionQuery::create()
            ->filterById($positionIds, Criteria::IN)
            ->find();
        foreach ($positions as $position) {
            $order[(int) $position->getId()] = [(int) $position->getOrder(), (string) $position->getName()];
        }

        return $order;
    }

    /**
     * Upsert one staffing requirement, on the schedule (template) or on a single
     * occurrence (override) — never both, which is what `vreq_one_parent_chk` says in SQL
     * and what this method says in PHP for the MySQL 5.7 installs that parse and ignore it.
     *
     * The unique keys `vreq_schedule_position_uidx` / `vreq_occurrence_position_uidx` make
     * this an upsert rather than an insert: re-posting the same position updates the counts
     * on the existing row instead of failing with a duplicate.
     *
     * @throws \RuntimeException
     */
    public function upsertRequirement(
        ?VolunteerSchedule $schedule,
        ?VolunteerOccurrence $occurrence,
        VolunteerPosition $position,
        int $minCount,
        ?int $maxCount = null,
        ?string $notes = null
    ): VolunteerRequirement {
        if (($schedule === null) === ($occurrence === null)) {
            throw new \RuntimeException(gettext('A staffing requirement belongs to exactly one of a schedule or an occurrence'));
        }

        if ($minCount < 0) {
            throw new \RuntimeException(gettext('The minimum count cannot be negative'));
        }
        if ($maxCount !== null && $maxCount < $minCount) {
            throw new \RuntimeException(gettext('The maximum count cannot be below the minimum count'));
        }

        $ministryId = $schedule !== null
            ? (int) $schedule->getMinistryId()
            : (int) $this->requireSchedule($occurrence)->getMinistryId();

        if ((int) $position->getMinistryId() !== $ministryId) {
            throw new \RuntimeException(gettext('The position belongs to a different ministry'));
        }

        $query = VolunteerRequirementQuery::create()->filterByPositionId((int) $position->getId());
        if ($schedule !== null) {
            $query->filterByScheduleId((int) $schedule->getId())->filterByOccurrenceId(null);
        } else {
            $query->filterByOccurrenceId((int) $occurrence->getId())->filterByScheduleId(null);
        }

        $requirement = $query->findOneOrCreate();
        $wasNew = $requirement->isNew();

        $requirement->setScheduleId($schedule === null ? null : (int) $schedule->getId());
        $requirement->setOccurrenceId($occurrence === null ? null : (int) $occurrence->getId());
        $requirement->setPositionId((int) $position->getId());
        $requirement->setMinCount($minCount);
        $requirement->setMaxCount($maxCount);
        $requirement->setNotes($notes === null || trim($notes) === '' ? null : trim($notes));
        $requirement->save();

        $this->logger->info($wasNew ? 'Volunteer requirement created' : 'Volunteer requirement updated', [
            'requirementId' => $requirement->getId(),
            'scheduleId' => $requirement->getScheduleId(),
            'occurrenceId' => $requirement->getOccurrenceId(),
            'positionId' => $requirement->getPositionId(),
        ]);

        return $requirement;
    }

    /**
     * The schedule an occurrence belongs to. Its FK is required and cascades, so a missing
     * row means the database is inconsistent rather than the caller being wrong.
     *
     * @throws \RuntimeException
     */
    public function requireSchedule(VolunteerOccurrence $occurrence): VolunteerSchedule
    {
        $schedule = VolunteerScheduleQuery::create()->findPk((int) $occurrence->getScheduleId());
        if ($schedule === null) {
            throw new \RuntimeException(gettext('The occurrence has no schedule'));
        }

        return $schedule;
    }

    // ── Small shared helpers ───────────────────────────────────────────────

    /**
     * `YYYY-MM-DD`, round-tripped so "2026-02-31" and "tomorrow" are both rejected.
     *
     * @throws \RuntimeException
     */
    private function requireDate(string $raw, string $label): string
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw, DateTimeUtils::getConfiguredTimezone());
        if ($parsed === false || $parsed->format('Y-m-d') !== $raw) {
            throw new \RuntimeException(sprintf(gettext('The %s must be a date in YYYY-MM-DD form'), $label));
        }

        return $raw;
    }

    /**
     * `HH:MM` or `HH:MM:SS`, normalised to `HH:MM:SS`.
     *
     * @throws \RuntimeException
     */
    private function requireTime(string $raw, string $label): string
    {
        foreach (['!H:i:s', '!H:i'] as $format) {
            $parsed = \DateTimeImmutable::createFromFormat($format, $raw, DateTimeUtils::getConfiguredTimezone());
            if ($parsed !== false && $parsed->format(ltrim($format, '!')) === $raw) {
                return $parsed->format('H:i:s');
            }
        }

        throw new \RuntimeException(sprintf(gettext('The %s must be a time in HH:MM form'), $label));
    }

    /** A TIME column hydrates as a DateTime; a freshly-set one may still be a string. */
    private function formatTime(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i:s');
        }
        if (is_string($value) && $value !== '') {
            return substr($value, 0, 8);
        }

        return null;
    }

    /** Naive wall-clock in sTimeZone, exactly as every event DATETIME is stored (F24). */
    private function occurrenceStart(\DateTimeInterface $date, ?string $startTime): string
    {
        return $date->format('Y-m-d') . ' ' . ($startTime ?? '00:00:00');
    }

    /**
     * The end timestamp, rolled into the next day when the end time is at or before the
     * start — the same rule EventService::occurrenceEnd() applies, so a 22:00–01:00 shift
     * does not end before it began.
     */
    private function occurrenceEnd(\DateTimeInterface $date, ?string $startTime, ?string $endTime): ?string
    {
        if ($endTime === null) {
            return null;
        }

        $day = $date->format('Y-m-d');
        if ($startTime !== null && $endTime <= $startTime) {
            $day = DateTimeUtils::createDateTime($day)->modify('+1 day')->format('Y-m-d');
        }

        return $day . ' ' . $endTime;
    }

    /** MM-DD for a yearly standalone schedule — see the note at the call site. */
    private function resolveYearlyMonthDay(VolunteerSchedule $schedule): ?string
    {
        $windowStart = $schedule->getWindowStart();

        return $windowStart instanceof \DateTimeInterface ? $windowStart->format('m-d') : null;
    }
}
