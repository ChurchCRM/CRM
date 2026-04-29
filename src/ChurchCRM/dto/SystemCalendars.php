<?php

namespace ChurchCRM\dto;

use ChurchCRM\model\ChurchCRM\Calendar;
use ChurchCRM\Plugin\Hook\HookManager;
use ChurchCRM\Plugin\Hooks;
use ChurchCRM\Plugin\PluginManager;
use ChurchCRM\SystemCalendars\AnniversariesCalendar;
use ChurchCRM\SystemCalendars\BirthdaysCalendar;
use ChurchCRM\SystemCalendars\SystemCalendar;
use ChurchCRM\SystemCalendars\UnpinnedEvents;
use Propel\Runtime\Collection\ObjectCollection;

class SystemCalendars
{
    /**
     * @return SystemCalendar[]
     */
    private static function getCalendars(): array
    {
        $systemCalendarNames = [
            BirthdaysCalendar::class,
            AnniversariesCalendar::class,
            UnpinnedEvents::class,
        ];

        $calendars = [];
        foreach ($systemCalendarNames as $systemCalendarName) {
            if ($systemCalendarName::isAvailable()) {
                $calendars[] = new $systemCalendarName();
            }
        }

        // Allow plugins to contribute additional system calendars.
        // Lazy-init PluginManager because the API entry point does not
        // initialize it the way Header.php does.
        try {
            PluginManager::init(SystemURLs::getDocumentRoot() . '/plugins');
            $calendars = HookManager::applyFilters(Hooks::SYSTEM_CALENDARS_REGISTER, $calendars);
        } catch (\Throwable $e) {
            // Plugin discovery failure must not break core calendars
        }

        return $calendars;
    }

    public static function getCalendarList(): ObjectCollection
    {
        $calendars = new ObjectCollection();
        $calendars->setModel(Calendar::class);
        foreach (self::getCalendars() as $calendar) {
            $calendars->push(self::toPropelCalendar($calendar));
        }

        return $calendars;
    }

    public static function getCalendarById($id)
    {
        $requestedCalendar = null;
        foreach (self::getCalendars() as $calendar) {
            if ($calendar->getId() == $id) {
                $requestedCalendar = $calendar;
                break;
            }
        }

        return $requestedCalendar;
    }

    public static function toPropelCalendar(SystemCalendar $calendar): Calendar
    {
        $procalendar = new Calendar();
        $procalendar->setId($calendar->getId());
        $procalendar->setName($calendar->getName());
        $procalendar->setAccessToken($calendar->getAccessToken());
        $procalendar->setBackgroundColor($calendar->getBackgroundColor());
        $procalendar->setForegroundColor($calendar->getForegroundColor());

        return $procalendar;
    }
}
