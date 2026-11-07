<?php

namespace ChurchCRM\Plugins\Holidays\Provider;

use DateTime;
use DateTimeZone;
use Yasumi\Holiday;
use Yasumi\Provider\AbstractProvider;
use Yasumi\Provider\CommonHolidays;

/**
 * Provider for public holidays in Sao Tome and Principe.
 */
class SaoTomeAndPrincipe extends AbstractProvider
{
    use CommonHolidays;

    /**
     * Initialize all fixed and movable public holidays for SaoTomeAndPrincipe.
     *
     * @return void
     */
    public function initialize(): void
    {
        $this->timezone = 'Africa/Sao_Tome';
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
            'kingAmadorDay',
            ['en' => "King Amador Day"],
            new DateTime("{$this->year}-01-04", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'martyrsDay',
            ['en' => "Martyrs' Day"],
            new DateTime("{$this->year}-02-03", $tz),
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
            'independenceDay',
            ['en' => "Independence Day"],
            new DateTime("{$this->year}-07-12", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'armedForcesDay',
            ['en' => "Armed Forces Day"],
            new DateTime("{$this->year}-09-06", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'agriculturalReformDay',
            ['en' => "Agricultural Reform Day"],
            new DateTime("{$this->year}-09-30", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'allSaintsDay',
            ['en' => "All Saints' Day"],
            new DateTime("{$this->year}-11-01", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'autonomousRegionDay',
            ['en' => "Autonomous Region Day"],
            new DateTime("{$this->year}-12-21", $tz),
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
