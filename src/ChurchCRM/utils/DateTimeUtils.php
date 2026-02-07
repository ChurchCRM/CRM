<?php

namespace ChurchCRM\Utils;

use ChurchCRM\dto\SystemConfig;

/**
 * Utility class for timezone-aware date/time operations.
 *
 * All methods use the configured sTimeZone system setting to ensure
 * consistent date calculations across the application.
 */
class DateTimeUtils
{
    /**
     * Get the configured timezone for the church.
     *
     * @return \DateTimeZone The timezone configured in sTimeZone system setting
     */
    public static function getConfiguredTimezone(): \DateTimeZone
    {
        return new \DateTimeZone(SystemConfig::getValue('sTimeZone'));
    }

    /**
     * Get today's date in the configured timezone.
     *
     * Use this instead of `new \DateTime()` to ensure "today" is calculated
     * correctly for the church's timezone, not the server's default timezone.
     *
     * @return \DateTime Today's date/time in the configured timezone
     */
    public static function getToday(): \DateTime
    {
        return new \DateTime('now', self::getConfiguredTimezone());
    }

    /**
     * Create a DateTime object for a specific date in the configured timezone.
     *
     * @param string $dateString A date string (e.g., "2026-02-07", "next monday")
     *
     * @return \DateTime The DateTime object in the configured timezone
     */
    public static function createDateTime(string $dateString): \DateTime
    {
        return new \DateTime($dateString, self::getConfiguredTimezone());
    }

    /**
     * Get the current year in the configured timezone.
     *
     * @return int The current year (e.g., 2026)
     */
    public static function getCurrentYear(): int
    {
        return (int) self::getToday()->format('Y');
    }

    /**
     * Get the current month in the configured timezone.
     *
     * @return int The current month (1-12)
     */
    public static function getCurrentMonth(): int
    {
        return (int) self::getToday()->format('m');
    }

    /**
     * Get the current day of month in the configured timezone.
     *
     * @return int The current day (1-31)
     */
    public static function getCurrentDay(): int
    {
        return (int) self::getToday()->format('d');
    }

    /**
     * Get the current date formatted as Y-m-d in the configured timezone.
     *
     * Use this instead of `date('Y-m-d')` to ensure the correct date
     * for the church's configured timezone.
     *
     * @return string Today's date in Y-m-d format
     */
    public static function getTodayDate(): string
    {
        return self::getToday()->format('Y-m-d');
    }

    /**
     * Get the current datetime formatted as Y-m-d H:i:s in the configured timezone.
     *
     * Use this instead of `date('Y-m-d H:i:s')` for timestamps that should
     * reflect the church's local time.
     *
     * @return string Current datetime in Y-m-d H:i:s format
     */
    public static function getNowDateTime(): string
    {
        return self::getToday()->format('Y-m-d H:i:s');
    }

    /**
     * Format a date using strtotime relative to the configured timezone.
     *
     * Use this instead of `date('Y-m-d', strtotime($modifier))` to ensure
     * timezone-aware date calculations.
     *
     * @param string $modifier A strtotime modifier (e.g., "+1 week", "last monday")
     * @param string $format   The output format (default: Y-m-d)
     *
     * @return string The formatted date string
     */
    public static function getRelativeDate(string $modifier, string $format = 'Y-m-d'): string
    {
        $date = self::getToday();
        $date->modify($modifier);

        return $date->format($format);
    }

    /**
     * Format a date relative to a base date in the configured timezone.
     *
     * Use this for calculations like "next occurrence of this event".
     *
     * @param string $baseDate The base date string (Y-m-d format)
     * @param string $modifier A strtotime modifier (e.g., "+1 week")
     * @param string $format   The output format (default: Y-m-d)
     *
     * @return string The formatted date string
     */
    public static function getDateRelativeTo(string $baseDate, string $modifier, string $format = 'Y-m-d'): string
    {
        $date = self::createDateTime($baseDate);
        $date->modify($modifier);

        return $date->format($format);
    }

    /**
     * Create a date from year, month, day components in the configured timezone.
     *
     * Use this instead of `mktime()` to ensure timezone-aware date construction.
     *
     * @param int    $year   The year
     * @param int    $month  The month (1-12)
     * @param int    $day    The day of month (1-31)
     * @param string $format The output format (default: Y-m-d)
     *
     * @return string The formatted date string
     */
    public static function formatDateFromComponents(int $year, int $month, int $day, string $format = 'Y-m-d'): string
    {
        $date = self::createDateTime(sprintf('%04d-%02d-%02d', $year, $month, $day));

        return $date->format($format);
    }
}
