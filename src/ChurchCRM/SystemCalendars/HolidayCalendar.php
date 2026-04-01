<?php

namespace ChurchCRM\SystemCalendars;

use ChurchCRM\data\Countries;
use ChurchCRM\data\Country;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Event;
use ChurchCRM\Utils\DateTimeUtils;
use Propel\Runtime\Collection\ObjectCollection;
use Yasumi\Holiday;
use Yasumi\Yasumi;

class HolidayCalendar implements SystemCalendar
{
    public static function isAvailable(): bool
    {
        try {
            $countryName = SystemConfig::getValue('sChurchCountry');
            if (empty($countryName)) {
                return false;
            }
            $systemCountry = Countries::getCountryByName($countryName);
            if ($systemCountry instanceof Country) {
                return $systemCountry->getCountryNameYasumi() !== null;
            }
        } catch (\Exception $e) {
            // If the country is invalid or unavailable, return false
            return false;
        }

        return false;
    }

    public function getAccessToken(): bool
    {
        return false;
    }

    public function getBackgroundColor(): string
    {
        return '6dfff5';
    }

    public function getForegroundColor(): string
    {
        return '000000';
    }

    public function getId(): int
    {
        return 2;
    }

    public function getName(): string
    {
        return gettext('Holidays');
    }

    public function getEvents(string $start, string $end): ObjectCollection
    {
        $events = new ObjectCollection();
        $events->setModel(Event::class);

        try {
            $countryName = SystemConfig::getValue('sChurchCountry');
            if (empty($countryName)) {
                return $events;
            }
            $Country = Countries::getCountryByName($countryName);
            if (!($Country instanceof Country)) {
                return $events;
            }
            $year = DateTimeUtils::getCurrentYear();
            $holidays = Yasumi::create($Country->getCountryNameYasumi(), $year);

            foreach ($holidays->getHolidays() as $holiday) {
                $event = $this->yasumiHolidayToEvent($holiday);
                $events->push($event);
            }
        } catch (\Exception $e) {
            // If holiday calendar fails, return empty set
        }

        return $events;
    }

    public function getEventById(int $Id): ObjectCollection
    {
        $emptySet = new ObjectCollection();
        $emptySet->setModel(Event::class);

        return $emptySet;
    }

    private function yasumiHolidayToEvent(Holiday $holiday): Event
    {
        $id = crc32($holiday->getName() . $holiday->getTimestamp());
        $holidayEvent = new Event();
        $holidayEvent->setId($id);
        $holidayEvent->setEditable(false);
        $holidayEvent->setTitle($holiday->getName());
        $holidayEvent->setStart($holiday->getTimestamp());

        return $holidayEvent;
    }
}
