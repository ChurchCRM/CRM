<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\CalendarQuery;
use ChurchCRM\model\ChurchCRM\Event;
use ChurchCRM\model\ChurchCRM\EventAudience;
use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\model\ChurchCRM\EventType;
use ChurchCRM\model\ChurchCRM\EventTypeQuery;
use ChurchCRM\model\ChurchCRM\Map\EventTableMap;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;

/**
 * Service for managing event business logic, including bulk repeat event creation.
 *
 * This is the **only** recurring-event generator in the codebase. Both HTTP entry
 * points — POST /api/events/repeat and POST /api/events/generate-recurring — and the
 * repeat-event editor UI funnel through createRecurringEvents(), so the same input
 * always produces the same dates, the same times and the same duplicate handling
 * (issue #9735).
 *
 * Documented, shared policy:
 *
 * - **Cap** — a single call creates at most self::MAX_REPEAT_OCCURRENCES events.
 *   The cap is expressed in occurrences rather than calendar span because that is
 *   the unit that bounds the actual work (rows written in one request) and it is
 *   meaningful for all three recurrence types — a calendar horizon short enough to
 *   protect weekly recurrence would make yearly recurrence pointless.
 * - **Recurrence** — a caller-supplied recurType wins and its day fields fall back
 *   to the event type's defaults, then to the legacy literals (Sunday / 1st / 01-01).
 *   When no recurType is supplied it is read from the event type, which must then
 *   also supply the matching day field; an incomplete event type is rejected rather
 *   than silently defaulted.
 * - **Title** — a caller-supplied title is used verbatim for every occurrence.
 *   Otherwise each occurrence is titled "<Event type name> — <M j, Y>".
 * - **Times** — caller-supplied startTime/endTime win; otherwise startTime comes
 *   from the event type's default start time (falling back to 09:00) and endTime is
 *   one hour after startTime. A derived end time that crosses midnight (start at
 *   23:xx) lands on the following calendar day rather than wrapping back to 00:xx
 *   on the occurrence date itself.
 * - **Idempotency** — with skipExisting, an occurrence date that already has an
 *   active event of the same type is skipped instead of duplicated. The key is
 *   (event type, calendar date), so re-running the same request creates nothing.
 *   The whole series is written in one transaction and the existence check is a
 *   locking read, so two concurrent calls for the same event type serialise
 *   instead of both passing the check and inserting duplicates.
 */
class EventService
{
    /**
     * Hard cap on the number of events a single createRecurringEvents() call can
     * generate. Prevents a wide date range (e.g. weekly for 10 years) from
     * creating thousands of rows in one request and locking the table.
     *
     * This is the one cap for both HTTP entry points (#9735). 366 is "at most a
     * year of daily occurrences".
     */
    public const MAX_REPEAT_OCCURRENCES = 366;

    /**
     * Fallback start time when neither the caller nor the event type supplies one.
     */
    private const DEFAULT_START_TIME = '09:00:00';

    private RecurrenceDateGenerator $recurrenceDateGenerator;

    public function __construct(?RecurrenceDateGenerator $recurrenceDateGenerator = null)
    {
        $this->recurrenceDateGenerator = $recurrenceDateGenerator ?? new RecurrenceDateGenerator();
    }

    /**
     * Create a series of repeat events and return just the created IDs.
     *
     * Thin wrapper over createRecurringEvents() kept for callers that only care
     * about the IDs (the repeat-event editor and POST /api/events/repeat).
     *
     * @param array{
     *   title?: string|null,
     *   typeId: int,
     *   desc?: string,
     *   text?: string,
     *   startTime?: string|null,
     *   endTime?: string|null,
     *   recurType?: string|null,
     *   recurDOW?: string|null,
     *   recurDOM?: int|null,
     *   recurDOY?: string|null,
     *   rangeStart: string,
     *   rangeEnd: string,
     *   linkedGroupId?: int,
     *   pinnedCalendars?: int[],
     *   inactive?: int,
     *   skipExisting?: bool
     * } $data Event template and recurrence parameters
     *
     * @return int[] Array of created event IDs
     *
     * @throws \InvalidArgumentException if event type is invalid or date range is invalid
     */
    public function createRepeatEvents(array $data): array
    {
        return array_column($this->createRecurringEvents($data)['events'], 'id');
    }

