<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\CalendarQuery;
use ChurchCRM\model\ChurchCRM\Event;
use ChurchCRM\model\ChurchCRM\EventAudience;
use ChurchCRM\model\ChurchCRM\EventTypeQuery;
use ChurchCRM\Utils\DateTimeUtils;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * Service for managing event business logic, including bulk repeat event creation.
 */
class EventService
{
    /**
     * Create a series of repeat events based on a template and recurrence settings.
     *
     * Generates individual Event records for each occurrence of a recurring
     * event within a date range. Each event is independently editable after creation.
     *
     * @param array{
     *   title: string,
     *   typeId: int,
     *   desc: string,
     *   text: string,
     *   startTime: string,
     *   endTime: string,
     *   recurType: string,
     *   recurDOW?: string,
     *   recurDOM?: int,
     *   recurDOY?: string,
     *   rangeStart: string,
     *   rangeEnd: string,
     *   linkedGroupId?: int,
     *   pinnedCalendars?: int[],
     *   inactive?: int
     * } $data Event template and recurrence parameters
     *
     * @return int[] Array of created event IDs
     *
     * @throws \InvalidArgumentException if event type is invalid or date range is invalid
     */
    public function createRepeatEvents(array $data): array
    {
        $type = EventTypeQuery::create()->findOneById((int) $data['typeId']);
        if ($type === null) {
            throw new \InvalidArgumentException(gettext('Invalid event type ID'));
        }

        $rangeStart = new \DateTime($data['rangeStart']);
        $rangeStart->setTime(0, 0, 0);
        $rangeEnd = new \DateTime($data['rangeEnd']);
        $rangeEnd->setTime(23, 59, 59);

        if ($rangeStart > $rangeEnd) {
            throw new \InvalidArgumentException(gettext('Range start must be before range end'));
        }

        $startTime = $data['startTime'] ?? '09:00';
        $endTime = $data['endTime'] ?? '10:00';
        $recurType = $data['recurType'] ?? 'weekly';
        $title = $data['title'];
        $desc = $data['desc'] ?? '';
        $text = $data['text'] ?? '';
        $inactive = (int) ($data['inactive'] ?? 0);
        $linkedGroupId = (int) ($data['linkedGroupId'] ?? 0);

        $calendars = null;
        if (!empty($data['pinnedCalendars'])) {
            $calendars = CalendarQuery::create()
                ->filterById($data['pinnedCalendars'], Criteria::IN)
                ->find();
        }

        $occurrenceDates = $this->generateOccurrenceDates(
            $recurType,
            $data['recurDOW'] ?? null,
            isset($data['recurDOM']) ? (int) $data['recurDOM'] : null,
            $data['recurDOY'] ?? null,
            $rangeStart,
            $rangeEnd
        );

        $createdIds = [];
        foreach ($occurrenceDates as $occurrenceDate) {
            $eventStart = $occurrenceDate->format('Y-m-d') . ' ' . $startTime . ':00';
            $eventEnd = $occurrenceDate->format('Y-m-d') . ' ' . $endTime . ':00';

            $event = new Event();
            $event->setTitle($title);
            $event->setEventType($type);
            $event->setDesc($desc);
            $event->setText($text);
            $event->setStart($eventStart);
            $event->setEnd($eventEnd);
            $event->setInActive($inactive);

            if ($calendars !== null) {
                $event->setCalendars($calendars);
            }

            $event->save();
            $event->reload();
            $eventId = $event->getId();

            if ($linkedGroupId > 0) {
                $audience = new EventAudience();
                $audience->setEventId($eventId);
                $audience->setGroupId($linkedGroupId);
                $audience->save();
            }

            $createdIds[] = $eventId;
        }

        return $createdIds;
    }

