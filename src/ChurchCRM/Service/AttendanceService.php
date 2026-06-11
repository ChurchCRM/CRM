<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\EventAttendQuery;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * Service for per-person attendance history and summary statistics.
 *
 * Queries EventAttend → Event → EventType via Propel ORM and computes
 * attendance streaks using a median-gap-based threshold algorithm.
 */
class AttendanceService
{
    /**
     * Number of days within which the most recent attendance must fall
     * to be counted as part of an active streak.
     */
    private const STREAK_FRESHNESS_DAYS = 60;

    /**
     * Minimum threshold in days for streak gap detection.
     * Used as a floor when median gap is very small (e.g. daily events).
     */
    private const STREAK_MIN_THRESHOLD_DAYS = 14;

    /**
     * Multiplier applied to the median gap to determine the streak threshold.
     */
    private const STREAK_MEDIAN_MULTIPLIER = 2;

    /**
     * Get attendance history for a person, sorted by event start date descending.
     *
     * Includes events where the person is checked in, including events linked
     * to inactive event types. Each record is flagged with `eventInactive` so
     * the UI can badge them.
     *
     * @param int $personId Person ID to fetch attendance for
     *
     * @return array{
     *   records: list<array{
     *     attendId: int,
     *     eventId: int,
     *     eventUrl: string,
     *     eventTitle: string,
     *     eventTypeId: int|null,
     *     eventTypeName: string,
     *     eventStart: string,
     *     eventEnd: string,
     *     checkinDate: string|null,
     *     checkoutDate: string|null,
     *     eventInactive: bool
     *   }>,
     *   summary: array{
     *     totalEvents: int,
     *     lastAttendanceDate: string|null,
     *     streaks: list<array{typeId: int|null, typeName: string, length: int}>
     *   }
     * }
     */
    public function getPersonAttendanceHistory(int $personId): array
    {
        $attendRecords = EventAttendQuery::create()
            ->filterByPersonId($personId)
            ->filterByCheckinDate(null, Criteria::ISNOTNULL)
            ->joinWithEvent(Criteria::LEFT_JOIN)
            ->useEventQuery(null, Criteria::LEFT_JOIN)
                ->leftJoinWithEventType()
            ->endUse()
            ->orderBy('events_event.event_start', Criteria::DESC)
            ->find();

        $records = [];
        foreach ($attendRecords as $attend) {
            $event = $attend->getEvent();
            if ($event === null) {
                // Skip orphaned attendance records (deleted events)
                continue;
            }

            $eventType = $event->getEventType();
            $typeId = $eventType !== null ? (int) $eventType->getId() : null;
            $typeName = $eventType !== null ? (string) $eventType->getName() : gettext('Unknown');

            $checkinDate = $attend->getCheckinDate('Y-m-d H:i:s');
            $checkoutDate = $attend->getCheckoutDate('Y-m-d H:i:s');

            $records[] = [
                'attendId'      => (int) $attend->getAttendId(),
                'eventId'       => (int) $event->getId(),
                'eventUrl'      => $event->getViewURI(),
                'eventTitle'    => (string) $event->getTitle(),
                'eventTypeId'   => $typeId,
                'eventTypeName' => $typeName,
                'eventStart'    => (string) $event->getStart('Y-m-d H:i:s'),
                'eventEnd'      => (string) $event->getEnd('Y-m-d H:i:s'),
                'checkinDate'   => $checkinDate !== false ? $checkinDate : null,
                'checkoutDate'  => $checkoutDate !== false ? $checkoutDate : null,
                'eventInactive' => (bool) $event->getInActive(),
            ];
        }

        $summary = $this->buildSummary($records);

        return [
            'records' => $records,
            'summary' => $summary,
        ];
    }

