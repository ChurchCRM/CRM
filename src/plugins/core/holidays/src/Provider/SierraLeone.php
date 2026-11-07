<?php

namespace ChurchCRM\Plugins\Holidays\Provider;

use DateTime;
use DateTimeZone;
use Yasumi\Holiday;
use Yasumi\Provider\AbstractProvider;
use Yasumi\Provider\CommonHolidays;
use Yasumi\Provider\ChristianHolidays;

/**
 * Provider for public holidays in Sierra Leone.
 */
class SierraLeone extends AbstractProvider
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
     * Estimated Gregorian dates for Eid al-Adha, keyed by year.
     */
    private const EID_AL_ADHA_DATES = [
        2024 => '06-17',
        2025 => '06-07',
        2026 => '05-27',
        2027 => '05-17',
        2028 => '05-05',
        2029 => '04-24',
        2030 => '04-13',
        2031 => '04-02',
        2032 => '03-22',
        2033 => '03-12',
        2034 => '03-01',
        2035 => '02-18',
    ];

    /**
     * Estimated Gregorian dates for Mawlid (Prophet's Birthday), keyed by year.
     */
    private const MAWLID_DATES = [
        2024 => '09-16',
        2025 => '09-05',
        2026 => '08-26',
        2027 => '08-15',
        2028 => '08-03',
        2029 => '07-23',
        2030 => '07-13',
        2031 => '07-02',
        2032 => '06-20',
        2033 => '06-09',
        2034 => '05-30',
        2035 => '05-19',
    ];

    /**
     * Initialize all fixed and movable public holidays for SierraLeone.
     *
     * @return void
     */
    public function initialize(): void
    {
        $this->timezone = 'Africa/Freetown';
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
            new DateTime("{$this->year}-04-27", $tz),
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
        $this->addEidAlAdha($tz);
        $this->addMawlid($tz);

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

    /**
     * Add Eid al-Adha if an estimated date is available for this year.
     *
     * @param DateTimeZone $tz Timezone instance.
     * @return void
     */
    private function addEidAlAdha(DateTimeZone $tz): void
    {
        if (!isset(self::EID_AL_ADHA_DATES[$this->year])) {
            return;
        }
        $monthDay = self::EID_AL_ADHA_DATES[$this->year];
        $this->addHoliday(new Holiday(
            'eidAlAdha',
            ['en' => 'Eid al-Adha'],
            new DateTime("{$this->year}-{$monthDay}", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));
    }

    /**
     * Add Mawlid (Prophet's Birthday) if an estimated date is available for this year.
     *
     * @param DateTimeZone $tz Timezone instance.
     * @return void
     */
    private function addMawlid(DateTimeZone $tz): void
    {
        if (!isset(self::MAWLID_DATES[$this->year])) {
            return;
        }
        $monthDay = self::MAWLID_DATES[$this->year];
        $this->addHoliday(new Holiday(
            'mawlid',
            ['en' => "Mawlid (Prophet's Birthday)"],
            new DateTime("{$this->year}-{$monthDay}", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));
    }

}
