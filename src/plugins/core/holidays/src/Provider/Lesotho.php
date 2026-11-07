<?php

namespace ChurchCRM\Plugins\Holidays\Provider;

use DateTime;
use DateTimeZone;
use Yasumi\Holiday;
use Yasumi\Provider\AbstractProvider;
use Yasumi\Provider\CommonHolidays;
use Yasumi\Provider\ChristianHolidays;

/**
 * Provider for public holidays in Lesotho.
 */
class Lesotho extends AbstractProvider
{
    use CommonHolidays;
    use ChristianHolidays;

    /**
     * Initialize all fixed and movable public holidays for Lesotho.
     *
     * @return void
     */
    public function initialize(): void
    {
        $this->timezone = 'Africa/Maseru';
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
            'moshoeshoeDay',
            ['en' => "Moshoeshoe Day"],
            new DateTime("{$this->year}-03-11", $tz),
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
            'africaHeroesDay',
            ['en' => "Africa Heroes Day"],
            new DateTime("{$this->year}-05-25", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'kingsBirthday',
            ['en' => "King Letsie III's Birthday"],
            new DateTime("{$this->year}-07-17", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'independenceDay',
            ['en' => "Independence Day"],
            new DateTime("{$this->year}-10-04", $tz),
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
            'boxingDay',
            ['en' => "Boxing Day"],
            new DateTime("{$this->year}-12-26", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        // Movable Christian Holidays
        $this->addHoliday($this->goodFriday($this->year, $this->timezone, $this->locale));
        $this->addHoliday($this->easterMonday($this->year, $this->timezone, $this->locale));
        $this->addHoliday($this->ascensionDay($this->year, $this->timezone, $this->locale));

    }

}