    /**
     * Create a series of events for every occurrence of a recurrence within a range.
     *
     * Generates individual Event records so each occurrence is independently
     * editable after creation. See the class docblock for the title / time /
     * recurrence / idempotency / cap rules, which are identical for every caller.
     *
     * @param array $data Same shape as createRepeatEvents()
     *
     * @return array{events: array<int, array{id: int, title: string, date: string}>, skipped: int}
     *
     * @throws \InvalidArgumentException if event type is invalid or date range is invalid
     */
    public function createRecurringEvents(array $data): array
    {
        $type = EventTypeQuery::create()->findOneById((int) ($data['typeId'] ?? 0));
        if ($type === null) {
            throw new \InvalidArgumentException(gettext('Invalid event type ID'));
        }

        // Explicit catch so a malformed date string returns 400 instead of
        // bubbling up as a 500.
        try {
            $rangeStart = new \DateTime($data['rangeStart'] ?? '');
            $rangeStart->setTime(0, 0, 0);
            $rangeEnd = new \DateTime($data['rangeEnd'] ?? '');
            $rangeEnd->setTime(23, 59, 59);
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException(gettext('Invalid date format for rangeStart or rangeEnd'));
        }

        if ($rangeStart > $rangeEnd) {
            throw new \InvalidArgumentException(gettext('Range start must be before range end'));
        }

        [$recurType, $dow, $dom, $doy] = $this->resolveRecurrence($data, $type);

        // Normalize start/end time to HH:MM:SS so we don't end up appending
        // ":00" to a string that already includes seconds (e.g. "09:00:00"
        // → "09:00:00:00" which would be an invalid timestamp).
        $startTime = $this->resolveStartTime($data, $type);
        [$endTime, $endsNextDay] = $this->resolveEndTime($data, $startTime);

        $title = trim((string) ($data['title'] ?? ''));
        $desc = $data['desc'] ?? '';
        $text = $data['text'] ?? '';
        $inactive = (int) ($data['inactive'] ?? 0);
        $linkedGroupId = (int) ($data['linkedGroupId'] ?? 0);
        $skipExisting = (bool) ($data['skipExisting'] ?? false);
        $typeId = (int) $type->getId();

        $calendars = null;
        if (!empty($data['pinnedCalendars'])) {
            $calendars = CalendarQuery::create()
                ->filterById($data['pinnedCalendars'], Criteria::IN)
                ->find();
        }

        $occurrenceDates = $this->generateOccurrenceDates(
            $recurType,
            $dow,
            $dom,
            $doy,
            $rangeStart,
            $rangeEnd
        );

        if (count($occurrenceDates) > self::MAX_REPEAT_OCCURRENCES) {
            throw new \InvalidArgumentException(sprintf(
                gettext('Too many occurrences (%d) — narrow the date range. Maximum allowed: %d'),
                count($occurrenceDates),
                self::MAX_REPEAT_OCCURRENCES
            ));
        }

        $created = [];
        $skipped = 0;

        // One transaction for the whole series: the existence check below is a
        // locking read, so the check and the insert it guards cannot interleave
        // with a concurrent call for the same event type (#9735). It also makes
        // a series all-or-nothing instead of leaving a half-written run behind.
        $con = Propel::getWriteConnection(EventTableMap::DATABASE_NAME);
        $con->beginTransaction();

        try {
            foreach ($occurrenceDates as $occurrenceDate) {
                $date = $occurrenceDate->format('Y-m-d');

                if ($skipExisting && $this->hasEventOnDate($typeId, $date, $con)) {
                    $skipped++;
                    continue;
                }

                $eventTitle = $title !== ''
                    ? $title
                    : $type->getName() . ' — ' . $occurrenceDate->format('M j, Y');

                $event = new Event();
                $event->setTitle($eventTitle);
                $event->setEventType($type);
                $event->setDesc($desc);
                $event->setText($text);
                $event->setStart($date . ' ' . $startTime);
                $event->setEnd($this->occurrenceEnd($occurrenceDate, $endTime, $endsNextDay));
                $event->setInActive($inactive);

                if ($calendars !== null) {
                    $event->setCalendars($calendars);
                }

                // Always pass the transaction's connection: a default read
                // connection could not see these uncommitted rows.
                $event->save($con);
                $event->reload(false, $con);
                $eventId = $event->getId();

                if ($linkedGroupId > 0) {
                    $audience = new EventAudience();
                    $audience->setEventId($eventId);
                    $audience->setGroupId($linkedGroupId);
                    $audience->save($con);
                }

                $created[] = [
                    'id' => $eventId,
                    'title' => $eventTitle,
                    'date' => $date,
                ];
            }

            $con->commit();
        } catch (\Throwable $e) {
            $con->rollBack();

            throw $e;
        }

        return [
            'events' => $created,
            'skipped' => $skipped,
        ];
    }

