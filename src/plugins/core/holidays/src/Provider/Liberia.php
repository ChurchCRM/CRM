<?php

namespace ChurchCRM\Plugins\Holidays\Provider;

use DateTime;
use DateTimeZone;
use Yasumi\Holiday;
use Yasumi\Provider\AbstractProvider;
use Yasumi\Provider\CommonHolidays;

/**
 * Provider for public holidays in Liberia.
 */
class Liberia extends AbstractProvider
{
    use CommonHolidays;

    /**
     * Initialize all fixed and movable public holidays for Liberia.
     *
     * @return void
     */
    public function initialize(): void
    {
        $this->timezone = 'Africa/Monrovia';
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
            'armedForcesDay',
            ['en' => "Armed Forces Day"],
            new DateTime("{$this->year}-02-11", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'robertsBirthday',
            ['en' => "Joseph Jenkins Roberts Birthday"],
            new DateTime("{$this->year}-03-15", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'unificationDay',
            ['en' => "National Unification Day"],
            new DateTime("{$this->year}-05-14", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'independenceDay',
            ['en' => "Independence Day"],
            new DateTime("{$this->year}-07-26", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'flagDay',
            ['en' => "National Flag Day"],
            new DateTime("{$this->year}-08-24", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'tubmanBirthday',
            ['en' => "William V.S. Tubman Birthday"],
            new DateTime("{$this->year}-11-29", $tz),
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

        // Dynamic / Rule-based Public Holidays
        $this->addHoliday(new Holiday(
            'decorationDay',
            ['en' => "Decoration Day"],
            new DateTime("second Wednesday of March {$this->year}", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'fastAndPrayerDay',
            ['en' => "National Fast and Prayer Day"],
            new DateTime("second Friday of April {$this->year}", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'thanksgivingDay',
            ['en' => "Thanksgiving Day"],
            new DateTime("first Thursday of November {$this->year}", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

    }

}
