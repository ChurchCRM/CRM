<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\Event;
use ChurchCRM\model\ChurchCRM\EventAttend;
use ChurchCRM\model\ChurchCRM\EventAttendQuery;
use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Utils\LoggerUtils;

class AttendanceService
{
    /**
     * Import attendance records from CSV data.
     *
     * @param array $csvData Array of attendance records [personId, date, time]
     * @param int $eventId Event ID to associate attendance with
     * @return array Import result with counts and errors
     */
    public function importAttendanceRecords(array $csvData, int $eventId): array
    {
        $logger = LoggerUtils::getAppLogger();
        $imported = 0;
        $skipped = 0;
        $errors = [];

        $event = EventQuery::create()->findOneById($eventId);
        if ($event === null) {
            return [
                'success' => false,
                'imported' => 0,
                'skipped' => 0,
                'errors' => ['Event not found with ID: ' . $eventId],
            ];
        }

        foreach ($csvData as $index => $record) {
            try {
                // Validate record has required fields
                if (empty($record['personId'])) {
                    $errors[] = 'Row ' . ($index + 1) . ': Missing Person ID';
                    $skipped++;
                    continue;
                }

                // Find person by ID
                $person = PersonQuery::create()->findOneById((int)$record['personId']);
                if ($person === null) {
                    $errors[] = 'Row ' . ($index + 1) . ': Person not found with ID ' . $record['personId'];
                    $skipped++;
                    continue;
                }

                // Parse date/time
                $checkinDateTime = $this->parseDateTime($record);
                if ($checkinDateTime === null) {
                    $errors[] = 'Row ' . ($index + 1) . ': Invalid date/time format';
                    $skipped++;
                    continue;
                }

                // Check if attendance record already exists for this person and event on this date
                // Extract just the date portion for duplicate checking
                $dateOnly = date('Y-m-d', strtotime($checkinDateTime));
                $existingRecord = EventAttendQuery::create()
                    ->filterByEventId($eventId)
                    ->filterByPersonId((int)$record['personId'])
                    ->filterByCheckinDate(['min' => $dateOnly . ' 00:00:00', 'max' => $dateOnly . ' 23:59:59'])
                    ->findOne();

                if ($existingRecord !== null) {
                    $logger->debug('Attendance record already exists', [
                        'person_id' => $record['personId'],
                        'event_id' => $eventId,
                        'date' => $checkinDateTime,
                    ]);
                    $skipped++;
                    continue;
                }

                // Create new attendance record
                $attendance = new EventAttend();
                $attendance->setEventId($eventId);
                $attendance->setPersonId((int)$record['personId']);
                $attendance->setCheckinDate($checkinDateTime);
                $attendance->save();

                $logger->info('Attendance record imported', [
                    'person_id' => $record['personId'],
                    'event_id' => $eventId,
                    'checkin_date' => $checkinDateTime,
                ]);

                $imported++;
            } catch (\Exception $e) {
                $errors[] = 'Row ' . ($index + 1) . ': ' . $e->getMessage();
                $logger->error('Failed to import attendance record', [
                    'row' => $index + 1,
                    'error' => $e->getMessage(),
                ]);
                $skipped++;
            }
        }

        return [
            'success' => true,
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Parse date and time from CSV record.
     *
     * @param array $record CSV record with date/time fields
     * @return string|null Formatted datetime string or null if invalid
     */
    private function parseDateTime(array $record): ?string
    {
        // Try to parse combined datetime field first
        if (!empty($record['datetime'])) {
            $timestamp = strtotime($record['datetime']);
            if ($timestamp !== false) {
                return date('Y-m-d H:i:s', $timestamp);
            }
        }

        // Try separate date and time fields
        if (!empty($record['date'])) {
            $dateStr = $record['date'];
            $timeStr = $record['time'] ?? '00:00:00';

            // Combine date and time
            $timestamp = strtotime($dateStr . ' ' . $timeStr);
            if ($timestamp !== false) {
                return date('Y-m-d H:i:s', $timestamp);
            }
        }

        return null;
    }

    /**
     * Get or create a recurring event for attendance tracking.
     *
     * @param string $eventTitle Event title (e.g., "Sunday Service")
     * @param int $eventTypeId Event type ID
     * @return Event Event object
     */
    public function getOrCreateRecurringEvent(string $eventTitle, int $eventTypeId = 1): Event
    {
        // Try to find existing event with this title
        $event = EventQuery::create()
            ->filterByTitle($eventTitle)
            ->filterByInactive(0)
            ->findOne();

        if ($event === null) {
            // Create new event
            $event = new Event();
            $event->setTitle($eventTitle);
            $event->setEventType($eventTypeId);
            $event->setText('Recurring event for attendance tracking');
            $event->setInactive(0);
            $event->save();

            $logger = LoggerUtils::getAppLogger();
            $logger->info('Created recurring event for attendance', [
                'title' => $eventTitle,
                'event_id' => $event->getId(),
            ]);
        }

        return $event;
    }

    /**
     * Get attendance summary for a person.
     *
     * @param int $personId Person ID
     * @param string|null $startDate Start date filter (optional)
     * @param string|null $endDate End date filter (optional)
     * @return array Attendance records
     */
    public function getPersonAttendance(int $personId, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = EventAttendQuery::create()
            ->filterByPersonId($personId)
            ->orderByCheckinDate('DESC');

        if ($startDate !== null) {
            $query->filterByCheckinDate(['min' => $startDate]);
        }

        if ($endDate !== null) {
            $query->filterByCheckinDate(['max' => $endDate]);
        }

        $records = $query->find();

        $attendance = [];
        foreach ($records as $record) {
            $event = $record->getEvent();
            $attendance[] = [
                'attend_id' => $record->getAttendId(),
                'event_id' => $record->getEventId(),
                'event_title' => $event ? $event->getTitle() : 'Unknown Event',
                'checkin_date' => $record->getCheckinDate('Y-m-d H:i:s'),
                'checkout_date' => $record->getCheckoutDate('Y-m-d H:i:s'),
            ];
        }

        return $attendance;
    }

    /**
     * Get attendance count for a person.
     *
     * @param int $personId Person ID
     * @param int|null $eventId Optional event ID to filter by
     * @return int Attendance count
     */
    public function getPersonAttendanceCount(int $personId, ?int $eventId = null): int
    {
        $query = EventAttendQuery::create()
            ->filterByPersonId($personId);

        if ($eventId !== null) {
            $query->filterByEventId($eventId);
        }

        return $query->count();
    }
}
