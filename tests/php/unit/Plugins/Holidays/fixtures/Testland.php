<?php

namespace ChurchCRM\Plugins\Holidays\Provider;

use Yasumi\Holiday;
use Yasumi\Provider\AbstractProvider;
use Yasumi\Provider\ChristianHolidays;

/**
 * A custom provider shaped like the plugin's own ones: a Yasumi helper
 * holiday (Good Friday) plus a holiday with only an English title.
 */
class Testland extends AbstractProvider
{
    use ChristianHolidays;

    public function initialize(): void
    {
        $this->timezone = 'UTC';

        $this->addHoliday($this->goodFriday($this->year, $this->timezone, $this->locale));
        $this->addHoliday(new Holiday(
            'foundersDay',
            ['en' => "Founders' Day"],
            new \DateTime("{$this->year}-06-01", new \DateTimeZone($this->timezone)),
            $this->locale,
        ));
    }

    public function getSources(): array
    {
        return [];
    }
}
