<?php

namespace ChurchCRM\Plugins\Holidays\Provider;

use DateTime;
use DateTimeZone;
use Yasumi\Holiday;
use Yasumi\Provider\AbstractProvider;
use Yasumi\Provider\CommonHolidays;
use Yasumi\Provider\ChristianHolidays;

/**
 * Provider for public holidays in Angola.
 */
class Angola extends AbstractProvider
{
    use CommonHolidays;
    use ChristianHolidays;

    /**
     * Initialize all fixed and movable public holidays for Angola.
     *
     * @return void
     */
    public function initialize(): void
    {
        $this->timezone = 'Africa/Luanda';
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
            'armedStruggleDay',
            ['en' => "Day of the Armed Struggle"],
            new DateTime("{$this->year}-02-04", $tz),
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
            'southernAfricaLiberationDay',
            ['en' => "Southern Africa Liberation Day"],
            new DateTime("{$this->year}-03-23", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'peaceDay',
            ['en' => "Peace Day"],
            new DateTime("{$this->year}-04-04", $tz),
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
            'nationalHeroesDay',
            ['en' => "National Heroes Day"],
            new DateTime("{$this->year}-09-17", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'allSoulsDay',
            ['en' => "All Souls' Day"],
            new DateTime("{$this->year}-11-02", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'independenceDay',
            ['en' => "Independence Day"],
            new DateTime("{$this->year}-11-11", $tz),
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

    }

}
