<?php

namespace ChurchCRM\Plugins\Holidays\Provider;

use DateTime;
use DateTimeZone;
use Yasumi\Holiday;
use Yasumi\Provider\AbstractProvider;
use Yasumi\Provider\CommonHolidays;
use Yasumi\Provider\ChristianHolidays;

/**
 * Provider for public holidays in Malawi.
 */
class Malawi extends AbstractProvider
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
     * Initialize all fixed and movable public holidays for Malawi.
     *
     * @return void
     */
    public function initialize(): void
    {
        $this->timezone = 'Africa/Blantyre';
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
            'chilembweDay',
            ['en' => "John Chilembwe Day"],
            new DateTime("{$this->year}-01-15", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'martyrsDay',
            ['en' => "Martyrs' Day"],
            new DateTime("{$this->year}-03-03", $tz),
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
            'kamuzuDay',
            ['en' => "Kamuzu Day"],
            new DateTime("{$this->year}-05-14", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'independenceDay',
            ['en' => "Independence Day"],
            new DateTime("{$this->year}-07-06", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'mothersDay',
            ['en' => "Mother's Day"],
            new DateTime("{$this->year}-10-15", $tz),
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
