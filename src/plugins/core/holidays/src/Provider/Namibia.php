<?php

namespace ChurchCRM\Plugins\Holidays\Provider;

use DateTime;
use DateTimeZone;
use Yasumi\Holiday;
use Yasumi\Provider\AbstractProvider;
use Yasumi\Provider\CommonHolidays;
use Yasumi\Provider\ChristianHolidays;

/**
 * Provider for public holidays in Namibia.
 */
class Namibia extends AbstractProvider
{
    use CommonHolidays;
    use ChristianHolidays;

    /**
     * Initialize all fixed and movable public holidays for Namibia.
     *
     * @return void
     */
    public function initialize(): void
    {
        $this->timezone = 'Africa/Windhoek';
        $tz = new DateTimeZone($this->timezone);

        // Fixed Public Holidays
        $this->addHoliday(new Holiday(
            'newYearsDay',
            ['en' => "New Year's Day"],
            new DateTime("{$this->year}-01-01", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'independenceDay',
            ['en' => "Independence Day"],
            new DateTime("{$this->year}-03-21", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'workersDay',
            ['en' => "Workers' Day"],
            new DateTime("{$this->year}-05-01", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'cassingaDay',
            ['en' => "Cassinga Day"],
            new DateTime("{$this->year}-05-04", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'africaDay',
            ['en' => "Africa Day"],
            new DateTime("{$this->year}-05-25", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        // Genocide Remembrance Day: observed annually starting May 28, 2025
        if ($this->year >= 2025) {
            $this->addHoliday(new Holiday(
                'genocideRemembranceDay',
                ['en' => "Genocide Remembrance Day"],
                new DateTime("{$this->year}-05-28", $tz),
                $this->locale,
                Holiday::TYPE_OFFICIAL
            ));
        }

        $this->addHoliday(new Holiday(
            'heroesDay',
            ['en' => "Heroes' Day"],
            new DateTime("{$this->year}-08-26", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'humanRightsDay',
            ['en' => "Human Rights Day and Women's Day"],
            new DateTime("{$this->year}-12-10", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'christmasDay',
            ['en' => "Christmas Day"],
            new DateTime("{$this->year}-12-25", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'familyDay',
            ['en' => "Family Day"],
            new DateTime("{$this->year}-12-26", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        // Movable Christian Holidays
        $this->addHoliday($this->goodFriday($this->year, $this->timezone, $this->locale));
        $this->addHoliday($this->easterMonday($this->year, $this->timezone, $this->locale));
        $this->addHoliday($this->ascensionDay($this->year, $this->timezone, $this->locale));

    }

    /**
     * Get the sources used for Namibia public holidays.
     *
     * @return array<string> List of official source URLs.
     */
    public function getSources(): array
    {
        return [
            'https://mhaiss.gov.na/',
        ];
    }
}
