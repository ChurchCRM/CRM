<?php

namespace ChurchCRM\Utils;

use ChurchCRM\dto\SystemConfig;

/**
 * A month/day (and optional start year) that recurs every year: birthdays,
 * wedding, death and membership anniversaries.
 *
 * "Today" is the church's configured timezone. The February 29 rule
 * (`sFeb29Observance`) is read in occurrenceInYear() and nowhere else.
 * Recorded dates are never changed; only the day an occurrence falls on.
 */
final class AnnualDate
{
    public const FEB28 = 'feb28';
    public const MAR1 = 'mar1';
    public const LEAP = 'leap';

    /** Years searched in each direction for an occurrence (a Feb 29 under `leap` recurs every 4 years, 8 across a century skip). */
    private const SEARCH_YEARS = 8;

    private ?int $month;
    private ?int $day;

    public function __construct(?int $month, ?int $day, private readonly ?int $year = null)
    {
        $valid = $month !== null && $day !== null && $month >= 1 && $month <= 12 && $day >= 1 && $day <= self::daysInMonth($month, 2000);
        $this->month = $valid ? $month : null;
        $this->day = $valid ? $day : null;
    }

    public static function fromDate(?\DateTimeInterface $date): self
    {
        if ($date === null) {
            return new self(null, null);
        }

        return new self((int) $date->format('n'), (int) $date->format('j'), (int) $date->format('Y'));
    }

    public function hasMonthDay(): bool
    {
        return $this->month !== null;
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public static function observance(): string
    {
        $value = (string) SystemConfig::getValue('sFeb29Observance');

        return in_array($value, [self::FEB28, self::MAR1, self::LEAP], true) ? $value : self::MAR1;
    }

    /**
     * The day this date is observed in $year, at midnight in the configured
     * timezone; null when it is not observed that year (February 29 under `leap`).
     */
    public function occurrenceInYear(int $year): ?\DateTimeImmutable
    {
        if ($this->month === null || $this->day === null) {
            return null;
        }

        $month = $this->month;
        $day = $this->day;
        if ($month === 2 && $day === 29 && self::daysInMonth(2, $year) === 28) {
            switch (self::observance()) {
                case self::LEAP:
                    return null;
                case self::FEB28:
                    $day = 28;
                    break;
                default:
                    $month = 3;
                    $day = 1;
            }
        }

        $occurrence = \DateTimeImmutable::createFromFormat('!Y-n-j', "$year-$month-$day", DateTimeUtils::getConfiguredTimezone());

        return $occurrence === false ? null : $occurrence;
    }

    public function isToday(?\DateTimeInterface $today = null): bool
    {
        return $this->daysUntil($today) === 0;
    }

    /**
     * Signed days to the nearest observed occurrence: 0 today, positive upcoming,
     * negative recent. An occurrence in the previous or next year counts when it
     * is nearer (a 3 January date is 3 days ahead on 31 December); on a tie the
     * upcoming one wins. Null when there is no month/day.
     */
    public function daysUntil(?\DateTimeInterface $today = null): ?int
    {
        if (!$this->hasMonthDay()) {
            return null;
        }

        $today ??= DateTimeUtils::getStartOfToday();
        $todayNumber = self::dayNumber($today);
        $currentYear = (int) $today->format('Y');

        $upcoming = null;
        for ($year = $currentYear; $year <= $currentYear + self::SEARCH_YEARS; $year++) {
            $occurrence = $this->occurrenceInYear($year);
            if ($occurrence !== null && self::dayNumber($occurrence) >= $todayNumber) {
                $upcoming = self::dayNumber($occurrence) - $todayNumber;
                break;
            }
        }

        $recent = null;
        for ($year = $currentYear; $year >= $currentYear - self::SEARCH_YEARS; $year--) {
            $occurrence = $this->occurrenceInYear($year);
            if ($occurrence !== null && self::dayNumber($occurrence) < $todayNumber) {
                $recent = self::dayNumber($occurrence) - $todayNumber;
                break;
            }
        }

        if ($upcoming === null) {
            return $recent;
        }
        if ($recent === null) {
            return $upcoming;
        }

        return $upcoming <= -$recent ? $upcoming : $recent;
    }

    /** Within $days before or after today, inclusive. */
    public function isWithinWindow(int $days, ?\DateTimeInterface $today = null): bool
    {
        $until = $this->daysUntil($today);

        return $until !== null && abs($until) <= $days;
    }

    /**
     * Years completed on the observed occurrence in $year (occurrence year minus
     * start year), for "turning N" and anniversary labels. Independent of
     * completed-years age. Null without a start year or when not observed that year.
     */
    public function yearsOnOccurrence(int $year): ?int
    {
        $occurrence = $this->occurrenceInYear($year);
        if ($occurrence === null || $this->year === null || $this->year <= 0) {
            return null;
        }

        return (int) $occurrence->format('Y') - $this->year;
    }

    /**
     * Recorded [month, day] pairs observed on $date, for query helpers: the date's
     * own month/day, plus 29 February when that date is where February 29 lands.
     *
     * @return list<array{0: int, 1: int}>
     */
    public static function recordedMonthDaysObservedOn(\DateTimeInterface $date): array
    {
        $pairs = [[(int) $date->format('n'), (int) $date->format('j')]];

        $feb29 = new self(2, 29);
        $observed = $feb29->occurrenceInYear((int) $date->format('Y'));
        if ($observed !== null && self::dayNumber($observed) === self::dayNumber($date) && $pairs[0] !== [2, 29]) {
            $pairs[] = [2, 29];
        }

        return $pairs;
    }

    /**
     * Recorded [month, day] pairs observed on any day from $from to $to inclusive.
     *
     * @return list<array{0: int, 1: int}>
     */
    public static function recordedMonthDaysObservedBetween(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $pairs = [];
        for ($number = self::dayNumber($from); $number <= self::dayNumber($to); $number++) {
            $day = (new \DateTimeImmutable('@' . ($number * 86400)))->setTimezone(new \DateTimeZone('UTC'));
            foreach (self::recordedMonthDaysObservedOn($day) as $pair) {
                $pairs[$pair[0] * 100 + $pair[1]] = $pair;
            }
        }

        return array_values($pairs);
    }

    /** The calendar days $days before and after today, as [from, to] in the configured timezone. */
    public static function windowAroundToday(int $days, ?\DateTimeInterface $today = null): array
    {
        $start = \DateTimeImmutable::createFromInterface($today ?? DateTimeUtils::getStartOfToday())->setTime(0, 0);

        return [$start->modify("-$days days"), $start->modify("+$days days")];
    }

    private static function daysInMonth(int $month, int $year): int
    {
        return (int) (new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month), new \DateTimeZone('UTC')))->format('t');
    }

    /** Whole days since the Unix epoch of the calendar date, ignoring time of day and zone. */
    private static function dayNumber(\DateTimeInterface $date): int
    {
        $utc = \DateTimeImmutable::createFromFormat('!Y-n-j', $date->format('Y-n-j'), new \DateTimeZone('UTC'));

        return intdiv($utc->getTimestamp(), 86400);
    }
}
