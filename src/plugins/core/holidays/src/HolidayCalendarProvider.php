<?php

namespace ChurchCRM\Plugins\Holidays;

use ChurchCRM\model\ChurchCRM\Event;
use ChurchCRM\SystemCalendars\SystemCalendar;
use ChurchCRM\Utils\DateTimeUtils;
use Propel\Runtime\Collection\ObjectCollection;
use Yasumi\Holiday;
use Yasumi\Yasumi;

/**
 * One holiday calendar (for a specific Yasumi country), instantiated by the
 * Holidays plugin. Replaces the old hard-wired ChurchCRM\SystemCalendars\HolidayCalendar.
 *
 * Behaviour vs. the legacy implementation:
 *  - Country is constructor-injected instead of read from sChurchCountry directly.
 *  - Honours the FullCalendar $start/$end range (the legacy version was hard-coded
 *    to the current year, so navigating to other years showed no holidays).
 *  - Optional category filter (official, observance, bank, seasonal, religious).
 *  - Stable per-country ID via crc32 so multiple holiday calendars can coexist.
 */
class HolidayCalendarProvider implements SystemCalendar
{
    /**
     * Reserve [10000, PHP_INT_MAX) for plugin-contributed system calendars so
     * we never collide with the small core IDs (0..9).
     */
    private const ID_OFFSET = 10000;

    /** Distinct pastel background colours, one per country (black text works on all). */
    private const CALENDAR_BG_COLORS = [
        '91CAF9', // blue
        '95E6B3', // green
        'FAC56E', // amber
        'D4A4EB', // purple
        'F4919C', // coral
        '69D5D5', // teal
        'F9E080', // yellow
        'F9AEDE', // pink
        'A8BAED', // indigo
        'B2D8A8', // sage
    ];

    private string $yasumiCountry;

    /** @var string[] Lowercase category names; empty means "all". */
    private array $categories;

    /**
     * @param string   $yasumiCountry Yasumi country class name (e.g. "USA", "Mexico")
     * @param string[] $categories    Optional list of categories to include
     */
    public function __construct(string $yasumiCountry, array $categories = [])
    {
        $this->yasumiCountry = $yasumiCountry;
        $this->categories = array_map('strtolower', $categories);
    }

    public static function isAvailable(): bool
    {
        // Availability is decided by the plugin itself when registering this provider.
        return true;
    }

    public function getAccessToken(): bool
    {
        return false;
    }

    public function getBackgroundColor(): string
    {
        $index = (crc32($this->yasumiCountry) & 0xffffffff) % count(self::CALENDAR_BG_COLORS);
        return self::CALENDAR_BG_COLORS[$index];
    }

    public function getForegroundColor(): string
    {
        return '000000';
    }

    public function getId(): int
    {
        // Stable, deterministic, unsigned ID derived from the country name.
        // Modulo keeps it inside a comfortable range; offset prevents collision
        // with built-in system calendar IDs (0..9).
        return self::ID_OFFSET + (int) (crc32($this->yasumiCountry) & 0xffffffff) % 1000000;
    }

    public function getName(): string
    {
        $display = preg_replace('/(?<=[a-z])(?=[A-Z])/', ' ', $this->yasumiCountry) ?? $this->yasumiCountry;
        return sprintf(gettext('Holidays (%s)'), $display);
    }

    public function getEvents(string $start, string $end): ObjectCollection
    {
        $events = new ObjectCollection();
        $events->setModel(Event::class);

        try {
            [$startYear, $endYear] = $this->resolveYearRange($start, $end);
            $startDate = !empty($start) ? new \DateTimeImmutable($start) : null;
            $endDate   = !empty($end)   ? new \DateTimeImmutable($end)   : null;

            for ($year = $startYear; $year <= $endYear; $year++) {
                $holidays = Yasumi::create($this->yasumiCountry, $year);

                foreach ($holidays->getHolidays() as $holiday) {
                    if (!$this->matchesCategory($holiday)) {
                        continue;
                    }
                    if (!$this->inDateRange($holiday, $startDate, $endDate)) {
                        continue;
                    }
                    $events->push($this->yasumiHolidayToEvent($holiday));
                }
            }
        } catch (\Throwable $e) {
            // Never break the calendar UI on a Yasumi failure.
        }

        return $events;
    }

    public function getEventById(int $_id): ObjectCollection
    {
        $emptySet = new ObjectCollection();
        $emptySet->setModel(Event::class);

        return $emptySet;
    }

    /**
     * @return array{0:int,1:int} [startYear, endYear]
     */
    private function resolveYearRange(string $start, string $end): array
    {
        $currentYear = DateTimeUtils::getCurrentYear();
        $startYear = $currentYear;
        $endYear = $currentYear;

        if (!empty($start)) {
            try {
                $startYear = (int) (new \DateTimeImmutable($start))->format('Y');
            } catch (\Throwable $e) {
                // ignore, keep default
            }
        }
        if (!empty($end)) {
            try {
                $endYear = (int) (new \DateTimeImmutable($end))->format('Y');
            } catch (\Throwable $e) {
                // ignore, keep default
            }
        }

        // Guard against pathological ranges
        if ($endYear < $startYear) {
            $endYear = $startYear;
        }
        if (($endYear - $startYear) > 5) {
            $endYear = $startYear + 5;
        }

        return [$startYear, $endYear];
    }

    private function matchesCategory(Holiday $holiday): bool
    {
        if (empty($this->categories)) {
            return true;
        }

        $type = strtolower($holiday->getType());
        return in_array($type, $this->categories, true);
    }

    private function inDateRange(Holiday $holiday, ?\DateTimeImmutable $start, ?\DateTimeImmutable $end): bool
    {
        if ($start === null && $end === null) {
            return true;
        }
        $ts = $holiday->getTimestamp();
        if ($start !== null && $ts < $start->setTime(0, 0, 0)->getTimestamp()) {
            return false;
        }
        // FullCalendar $end is exclusive (the day after the last visible day)
        if ($end !== null && $ts >= $end->setTime(0, 0, 0)->getTimestamp()) {
            return false;
        }
        return true;
    }

    private function yasumiHolidayToEvent(Holiday $holiday): Event
    {
        $id = (int) (crc32($this->yasumiCountry . '|' . $holiday->getName() . '|' . $holiday->getTimestamp()) & 0x7fffffff);
        $event = new Event();
        $event->setId($id);
        $event->setEditable(false);
        $event->setTitle($holiday->getName());
        $event->setStart($holiday->getTimestamp());
        $event->setVirtualColumn('holidayCountry', $this->yasumiCountry);
        $event->setVirtualColumn('holidayType', $holiday->getType());

        return $event;
    }
}
