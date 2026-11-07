<?php

namespace ChurchCRM\Plugins\Holidays\Provider;

use DateTime;
use DateTimeZone;
use Yasumi\Holiday;
use Yasumi\Provider\AbstractProvider;
use Yasumi\Provider\CommonHolidays;

/**
 * Provider for public holidays in the Democratic Republic of the Congo.
 */
class DRCongo extends AbstractProvider
{
    use CommonHolidays;

    /**
     * Initialize all fixed and movable public holidays for DRCongo.
     *
     * @return void
     */
    public function initialize(): void
    {
        $this->timezone = 'Africa/Kinshasa';
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
            'martyrsDay',
            ['en' => "Martyrs of Independence Day"],
            new DateTime("{$this->year}-01-04", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'kabilaAssassinationDay',
            ['en' => "Laurent-Desire Kabila Assassination Day"],
            new DateTime("{$this->year}-01-16", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'lumumbaAssassinationDay',
            ['en' => "Patrice Lumumba Assassination Day"],
            new DateTime("{$this->year}-01-17", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'kimbanguDay',
            ['en' => "Simon Kimbangu and African Consciousness Day"],
            new DateTime("{$this->year}-04-06", $tz),
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
            'liberationDay',
            ['en' => "Liberation Day"],
            new DateTime("{$this->year}-05-17", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'independenceDay',
            ['en' => "Independence Day"],
            new DateTime("{$this->year}-06-30", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'parentsDay',
            ['en' => "Parents' Day"],
            new DateTime("{$this->year}-08-01", $tz),
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

    }

}