    /**
     * Resolve the recurrence pattern from the caller's input and the event type.
     *
     * A caller-supplied recurType wins, and its day fields fall back to the event
     * type's defaults and then to the legacy literals. When the recurrence itself
     * comes from the event type, the event type must be complete — silently
     * defaulting a mis-configured type to "Sundays" would create the wrong events.
     *
     * @return array{0: string, 1: string|null, 2: int|null, 3: string|null} recurType, dow, dom, doy
     *
     * @throws \InvalidArgumentException when the event type's recurrence is incomplete
     */
    private function resolveRecurrence(array $data, EventType $type): array
    {
        $callerRecurType = isset($data['recurType']) && $data['recurType'] !== ''
            ? (string) $data['recurType']
            : null;

        $recurType = $callerRecurType ?? ($type->getDefRecurType() ?: 'none');
        if ($recurType === 'none' || $recurType === '') {
            throw new \InvalidArgumentException(gettext('Event type has no recurrence pattern configured'));
        }

        $typeIsSource = $callerRecurType === null;

        $dow = $this->firstNonEmptyString($data['recurDOW'] ?? null, $type->getDefRecurDOW());
        if ($recurType === RecurrenceDateGenerator::RECUR_WEEKLY) {
            if ($dow === null && $typeIsSource) {
                throw new \InvalidArgumentException(gettext('Event type has no day-of-week configured for weekly recurrence'));
            }
            $dow ??= 'Sunday';
        }

        $dom = isset($data['recurDOM']) && (int) $data['recurDOM'] > 0
            ? (int) $data['recurDOM']
            : (int) $type->getDefRecurDOM();
        if ($recurType === RecurrenceDateGenerator::RECUR_MONTHLY) {
            if ($dom < 1 || $dom > 31) {
                if ($typeIsSource) {
                    throw new \InvalidArgumentException(gettext('Event type has no valid day-of-month configured for monthly recurrence'));
                }
                $dom = 1;
            }
        }

        $doy = $this->firstNonEmptyString($data['recurDOY'] ?? null, $this->formatDefRecurDoy($type));
        if ($recurType === RecurrenceDateGenerator::RECUR_YEARLY) {
            if ($doy === null && $typeIsSource) {
                throw new \InvalidArgumentException(gettext('Event type has no date configured for yearly recurrence'));
            }
            $doy ??= '01-01';
        }

        return [$recurType, $dow, $dom > 0 ? $dom : null, $doy];
    }

    /**
     * Generate all occurrence dates within a range based on recurrence settings.
     *
     * The single date-math entry point for every recurring-event caller; the math
     * itself lives in the event-free RecurrenceDateGenerator so other modules can
     * reuse it without dragging in event persistence.
     *
     * @param string      $recurType  One of: weekly, monthly, yearly
     * @param string|null $dow        Day of week name (e.g. "Sunday") — used when $recurType is 'weekly'
     * @param int|null    $dom        Day of month (1–31) — used when $recurType is 'monthly'
     * @param string|null $doy        Month-day string in MM-DD format (e.g. "04-12") — used when $recurType is 'yearly'
     * @param \DateTime   $rangeStart Inclusive start of the date range
     * @param \DateTime   $rangeEnd   Inclusive end of the date range
     *
     * @return \DateTime[]
     */
    private function generateOccurrenceDates(
        string $recurType,
        ?string $dow,
        ?int $dom,
        ?string $doy,
        \DateTime $rangeStart,
        \DateTime $rangeEnd
    ): array {
        return $this->recurrenceDateGenerator->generate($recurType, $dow, $dom, $doy, $rangeStart, $rangeEnd);
    }

    /**
     * Is there already an active event of this type on this calendar date?
     *
     * The shared skipExisting key: (event type, calendar date). Deliberately
     * ignores the time of day so a series regenerated with a different start time
     * still counts as "already there".
     *
     * Runs as a locking read (SELECT ... FOR UPDATE) on the caller's transaction
     * connection. With no unique constraint on (event type, date) in the schema,
     * the lock is what stops two concurrent generations of the same event type
     * from both seeing "nothing here" and both inserting: the range scanned on
     * the event_type index is gap-locked for the rest of the transaction.
     */
    private function hasEventOnDate(int $typeId, string $date, ConnectionInterface $con): bool
    {
        return EventQuery::create()
            ->filterByType($typeId)
            ->filterByStart($date . ' 00:00:00', Criteria::GREATER_EQUAL)
            ->filterByStart($date . ' 23:59:59', Criteria::LESS_EQUAL)
            ->filterByInActive(0)
            ->lockForUpdate()
            ->findOne($con) !== null;
    }

