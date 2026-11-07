<?php

namespace ChurchCRM\Plugins\Holidays\Provider;

use DateTime;
use DateTimeZone;
use Yasumi\Holiday;
use Yasumi\Provider\AbstractProvider;
use Yasumi\Provider\CommonHolidays;

/**
 * Provider for public holidays in Libya.
 */
class Libya extends AbstractProvider
{
    use CommonHolidays;

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
     * Estimated Gregorian dates for Islamic New Year (1st Muharram), keyed by year.
     */
    private const ISLAMIC_NEW_YEAR_DATES = [
        2024 => '07-07',
        2025 => '06-26',
        2026 => '06-16',
        2027 => '06-06',
        2028 => '05-25',
        2029 => '05-14',
        2030 => '05-04',
        2031 => '04-23',
        2032 => '04-11',
        2033 => '04-01',
        2034 => '03-21',
        2035 => '03-10',
    ];

    /**
     * Estimated Gregorian dates for Ashura (10th Muharram), keyed by year.
     */
    private const ASHURA_DATES = [
        2024 => '07-16',
        2025 => '07-05',
        2026 => '06-25',
        2027 => '06-15',
        2028 => '06-03',
        2029 => '05-23',
        2030 => '05-13',
        2031 => '05-02',
        2032 => '04-20',
        2033 => '04-10',
        2034 => '03-30',
        2035 => '03-19',
    ];

    /**
     * Initialize all fixed and movable public holidays for Libya.
     *
     * @return void
     */
    public function initialize(): void
    {
        $this->timezone = 'Africa/Tripoli';
        $tz = new DateTimeZone($this->timezone);

        // Fixed Public Holidays
        $this->addHoliday(new Holiday(
            'revolutionDay',
            ['en' => "February 17 Revolution Day"],
            new DateTime("{$this->year}-02-17", $tz),
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
            'martyrsDay',
            ['en' => "Martyrs' Day"],
            new DateTime("{$this->year}-09-16", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'liberationDay',
            ['en' => "Liberation Day"],
            new DateTime("{$this->year}-10-23", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'independenceDay',
            ['en' => "Independence Day"],
            new DateTime("{$this->year}-12-24", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        // Movable Islamic Holidays (estimated astronomical projections)
        $this->addEidAlFitr($tz);
        $this->addEidAlAdha($tz);
        $this->addMawlid($tz);
        $this->addIslamicNewYear($tz);
        $this->addAshura($tz);

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

    /**
     * Add Islamic New Year if an estimated date is available for this year.
     *
     * @param DateTimeZone $tz Timezone instance.
     * @return void
     */
    private function addIslamicNewYear(DateTimeZone $tz): void
    {
        if (!isset(self::ISLAMIC_NEW_YEAR_DATES[$this->year])) {
            return;
        }
        $monthDay = self::ISLAMIC_NEW_YEAR_DATES[$this->year];
        $this->addHoliday(new Holiday(
            'islamicNewYear',
            ['en' => 'Islamic New Year'],
            new DateTime("{$this->year}-{$monthDay}", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));
    }

    /**
     * Add Ashura if an estimated date is available for this year.
     *
     * @param DateTimeZone $tz Timezone instance.
     * @return void
     */
    private function addAshura(DateTimeZone $tz): void
    {
        if (!isset(self::ASHURA_DATES[$this->year])) {
            return;
        }
        $monthDay = self::ASHURA_DATES[$this->year];
        $this->addHoliday(new Holiday(
            'ashura',
            ['en' => 'Ashura'],
            new DateTime("{$this->year}-{$monthDay}", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));
    }

}
