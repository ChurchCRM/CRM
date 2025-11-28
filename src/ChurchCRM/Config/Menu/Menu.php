<?php

namespace ChurchCRM\Config\Menu;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Config;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\MenuLinkQuery;

class Menu
{
    /**
     * @var array<string, MenuItem>|null
     */
    private static ?array $menuItems = null;

    public static function init(): void
    {
        self::$menuItems = self::buildMenuItems();
    }

    public static function getMenu(): ?array
    {
        return self::$menuItems;
    }

    private static function buildMenuItems(): array
    {
        $currentUser = AuthenticationManager::getCurrentUser();
        $isAdmin = $currentUser->isAdmin();
        $menus = [
            'Dashboard'    => new MenuItem(gettext('Dashboard'), 'v2/dashboard', true, 'fa-tachometer-alt'),
            'Calendar'     => self::getCalendarMenu(),
            'People'       => self::getPeopleMenu($isAdmin, $currentUser->isAddRecordsEnabled()),
            'Groups'       => self::getGroupMenu($isAdmin),
            'SundaySchool' => self::getSundaySchoolMenu(),
            'Email'        => new MenuItem(gettext('Email'), 'v2/email/dashboard', SystemConfig::getBooleanValue('bEnabledEmail'), 'fa-envelope'),
            'Events'       => self::getEventsMenu($currentUser->isAddEventEnabled()),
            'Deposits'     => self::getDepositsMenu($isAdmin, $currentUser->isFinanceEnabled()),
            'Fundraiser'   => self::getFundraisersMenu(),
            'Reports'      => self::getReportsMenu(),
            'Custom'       => self::getCustomMenu()
        ];
        if ($isAdmin) {
            $menus['Admin'] = self::getAdminMenu($isAdmin);
        }
        return $menus;

    }

    private static function getCalendarMenu(): MenuItem
    {
        $calendarMenu = new MenuItem(gettext('Calendar'), 'v2/calendar', true, 'fa-calendar');
        // Anniversaries calendar (ID 1) - black background
        $calendarMenu->addCounter(new MenuCounter('AnniversaryNumber', 'bg-dark', 0, gettext("Today's Wedding Anniversaries")));
        // Birthdays calendar (ID 0) - blue background  
        $calendarMenu->addCounter(new MenuCounter('BirthdateNumber', 'bg-primary', 0, gettext("Today's Birthdays")));
        // Events happening today - yellow/warning background
        $calendarMenu->addCounter(new MenuCounter('EventsNumber', 'bg-warning', 0, gettext('Events Today')));

        return $calendarMenu;
    }

    private static function getPeopleMenu(bool $isAdmin, bool $isAddRecordsEnabled): MenuItem
    {
        $peopleMenu = new MenuItem(gettext('People'), '', true, 'fa-user');
        $peopleMenu->addSubMenu(new MenuItem(gettext('Dashboard'), 'PeopleDashboard.php'));
        $peopleMenu->addSubMenu(new MenuItem(gettext('Add New Person'), 'PersonEditor.php', $isAddRecordsEnabled));
        $peopleMenu->addSubMenu(new MenuItem(gettext('View Active People'), 'v2/people'));
        $peopleMenu->addSubMenu(new MenuItem(gettext('View Inactive People'), 'v2/people?familyActiveStatus=inactive'));
        $peopleMenu->addSubMenu(new MenuItem(gettext('View All People'), 'v2/people?familyActiveStatus=all'));
        $peopleMenu->addSubMenu(new MenuItem(gettext('Add New Family'), 'FamilyEditor.php', $isAddRecordsEnabled));
        $peopleMenu->addSubMenu(new MenuItem(gettext('View Active Families'), 'v2/family'));
        $peopleMenu->addSubMenu(new MenuItem(gettext('View Inactive Families'), 'v2/family?mode=inactive'));

        if ($isAdmin) {
            $adminMenu = new MenuItem(gettext('Admin'), '', $isAdmin);
            $adminMenu->addSubMenu(new MenuItem(gettext('Classifications Manager'), 'OptionManager.php?mode=classes', $isAdmin));
            $adminMenu->addSubMenu(new MenuItem(gettext('Family Roles'), 'OptionManager.php?mode=famroles', $isAdmin));
            $adminMenu->addSubMenu(new MenuItem(gettext('Family Properties'), 'PropertyList.php?Type=f', $isAdmin));
            $adminMenu->addSubMenu(new MenuItem(gettext('Family Custom Fields'), 'FamilyCustomFieldsEditor.php', $isAdmin));
            $adminMenu->addSubMenu(new MenuItem(gettext('People Properties'), 'PropertyList.php?Type=p', $isAdmin));
            $adminMenu->addSubMenu(new MenuItem(gettext('Person Custom Fields'), 'PersonCustomFieldsEditor.php', $isAdmin));
            $adminMenu->addSubMenu(new MenuItem(gettext('Volunteer Opportunities'), 'VolunteerOpportunityEditor.php', $isAdmin));
    
            $peopleMenu->addSubMenu($adminMenu);
        }

        return $peopleMenu;
    }

