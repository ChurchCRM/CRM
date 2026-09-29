<?php

namespace ChurchCRM\Plugins\Holidays\Provider;

use DateTime;
use DateTimeZone;
use Yasumi\Holiday;
use Yasumi\Provider\AbstractProvider;
use Yasumi\Provider\CommonHolidays;

/**
 * Provider for public holidays in Ghana.
 */
class Ghana extends AbstractProvider
{
    use CommonHolidays;

    public function initialize(): void
    {
        $this->timezone = 'Africa/Accra';

        // Official Fixed Public Holidays in Ghana
        $this->addHoliday(new Holiday('newYearsDay', ['en' => "New Year's Day"], new DateTime("{$this->year}-01-01", new DateTimeZone($this->timezone)), Holiday::TYPE_OFFICIAL));
        $this->addHoliday(new Holiday('constitutionDay', ['en' => 'Constitution Day'], new DateTime("{$this->year}-01-07", new DateTimeZone($this->timezone)), Holiday::TYPE_OFFICIAL));
        $this->addHoliday(new Holiday('independenceDay', ['en' => 'Independence Day'], new DateTime("{$this->year}-03-06", new DateTimeZone($this->timezone)), Holiday::TYPE_OFFICIAL));
        $this->addHoliday(new Holiday('workersDay', ['en' => "Workers' Day"], new DateTime("{$this->year}-05-01", new DateTimeZone($this->timezone)), Holiday::TYPE_OFFICIAL));
        $this->addHoliday(new Holiday('foundersDay', ['en' => "Founders' Day"], new DateTime("{$this->year}-08-04", new DateTimeZone($this->timezone)), Holiday::TYPE_OFFICIAL));
        $this->addHoliday(new Holiday('kwameNkrumahDay', ['en' => 'Kwame Nkrumah Memorial Day'], new DateTime("{$this->year}-09-21", new DateTimeZone($this->timezone)), Holiday::TYPE_OFFICIAL));
        $this->addHoliday(new Holiday('farmersDay', ['en' => "Farmers' Day"], new DateTime("first Friday of December {$this->year}", new DateTimeZone($this->timezone)), Holiday::TYPE_OFFICIAL));
        $this->addHoliday(new Holiday('christmasDay', ['en' => 'Christmas Day'], new DateTime("{$this->year}-12-25", new DateTimeZone($this->timezone)), Holiday::TYPE_OFFICIAL));
        $this->addHoliday(new Holiday('boxingDay', ['en' => 'Boxing Day'], new DateTime("{$this->year}-12-26", new DateTimeZone($this->timezone)), Holiday::TYPE_OFFICIAL));

        // Easter Holidays (Good Friday & Easter Monday)
        $this->addHoliday($this->goodFriday($this->year, $this->timezone, $this->locale));
        $this->addHoliday($this->easterMonday($this->year, $this->timezone, $this->locale));
    }
}
