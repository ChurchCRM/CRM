<?php

namespace ChurchCRM\Service;

use ChurchCRM\Utils\DateTimeUtils;

/**
 * Event-free recurrence date math.
 *
 * Produces the set of occurrence dates described by a recurrence pattern
 * (weekly / monthly / yearly) inside an inclusive date range. It knows nothing
 * about events, calendars or the database, so any module that needs "the dates
 * this thing repeats on" can reuse it instead of growing another copy of the
 * same switch statement.
 *
 * This is the single implementation of that math in the codebase — see
 * EventService, which is the only event-aware caller.
 */
class RecurrenceDateGenerator
{
    public const RECUR_WEEKLY = 'weekly';
    public const RECUR_MONTHLY = 'monthly';
    public const RECUR_YEARLY = 'yearly';

    /**
     * Recurrence types this generator understands.
     */
    public const SUPPORTED_RECUR_TYPES = [
        self::RECUR_WEEKLY,
        self::RECUR_MONTHLY,
        self::RECUR_YEARLY,
    ];

    /**
     * Generate all occurrence dates within a range based on recurrence settings.
     *
     * Every returned DateTime is anchored at midnight — callers apply their own
     * time-of-day.
     *
     * @param string      $recurType  One of: weekly, monthly, yearly
     * @param string|null $dow        Day of week name (e.g. "Sunday") — used when $recurType is 'weekly'
     * @param int|null    $dom        Day of month (1–31) — used when $recurType is 'monthly'
     * @param string|null $doy        Month-day string in MM-DD format (e.g. "04-12") — used when $recurType is 'yearly'
     * @param \DateTime   $rangeStart Inclusive start of the date range
     * @param \DateTime   $rangeEnd   Inclusive end of the date range
     *
     * @return \DateTime[] Occurrence dates in ascending order; empty for an unknown $recurType
     *
     * @throws \InvalidArgumentException if $doy is not in MM-DD format
     */
    public function generate(
        string $recurType,
        ?string $dow,
        ?int $dom,
        ?string $doy,
        \DateTime $rangeStart,
        \DateTime $rangeEnd
    ): array {
        return match ($recurType) {
            self::RECUR_WEEKLY => $this->generateWeeklyDates($dow ?? 'Sunday', $rangeStart, $rangeEnd),
            self::RECUR_MONTHLY => $this->generateMonthlyDates($dom ?? 1, $rangeStart, $rangeEnd),
            self::RECUR_YEARLY => $this->generateYearlyDates($doy ?? '01-01', $rangeStart, $rangeEnd),
            default => [],
        };
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

        $targetDay = strtolower(trim($dow));
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
     * silently skipped rather than rolling over into the next month.
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