    private static function getGroupMenu(bool $isAdmin): MenuItem
    {
        $groupMenu = new MenuItem(gettext('Groups'), '', true, 'fa-users');
        $groupMenu->addSubMenu(new MenuItem(gettext('List Groups'), 'GroupList.php'));

        $listOptions = ListOptionQuery::create()->filterById(3)->orderByOptionSequence()->find();

        foreach ($listOptions as $listOption) {
            if ($listOption->getOptionId() !== 4) {// we avoid the sundaySchool, it's done under
                $tmpMenu = self::addGroupSubMenus($listOption->getOptionName(), $listOption->getOptionId(), 'GroupView.php?GroupID=');
                if ($tmpMenu instanceof MenuItem) {
                    $groupMenu->addSubMenu($tmpMenu);
                }
            }
        }

        // now we're searching the unclassified groups
        $tmpMenu = self::addGroupSubMenus(gettext('Unassigned'), 0, 'GroupView.php?GroupID=');
        if ($tmpMenu instanceof MenuItem) {
            $groupMenu->addSubMenu($tmpMenu);
        }

        if ($isAdmin) {
            $adminMenu = new MenuItem(gettext('Admin'), '', $isAdmin);
            $adminMenu->addSubMenu(new MenuItem(gettext('Group Properties'), 'PropertyList.php?Type=g', $isAdmin));
            $adminMenu->addSubMenu(new MenuItem(gettext('Group Types'), 'OptionManager.php?mode=grptypes', $isAdmin));

            $groupMenu->addSubMenu($adminMenu);
        }

        return $groupMenu;
    }

    private static function getSundaySchoolMenu(): MenuItem
    {
        $sundaySchoolMenu = new MenuItem(gettext('Sunday School'), '', SystemConfig::getBooleanValue('bEnabledSundaySchool'), 'fa-children');
        $sundaySchoolMenu->addSubMenu(new MenuItem(gettext('Dashboard'), 'sundayschool/SundaySchoolDashboard.php'));
        // now we're searching the unclassified groups
        $tmpMenu = self::addGroupSubMenus(gettext('Classes'), 4, 'sundayschool/SundaySchoolClassView.php?groupId=');
        if ($tmpMenu instanceof MenuItem) {
            $sundaySchoolMenu->addSubMenu($tmpMenu);
        }

        return $sundaySchoolMenu;
    }

    private static function getEventsMenu(bool $isAddEventEnabled): MenuItem
    {
        $eventsMenu = new MenuItem(gettext('Events'), '', SystemConfig::getBooleanValue('bEnabledEvents'), 'fa-ticket-alt');
        $eventsMenu->addSubMenu(new MenuItem(gettext('Add Church Event'), 'EventEditor.php', $isAddEventEnabled));
        $eventsMenu->addSubMenu(new MenuItem(gettext('List Church Events'), 'ListEvents.php'));
        $eventsMenu->addSubMenu(new MenuItem(gettext('List Event Types'), 'EventNames.php', $isAddEventEnabled));
        $eventsMenu->addSubMenu(new MenuItem(gettext('Check-in and Check-out'), 'Checkin.php'));
        $eventsMenu->addSubMenu(new MenuItem(gettext('Event Attendance Reports'), 'EventAttendance.php'));

        return $eventsMenu;
    }

    private static function getDepositsMenu(bool $isAdmin, bool $isFinanceEnabled): MenuItem
    {
        $depositsMenu = new MenuItem(gettext('Deposit'), '', SystemConfig::getBooleanValue('bEnabledFinance') && $isFinanceEnabled, 'fa-cash-register');
        $depositsMenu->addSubMenu(new MenuItem(gettext('View All Deposits'), 'FindDepositSlip.php', $isFinanceEnabled));
        $depositsMenu->addSubMenu(new MenuItem(gettext('Deposit Reports'), 'FinancialReports.php', $isFinanceEnabled));
        $depositsMenu->addSubMenu(new MenuItem(gettext('Edit Deposit Slip'), 'DepositSlipEditor.php?DepositSlipID=' . $_SESSION['iCurrentDeposit'], $isFinanceEnabled));

        if ($isAdmin) {
            $adminMenu = new MenuItem(gettext('Admin'), '', $isAdmin);
            $adminMenu->addSubMenu(new MenuItem(gettext('Envelope Manager'), 'ManageEnvelopes.php', $isAdmin));
            $adminMenu->addSubMenu(new MenuItem(gettext('Donation Funds'), 'DonationFundEditor.php', $isAdmin));

            $depositsMenu->addSubMenu($adminMenu);
        }
        return $depositsMenu;
    }

