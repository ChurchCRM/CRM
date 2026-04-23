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
     * Get the current date and time ("now") in the configured timezone.
     *
     * Use this instead of `new \DateTime()` to ensure the current moment is
     * calculated correctly for the church's timezone, not the server's
     * default timezone.
     *
     * Note: This returns a timestamp with the current time. For midnight at
     * the start of today, use getStartOfToday().
     *
     * @return \DateTime Current date/time in the configured timezone
     */
    public static function getToday(): \DateTime
    {
        return new \DateTime('now', self::getConfiguredTimezone());
    }

    /**
     * Get a DateTime representing the start of today (midnight) in the
     * configured timezone.
     *
     * This is useful for date-only comparisons where the time of day should
     * be normalized to 00:00:00.
     *
     * @return \DateTime Today's date at midnight in the configured timezone
     */
    public static function getStartOfToday(): \DateTime
    {
        return new \DateTime('today', self::getConfiguredTimezone());
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

    /**
     * Return the number of days in a given month/year.
     *
     * Use this instead of `cal_days_in_month()` — the PHP calendar extension
     * is not enabled in all environments (notably the Docker test image).
     *
     * @param int $month The month (1-12)
     * @param int $year  The year (e.g., 2026)
     *
     * @return int Number of days in the month (28-31)
     */
    public static function getDaysInMonth(int $month, int $year): int
    {
        return (int) date('t', mktime(0, 0, 0, $month, 1, $year));
    }

    /**
     * Returns the system datetime format converted to Moment.js syntax,
     * JSON-encoded and ready for embedding in a JavaScript literal.
     *
     * Example output: "MM/DD/YYYY h:mm a"
     */
    public static function getDateTimeFormatForJs(): string
    {
        static $phpToMoment = [
            'd' => 'DD',   'D' => 'ddd',  'j' => 'D',    'l' => 'dddd',
            'N' => 'E',    'S' => 'o',    'w' => 'e',    'z' => 'DDD',
            'W' => 'W',    'F' => 'MMMM', 'm' => 'MM',   'M' => 'MMM',
            'n' => 'M',    't' => '',     'L' => '',     'o' => 'YYYY',
            'Y' => 'YYYY', 'y' => 'YY',   'a' => 'a',    'A' => 'A',
            'B' => '',     'g' => 'h',    'G' => 'H',    'h' => 'hh',
            'H' => 'HH',   'i' => 'mm',   's' => 'ss',   'u' => 'SSS',
            'I' => '',     'O' => '',     'P' => '',     'T' => '',
            'Z' => '',     'c' => '',     'r' => '',     'U' => 'X',
        ];

        return json_encode(strtr(SystemConfig::getValue('sDateTimeFormat'), $phpToMoment), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
    }

    /**
     * Converts a date string to the system-configured date picker format.
     * Migrated from change_date_for_place_holder() in Functions.php.
     */
    public static function formatForDatePicker(?string $string = null): string
    {
        $string ??= '';
        $timestamp = strtotime($string);

        if ($timestamp !== false) {
            return date(SystemConfig::getValue("sDatePickerFormat"), $timestamp);
        }

        return '';
    }

    /**
     * Formats a MySQL DateTime or DateTime object for display using the system date format.
     * Migrated from FormatDate() in Functions.php.
     */
    public static function formatDate($dDate, bool $bWithTime = false): string
    {
        if ($dDate === '' || $dDate === '0000-00-00 00:00:00' || $dDate === '0000-00-00' || $dDate === null) {
            return '';
        }

        try {
            if ($dDate instanceof \DateTime || $dDate instanceof \DateTimeInterface) {
                $dateObj = $dDate;
            } else {
                $dateObj = new \DateTime($dDate);
            }
        } catch (\Exception $e) {
            return '';
        }

        $dateFormat = SystemConfig::getValue("sDateFormatLong");
        $dateFormat = str_replace("d", "d", $dateFormat);
        $dateFormat = str_replace("m", "F", $dateFormat);
        $dateFormat = str_replace("Y", "Y", $dateFormat);
        $dateFormat = str_replace("/", " ", $dateFormat);
        $dateFormat = str_replace("-", " ", $dateFormat);

        if ($bWithTime) {
            return $dateObj->format($dateFormat . ' g:i A');
        }

        return $dateObj->format($dateFormat);
    }

    /**
     * Assembles and validates year/month/day components into YYYY-MM-DD format.
     * Helper for parseAndValidate(). Migrated from assembleYearMonthDay() in Functions.php.
     */
    private static function assembleYearMonthDay($sYear, $sMonth, $sDay, $pasfut = 'future')
    {
        if (strlen($sYear) === 2) {
            $thisYear = date('Y');
            $twoDigit = mb_substr($thisYear, 2, 2);
            if ($sYear == $twoDigit) {
                $sYear = mb_substr($thisYear, 0, 4);
            } elseif ($pasfut == 'future') {
                if ($sYear > $twoDigit) {
                    $sYear = mb_substr($thisYear, 0, 2) . $sYear;
                } else {
                    $sNextCentury = $thisYear + 100;
                    $sYear = mb_substr($sNextCentury, 0, 2) . $sYear;
                }
            } else {
                if ($sYear < $twoDigit) {
                    $sYear = mb_substr($thisYear, 0, 2) . $sYear;
                } else {
                    $sLastCentury = $thisYear - 100;
                    $sYear = mb_substr($sLastCentury, 0, 2) . $sYear;
                }
            }
        }
        if (strlen($sYear) !== 4) {
            return false;
        }

        if (strlen($sMonth) === 1) {
            $sMonth = '0' . $sMonth;
        }
        if (strlen($sMonth) !== 2) {
            return false;
        }

        if (strlen($sDay) === 1) {
            $sDay = '0' . $sDay;
        }
        if (strlen($sDay) !== 2) {
            return false;
        }

        $sScanString = $sYear . '-' . $sMonth . '-' . $sDay;
        [$iYear, $iMonth, $iDay] = sscanf($sScanString, '%04d-%02d-%02d');

        if (checkdate($iMonth, $iDay, $iYear)) {
            return $sScanString;
        } else {
            return false;
        }
    }

    /**
     * Parse a partial date in the formats CSV imports commonly produce:
     * YYYY-MM-DD, YYYY-M-D, M/D/YYYY, M-D-YYYY, M/D/YY, M-D-YY, M/D, M-D.
     * Year may be omitted (bare "7/4") or zero ("0000-07-04") — in which case
     * the returned `year` is null.
     *
     * Unlike `parseAndValidate()`, this is lenient about missing years and is
     * the shared source of truth for CSV importers, which must handle both
     * month-day-only values (valid for Person.BirthYear) and full dates
     * (required for SQL DATE custom-field columns) with the same rules. The
     * previous behaviour called `strtotime()` directly, which silently
     * assigned the current year to "7/4" and corrupted user data.
     *
     * Returns null when the input can't be interpreted as month+day.
     *
     * @return array{month:int, day:int, year:?int}|null
     */
    public static function parsePartialDate(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        // Year-less inputs validate month/day against a known leap year so 2/29
        // round-trips correctly; year-bearing inputs validate against the actual year.
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $raw, $m)) {
            // YYYY-MM-DD or YYYY-M-D (e.g. 2001-07-04, 0000-07-04)
            $y     = (int) $m[1];
            $month = (int) $m[2];
            $day   = (int) $m[3];
            if (!checkdate($month, $day, $y > 0 ? $y : 2000)) {
                return null;
            }
            return ['month' => $month, 'day' => $day, 'year' => $y > 0 ? $y : null];
        }
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})$/', $raw, $m)) {
            // M/D/YYYY, M-D-YYYY, M/D/YY (e.g. 1/1/2025, 7-4-2001, 7/4/25, 7/4/00)
            $y = (int) $m[3];
            if (strlen($m[3]) === 2) {
                // Match PHP's strtotime rules: 00-69 => 2000-2069, 70-99 => 1970-1999
                $y += $y >= 70 ? 1900 : 2000;
            }
            $month = (int) $m[1];
            $day   = (int) $m[2];
            if (!checkdate($month, $day, $y > 0 ? $y : 2000)) {
                return null;
            }
            return ['month' => $month, 'day' => $day, 'year' => $y > 0 ? $y : null];
        }
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})$/', $raw, $m)) {
            // M/D or M-D (e.g. 1/1, 7/4, 7-4) — no year
            $month = (int) $m[1];
            $day   = (int) $m[2];
            if (!checkdate($month, $day, 2000)) {
                return null;
            }
            return ['month' => $month, 'day' => $day, 'year' => null];
        }
        // Last-resort fallback for month-name / ISO datetime / etc. The
        // structural patterns above cover the formats most CSV exporters
        // emit; strtotime is only used when none of them matched.
        $ts = strtotime($raw);
        if ($ts === false) {
            return null;
        }
        return [
            'month' => (int) date('n', $ts),
            'day'   => (int) date('j', $ts),
            'year'  => (int) date('Y', $ts),
        ];
    }

    /**
     * Parses a human-entered date string into YYYY-MM-DD format.
     * Supports US (M/D/Y), European (D/M/Y), and ISO (Y-M-D) formats.
     * Migrated from parseAndValidateDate() in Functions.php.
     *
     * @return string|false YYYY-MM-DD on success, false on invalid input
     */
    public static function parseAndValidate($data, $locale = 'US', $pasfut = 'future')
    {
        if (mb_substr_count($data, '-') === 2) {
            $iFirstDelimiter = strpos($data, '-');
            $iSecondDelimiter = strpos($data, '-', $iFirstDelimiter + 1);

            $sYear = mb_substr($data, 0, $iFirstDelimiter);
            $sMonth = mb_substr($data, $iFirstDelimiter + 1, $iSecondDelimiter - $iFirstDelimiter - 1);
            $sDay = mb_substr($data, $iSecondDelimiter + 1);

            return self::assembleYearMonthDay($sYear, $sMonth, $sDay, $pasfut);
        } elseif ((mb_substr_count($data, '/') == 2) && ($locale == 'US')) {
            $iFirstDelimiter = strpos($data, '/');
            $iSecondDelimiter = strpos($data, '/', $iFirstDelimiter + 1);

            $sMonth = mb_substr($data, 0, $iFirstDelimiter);
            $sDay = mb_substr($data, $iFirstDelimiter + 1, $iSecondDelimiter - $iFirstDelimiter - 1);
            $sYear = mb_substr($data, $iSecondDelimiter + 1);

            return self::assembleYearMonthDay($sYear, $sMonth, $sDay, $pasfut);
        } elseif (mb_substr_count($data, '/') == 2) {
            $iFirstDelimiter = strpos($data, '/');
            $iSecondDelimiter = strpos($data, '/', $iFirstDelimiter + 1);

            $sDay = mb_substr($data, 0, $iFirstDelimiter);
            $sMonth = mb_substr($data, $iFirstDelimiter + 1, $iSecondDelimiter - $iFirstDelimiter - 1);
            $sYear = mb_substr($data, $iSecondDelimiter + 1);

            return self::assembleYearMonthDay($sYear, $sMonth, $sDay, $pasfut);
        }

        $timeStamp = strtotime($data);
        if ($timeStamp === false || $timeStamp <= 0) {
            return false;
        }

        $dateString = date('Y-m-d', $timeStamp);

        if (strlen($dateString) !== 10) {
            return false;
        }

        if ($dateString > '1970-01-01' && $dateString < '2038-01-19') {
            return $dateString;
        }

        return false;
    }
}