    /**
     * Generate all occurrence dates within a range based on recurrence settings.
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
        switch ($recurType) {
            case 'weekly':
                return $this->generateWeeklyDates($dow ?? 'Sunday', $rangeStart, $rangeEnd);
            case 'monthly':
                return $this->generateMonthlyDates($dom ?? 1, $rangeStart, $rangeEnd);
            case 'yearly':
                return $this->generateYearlyDates($doy ?? '01-01', $rangeStart, $rangeEnd);
            default:
                return [];
        }
    }

    /**
     * Generate weekly occurrence dates within a range.
     *
     * The first occurrence is the first matching day-of-week on or after $rangeStart.
     * Subsequent occurrences are every 7 days thereafter.
     *
     * @param string    $dow        Day name, e.g. "Sunday"
     * @param \DateTime $rangeStart Inclusive start
     * @param \DateTime $rangeEnd   Inclusive end
     *
     * @return \DateTime[]
     */
    private function generateWeeklyDates(string $dow, \DateTime $rangeStart, \DateTime $rangeEnd): array
    {
        $dates = [];
        $current = clone $rangeStart;
        $current->setTime(0, 0, 0);

        $targetDay = strtolower($dow);
        $currentDay = strtolower($current->format('l'));

        if ($currentDay !== $targetDay) {
            $current->modify('next ' . $targetDay);
        }

        while ($current <= $rangeEnd) {
            $dates[] = clone $current;
            $current->modify('+1 week');
        }

        return $dates;
    }

    /**
     * Generate monthly occurrence dates within a range.
     *
     * One event per month, on the specified day of month (clamped to the last
     * valid day when the month is shorter than $dom).
     *
     * @param int       $dom        Day of month (1–31)
     * @param \DateTime $rangeStart Inclusive start
     * @param \DateTime $rangeEnd   Inclusive end
     *
     * @return \DateTime[]
     */
    private function generateMonthlyDates(int $dom, \DateTime $rangeStart, \DateTime $rangeEnd): array
    {
        $dates = [];
        $current = clone $rangeStart;
        $current->setDate((int) $current->format('Y'), (int) $current->format('m'), 1);
        $current->setTime(0, 0, 0);

        while ($current <= $rangeEnd) {
            $year = (int) $current->format('Y');
            $month = (int) $current->format('m');
            $daysInMonth = DateTimeUtils::getDaysInMonth($month, $year);
            $actualDay = min($dom, $daysInMonth);

            $occurrence = new \DateTime(sprintf('%04d-%02d-%02d', $year, $month, $actualDay));
            $occurrence->setTime(0, 0, 0);

            if ($occurrence >= $rangeStart && $occurrence <= $rangeEnd) {
                $dates[] = $occurrence;
            }

            $current->modify('+1 month');
        }

        return $dates;
    }

    /**
     * Generate yearly occurrence dates within a range.
     *
     * One event per year, on the specified month-day (e.g. "04-12" for April 12).
     * Dates that don't exist in a given year (e.g. Feb 29 in a non-leap year) are
     * silently skipped.
     *
     * @param string    $doy        Month-day string in MM-DD format
     * @param \DateTime $rangeStart Inclusive start
     * @param \DateTime $rangeEnd   Inclusive end
     *
     * @return \DateTime[]
     *
     * @throws \InvalidArgumentException if $doy is not in MM-DD format
     */
    private function generateYearlyDates(string $doy, \DateTime $rangeStart, \DateTime $rangeEnd): array
    {
        $dates = [];
        $parts = explode('-', $doy);
        if (count($parts) !== 2 || !is_numeric($parts[0]) || !is_numeric($parts[1])) {
            throw new \InvalidArgumentException(
                gettext('Invalid yearly recurrence format; expected MM-DD (e.g. 04-12)')
            );
        }

        $month = (int) $parts[0];
        $day = (int) $parts[1];

        if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
            throw new \InvalidArgumentException(
                gettext('Invalid yearly recurrence format; expected MM-DD (e.g. 04-12)')
            );
        }

        $startYear = (int) $rangeStart->format('Y');
        $endYear = (int) $rangeEnd->format('Y');

        for ($year = $startYear; $year <= $endYear; $year++) {
            // Skip dates that don't exist in this year (e.g. Feb 29 in a non-leap year)
            if (!checkdate($month, $day, $year)) {
                continue;
            }

            $occurrence = new \DateTime(sprintf('%04d-%02d-%02d', $year, $month, $day));
            $occurrence->setTime(0, 0, 0);

            if ($occurrence >= $rangeStart && $occurrence <= $rangeEnd) {
                $dates[] = $occurrence;
            }
        }

        return $dates;
    }
}
