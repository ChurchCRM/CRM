<?php

namespace ChurchCRM\SystemCalendars;

use ChurchCRM\data\Countries;
use ChurchCRM\data\Country;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Event;
use Propel\Runtime\Collection\ObjectCollection;
use Yasumi\Holiday;
use Yasumi\Yasumi;

class HolidayCalendar implements SystemCalendar
{
    public static function isAvailable(): bool
    {
        $systemCountry = Countries::getCountryByName(SystemConfig::getValue('sChurchCountry'));
        if ($systemCountry instanceof Country) {
            return $systemCountry->getCountryNameYasumi() !== null;
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

    public function getEvents(string $start, string $end)
    {
        $Country = Countries::getCountryByName(SystemConfig::getValue('sChurchCountry'));
        $year = date('Y');
        $holidays = Yasumi::create($Country->getCountryNameYasumi(), (int) $year);
        $events = new ObjectCollection();
        $events->setModel(Event::class);

        foreach ($holidays->getHolidays() as $holiday) {
            $event = $this->yasumiHolidayToEvent($holiday);
            $events->push($event);
        }

        return $events;
    }

    public function getEventById(int $Id)
    {
        return false;
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