    /**
     * Build summary statistics from attendance records.
     *
     * Computes:
     * - totalEvents: count of distinct events
     * - lastAttendanceDate: most recent event start date (ISO 8601)
     * - streaks: per-event-type streak using median-gap threshold
     *
     * @param list<array{
     *   eventId: int,
     *   eventTypeId: int|null,
     *   eventTypeName: string,
     *   eventStart: string,
     *   eventInactive: bool
     * }> $records Attendance records sorted by eventStart DESC
     *
     * @return array{
     *   totalEvents: int,
     *   lastAttendanceDate: string|null,
     *   streaks: list<array{typeId: int|null, typeName: string, length: int}>
     * }
     */
    private function buildSummary(array $records): array
    {
        $totalEvents = count($records);
        $lastDate = $totalEvents > 0 ? $records[0]['eventStart'] : null;

        // Group by event type for streak calculation
        /** @var array<string, list<array{typeId: int|null, typeName: string, eventStart: string}>> */
        $byType = [];
        foreach ($records as $rec) {
            $key = $rec['eventTypeId'] !== null ? (string) $rec['eventTypeId'] : 'null';
            $byType[$key][] = [
                'typeId'     => $rec['eventTypeId'],
                'typeName'   => $rec['eventTypeName'],
                'eventStart' => $rec['eventStart'],
            ];
        }

        $streaks = [];
        foreach ($byType as $key => $typeRecords) {
            $typeName = $typeRecords[0]['typeName'];
            $typeId = $typeRecords[0]['typeId'];
            $streak = $this->computeStreak($typeRecords);
            if ($streak > 0) {
                $streaks[] = [
                    'typeId'   => $typeId,
                    'typeName' => $typeName,
                    'length'   => $streak,
                ];
            }
        }

        // Sort streaks descending by length
        usort($streaks, static fn (array $a, array $b): int => $b['length'] - $a['length']);

        return [
            'totalEvents'        => $totalEvents,
            'lastAttendanceDate' => $lastDate,
            'streaks'            => $streaks,
        ];
    }

    /**
     * Compute consecutive attendance streak for a set of events of the same type.
     *
     * Algorithm:
     * 1. Sort events ascending by date.
     * 2. Compute inter-event gaps.
     * 3. Calculate threshold = max(2 × median_gap, STREAK_MIN_THRESHOLD_DAYS) days.
     * 4. Walking backwards from the most recent event, count consecutive events
     *    where each gap ≤ threshold.
     * 5. The most recent event must be within STREAK_FRESHNESS_DAYS of today.
     *
     * Returns 0 if no streak (most recent event is stale, or only 1 event total).
     *
     * @param list<array{eventStart: string}> $typeRecords Records for one event type (any order)
     *
     * @return int Streak length (0 = no active streak)
     */
    private function computeStreak(array $typeRecords): int
    {
        if (count($typeRecords) < 1) {
            return 0;
        }

        // Parse dates and sort ascending
        $dates = array_map(
            static fn (array $r): \DateTimeImmutable => new \DateTimeImmutable($r['eventStart']),
            $typeRecords
        );
        sort($dates);

        $now = new \DateTimeImmutable('today');
        $mostRecent = $dates[count($dates) - 1];

        // Check freshness: most recent event must be within 60 days
        $daysSinceLastEvent = (int) $now->diff($mostRecent)->days;
        if ($daysSinceLastEvent > self::STREAK_FRESHNESS_DAYS) {
            return 0;
        }

        // With only one event, streak = 1 (if fresh)
        if (count($dates) === 1) {
            return 1;
        }

        // Compute gaps (in days) between consecutive events
        $gaps = [];
        for ($i = 1; $i < count($dates); $i++) {
            $gaps[] = (int) $dates[$i]->diff($dates[$i - 1])->days;
        }

        // Compute median gap
        sort($gaps);
        $medianGap = $this->median($gaps);

        // Threshold: at least STREAK_MIN_THRESHOLD_DAYS, at most 2× median
        $threshold = (int) max(
            self::STREAK_MIN_THRESHOLD_DAYS,
            self::STREAK_MEDIAN_MULTIPLIER * $medianGap
        );

        // Walk backwards from most recent event, counting consecutive events
        // where each gap ≤ threshold
        $streak = 1;
        for ($i = count($dates) - 1; $i >= 1; $i--) {
            $gap = (int) $dates[$i]->diff($dates[$i - 1])->days;
            if ($gap <= $threshold) {
                $streak++;
            } else {
                break;
            }
        }

        return $streak;
    }

    /**
     * Calculate the median of a sorted array of numbers.
     *
     * @param list<int> $sorted Sorted array of integers
     *
     * @return float Median value
     */
    private function median(array $sorted): float
    {
        $count = count($sorted);
        if ($count === 0) {
            return 0.0;
        }

        $mid = (int) floor($count / 2);
        if ($count % 2 === 0) {
            return ($sorted[$mid - 1] + $sorted[$mid]) / 2.0;
        }

        return (float) $sorted[$mid];
    }
}
