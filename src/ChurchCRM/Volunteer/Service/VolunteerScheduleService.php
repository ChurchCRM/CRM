<?php

namespace ChurchCRM\Volunteer\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Event;
use ChurchCRM\model\ChurchCRM\EventAudienceQuery;
use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\model\ChurchCRM\EventTypeQuery;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\Map\VolunteerOccurrenceTableMap;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\VolunteerAssignmentQuery;
use ChurchCRM\model\ChurchCRM\VolunteerMinistry;
use ChurchCRM\model\ChurchCRM\VolunteerMinistryQuery;
use ChurchCRM\model\ChurchCRM\VolunteerOccurrence;
use ChurchCRM\model\ChurchCRM\VolunteerOccurrenceQuery;
use ChurchCRM\model\ChurchCRM\VolunteerPosition;
use ChurchCRM\model\ChurchCRM\VolunteerPositionQuery;
use ChurchCRM\model\ChurchCRM\VolunteerQualificationQuery;
use ChurchCRM\model\ChurchCRM\VolunteerRequirement;
use ChurchCRM\model\ChurchCRM\VolunteerRequirementQuery;
use ChurchCRM\model\ChurchCRM\VolunteerSchedule;
use ChurchCRM\model\ChurchCRM\VolunteerScheduleQuery;
use ChurchCRM\model\ChurchCRM\VolunteerTeamQuery;
use ChurchCRM\Service\EventService;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Volunteer\VolunteerException;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Propel;
use Psr\Log\LoggerInterface;

/**
 * Volunteer Management v2 (#9708, epic #9701): schedules, occurrence generation and
 * staffing requirements.
 *
 * **Every occurrence is anchored to a calendar event (D20).** V2 generates no dates of its
 * own: generation only ever *selects* `events_event` rows and attaches one occurrence to
 * each, and it never creates, edits or deletes an event. A schedule's link mode says how
 * its events are found (D22):
 *
 *   event_type  events of one type with exactly one title (church-wide services)
 *   class       events whose Linked Group (`event_audience`) is the schedule's group
 *   ministry    events the schedule's ministry owns (`event_ministry_id`) with exactly one title
 *   event       exactly one event — the hidden schedule behind Staff this event
 *
 * Anchoring is a read-only reference: any event may be anchored, not only the ministry's.
 * A schedule follows events that already exist (D31): it is created, or re-pointed, only
 * while at least one upcoming event matches, and generation stops at the church-wide
 * scheduling horizon, which a daily timer job keeps every schedule filled up to.
 *
 * The occurrence stores no times. resolveOccurrenceWindow() reads them lazily from the
 * event and adds the schedule's offsets (D21), so moving the event moves the shift.
 *
 * Idempotency is a *database* property: `vocc_schedule_event_uidx` dedupes, and generation
 * uses findOneOrCreate() inside one transaction — the same idiom Event::checkInPerson()
 * uses (§2.9).
 *
 * Time handling follows the storage convention: every DATETIME is naive wall-clock in
 * `sTimeZone` and every "now"/"today" comes from DateTimeUtils (F24, timezone-handling.md).
 *
 * The route's entity middleware decides whether the caller may touch an existing schedule
 * or occurrence (§4.5); creation re-checks the resolved team here because the team comes
 * from the payload.
 */
class VolunteerScheduleService
{
    /**
     * The most occurrences one generation run may materialise — the same number the
     * calendar's repeat-event cap uses, so a silly `through` gets the same answer here.
     */
    public const MAX_GENERATED_OCCURRENCES = EventService::MAX_REPEAT_OCCURRENCES;

    /** Hard cap on a coordinator occurrence listing (design M9). */
    public const MAX_OCCURRENCE_LIST = 500;

    /** Hard cap on the Staff this event search. */
    public const MAX_EVENT_SEARCH = 50;

    /** `group_grp.grp_Type` of a Sunday School class (list_lst 3, option 4). */
    public const SUNDAY_SCHOOL_GROUP_TYPE = 4;

    /** The removed standalone mode's fields. A payload naming one is refused, never ignored (D20). */
    private const RETIRED_FIELDS = ['recurType', 'recurDow', 'recurDom', 'startTime', 'endTime'];

    /** Admin → Ministry Settings bounds and default for the scheduling horizon (D31). */
    public const MIN_HORIZON_WEEKS = 1;
    public const MAX_HORIZON_WEEKS = 52;
    public const DEFAULT_HORIZON_WEEKS = 8;

    private LoggerInterface $logger;

    public function __construct()
    {
        $this->logger = LoggerUtils::getAppLogger();
    }

    /**
     * How many weeks ahead occurrences are made (D31): one church-wide setting. The config
     * API refuses a blank or a non-number (D32), but the row can still be written other ways
     * (System Settings, the database), so the value is read defensively: blank or not a
     * number is the default, anything else is held within the bounds.
     */
    public static function horizonWeeks(): int
    {
        $raw = trim((string) SystemConfig::getValue('iVolunteerSchedulingHorizonWeeks'));
        if (!is_numeric($raw)) {
            return self::DEFAULT_HORIZON_WEEKS;
        }

        return max(self::MIN_HORIZON_WEEKS, min(self::MAX_HORIZON_WEEKS, (int) $raw));
    }

    // ── Schedule CRUD ──────────────────────────────────────────────────────

    /**
     * Create a schedule in one of the editable link modes (`event_type`, `class`,
     * `ministry`). The `event` mode is reachable only through {@see self::staffEvent()}.
     *
     * @param array<string, mixed> $fields
     *
     * @throws VolunteerException 403 when the actor may neither administer the ministry nor
     *                            lead the team the payload names (§4.6, #9868)
     * @throws \RuntimeException  when a §2.8 invariant is violated
     */
    public function createSchedule(VolunteerMinistry $ministry, array $fields, User $actor): VolunteerSchedule
    {
        $schedule = $this->insertSchedule($ministry, $fields, $actor, false);

        $this->logger->info('Volunteer schedule created', [
            'scheduleId' => $schedule->getId(),
            'ministryId' => $ministry->getId(),
            'linkMode' => $schedule->getLinkMode(),
            'actorPersonId' => $actor->getId(),
        ]);

        return $schedule;
    }

    /**
     * Create a schedule and materialise its occurrences up to the scheduling horizon in one
     * transaction (D33: every new schedule generates on Save), so a run over the cap leaves
     * no schedule behind. A schedule saved inactive is paused, so it makes none — as the daily
     * top-up leaves it alone — and `generated` is null. A caller already inside a transaction
     * nests into it.
     *
     * @param array<string, mixed> $fields
     *
     * @return array{schedule: VolunteerSchedule, generated: array{created: int, existing: int, from: string, through: string, createdIds: int[]}|null}
     *
     * @throws \RuntimeException
     */
    public function createScheduleAndGenerate(VolunteerMinistry $ministry, array $fields, User $actor): array
    {
        $con = Propel::getWriteConnection(VolunteerOccurrenceTableMap::DATABASE_NAME);
        $con->beginTransaction();

        try {
            $schedule = $this->createSchedule($ministry, $fields, $actor);
            $generated = $schedule->getActive() ? $this->generateOccurrences($schedule) : null;
            $con->commit();
        } catch (\Throwable $e) {
            $con->rollBack();

            throw $e;
        }

        return ['schedule' => $schedule, 'generated' => $generated];
    }

