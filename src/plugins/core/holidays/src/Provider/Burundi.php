<?php

namespace ChurchCRM\Plugins\Holidays\Provider;

use DateTime;
use DateTimeZone;
use Yasumi\Holiday;
use Yasumi\Provider\AbstractProvider;
use Yasumi\Provider\CommonHolidays;
use Yasumi\Provider\ChristianHolidays;

/**
 * Provider for public holidays in Burundi.
 */
class Burundi extends AbstractProvider
{
    use CommonHolidays;
    use ChristianHolidays;

    /**
     * Estimated Gregorian dates for Eid al-Fitr, keyed by year.
     */
    private const EID_AL_FITR_DATES = [
        2024 => '04-10',
        2025 => '03-31',
        2026 => '03-20',
        2027 => '03-10',
        2028 => '02-27',
        2029 => '02-15',
        2030 => '02-05',
        2031 => '01-25',
        2032 => '01-14',
        2033 => '01-03',
        2034 => '12-23',
        2035 => '12-12',
    ];

    /**
     * Initialize all fixed and movable public holidays for Burundi.
     *
     * @return void
     */
    public function initialize(): void
    {
        $this->timezone = 'Africa/Bujumbura';
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
            'unityDay',
            ['en' => "National Unity Day"],
            new DateTime("{$this->year}-02-05", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'presidentNtaryamiraDay',
            ['en' => "President Cyprien Ntaryamira Day"],
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
            'independenceDay',
            ['en' => "Independence Day"],
            new DateTime("{$this->year}-07-01", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'armedForcesDay',
            ['en' => "Day of the Armed Forces"],
            new DateTime("{$this->year}-07-02", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'assumptionDay',
            ['en' => "Assumption Day"],
            new DateTime("{$this->year}-08-15", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'princeRwagasoreDay',
            ['en' => "Prince Louis Rwagasore Day"],
            new DateTime("{$this->year}-10-13", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'presidentNdadayeDay',
            ['en' => "President Melchior Ndadaye Day"],
            new DateTime("{$this->year}-10-21", $tz),
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
            'christmasDay',
            ['en' => "Christmas Day"],
            new DateTime("{$this->year}-12-25", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        // Movable Christian Holidays
        $this->addHoliday($this->ascensionDay($this->year, $this->timezone, $this->locale));

        // Movable Islamic Holidays (estimated astronomical projections)
        $this->addEidAlFitr($tz);

    }

    /**
     * Add Eid al-Fitr if an estimated date is available for this year.
     *
     * @param DateTimeZone $tz Timezone instance.
     * @return void
     */
    private function addEidAlFitr(DateTimeZone $tz): void
    {
        if (!isset(self::EID_AL_FITR_DATES[$this->year])) {
            return;
        }
        $monthDay = self::EID_AL_FITR_DATES[$this->year];
        $this->addHoliday(new Holiday(
            'eidAlFitr',
            ['en' => 'Eid al-Fitr'],
            new DateTime("{$this->year}-{$monthDay}", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));
    }

}
