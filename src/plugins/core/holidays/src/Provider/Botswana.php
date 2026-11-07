<?php

namespace ChurchCRM\Plugins\Holidays\Provider;

use DateTime;
use DateTimeZone;
use Yasumi\Holiday;
use Yasumi\Provider\AbstractProvider;
use Yasumi\Provider\CommonHolidays;
use Yasumi\Provider\ChristianHolidays;

/**
 * Provider for public holidays in Botswana.
 */
class Botswana extends AbstractProvider
{
    use CommonHolidays;
    use ChristianHolidays;

    /**
     * Initialize all fixed and movable public holidays for Botswana.
     *
     * @return void
     */
    public function initialize(): void
    {
        $this->timezone = 'Africa/Gaborone';
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
            'newYearHoliday',
            ['en' => "New Year Holiday"],
            new DateTime("{$this->year}-01-02", $tz),
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
            'sirSeretseKhamaDay',
            ['en' => "Sir Seretse Khama Day"],
            new DateTime("{$this->year}-07-01", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'botswanaDay',
            ['en' => "Botswana Day"],
            new DateTime("{$this->year}-09-30", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'botswanaDayHoliday',
            ['en' => "Botswana Day Holiday"],
            new DateTime("{$this->year}-10-01", $tz),
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

        // Dynamic / Rule-based Public Holidays
        $this->addHoliday(new Holiday(
            'presidentsDay',
            ['en' => "President's Day"],
            new DateTime("third Monday of July {$this->year}", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'presidentsDayHoliday',
            ['en' => "President's Day Holiday"],
            new DateTime("third Tuesday of July {$this->year}", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

    }

}
