<?php

namespace ChurchCRM\Config\Menu;

use ChurchCRM\Config;
use ChurchCRM\SessionUser;

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
            "Dashboard" => new MenuItem(gettext("Dashboard"), "Menu.php", true, 'fa-dashboard'),
            "Calendar" => self::getCalendarMenu(),
            "People" => self::getPeopleMenu(),
            "Groups" => self::getGroupMenu(),
            "SundaySchool" => self::getSundaySchoolMenu(),
            "Email" => new MenuItem(gettext("Email"), "v2/email/dashboard", true, 'fa-envelope'),
            "Events" => self::getEventsMenu(),
            "Deposits" => self::getDepositsMenu(),
            "Fundraiser" => self::getFundraisersMenu(),
            "Reports" => self::getReportsMenu(),
        );
    }

    private static function getCalendarMenu()
    {
        $calendarMenu = new MenuItem(gettext("Calendar"), "v2/calendar", true, 'fa-calendar');
        $calendarMenu->addCounter(new MenuCounter("AnniversaryNumber", "bg-blue"));
        $calendarMenu->addCounter(new MenuCounter("BirthdateNumber", "bg-red"));
        $calendarMenu->addCounter(new MenuCounter("EventsNumber", "bg-yellow"));
        return $calendarMenu;
    }

    private static function getPeopleMenu()
    {
        $peopleMenu = new MenuItem(gettext("People"), "", true, 'fa-users');
        $peopleMenu->addSubMenu(new MenuItem(gettext("Dashboard"), "PeopleDashboard.php"));
        $peopleMenu->addSubMenu(new MenuItem(gettext("Add New Person"), "PersonEditor.php", SessionUser::getUser()->isAddRecords()));
        $peopleMenu->addSubMenu(new MenuItem(gettext("View All Persons"), "SelectList.php?mode=person"));
        $peopleMenu->addSubMenu(new MenuItem(gettext("Add New Family"), "FamilyEditor.php", SessionUser::getUser()->isAddRecords()));
        $peopleMenu->addSubMenu(new MenuItem(gettext("View Active Families"), "FamilyList.php"));
        $peopleMenu->addSubMenu(new MenuItem(gettext("View Inactive Families"), "FamilyList.php?mode=inactive"));
        return $peopleMenu;
    }

    private static function getGroupMenu()
    {
        $groupMenu = new MenuItem(gettext("Groups"), "", true, 'fa-tag');
        $groupMenu->addSubMenu(new MenuItem(gettext("List Groups"), "GroupList.php"));
        $groupMenu->addSubMenu(new MenuItem(gettext("Group Assignment Helper"), "SelectList.php?mode=groupassign"));
        return $groupMenu;
    }

    private static function getSundaySchoolMenu()
    {
        $sundaySchoolMenu = new MenuItem(gettext("Sunday School"), "", true, 'fa-child');
        $sundaySchoolMenu->addSubMenu(new MenuItem(gettext("Dashboard"), "sundayschool/SundaySchoolDashboard.php"));
        return $sundaySchoolMenu;
    }

    private static function getEventsMenu()
    {
        $eventsMenu = new MenuItem(gettext("Events"), "", true, 'fa-ticket');
        $eventsMenu->addSubMenu(new MenuItem(gettext("Add Church Event"), "EventEditor.php", SessionUser::getUser()->isAddEvent()));
        $eventsMenu->addSubMenu(new MenuItem(gettext("List Church Events"), "ListEvents.php"));
        $eventsMenu->addSubMenu(new MenuItem(gettext("List Event Types"), "EventNames.php"));
        $eventsMenu->addSubMenu(new MenuItem(gettext("Check-in and Check-out"), "Checkin.php"));
        return $eventsMenu;
    }

    private static function getDepositsMenu()
    {
        $depositsMenu = new MenuItem(gettext("Deposit"), "", SessionUser::getUser()->isFinanceEnabled(), 'fa-bank');
        $depositsMenu->addSubMenu(new MenuItem(gettext("Envelope Manager"), "ManageEnvelopes.php", SessionUser::getUser()->isAdmin()));
        $depositsMenu->addSubMenu(new MenuItem(gettext("View All Deposits"), "FinancialReports.php", SessionUser::getUser()->isFinanceEnabled()));
        $depositsMenu->addSubMenu(new MenuItem(gettext("Deposit Reports"), "FinancialReports.php", SessionUser::getUser()->isFinanceEnabled()));
        $depositsMenu->addSubMenu(new MenuItem(gettext("Edit Deposit Slip"), "DepositSlipEditor.php", SessionUser::getUser()->isFinanceEnabled()));
        return $depositsMenu;
    }


    private static function getFundraisersMenu()
    {
        $fundraiserMenu = new MenuItem(gettext("Fundraiser"), "", true, 'fa-money');
        $fundraiserMenu->addSubMenu(new MenuItem(gettext("Create New Fundraiser"), "FundRaiserEditor.php?FundRaiserID=-1"));
        $fundraiserMenu->addSubMenu(new MenuItem(gettext("View All Fundraisers"), "FindFundRaiser.php"));
        $fundraiserMenu->addSubMenu(new MenuItem(gettext("Edit Fundraiser"), "FundRaiserEditor.php"));
        $fundraiserMenu->addSubMenu(new MenuItem(gettext("Add Donors to Buyer List"), "AddDonors.php"));
        $fundraiserMenu->addSubMenu(new MenuItem(gettext("View Buyers"), "PaddleNumList.php"));

        return $fundraiserMenu;
    }

    private static function getReportsMenu()
    {
        $reportsMenu = new MenuItem(gettext("Data/Reports"), "", true, 'fa-file-pdf-o');
        $reportsMenu->addSubMenu(new MenuItem(gettext("Reports Menu"), "ReportList.php"));
        $reportsMenu->addSubMenu(new MenuItem(gettext("Query Menu"), "QueryList.php"));
        return $reportsMenu;
    }
}
