<?php

namespace ChurchCRM\Utils;

use ChurchCRM\dto\SystemConfig;

/**
 * The tax year donors file for. It starts on the configured month and day, whatever the
 * church's fiscal year is. A tax year is named by the calendar year it ends in; starting on
 * 1 January it is that calendar year.
 */
class TaxYearUtils
{
    public static function startMonth(): int
    {
        $month = SystemConfig::getIntValue('iTaxYearStartMonth');

        return $month >= 1 && $month <= 12 ? $month : 1;
    }

    public static function startDay(): int
    {
        $day = SystemConfig::getIntValue('iTaxYearStartDay');

        return $day >= 1 && $day <= 28 ? $day : 1;
    }

    /** The calendar year in which the tax year containing today ends. */
    public static function currentEndYear(): int
    {
        return self::endYearForDate(DateTimeUtils::getTodayDate());
    }

    /** The calendar year in which the tax year containing the date ends. */
    public static function endYearForDate(string $date): int
    {
        $parts = date_parse($date);
        if ($parts['error_count'] > 0 || !is_int($parts['year']) || !is_int($parts['month']) || !is_int($parts['day'])) {
            return self::endYearForDate(DateTimeUtils::getTodayDate());
        }
        if (self::startsOnNewYear()) {
            return $parts['year'];
        }
        $onOrAfterStart = $parts['month'] > self::startMonth()
            || ($parts['month'] === self::startMonth() && $parts['day'] >= self::startDay());

        return $onOrAfterStart ? $parts['year'] + 1 : $parts['year'];
    }

    /**
     * @return array{startDate: string, endDate: string, label: string}
     */
    public static function datesForEndYear(int $endYear): array
    {
        if (self::startsOnNewYear()) {
            return ['startDate' => $endYear . '-01-01', 'endDate' => $endYear . '-12-31', 'label' => (string) $endYear];
        }

        $nextStart = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $endYear, self::startMonth(), self::startDay()));

        return [
            'startDate' => sprintf('%04d-%02d-%02d', $endYear - 1, self::startMonth(), self::startDay()),
            'endDate' => $nextStart->modify('-1 day')->format('Y-m-d'),
            'label' => ($endYear - 1) . '/' . mb_substr((string) $endYear, 2, 2),
        ];
    }

    /** A period in the church's date format: "<start> through <end>", or one date when they match. */
    public static function describePeriod(string $dateStart, string $dateEnd): string
    {
        $start = DateTimeUtils::formatDate($dateStart);

        return $dateStart === $dateEnd ? $start : sprintf(gettext('%s through %s'), $start, DateTimeUtils::formatDate($dateEnd));
    }

    /** The dates a tax year covers, for telling a donor which gifts a statement includes. */
    public static function periodText(int $endYear): string
    {
        $year = self::datesForEndYear($endYear);

        return self::describePeriod($year['startDate'], $year['endDate']);
    }

    /** A tax year as a list shows it: its name, with its dates when it is not the calendar year. */
    public static function yearLabel(int $endYear): string
    {
        $label = self::datesForEndYear($endYear)['label'];

        return self::startsOnNewYear() ? $label : sprintf('%s (%s)', $label, self::periodText($endYear));
    }

    private static function startsOnNewYear(): bool
    {
        return self::startMonth() === 1 && self::startDay() === 1;
    }
}
