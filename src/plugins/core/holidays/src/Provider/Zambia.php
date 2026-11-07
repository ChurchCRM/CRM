<?php

namespace ChurchCRM\Plugins\Holidays\Provider;

use DateTime;
use DateTimeZone;
use Yasumi\Holiday;
use Yasumi\Provider\AbstractProvider;
use Yasumi\Provider\CommonHolidays;
use Yasumi\Provider\ChristianHolidays;

/**
 * Provider for public holidays in Zambia.
 */
class Zambia extends AbstractProvider
{
    use CommonHolidays;
    use ChristianHolidays;

    /**
     * Initialize all fixed and movable public holidays for Zambia.
     *
     * @return void
     */
    public function initialize(): void
    {
        $this->timezone = 'Africa/Lusaka';
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
            'womenDay',
            ['en' => "International Women's Day"],
            new DateTime("{$this->year}-03-08", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'youthDay',
            ['en' => "Youth Day"],
            new DateTime("{$this->year}-03-12", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'labourDay',
            ['en' => "Labour Day"],
            new DateTime("{$this->year}-05-01", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'africaFreedomDay',
            ['en' => "Africa Freedom Day"],
            new DateTime("{$this->year}-05-25", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'prayerDay',
            ['en' => "National Day of Prayer"],
            new DateTime("{$this->year}-10-18", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'independenceDay',
            ['en' => "Independence Day"],
            new DateTime("{$this->year}-10-24", $tz),
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

        // Movable Christian Holidays
        $this->addHoliday($this->goodFriday($this->year, $this->timezone, $this->locale));
        $this->addHoliday($this->easterMonday($this->year, $this->timezone, $this->locale));

        // Dynamic / Rule-based Public Holidays
        $this->addHoliday(new Holiday(
            'heroesDay',
            ['en' => "Heroes' Day"],
            new DateTime("first Monday of July {$this->year}", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'unityDay',
            ['en' => "Unity Day"],
            new DateTime("first Tuesday of July {$this->year}", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'farmersDay',
            ['en' => "Farmers' Day"],
            new DateTime("first Monday of August {$this->year}", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

    }

}