    /**
     * Full "Y-m-d H:i:s" end timestamp for one occurrence.
     *
     * The day roll-over is carried as a separate flag rather than being baked
     * into the time string, so a derived end that crosses midnight advances the
     * date instead of landing before its own start (#9735).
     */
    private function occurrenceEnd(\DateTime $occurrenceDate, string $endTime, bool $endsNextDay): string
    {
        $endDate = $endsNextDay
            ? (clone $occurrenceDate)->modify('+1 day')
            : $occurrenceDate;

        return $endDate->format('Y-m-d') . ' ' . $endTime;
    }

    /**
     * Caller start time, else the event type's default, else 09:00.
     */
    private function resolveStartTime(array $data, EventType $type): string
    {
        $callerTime = $this->firstNonEmptyString($data['startTime'] ?? null, null);
        if ($callerTime !== null) {
            return self::normalizeTime($callerTime);
        }

        // getDefStartTime() can return either a DateTime or a raw string
        // depending on the Propel codegen path — handle both safely.
        $defStartTime = $type->getDefStartTime();
        if ($defStartTime instanceof \DateTimeInterface) {
            return $defStartTime->format('H:i:s');
        }
        if (is_string($defStartTime) && $defStartTime !== '') {
            return self::normalizeTime($defStartTime);
        }

        return self::DEFAULT_START_TIME;
    }

    /**
     * Caller end time, else one hour after the start time.
     *
     * @return array{0: string, 1: bool} the HH:MM:SS end time, and whether it
     *                                   falls on the day after the occurrence date
     */
    private function resolveEndTime(array $data, string $startTime): array
    {
        $callerTime = $this->firstNonEmptyString($data['endTime'] ?? null, null);
        if ($callerTime !== null) {
            // An explicit end time is used on the occurrence date itself, as it
            // always has been for POST /events/repeat.
            return [self::normalizeTime($callerTime), false];
        }

        // Anchor the "+1 hour" on a full datetime (fixed date, UTC, so no DST
        // shift can distort a pure wall-clock offset). Adding an hour to a
        // bare H:i:s and re-formatting as H:i:s silently drops the day
        // roll-over, which turned a 23:00 start into a 00:00 end on the *same*
        // date — i.e. end before start.
        $start = \DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            '2000-01-01 ' . $startTime,
            new \DateTimeZone('UTC')
        );
        if ($start === false) {
            return ['10:00:00', false];
        }

        $end = $start->modify('+1 hour');

        return [$end->format('H:i:s'), $end->format('Y-m-d') !== '2000-01-01'];
    }

    /**
     * The event type's yearly recurrence day as an MM-DD string, or null.
     */
    private function formatDefRecurDoy(EventType $type): ?string
    {
        $doy = $type->getDefRecurDOY();
        if ($doy instanceof \DateTimeInterface) {
            return $doy->format('m-d');
        }
        if (is_string($doy) && $doy !== '') {
            // Stored as a DATE ("2016-04-12") in some installs; keep only MM-DD.
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $doy);

            return $parsed !== false ? $parsed->format('m-d') : $doy;
        }

        return null;
    }

    /**
     * First of the two values that is a non-empty, trimmed string, else null.
     */
    private function firstNonEmptyString(mixed $preferred, mixed $fallback): ?string
    {
        foreach ([$preferred, $fallback] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }

    /**
     * Normalize a time string to HH:MM:SS so the caller can safely concatenate
     * it with a date without worrying about whether the input had seconds.
     *
     * Accepts:
     *   - "9:00"      → "09:00:00"
     *   - "09:00"     → "09:00:00"
     *   - "09:00:00"  → "09:00:00"
     * Falls back to "09:00:00" for unparseable input.
     */
    private static function normalizeTime(string $time): string
    {
        $parsed = \DateTimeImmutable::createFromFormat('H:i:s', $time)
            ?: \DateTimeImmutable::createFromFormat('H:i', $time);
        if ($parsed === false) {
            return self::DEFAULT_START_TIME;
        }

        return $parsed->format('H:i:s');
    }
}
