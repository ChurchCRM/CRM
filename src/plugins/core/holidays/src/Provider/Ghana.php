<?php

namespace ChurchCRM\Plugins\Holidays\Provider;

use DateTime;
use DateTimeZone;
use Yasumi\Holiday;
use Yasumi\Provider\AbstractProvider;
use Yasumi\Provider\CommonHolidays;
use Yasumi\Provider\ChristianHolidays;

/**
 * Provider for public holidays in Ghana.
 *
 * Based on the Public Holidays and Commemorative Days Act, as amended by
 * Act 1142 (2025), which reinstated Republic Day (July 1) and confirms
 * 14 statutory public holidays including two Islamic observances.
 *
 * Islamic holiday dates (Eid al-Fitr, Eid al-Adha) are estimated from
 * astronomical projections. Actual dates may shift by one day based on
 * local moon sighting as declared by the Ministry of the Interior.
 */
class Ghana extends AbstractProvider
{
    use CommonHolidays;
    use ChristianHolidays;

    /**
     * Estimated Gregorian dates for Eid al-Fitr, keyed by year.
     * Source: astronomical projections (robjhyndman.com, timeanddate.com).
     * Actual observance may differ by one day based on crescent sighting.
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
     * Initialize all fixed and movable public holidays for Ghana.
     *
     * @return void
     */
    public function initialize(): void
    {
        $this->timezone = 'Africa/Accra';
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
            'constitutionDay',
            ['en' => 'Constitution Day'],
            new DateTime("{$this->year}-01-07", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'independenceDay',
            ['en' => 'Independence Day'],
            new DateTime("{$this->year}-03-06", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'workersDay',
            ['en' => "Workers' Day (May Day)"],
            new DateTime("{$this->year}-05-01", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'republicDay',
            ['en' => 'Republic Day'],
            new DateTime("{$this->year}-07-01", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'foundersDay',
            ['en' => "Founders' Day"],
            new DateTime("{$this->year}-08-04", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'kwameNkrumahDay',
            ['en' => 'Kwame Nkrumah Memorial Day'],
            new DateTime("{$this->year}-09-21", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'farmersDay',
            ['en' => "National Farmers' Day"],
            new DateTime("first Friday of December {$this->year}", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'christmasDay',
            ['en' => 'Christmas Day'],
            new DateTime("{$this->year}-12-25", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        $this->addHoliday(new Holiday(
            'boxingDay',
            ['en' => 'Boxing Day'],
            new DateTime("{$this->year}-12-26", $tz),
            $this->locale,
            Holiday::TYPE_OFFICIAL
        ));

        // Movable Christian Holidays
        $this->addHoliday($this->goodFriday($this->year, $this->timezone, $this->locale));
        $this->addHoliday($this->easterMonday($this->year, $this->timezone, $this->locale));

        // Movable Islamic Holidays (estimated astronomical dates)
        $this->addEidAlFitr($tz);
        $this->addEidAlAdha($tz);
    }

    /**
     * Add Eid al-Fitr if an estimated date is available for this year.
     *
     * Eid al-Fitr 2034 falls on Dec 23, which means the month-day belongs
     * to the same Gregorian year as $this->year, so no cross-year logic
     * is needed for the lookup table as structured.
     *
     * @param DateTimeZone $tz The time zone instance for Accra.
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
     * @param DateTimeZone $tz The time zone instance for Accra.
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
     * Get the sources used for Ghana public holidays.
     *
     * @return array<string> List of official source URLs.
     */
    public function getSources(): array
    {
        return [
            'https://mint.gov.gh/',
        ];
    }
}
