<?php

namespace ChurchCRM\Plugins\Holidays;

use ChurchCRM\Bootstrapper;
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
 * Behavior vs. the legacy implementation:
 *  - Country is constructor-injected instead of read from sChurchCountry directly.
 *  - Honours the FullCalendar $start/$end range (the legacy version was hard-coded
 *    to the current year, so navigating to other years showed no holidays).
 *  - Optional category filter (official, observance, bank, seasonal, religious).
 *  - Stable per-country ID via crc32 so multiple holiday calendars can coexist.
 *  - Titles follow the request's ChurchCRM locale (see HolidayLocale for the
 *    English fallback). Event IDs use the holiday key, not its title, so they
 *    stay the same in every language.
 *  - A country Yasumi does not ship can come from src/Provider/{Country}.php.
 *    It is built through Yasumi::create(), so it gets Yasumi's shared
 *    translations, plus the plugin's own titles from locale/titles/.
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

    /** Yasumi locale for titles; resolved from the request on first use when null. */
    private ?string $locale;

    private ?HolidayTitleTranslations $titles;

    /**
     * @param string      $yasumiCountry Yasumi country class name (e.g. "USA", "Mexico")
     * @param string[]    $categories    Optional list of categories to include
     * @param string|null $locale        ChurchCRM locale for titles; defaults to the request's locale
     * @param HolidayTitleTranslations|null $titles Plugin titles; defaults to the plugin's locale/titles/
     */
    public function __construct(string $yasumiCountry, array $categories = [], ?string $locale = null, ?HolidayTitleTranslations $titles = null)
    {
        $this->yasumiCountry = $yasumiCountry;
        $this->categories = array_map('strtolower', $categories);
        $this->locale = $locale === null ? null : HolidayLocale::resolve($locale);
        $this->titles = $titles;
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

            $locale = $this->getLocale();
            $titles = $this->titles ??= HolidayTitleTranslations::forPlugin($locale);
            $providerClass = $this->resolveProviderClass();

            for ($year = $startYear; $year <= $endYear; $year++) {
                $holidays = Yasumi::create($providerClass, $year, $locale);

                foreach ($holidays->getHolidays() as $holiday) {
                    // Fills in locales that Yasumi's own translations lack.
                    $holiday->mergeGlobalTranslations($titles);
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

    /** The Yasumi locale this calendar's titles use. */
    public function getLocale(): string
    {
        return $this->locale ??= HolidayLocale::resolve(self::currentCrmLocale());
    }

    private static function currentCrmLocale(): string
    {
        try {
            return (string) Bootstrapper::getCurrentLocale()->getLocale();
        } catch (\Throwable $e) {
            return HolidayLocale::FALLBACK;
        }
    }

    /**
     * Yasumi's own provider when it has one, else the plugin's provider in
     * src/Provider/. Yasumi::create() accepts either class name.
     */
    private function resolveProviderClass(): string
    {
        $custom = __NAMESPACE__ . '\\Provider\\' . $this->yasumiCountry;
        if (
            preg_match('/^[A-Za-z]+$/', $this->yasumiCountry)
            && !class_exists('Yasumi\\Provider\\' . $this->yasumiCountry)
            && class_exists($custom)
        ) {
            return $custom;
        }

        return $this->yasumiCountry;
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
        // Compare calendar dates: the holiday's instant is midnight in the provider's timezone.
        $date = $holiday->format('Y-m-d');
        if ($start !== null && $date < $start->format('Y-m-d')) {
            return false;
        }
        // FullCalendar $end is exclusive (the day after the last visible day)
        if ($end !== null && $date >= $end->format('Y-m-d')) {
            return false;
        }
        return true;
    }

    private function yasumiHolidayToEvent(Holiday $holiday): Event
    {
        // The key ("christmasDay"), not the translated title, keeps the ID the same in every language.
        $id = (int) (crc32($this->yasumiCountry . '|' . $holiday->getKey() . '|' . $holiday->getTimestamp()) & 0x7fffffff);
        $event = new Event();
        $event->setId($id);
        $event->setEditable(false);
        $event->setTitle($holiday->getName());
        // A timestamp would be midnight in the provider's timezone, the evening before further west.
        $event->setStart($holiday->format('Y-m-d'));
        $event->setVirtualColumn('holidayCountry', $this->yasumiCountry);
        $event->setVirtualColumn('holidayType', $holiday->getType());

        return $event;
    }
}