    /**
     * @param array<string, mixed> $fields only the keys present are applied
     *
     * @throws \RuntimeException when a §2.8 invariant is violated
     */
    public function updateSchedule(VolunteerSchedule $schedule, array $fields, User $actor): VolunteerSchedule
    {
        $con = Propel::getWriteConnection(VolunteerOccurrenceTableMap::DATABASE_NAME);
        $con->beginTransaction();

        try {
            $this->applyScheduleFields($schedule, $fields, false, false);
            $schedule->save();

            if (array_key_exists('requirements', $fields)) {
                $this->replaceRequirements($schedule, null, $fields['requirements'], $actor);
            }

            $con->commit();
        } catch (\Throwable $e) {
            $con->rollBack();

            throw $e;
        }

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
     * The row, its staffing needs and the authorization re-check, in one transaction: a
     * `requirements` array naming an unknown position must not leave a half-made schedule
     * behind (§2.10).
     *
     * @param array<string, mixed> $fields
     *
     * @throws \RuntimeException
     */
    private function insertSchedule(VolunteerMinistry $ministry, array $fields, User $actor, bool $allowEventMode): VolunteerSchedule
    {
        $schedule = new VolunteerSchedule();
        $schedule->setMinistryId((int) $ministry->getId());
        $schedule->setOneOff($allowEventMode);

        $con = Propel::getWriteConnection(VolunteerOccurrenceTableMap::DATABASE_NAME);
        $con->beginTransaction();

        try {
            $this->applyScheduleFields($schedule, $fields, true, $allowEventMode);
            $this->assertMayCreateForTeam($schedule, $actor);
            $schedule->save();

            if (array_key_exists('requirements', $fields)) {
                $this->replaceRequirements($schedule, null, $fields['requirements'], $actor);
            }

            $con->commit();
        } catch (\Throwable $e) {
            $con->rollBack();

            throw $e;
        }

        return $schedule;
    }

    /**
     * Layer three of §4.5 for schedule creation (#9868): asked again from the RESOLVED
     * row, after `applyScheduleFields()` has proved the team belongs to this ministry.
     * The rule is §4.6's: administer the ministry, or lead the team.
     *
     * @throws VolunteerException 403
     */
    private function assertMayCreateForTeam(VolunteerSchedule $schedule, User $actor): void
    {
        $authz = new VolunteerAuthorizationService();

        if ($authz->canManageMinistry($actor, (int) $schedule->getMinistryId())) {
            return;
        }

        if ($schedule->getTeamId() !== null && $authz->canManageTeam($actor, (int) $schedule->getTeamId())) {
            return;
        }

        throw VolunteerException::forbidden(gettext('You may only create a schedule for a team you lead'));
    }

    /**
     * Validate and apply the §2.8 field set.
     *
     * MySQL cannot express "this column is NOT NULL only when that one has this value", so
     * every invariant in §2.8 is enforced here. The columns a link mode does not use are
     * cleared, so switching modes never leaves a stale binding behind.
     *
     * @param array<string, mixed> $fields
     *
     * @throws \RuntimeException
     */
    private function applyScheduleFields(VolunteerSchedule $schedule, array $fields, bool $isCreate, bool $allowEventMode): void
    {
        $has = static fn (string $key): bool => array_key_exists($key, $fields);
        $value = static fn (string $key) => $fields[$key] ?? null;

        $this->refuseRetiredFields($fields);

        $storedMode = $isCreate ? null : (string) $schedule->getLinkMode();
        $storedGroupId = $isCreate ? null : $schedule->getGroupId();
        $storedSource = $isCreate ? null : $this->eventSourceKey($schedule);
        $linkMode = $this->resolveLinkMode($schedule, $fields, $isCreate, $allowEventMode);
        $schedule->setLinkMode($linkMode);

        if ($has('name') || $isCreate) {
            $name = trim((string) $value('name'));
            if ($name === '') {
                throw new \RuntimeException(gettext('A schedule name is required'));
            }
            $schedule->setName($name);
        }

        // D18: a schedule ALWAYS belongs to a team of its own ministry.
        if ($has('teamId') || $isCreate) {
            $raw = $value('teamId');
            if ($raw === null || $raw === '') {
                throw new \RuntimeException(gettext('A schedule belongs to a team; choose one'));
            }

            $teamId = (int) $raw;
            $team = VolunteerTeamQuery::create()->findPk($teamId);
            if ($team === null || (int) $team->getMinistryId() !== (int) $schedule->getMinistryId()) {
                throw new \RuntimeException(gettext('The team does not belong to this ministry'));
            }
            $schedule->setTeamId($teamId);
        }

        if ($has('windowStart') || $isCreate) {
            $schedule->setWindowStart($this->requireDate((string) $value('windowStart'), gettext('window start')));
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

        if ($has('active')) {
            $schedule->setActive((bool) filter_var($value('active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($has('startOffsetMinutes')) {
            $schedule->setStartOffsetMinutes($this->requireOffset($value('startOffsetMinutes'), gettext('start offset')));
        }
        if ($has('endOffsetMinutes')) {
            $schedule->setEndOffsetMinutes($this->requireOffset($value('endOffsetMinutes'), gettext('end offset')));
        }

        if ($has('titleFilter') || $isCreate) {
            $raw = $value('titleFilter');
            $filter = $raw === null ? '' : trim((string) $raw);
            $schedule->setTitleFilter($filter === '' ? null : $filter);
        }

        $this->applyBinding($schedule, $fields, $isCreate);

        // D29. A class schedule the ministry already had keeps working when edited.
        if ($linkMode === VolunteerSchedule::LINK_MODE_CLASS
            && ($storedMode !== VolunteerSchedule::LINK_MODE_CLASS || (int) $storedGroupId !== (int) $schedule->getGroupId())) {
            VolunteerClassLinkService::assertMinistryTeaches((int) $schedule->getMinistryId());
        }

        $this->assertFollowsExistingEvents($schedule, $storedSource);
    }

    /**
     * D31: a schedule names the events it follows, and they exist. `event_type` and
     * `ministry` schedules name one title — a schedule saved without one before D31 must
     * be given one when it is next edited. Creating a schedule, or changing what it
     * follows, needs at least one upcoming active event to match; editing anything else
     * (name, needs, offsets, dates) is never refused for it, so a series that ran out can
     * still be tidied up.
     *
     * @param array{mode: string, eventTypeId: ?int, groupId: ?int, title: string}|null $storedSource
     *
     * @throws \RuntimeException
     */
    private function assertFollowsExistingEvents(VolunteerSchedule $schedule, ?array $storedSource): void
    {
        $mode = (string) $schedule->getLinkMode();
        if ($mode === VolunteerSchedule::LINK_MODE_EVENT) {
            return;
        }

        if (in_array($mode, [VolunteerSchedule::LINK_MODE_EVENT_TYPE, VolunteerSchedule::LINK_MODE_MINISTRY], true)
            && !self::namesTitle($schedule)) {
            throw new \RuntimeException(gettext('Choose the event this schedule follows'));
        }

        if ($storedSource === $this->eventSourceKey($schedule) || $this->hasUpcomingEvents($schedule)) {
            return;
        }

        $source = $this->describeEventSource($schedule);
        throw new \RuntimeException(match ($mode) {
            VolunteerSchedule::LINK_MODE_CLASS => sprintf(
                gettext('%s has no upcoming meetings on the calendar. A schedule follows events that already exist: add the class\'s meetings first, with New recurring event on the ministry\'s Calendar tab.'),
                (string) $source['groupName']
            ),
            VolunteerSchedule::LINK_MODE_MINISTRY => sprintf(
                gettext('This ministry has no upcoming events titled "%s". A schedule follows events that already exist: add them first on the ministry\'s Calendar tab.'),
                (string) $source['titleFilter']
            ),
            default => sprintf(
                gettext('No upcoming %1$s events titled "%2$s" are on the calendar. A schedule follows events that already exist.'),
                (string) $source['eventTypeName'],
                (string) $source['titleFilter']
            ),
        });
    }

    /**
     * What a schedule follows, compared across an edit. Titles compare as generation
     * matches them: case-insensitively.
     *
     * @return array{mode: string, eventTypeId: ?int, groupId: ?int, title: string}
     */
    private function eventSourceKey(VolunteerSchedule $schedule): array
    {
        return [
            'mode' => (string) $schedule->getLinkMode(),
            'eventTypeId' => $schedule->getEventTypeId() === null ? null : (int) $schedule->getEventTypeId(),
            'groupId' => $schedule->getGroupId() === null ? null : (int) $schedule->getGroupId(),
            'title' => mb_strtolower(trim((string) $schedule->getTitleFilter())),
        ];
    }

    private static function namesTitle(VolunteerSchedule $schedule): bool
    {
        return trim((string) $schedule->getTitleFilter()) !== '';
    }

    /**
     * @param array<string, mixed> $fields
     *
     * @throws \RuntimeException
     */
    private function refuseRetiredFields(array $fields): void
    {
        $sent = array_values(array_intersect(self::RETIRED_FIELDS, array_keys($fields)));
        if ($sent !== []) {
            throw new \RuntimeException(sprintf(
                gettext('Schedules no longer carry their own recurrence or times; every occurrence follows a calendar event. Remove: %s'),
                implode(', ', $sent)
            ));
        }

        if (array_key_exists('generateAheadDays', $fields)) {
            throw new \RuntimeException(gettext('Schedules no longer set how far ahead they are generated: the scheduling horizon on Admin → Ministry Settings applies to every schedule. Remove: generateAheadDays'));
        }
    }

    /**
     * The mode the row ends up in. A Staff this event schedule keeps its one event for
     * life; the other three may be switched between freely.
     *
     * @param array<string, mixed> $fields
     *
     * @throws \RuntimeException
     */
    private function resolveLinkMode(VolunteerSchedule $schedule, array $fields, bool $isCreate, bool $allowEventMode): string
    {
        $current = $isCreate ? null : (string) $schedule->getLinkMode();

        if (!array_key_exists('linkMode', $fields)) {
            if ($current === null) {
                throw new \RuntimeException(gettext('Choose where the schedule\'s dates come from'));
            }

            return $current;
        }

        $requested = (string) $fields['linkMode'];
        if (!in_array($requested, VolunteerSchedule::allLinkModes(), true)) {
            throw new \RuntimeException(gettext('Unknown schedule link mode'));
        }

        if ($current === VolunteerSchedule::LINK_MODE_EVENT && $requested !== VolunteerSchedule::LINK_MODE_EVENT) {
            throw new \RuntimeException(gettext('A schedule that staffs one event cannot follow other events; delete it and add a schedule instead'));
        }
        if ($requested === VolunteerSchedule::LINK_MODE_EVENT && $current !== VolunteerSchedule::LINK_MODE_EVENT && !$allowEventMode) {
            throw new \RuntimeException(gettext('Use Staff this event to staff a single event'));
        }

        return $requested;
    }

    /**
     * The columns that say which events the schedule follows, per link mode (D22).
     *
     * @param array<string, mixed> $fields
     *
     * @throws \RuntimeException
     */
    private function applyBinding(VolunteerSchedule $schedule, array $fields, bool $isCreate): void
    {
        $mode = (string) $schedule->getLinkMode();
        $idField = static function (string $key, ?int $stored) use ($fields, $isCreate): ?int {
            if (!array_key_exists($key, $fields)) {
                return $isCreate ? null : $stored;
            }
            $raw = $fields[$key];

            return $raw === null || $raw === '' ? null : (int) $raw;
        };

        if ($mode !== VolunteerSchedule::LINK_MODE_EVENT && array_key_exists('eventId', $fields)) {
            throw new \RuntimeException(gettext('Use Staff this event to staff a single event'));
        }

        $eventTypeId = null;
        $groupId = null;
        $eventId = null;

        switch ($mode) {
            case VolunteerSchedule::LINK_MODE_EVENT_TYPE:
                $eventTypeId = $idField('eventTypeId', $schedule->getEventTypeId() === null ? null : (int) $schedule->getEventTypeId());
                if ($eventTypeId === null) {
                    throw new \RuntimeException(gettext('A schedule that follows an event type must name the event type'));
                }
                if (EventTypeQuery::create()->findPk($eventTypeId) === null) {
                    throw new \RuntimeException(gettext('The event type does not exist'));
                }
                break;

            case VolunteerSchedule::LINK_MODE_CLASS:
                $groupId = $idField('groupId', $schedule->getGroupId() === null ? null : (int) $schedule->getGroupId());
                if ($groupId === null) {
                    throw new \RuntimeException(gettext('A schedule that follows a class must name the class'));
                }
                if (GroupQuery::create()->findPk($groupId) === null) {
                    throw new \RuntimeException(gettext('The class does not exist'));
                }
                $schedule->setTitleFilter(null);
                break;

            case VolunteerSchedule::LINK_MODE_EVENT:
                $eventId = $idField('eventId', $schedule->getEventId() === null ? null : (int) $schedule->getEventId());
                if ($eventId === null) {
                    throw new \RuntimeException(gettext('Choose the event to staff'));
                }
                if (!$isCreate && $eventId !== (int) $schedule->getEventId()) {
                    throw new \RuntimeException(gettext('A schedule that staffs one event cannot follow other events; delete it and add a schedule instead'));
                }
                if (EventQuery::create()->findPk($eventId) === null) {
                    throw new \RuntimeException(gettext('The event does not exist'));
                }
                $schedule->setTitleFilter(null);
                break;
        }

        $schedule->setEventTypeId($eventTypeId);
        $schedule->setGroupId($groupId);
        $schedule->setEventId($eventId);
    }

    /**
     * A signed whole number of minutes within ±MAX_OFFSET_MINUTES (D21).
     *
     * @throws \RuntimeException
     */
    private function requireOffset(mixed $raw, string $label): int
    {
        if ($raw === null || $raw === '') {
            $minutes = 0;
        } elseif (is_int($raw)) {
            $minutes = $raw;
        } elseif (is_string($raw) && preg_match('/^[+-]?\d+$/', trim($raw)) === 1) {
            $minutes = (int) trim($raw);
        } else {
            throw new \RuntimeException(sprintf(gettext('The %s must be a whole number of minutes'), $label));
        }

        if (abs($minutes) > VolunteerSchedule::MAX_OFFSET_MINUTES) {
            throw new \RuntimeException(sprintf(
                gettext('The %s must be within %d minutes of the event'),
                $label,
                VolunteerSchedule::MAX_OFFSET_MINUTES
            ));
        }

        return $minutes;
    }

    // ── Staff this event (D22) ─────────────────────────────────────────────

    /**
     * Staff one calendar event: a hidden `event`-mode schedule (`vsch_OneOff = 1`) with its
     * staffing needs and exactly one occurrence, in one transaction. The schedule is where
     * the team, the offsets and the needs live; the Schedules tab never lists it and
     * deleteOccurrence() removes it with its only occurrence.
     *
     * @param array<string, mixed> $fields eventId, teamId, requirements?, name?, startOffsetMinutes?, endOffsetMinutes?
     *
     * @throws VolunteerException 409 when this team already staffs this event this way,
     *                            403 when the actor may not create for the team
     * @throws \RuntimeException  on an unknown, inactive or past event, or a §2.8 violation
     */
    public function staffEvent(VolunteerMinistry $ministry, array $fields, User $actor): VolunteerOccurrence
    {
        $this->refuseRetiredFields($fields);

        $eventId = isset($fields['eventId']) && is_numeric($fields['eventId']) ? (int) $fields['eventId'] : 0;
        $event = $eventId > 0 ? EventQuery::create()->findPk($eventId) : null;
        if ($event === null) {
            throw new \RuntimeException(gettext('The event does not exist'));
        }
        if ((int) $event->getInActive() !== 0) {
            throw new \RuntimeException(gettext('The event is inactive'));
        }

        $day = DateTimeUtils::createDateTime($event->getStart('Y-m-d'));
        $day->setTime(0, 0, 0);
        if ($day < DateTimeUtils::getStartOfToday()) {
            throw new \RuntimeException(gettext('This event has already happened'));
        }

        $teamId = isset($fields['teamId']) && is_numeric($fields['teamId']) ? (int) $fields['teamId'] : 0;
        $alreadyStaffed = VolunteerScheduleQuery::create()
            ->filterByLinkMode(VolunteerSchedule::LINK_MODE_EVENT)
            ->filterByEventId($eventId)
            ->filterByTeamId($teamId)
            ->exists();
        if ($alreadyStaffed) {
            throw VolunteerException::conflict(gettext('This team is already staffing this event'));
        }

        $name = trim((string) ($fields['name'] ?? ''));
        $scheduleFields = [
            'name' => $name !== '' ? $name : mb_substr((string) $event->getTitle(), 0, 100),
            'teamId' => $teamId,
            'linkMode' => VolunteerSchedule::LINK_MODE_EVENT,
            'eventId' => $eventId,
            'windowStart' => $day->format('Y-m-d'),
            'windowEnd' => $day->format('Y-m-d'),
            'active' => true,
        ];
        foreach (['startOffsetMinutes', 'endOffsetMinutes', 'requirements'] as $key) {
            if (array_key_exists($key, $fields)) {
                $scheduleFields[$key] = $fields[$key];
            }
        }

        $con = Propel::getWriteConnection(VolunteerOccurrenceTableMap::DATABASE_NAME);
        $con->beginTransaction();

        try {
            $schedule = $this->insertSchedule($ministry, $scheduleFields, $actor, true);
            $this->generateOccurrences($schedule, $day);

            $occurrence = VolunteerOccurrenceQuery::create()
                ->filterByScheduleId((int) $schedule->getId())
                ->findOne($con);
            if ($occurrence === null) {
                throw new \RuntimeException(gettext('The event could not be staffed'));
            }

            $con->commit();
        } catch (\Throwable $e) {
            $con->rollBack();

            throw $e;
        }

        $this->logger->info('Volunteer event staffed', [
            'occurrenceId' => $occurrence->getId(),
            'scheduleId' => $schedule->getId(),
            'ministryId' => $ministry->getId(),
            'eventId' => $eventId,
            'actorPersonId' => $actor->getId(),
        ]);

        return $occurrence;
    }

    // ── Occurrence generation ──────────────────────────────────────────────

    /**
     * Materialise occurrences up to `$through`, idempotently: find the schedule's events
     * inside the range (active ones only) and attach one occurrence to each. Never writes
     * to `events_event`, and never touches an occurrence that already exists.
     *
     * @return array{created: int, existing: int, from: string, through: string, createdIds: int[]}
     *         `from` and `through` are the dates actually looked at, after today and the
     *         schedule's own window have clamped them (`from` is after `through` when the
     *         window has ended or has not started yet); `createdIds` are the rows THIS run
     *         inserted, so a caller can act on the new occurrences only (the Generate
     *         dialog's default assignments)
     *
     * @throws \RuntimeException when the run would exceed MAX_GENERATED_OCCURRENCES
     */
    public function generateOccurrences(VolunteerSchedule $schedule, ?\DateTimeInterface $through = null): array
    {
        $range = $this->resolveGenerationRange($schedule, $through);
        $reportedThrough = $range['end']->format('Y-m-d');
        $reportedFrom = $range['start']->format('Y-m-d');

        if ($range['start'] > $range['end']) {
            // A window entirely in the past, or one that closed before it opened: nothing
            // to materialise, and explicitly not an error.
            return ['created' => 0, 'existing' => 0, 'from' => $reportedFrom, 'through' => $reportedThrough, 'createdIds' => []];
        }

        $events = $this->findEvents($schedule, $range['start'], $range['end']);
        $this->assertWithinCap(count($events));

        $result = $this->persistOccurrences($schedule, $events);

        $this->logger->info('Volunteer occurrences generated', [
            'scheduleId' => $schedule->getId(),
            'linkMode' => $schedule->getLinkMode(),
            'through' => $reportedThrough,
            'created' => $result['created'],
            'existing' => $result['existing'],
        ]);

        return $result + ['from' => $reportedFrom, 'through' => $reportedThrough];
    }

    /**
     * What a schedule looks for on the calendar, with the names a person reads (D30): the
     * Generate result explains an empty run with it.
     *
     * @return array{linkMode: string, groupId: ?int, groupName: ?string, eventTypeId: ?int, eventTypeName: ?string, titleFilter: ?string, ministryId: int, ministryName: ?string, eventId: ?int, eventTitle: ?string}
     */
    public function describeEventSource(VolunteerSchedule $schedule): array
    {
        $group = $schedule->getGroupId() === null ? null : GroupQuery::create()->findPk((int) $schedule->getGroupId());
        $type = $schedule->getEventTypeId() === null ? null : EventTypeQuery::create()->findPk((int) $schedule->getEventTypeId());
        $event = $schedule->getEventId() === null ? null : EventQuery::create()->findPk((int) $schedule->getEventId());
        $ministry = VolunteerMinistryQuery::create()->findPk((int) $schedule->getMinistryId());

        return [
            'linkMode' => (string) $schedule->getLinkMode(),
            'groupId' => $group === null ? null : (int) $group->getId(),
            'groupName' => $group === null ? null : (string) $group->getName(),
            'eventTypeId' => $type === null ? null : (int) $type->getId(),
            'eventTypeName' => $type === null ? null : (string) $type->getName(),
            'titleFilter' => $schedule->getTitleFilter(),
            'ministryId' => (int) $schedule->getMinistryId(),
            'ministryName' => $ministry === null ? null : (string) $ministry->getName(),
            'eventId' => $event === null ? null : (int) $event->getId(),
            'eventTitle' => $event === null ? null : (string) $event->getTitle(),
        ];
    }

    /**
     * The last date a run with no `through` of its own materialises (D31): the horizon, or
     * the schedule's last date when that comes first.
     */
    public function generationThrough(VolunteerSchedule $schedule): string
    {
        return $this->resolveGenerationRange($schedule, null)['end']->format('Y-m-d');
    }

    /**
     * The inclusive date range one run materialises.
     *
     * Start is the later of the schedule's window start and today: historical occurrences
     * are immutable (§2.9). End is the earliest of the caller's `through` (today plus the
     * scheduling horizon when it names none), the horizon itself and the schedule's window
     * end (D31). The horizon does not cap a Staff this event schedule: its one event was
     * picked by hand, however far ahead it is.
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

        $horizon = clone $today;
        $horizon->modify('+' . (self::horizonWeeks() * 7) . ' days');

        $end = $through === null ? clone $horizon : DateTimeUtils::createDateTime($through->format('Y-m-d'));
        $end->setTime(0, 0, 0);
        if ((string) $schedule->getLinkMode() !== VolunteerSchedule::LINK_MODE_EVENT && $end > $horizon) {
            $end = clone $horizon;
        }

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
     * The active events a schedule follows inside a date range, per link mode (D22). A
     * binding whose target was deleted (the FK set it to NULL), and a type or ministry
     * schedule saved without a title before D31, find nothing.
     *
     * @return Event[]
     */
    public function findEvents(VolunteerSchedule $schedule, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $query = $this->eventQuery($schedule, $start, $end);

        return $query === null ? [] : iterator_to_array($query->orderByStart()->find(), false);
    }

    /** D31: whether any active event the schedule follows is still to come, today included. */
    public function hasUpcomingEvents(VolunteerSchedule $schedule): bool
    {
        $query = $this->eventQuery($schedule, DateTimeUtils::getStartOfToday(), null);

        return $query !== null && $query->exists();
    }

    private function eventQuery(VolunteerSchedule $schedule, \DateTimeInterface $start, ?\DateTimeInterface $end): ?EventQuery
    {
        $query = EventQuery::create()
            ->filterByInActive(0)
            ->filterByStart($start->format('Y-m-d') . ' 00:00:00', Criteria::GREATER_EQUAL);
        if ($end !== null) {
            $query->filterByStart($end->format('Y-m-d') . ' 23:59:59', Criteria::LESS_EQUAL);
        }

        switch ((string) $schedule->getLinkMode()) {
            case VolunteerSchedule::LINK_MODE_EVENT_TYPE:
                if ($schedule->getEventTypeId() === null || !self::namesTitle($schedule)) {
                    return null;
                }

                return self::filterByExactTitle($query->filterByType((int) $schedule->getEventTypeId()), (string) $schedule->getTitleFilter());

            case VolunteerSchedule::LINK_MODE_CLASS:
                if ($schedule->getGroupId() === null) {
                    return null;
                }

                return $query->useEventAudienceQuery()->filterByGroupId((int) $schedule->getGroupId())->endUse();

            case VolunteerSchedule::LINK_MODE_MINISTRY:
                if (!self::namesTitle($schedule)) {
                    return null;
                }

                return self::filterByExactTitle($query->filterByMinistryId((int) $schedule->getMinistryId()), (string) $schedule->getTitleFilter());

            case VolunteerSchedule::LINK_MODE_EVENT:
                return $schedule->getEventId() === null ? null : $query->filterById((int) $schedule->getEventId());

            default:
                return null;
        }
    }

    /**
     * The active, non-one-off schedules of a ministry that follow events with this title,
     * type and Linked Groups (D33): `class` mode on one of the groups, `ministry` mode with
     * exactly the title, `event_type` mode with the type and exactly the title — the rules
     * eventQuery() finds events by. Core has no event series id (F7), so this is how a new
     * series is recognised as one a schedule already follows.
     *
     * @param int[] $groupIds
     *
     * @return VolunteerSchedule[] ordered by id
     */
    public function findSchedulesFollowing(int $ministryId, string $title, int $eventTypeId, array $groupIds): array
    {
        $base = static fn (string $mode): VolunteerScheduleQuery => VolunteerScheduleQuery::create()
            ->filterByMinistryId($ministryId)
            ->filterByActive(true)
            ->filterByOneOff(false)
            ->filterByLinkMode($mode);
        $titled = static fn (VolunteerScheduleQuery $query): VolunteerScheduleQuery => $query
            ->where('LOWER(VolunteerSchedule.TitleFilter) = LOWER(?)', trim($title), \PDO::PARAM_STR);

        $queries = [
            $titled($base(VolunteerSchedule::LINK_MODE_MINISTRY)),
            $titled($base(VolunteerSchedule::LINK_MODE_EVENT_TYPE)->filterByEventTypeId($eventTypeId)),
        ];
        if ($groupIds !== []) {
            $queries[] = $base(VolunteerSchedule::LINK_MODE_CLASS)->filterByGroupId($groupIds, Criteria::IN);
        }

        $found = [];
        foreach ($queries as $query) {
            foreach ($query->find() as $schedule) {
                $found[(int) $schedule->getId()] = $schedule;
            }
        }
        ksort($found);

        return array_values($found);
    }

    /**
     * D31: a title names one series exactly, ignoring case — "VBS" must not pick up
     * "VBS Day 2", which the `LIKE %title%` narrowing this replaced did.
     */
    public static function filterByExactTitle(EventQuery $query, string $title): EventQuery
    {
        return $query->where('LOWER(Event.Title) = LOWER(?)', trim($title), \PDO::PARAM_STR);
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
     * One transaction, findOneOrCreate per event: `vocc_schedule_event_uidx` does the
     * deduplication, so a second run over the same window inserts nothing and reports every
     * event as `existing` — `skipExisting` (F8) re-expressed as a database guarantee.
     *
     * @param Event[] $events
     *
     * @return array{created: int, existing: int, createdIds: int[]}
     */
    private function persistOccurrences(VolunteerSchedule $schedule, array $events): array
    {
        $created = 0;
        $existing = 0;
        $createdIds = [];
        $now = DateTimeUtils::getNowDateTime();
        $scheduleId = (int) $schedule->getId();

        $con = Propel::getWriteConnection(VolunteerOccurrenceTableMap::DATABASE_NAME);
        $con->beginTransaction();

        try {
            foreach ($events as $event) {
                $occurrence = VolunteerOccurrenceQuery::create()
                    ->filterByScheduleId($scheduleId)
                    ->filterByEventId((int) $event->getId())
                    ->findOneOrCreate($con);

                if (!$occurrence->isNew()) {
                    // Historical occurrences are immutable: an existing row is never
                    // re-pointed at another event, moved, or deleted (§2.9).
                    $existing++;
                    continue;
                }

                $occurrence->setScheduleId($scheduleId);
                $occurrence->setEventId((int) $event->getId());
                $occurrence->setOccurrenceDate($event->getStart('Y-m-d'));
                $occurrence->setStatus(VolunteerOccurrence::STATUS_SCHEDULED);
                $occurrence->setGeneratedDate($now);
                $occurrence->save($con);
                $created++;
                $createdIds[] = (int) $occurrence->getId();
            }

            $con->commit();
        } catch (\Throwable $e) {
            $con->rollBack();

            throw $e;
        }

        return ['created' => $created, 'existing' => $existing, 'createdIds' => $createdIds];
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

    /**
     * Delete one occurrence outright (product-owner decision, 2026-09-18) — the
     * Occurrences tab's checkbox-and-Delete for dates that should never have been
     * generated. Everything under it goes too: its requirement overrides, its
     * assignments and, through them, their responses, swaps and queued
     * notifications (all ON DELETE CASCADE, §2.9 / §2.11). Unlike cancelling, a
     * deleted occurrence CAN come back from a later generation run, because the event
     * it was made from still exists; that is what "delete" means here.
     */
    public function deleteOccurrence(VolunteerOccurrence $occurrence, User $actor): void
    {
        $occurrenceId = (int) $occurrence->getId();
        $scheduleId = (int) $occurrence->getScheduleId();
        $assignments = VolunteerAssignmentQuery::create()->filterByOccurrenceId($occurrenceId)->count();

        $occurrence->delete();

        // A Staff this event schedule has no life of its own: it exists to carry this one
        // occurrence's team, offsets and staffing needs, so it goes with it. This is NOT a
        // general "last occurrence deletes the schedule" rule — an ordinary schedule with
        // no occurrences left is a plan waiting to be generated.
        $schedule = VolunteerScheduleQuery::create()->findPk($scheduleId);
        $removedSchedule = false;
        if ($schedule !== null && $schedule->getOneOff()
            && VolunteerOccurrenceQuery::create()->filterByScheduleId($scheduleId)->count() === 0) {
            $schedule->delete();
            $removedSchedule = true;
        }

        $this->logger->info('Volunteer occurrence deleted', [
            'occurrenceId' => $occurrenceId,
            'scheduleId' => $scheduleId,
            'occurrenceDate' => $occurrence->getOccurrenceDate('Y-m-d'),
            'assignments' => $assignments,
            'removedStaffedEventSchedule' => $removedSchedule,
            'actorPersonId' => $actor->getId(),
        ]);
    }

    // ── Effective time ─────────────────────────────────────────────────────

    /**
     * The volunteers' real start and end: the anchored event's, moved by the schedule's
     * offsets (D21). **The single source of truth** — nothing else in V2 may decide what
     * time an occurrence happens at.
     *
     * Both are null once the event was deleted: the FK is ON DELETE SET NULL, so such a row
     * survives on `vocc_OccurrenceDate` alone (§2.9).
     *
     * @return array{start: ?\DateTime, end: ?\DateTime}
     */
    public function resolveOccurrenceWindow(VolunteerOccurrence $occurrence): array
    {
        $eventId = $occurrence->getEventId();
        $event = $eventId === null ? null : EventQuery::create()->findPk((int) $eventId);
        if ($event === null) {
            return ['start' => null, 'end' => null];
        }

        return $this->shiftedWindow($event, VolunteerScheduleQuery::create()->findPk((int) $occurrence->getScheduleId()));
    }

    /**
     * An event's start and end moved by a schedule's offsets.
     *
     * @return array{start: ?\DateTime, end: ?\DateTime}
     */
    public function shiftedWindow(Event $event, ?VolunteerSchedule $schedule): array
    {
        $start = $this->shift($event->getStart(), $schedule === null ? 0 : (int) $schedule->getStartOffsetMinutes());
        $end = $this->shift($event->getEnd(), $schedule === null ? 0 : (int) $schedule->getEndOffsetMinutes());

        // Offsets that cross on a short event would end the shift before it starts.
        if ($start !== null && $end !== null && $end < $start) {
            $end = clone $start;
        }

        return ['start' => $start, 'end' => $end];
    }

    /**
     * The schedule's offsets in words, for a page that shows the event beside the shift;
     * empty when the volunteers keep the event's own times.
     */
    public function offsetSummary(VolunteerSchedule $schedule): string
    {
        $parts = [];

        $start = (int) $schedule->getStartOffsetMinutes();
        if ($start !== 0) {
            $parts[] = sprintf(
                $start < 0
                    ? ngettext('Volunteers start %d minute before the event starts.', 'Volunteers start %d minutes before the event starts.', abs($start))
                    : ngettext('Volunteers start %d minute after the event starts.', 'Volunteers start %d minutes after the event starts.', abs($start)),
                abs($start)
            );
        }

        $end = (int) $schedule->getEndOffsetMinutes();
        if ($end !== 0) {
            $parts[] = sprintf(
                $end > 0
                    ? ngettext('They finish %d minute after the event ends.', 'They finish %d minutes after the event ends.', abs($end))
                    : ngettext('They finish %d minute before the event ends.', 'They finish %d minutes before the event ends.', abs($end)),
                abs($end)
            );
        }

        return implode(' ', $parts);
    }

    private function shift(mixed $at, int $minutes): ?\DateTime
    {
        if (!$at instanceof \DateTimeInterface) {
            return null;
        }

        // A copy: Propel hands out the model's own DateTime, and modifying it in place
        // would move the event.
        $shifted = \DateTime::createFromInterface($at);
        if ($minutes !== 0) {
            $shifted->modify(sprintf('%+d minutes', $minutes));
        }

        return $shifted;
    }

    // ── What the schedule dialogs offer ────────────────────────────────────

    /**
     * The distinct titles of the upcoming active events a title filter could name — of one
     * event type (`event_type` mode) or owned by one ministry (`ministry` mode) — with the
     * next date and how many there are.
     *
     * @return array<int, array{title: string, nextStart: string, count: int}>
     */
    public function listEventTitles(?int $eventTypeId, ?int $ministryId, string $from): array
    {
        $query = EventQuery::create()
            ->filterByInActive(0)
            ->filterByStart($from . ' 00:00:00', Criteria::GREATER_EQUAL);
        if ($eventTypeId !== null) {
            $query->filterByType($eventTypeId);
        }
        if ($ministryId !== null) {
            $query->filterByMinistryId($ministryId);
        }

        // Keyed as generation matches titles (D31), so "VBS" and "vbs" are one series.
        $series = [];
        foreach ($query->orderByStart()->select(['Title', 'Start'])->find() as $row) {
            $title = (string) $row['Title'];
            $key = mb_strtolower(trim($title));
            if (!isset($series[$key])) {
                $series[$key] = ['title' => $title, 'nextStart' => (string) $row['Start'], 'count' => 0];
            }
            $series[$key]['count']++;
        }

        return array_values($series);
    }

    /**
     * The groups a `class` schedule may follow: every active Sunday School class, and any
     * other group that has an upcoming active event linked to it. Alphabetical.
     *
     * @return array<int, array{groupId: int, name: string, sundaySchool: bool, upcomingCount: int, nextStart: ?string}>
     */
    public function listClassGroups(string $from): array
    {
        $upcoming = [];
        $rows = EventAudienceQuery::create()
            ->useEventQuery()
                ->filterByInActive(0)
                ->filterByStart($from . ' 00:00:00', Criteria::GREATER_EQUAL)
            ->endUse()
            ->withColumn('Event.Start', 'EventStart')
            ->select(['GroupId', 'EventStart'])
            ->find();
        foreach ($rows as $row) {
            $groupId = (int) $row['GroupId'];
            $start = (string) $row['EventStart'];
            $upcoming[$groupId] ??= ['count' => 0, 'next' => $start];
            $upcoming[$groupId]['count']++;
            if ($start < $upcoming[$groupId]['next']) {
                $upcoming[$groupId]['next'] = $start;
            }
        }

        $groups = GroupQuery::create()
            ->condition('isClass', 'Group.Type = ?', self::SUNDAY_SCHOOL_GROUP_TYPE)
            ->condition('isActive', 'Group.Active = ?', 1)
            ->combine(['isClass', 'isActive'], Criteria::LOGICAL_AND, 'activeClass')
            ->condition('hasEvents', 'Group.Id IN ?', $upcoming === [] ? [0] : array_keys($upcoming))
            ->where(['activeClass', 'hasEvents'], Criteria::LOGICAL_OR)
            ->orderByName()
            ->find();

        $result = [];
        foreach ($groups as $group) {
            $groupId = (int) $group->getId();
            $result[] = [
                'groupId' => $groupId,
                'name' => (string) $group->getName(),
                'sundaySchool' => (int) $group->getType() === self::SUNDAY_SCHOOL_GROUP_TYPE,
                'upcomingCount' => $upcoming[$groupId]['count'] ?? 0,
                'nextStart' => $upcoming[$groupId]['next'] ?? null,
            ];
        }

        return $result;
    }

    /**
     * Upcoming active events for the Staff this event picker: title substring, date range,
     * soonest first, capped. `staffedByTeam` says whether `$teamId` already has an
     * occurrence anchored to the event, from any of its schedules.
     *
     * @return array<int, array{id: int, title: string, start: string, end: string, eventTypeId: int, eventTypeName: ?string, staffedByTeam: bool}>
     */
    public function searchUpcomingEvents(?string $text, string $from, ?string $to, ?int $teamId): array
    {
        $query = EventQuery::create()
            ->filterByInActive(0)
            ->filterByStart($from . ' 00:00:00', Criteria::GREATER_EQUAL);
        if ($to !== null) {
            $query->filterByStart($to . ' 23:59:59', Criteria::LESS_EQUAL);
        }
        if ($text !== null && $text !== '') {
            $query->filterByTitle('%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $text) . '%', Criteria::LIKE);
        }

        $events = iterator_to_array($query->orderByStart()->limit(self::MAX_EVENT_SEARCH)->find(), false);
        if ($events === []) {
            return [];
        }

        $staffed = [];
        if ($teamId !== null) {
            $eventIds = array_map(static fn (Event $event): int => (int) $event->getId(), $events);
            $rows = VolunteerOccurrenceQuery::create()
                ->filterByEventId($eventIds, Criteria::IN)
                ->useScheduleQuery()->filterByTeamId($teamId)->endUse()
                ->select(['EventId'])
                ->find();
            foreach ($rows as $eventId) {
                $staffed[(int) $eventId] = true;
            }
        }

        $typeNames = [];
        $result = [];
        foreach ($events as $event) {
            $typeId = (int) $event->getType();
            if (!array_key_exists($typeId, $typeNames)) {
                $type = EventTypeQuery::create()->findPk($typeId);
                $typeNames[$typeId] = $type === null ? null : (string) $type->getName();
            }
            $result[] = [
                'id' => (int) $event->getId(),
                'title' => (string) $event->getTitle(),
                'start' => (string) $event->getStart('Y-m-d H:i:s'),
                'end' => (string) $event->getEnd('Y-m-d H:i:s'),
                'eventTypeId' => $typeId,
                'eventTypeName' => $typeNames[$typeId],
                'staffedByTeam' => isset($staffed[(int) $event->getId()]),
            ];
        }

        return $result;
    }

    // ── Effective requirements ─────────────────────────────────────────────

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
     * Make one level's staffing needs match `$rows` exactly: upsert every row named,
     * delete every row at that level whose position is not named.
     *
     * This is the "set the whole plan at once" half of §2.10 that
     * {@see self::upsertRequirement()} — a one-position upsert — cannot express: a form
     * that lists every position with a checkbox has to be able to say "and NOT this one",
     * and a sequence of upserts has no way to. The two live side by side; this one is
     * built out of the other, so there is still exactly one place that writes a
     * requirement row.
     *
     * Exactly one of `$schedule` / `$occurrence` is non-null, same as the single upsert.
     * An empty `$rows` is legal and means "nothing is needed here" — the schedule form
     * warns about it, the service does not refuse it.
     *
     * A schedule's rows may also carry its default volunteer (D32): `defaultPersonId` (null
     * clears it; absent keeps the stored one) and `defaultAccepted`. An occurrence's may not.
     *
     * @param mixed $rows list of {positionId, minCount, maxCount?, notes?, defaultPersonId?, defaultAccepted?}
     *
     * @return VolunteerRequirement[] the resulting rows, in the order they were given
     *
     * @throws \RuntimeException
     */
    public function replaceRequirements(
        ?VolunteerSchedule $schedule,
        ?VolunteerOccurrence $occurrence,
        mixed $rows,
        ?User $actor = null
    ): array {
        if (($schedule === null) === ($occurrence === null)) {
            throw new \RuntimeException(gettext('A staffing requirement belongs to exactly one of a schedule or an occurrence'));
        }
        if (!is_array($rows)) {
            throw new \RuntimeException(gettext('The staffing needs must be a list'));
        }

        // Resolve and validate everything BEFORE the first write, so a bad row in the
        // middle of the list cannot leave the plan half-applied even outside a
        // transaction.
        $wanted = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['positionId'])) {
                throw new \RuntimeException(gettext('Each staffing need must name a position'));
            }

            $positionId = (int) $row['positionId'];
            if (isset($wanted[$positionId])) {
                throw new \RuntimeException(gettext('The same position is listed twice in the staffing needs'));
            }

            $position = VolunteerPositionQuery::create()->findPk($positionId);
            if ($position === null) {
                throw new \RuntimeException(gettext('Position not found'));
            }

            if (!isset($row['minCount']) || !is_numeric($row['minCount'])) {
                throw new \RuntimeException(gettext('A minimum count is required'));
            }

            $maxCount = isset($row['maxCount']) && $row['maxCount'] !== '' && $row['maxCount'] !== null
                ? (int) $row['maxCount']
                : null;

            if ($occurrence !== null && !empty($row['defaultPersonId'])) {
                throw VolunteerException::invalid(gettext('A default volunteer belongs to the schedule\'s staffing needs, not to one occurrence'));
            }

            $wanted[$positionId] = [
                'position' => $position,
                'minCount' => (int) $row['minCount'],
                'maxCount' => $maxCount,
                // The route sanitizer is declarative and per-field; a nested array never
                // passes through it, so the notes of a nested row are sanitized here.
                'notes' => isset($row['notes']) ? InputUtils::sanitizeText((string) $row['notes']) : null,
                'default' => $schedule === null ? null : $this->readDefault($row, $positionId, $this->templateRequirement($schedule, $positionId)),
            ];
        }

        $result = [];
        foreach ($wanted as $entry) {
            $requirement = $this->upsertRequirement(
                $schedule,
                $occurrence,
                $entry['position'],
                $entry['minCount'],
                $entry['maxCount'],
                $entry['notes']
            );
            if ($entry['default'] !== null) {
                $this->writeDefault($requirement, $entry['default'], $actor);
            }
            $result[] = $requirement;
        }

        $removed = $this->clearRequirements($schedule, $occurrence, array_keys($wanted));

        $this->logger->info('Volunteer staffing needs replaced', [
            'scheduleId' => $schedule === null ? null : (int) $schedule->getId(),
            'occurrenceId' => $occurrence === null ? null : (int) $occurrence->getId(),
            'kept' => count($result),
            'removed' => $removed,
        ]);

        return $result;
    }

    /**
     * D32: the default volunteers the Generate dialog names, saved on
     * the schedule's staffing needs — a blank person clears that position's. Each position
     * must be one the needs ask for, because the default lives on that need. Nothing is
     * written unless every entry is valid.
     *
     * @param array<int, mixed> $defaults list of {positionId, personId, accepted?}
     *
     * @throws VolunteerException
     */
    public function saveDefaults(VolunteerSchedule $schedule, array $defaults, User $actor): void
    {
        $plan = [];
        foreach ($defaults as $default) {
            if (!is_array($default)) {
                throw VolunteerException::invalid(gettext('Each default volunteer must name a position'));
            }
            $positionId = (int) ($default['positionId'] ?? 0);
            $requirement = $this->templateRequirement($schedule, $positionId);
            if ($requirement === null) {
                throw VolunteerException::invalid(gettext('A default volunteer can only be chosen for a position this schedule\'s staffing needs ask for'));
            }
            $plan[] = [$requirement, $this->readDefault(
                ['defaultPersonId' => $default['personId'] ?? null, 'defaultAccepted' => $default['accepted'] ?? false],
                $positionId,
                $requirement
            )];
        }

        foreach ($plan as [$requirement, $choice]) {
            $this->writeDefault($requirement, $choice, $actor);
        }
    }

    /** Whether a person may be assigned to a position at all (I2): an active qualification. */
    public static function holdsQualification(int $personId, int $positionId): bool
    {
        return VolunteerQualificationQuery::create()
            ->filterByPersonId($personId)
            ->filterByPositionId($positionId)
            ->filterByActive(true)
            ->exists();
    }

    private function templateRequirement(VolunteerSchedule $schedule, int $positionId): ?VolunteerRequirement
    {
        if ($schedule->isNew()) {
            return null;
        }

        return VolunteerRequirementQuery::create()
            ->filterByScheduleId((int) $schedule->getId())
            ->filterByOccurrenceId(null)
            ->filterByPositionId($positionId)
            ->findOne();
    }

    /**
     * One row's default as asked for, or null when the row does not mention it. A NEW choice
     * must be a person holding an active qualification for the position; the stored default,
     * named again, is kept as it is even after that qualification was revoked — it is not a
     * new choice, and generation leaves it open until they qualify again.
     *
     * @param array<string, mixed> $row
     *
     * @return array{personId: ?int, accepted: bool}|null
     *
     * @throws VolunteerException
     */
    private function readDefault(array $row, int $positionId, ?VolunteerRequirement $stored): ?array
    {
        if (!array_key_exists('defaultPersonId', $row)) {
            return null;
        }

        $raw = $row['defaultPersonId'];
        $personId = null;
        if ($raw !== null && $raw !== '' && (string) $raw !== '0') {
            if (!is_numeric($raw) || (int) $raw <= 0) {
                throw VolunteerException::invalid(gettext('The default volunteer must be a person'));
            }
            $personId = (int) $raw;
        }

        $unchanged = $personId !== null
            && $stored !== null
            && $stored->getDefaultPersonId() !== null
            && (int) $stored->getDefaultPersonId() === $personId;

        if ($personId !== null && !$unchanged) {
            if (PersonQuery::create()->findPk($personId) === null) {
                throw VolunteerException::notFound(gettext('Person not found'));
            }
            if (!self::holdsQualification($personId, $positionId)) {
                throw VolunteerException::forbidden(gettext('That person is not qualified for this position'));
            }
        }

        $accepted = false;
        if ($personId !== null) {
            $accepted = array_key_exists('defaultAccepted', $row)
                ? filter_var($row['defaultAccepted'], FILTER_VALIDATE_BOOLEAN)
                : $unchanged && (bool) $stored->getDefaultAccepted();
        }

        return ['personId' => $personId, 'accepted' => $accepted];
    }

    /**
     * @param array{personId: ?int, accepted: bool} $choice
     */
    private function writeDefault(VolunteerRequirement $requirement, array $choice, ?User $actor): void
    {
        $storedPerson = $requirement->getDefaultPersonId() === null ? null : (int) $requirement->getDefaultPersonId();
        if ($storedPerson === $choice['personId'] && (bool) $requirement->getDefaultAccepted() === $choice['accepted']) {
            return;
        }

        $requirement->setDefaultPersonId($choice['personId']);
        $requirement->setDefaultAccepted($choice['accepted']);
        $requirement->setDefaultSetByPersonId($choice['personId'] === null || $actor === null ? null : (int) $actor->getId());
        $requirement->save();

        $this->logger->info('Volunteer schedule default set', [
            'scheduleId' => $requirement->getScheduleId(),
            'positionId' => $requirement->getPositionId(),
            'personId' => $choice['personId'],
            'accepted' => $choice['accepted'],
            'actorPersonId' => $actor?->getId(),
        ]);
    }

    /**
     * Delete one level's requirement rows, optionally sparing the positions in `$keep`.
     *
     * With no `$keep` this is the "use the schedule's needs again" reset of an
     * occurrence: remove its overrides and `getEffectiveRequirements()` falls straight
     * back to the schedule's templates, because the merge is derived and nothing was
     * ever copied (§2.10).
     *
     * @param int[] $keep position ids to leave alone
     *
     * @return int how many rows were deleted
     *
     * @throws \RuntimeException
     */
    public function clearRequirements(
        ?VolunteerSchedule $schedule,
        ?VolunteerOccurrence $occurrence,
        array $keep = []
    ): int {
        if (($schedule === null) === ($occurrence === null)) {
            throw new \RuntimeException(gettext('A staffing requirement belongs to exactly one of a schedule or an occurrence'));
        }

        $query = VolunteerRequirementQuery::create();
        if ($schedule !== null) {
            $query->filterByScheduleId((int) $schedule->getId())->filterByOccurrenceId(null);
        } else {
            $query->filterByOccurrenceId((int) $occurrence->getId())->filterByScheduleId(null);
        }

        if ($keep !== []) {
            $query->filterByPositionId(array_map('intval', $keep), Criteria::NOT_IN);
        }

        $deleted = 0;
        foreach ($query->find() as $requirement) {
            $requirement->delete();
            $deleted++;
        }

        return $deleted;
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
}