    private static function getFundraisersMenu(): MenuItem
    {
        $fundraiserMenu = new MenuItem(gettext('Fundraiser'), '', SystemConfig::getBooleanValue('bEnabledFundraiser'), 'fa-money-bill-alt');
        $fundraiserMenu->addSubMenu(new MenuItem(gettext('Create New Fundraiser'), 'FundRaiserEditor.php?FundRaiserID=-1'));
        $fundraiserMenu->addSubMenu(new MenuItem(gettext('View All Fundraisers'), 'FindFundRaiser.php'));
        $fundraiserMenu->addSubMenu(new MenuItem(gettext('Edit Fundraiser'), 'FundRaiserEditor.php'));
        $fundraiserMenu->addSubMenu(new MenuItem(gettext('Add Donors to Buyer List'), 'AddDonors.php'));
        $fundraiserMenu->addSubMenu(new MenuItem(gettext('View Buyers'), 'PaddleNumList.php'));
        $iCurrentFundraiser = 0;
        if (array_key_exists('iCurrentFundraiser', $_SESSION)) {
            $iCurrentFundraiser = $_SESSION['iCurrentFundraiser'];
        }
        $fundraiserMenu->addCounter(new MenuCounter('iCurrentFundraiser', 'bg-blue', $iCurrentFundraiser));

        return $fundraiserMenu;
    }

    private static function getReportsMenu(): MenuItem
    {
        $reportsMenu = new MenuItem(gettext('Data/Reports'), '', true, 'fa-file-pdf');
        $reportsMenu->addSubMenu(new MenuItem(gettext('Query Menu'), 'QueryList.php'));

        return $reportsMenu;
    }

    private static function addGroupSubMenus($menuName, $groupId, string $viewURl): ?MenuItem
    {
        $groups = GroupQuery::create()->filterByType($groupId)->orderByName()->find();
        if (!$groups->isEmpty()) {
            $unassignedGroups = new MenuItem($menuName, '', true, 'fa-tag');
            foreach ($groups as $group) {
                $unassignedGroups->addSubMenu(
                    new MenuItem(
                        $group->getName(),
                        $viewURl . $group->getID(),
                        true,
                        'fa-user-tag'
                    )
                );
            }

            return $unassignedGroups;
        }

        return null;
    }

    private static function getAdminMenu(bool $isAdmin): MenuItem
    {
        $menu = new MenuItem(gettext('Admin'), '', true, 'fa-tools');
        $menu->addSubMenu(new MenuItem(gettext('Edit General Settings'), 'SystemSettings.php', $isAdmin));
        $menu->addSubMenu(new MenuItem(gettext('System Users'), 'UserList.php', $isAdmin));
        $menu->addSubMenu(new MenuItem(gettext('Property Types'), 'PropertyTypeList.php', $isAdmin));
        $menu->addSubMenu(new MenuItem(gettext('System Maintenance'), 'admin/system/maintenance', $isAdmin));
        $menu->addSubMenu(new MenuItem(gettext('CSV Import'), 'CSVImport.php', $isAdmin));
        $menu->addSubMenu(new MenuItem(gettext('CSV Export Records'), 'CSVExport.php', $isAdmin));
        $menu->addSubMenu(new MenuItem(gettext('Kiosk Manager'), 'KioskManager.php', $isAdmin));
        $menu->addSubMenu(new MenuItem(gettext('Custom Menus'), 'v2/admin/menus', $isAdmin));
        return $menu;
    }

    private static function getCustomMenu(): MenuItem
    {
        $menu = new MenuItem(gettext('Links'), '', SystemConfig::getBooleanValue('bEnabledMenuLinks'), 'fa-link');
        $menuLinks = MenuLinkQuery::create()->orderByOrder()->find();
        foreach ($menuLinks as $link) {
            $menu->addSubMenu(new MenuItem($link->getName(), $link->getUri()));
        }

        return $menu;
    }
}
