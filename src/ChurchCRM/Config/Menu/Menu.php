<?php

namespace ChurchCRM\Config\Menu;

use ChurchCRM\Config;

class Menu
{
    /**
     * @var Config[]
     */
    private static $menuItems;

    public static function init()
    {
        self::$menuItems = self::buildMenuItems();
        /*if (!empty($menuItems)) {
            self::scrapeDBMenuItems($menuItems);
        }*/
    }

    public static function getMenu()
    {
        return self::$menuItems;
    }

    private static function buildMenuItems()
    {
        return array(
            "Dashboard" => new MenuItem(gettext("Dashboard"), "Menu.php", 'bAll', 'fa-dashboard'),
            "Calendar" => self::getCalendarMenu(),
            "People" => self::getPeopleMenu()
        );
    }

    private static function getCalendarMenu() {
        $calendarMenu = new MenuItem(gettext("Calendar"), "v2/calendar", 'bAll', 'fa-calendar');
        $calendarMenu->addCounter(new MenuCounter("AnniversaryNumber" , "bg-blue"));
        $calendarMenu->addCounter(new MenuCounter("BirthdateNumber" , "bg-red"));
        $calendarMenu->addCounter(new MenuCounter("EventsNumber" , "bg-yellow"));
        return $calendarMenu;
    }
    private static function getPeopleMenu()
    {
        $peopleMenu = new MenuItem(gettext("People"), "", 'bAll',  'fa-users');
        $peopleMenu->addSubMenu(new MenuItem(gettext("Dashboard"), "PeopleDashboard.php"));
        return $peopleMenu;
    }

}
