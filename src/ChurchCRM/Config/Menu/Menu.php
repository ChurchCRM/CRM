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
     * @var Config[]
     */
    private static ?array $menuItems = null;

    public static function init(): void
    {
        self::$menuItems = self::buildMenuItems();
        /*if (!empty($menuItems)) {
            self::scrapeDBMenuItems($menuItems);
        }*/
    }

    public static function getMenu(): ?array
    {
        return self::$menuItems;
    }

    private static function buildMenuItems(): array
    {
        return [
            'Dashboard'    => new MenuItem(gettext('Dashboard'), 'v2/dashboard', true, 'fa-tachometer-alt'),
            'Calendar'     => self::getCalendarMenu(),
            'People'       => self::getPeopleMenu(),
            'Groups'       => self::getGroupMenu(),
            'SundaySchool' => self::getSundaySchoolMenu(),
            'Email'        => new MenuItem(gettext('Email'), 'v2/email/dashboard', SystemConfig::getBooleanValue('bEnabledEmail'), 'fa-envelope'),
            'Events'       => self::getEventsMenu(),
            'Deposits'     => self::getDepositsMenu(),
            'Fundraiser'   => self::getFundraisersMenu(),
            'Reports'      => self::getReportsMenu(),
            'Admin'        => self::getAdminMenu(),
            'Custom'       => self::getCustomMenu(),
        ];
    }

    private static function getCalendarMenu(): MenuItem
    {
        $calendarMenu = new MenuItem(gettext('Calendar'), 'v2/calendar', SystemConfig::getBooleanValue('bEnabledCalendar'), 'fa-calendar');
        $calendarMenu->addCounter(new MenuCounter('AnniversaryNumber', 'bg-blue'));
        $calendarMenu->addCounter(new MenuCounter('BirthdateNumber', 'bg-red'));
        $calendarMenu->addCounter(new MenuCounter('EventsNumber', 'bg-yellow'));

        return $calendarMenu;
    }

    private static function getPeopleMenu(): MenuItem
    {
        $peopleMenu = new MenuItem(gettext('People'), '', true, 'fa-user');
        $peopleMenu->addSubMenu(new MenuItem(gettext('Dashboard'), 'PeopleDashboard.php'));
        $peopleMenu->addSubMenu(new MenuItem(gettext('Add New Person'), 'PersonEditor.php', AuthenticationManager::getCurrentUser()->isAddRecordsEnabled()));
        $peopleMenu->addSubMenu(new MenuItem(gettext('View Active People'), 'v2/people'));
        $peopleMenu->addSubMenu(new MenuItem(gettext('View Inactive People'), 'v2/people?familyActiveStatus=inactive'));
        $peopleMenu->addSubMenu(new MenuItem(gettext('View All People'), 'v2/people?familyActiveStatus=all'));
        $peopleMenu->addSubMenu(new MenuItem(gettext('Add New Family'), 'FamilyEditor.php', AuthenticationManager::getCurrentUser()->isAddRecordsEnabled()));
        $peopleMenu->addSubMenu(new MenuItem(gettext('View Active Families'), 'v2/family'));
        $peopleMenu->addSubMenu(new MenuItem(gettext('View Inactive Families'), 'v2/family?mode=inactive'));
        $adminMenu = new MenuItem(gettext('Admin'), '', AuthenticationManager::getCurrentUser()->isAdmin());
        $adminMenu->addSubMenu(new MenuItem(gettext('Classifications Manager'), 'OptionManager.php?mode=classes', AuthenticationManager::getCurrentUser()->isAdmin()));
        $adminMenu->addSubMenu(new MenuItem(gettext('Family Roles'), 'OptionManager.php?mode=famroles', AuthenticationManager::getCurrentUser()->isAdmin()));
        $adminMenu->addSubMenu(new MenuItem(gettext('Family Properties'), 'PropertyList.php?Type=f', AuthenticationManager::getCurrentUser()->isAdmin()));
        $adminMenu->addSubMenu(new MenuItem(gettext('Family Custom Fields'), 'FamilyCustomFieldsEditor.php', AuthenticationManager::getCurrentUser()->isAdmin()));
        $adminMenu->addSubMenu(new MenuItem(gettext('People Properties'), 'PropertyList.php?Type=p', AuthenticationManager::getCurrentUser()->isAdmin()));
        $adminMenu->addSubMenu(new MenuItem(gettext('Person Custom Fields'), 'PersonCustomFieldsEditor.php', AuthenticationManager::getCurrentUser()->isAdmin()));
        $adminMenu->addSubMenu(new MenuItem(gettext('Volunteer Opportunities'), 'VolunteerOpportunityEditor.php', AuthenticationManager::getCurrentUser()->isAdmin()));

        $peopleMenu->addSubMenu($adminMenu);

        return $peopleMenu;
    }

    private static function getGroupMenu(): MenuItem
    {
        $groupMenu = new MenuItem(gettext('Groups'), '', true, 'fa-users');
        $groupMenu->addSubMenu(new MenuItem(gettext('List Groups'), 'GroupList.php'));

        $listOptions = ListOptionQuery::Create()->filterById(3)->orderByOptionSequence()->find();

        foreach ($listOptions as $listOption) {
            if ($listOption->getOptionId() != 4) {// we avoid the sundaySchool, it's done under
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

        $adminMenu = new MenuItem(gettext('Admin'), '', AuthenticationManager::getCurrentUser()->isAdmin());
        $adminMenu->addSubMenu(new MenuItem(gettext('Group Properties'), 'PropertyList.php?Type=g', AuthenticationManager::getCurrentUser()->isAdmin()));
        $adminMenu->addSubMenu(new MenuItem(gettext('Group Types'), 'OptionManager.php?mode=grptypes', AuthenticationManager::getCurrentUser()->isAdmin()));

        $groupMenu->addSubMenu($adminMenu);

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

    private static function getEventsMenu(): MenuItem
    {
        $eventsMenu = new MenuItem(gettext('Events'), '', SystemConfig::getBooleanValue('bEnabledEvents'), 'fa-ticket-alt');
        $eventsMenu->addSubMenu(new MenuItem(gettext('Add Church Event'), 'EventEditor.php', AuthenticationManager::getCurrentUser()->isAddEventEnabled()));
        $eventsMenu->addSubMenu(new MenuItem(gettext('List Church Events'), 'ListEvents.php'));
        $eventsMenu->addSubMenu(new MenuItem(gettext('List Event Types'), 'EventNames.php', AuthenticationManager::getCurrentUser()->isAddEventEnabled()));
        $eventsMenu->addSubMenu(new MenuItem(gettext('Check-in and Check-out'), 'Checkin.php'));
        $eventsMenu->addSubMenu(new MenuItem(gettext('Event Attendance Reports'), 'EventAttendance.php'));

        return $eventsMenu;
    }

    private static function getDepositsMenu(): MenuItem
    {
        $depositsMenu = new MenuItem(gettext('Deposit'), '', SystemConfig::getBooleanValue('bEnabledFinance') && AuthenticationManager::getCurrentUser()->isFinanceEnabled(), 'fa-cash-register');
        $depositsMenu->addSubMenu(new MenuItem(gettext('View All Deposits'), 'FindDepositSlip.php', AuthenticationManager::getCurrentUser()->isFinanceEnabled()));
        $depositsMenu->addSubMenu(new MenuItem(gettext('Deposit Reports'), 'FinancialReports.php', AuthenticationManager::getCurrentUser()->isFinanceEnabled()));
        $depositsMenu->addSubMenu(new MenuItem(gettext('Edit Deposit Slip'), 'DepositSlipEditor.php?DepositSlipID=' . $_SESSION['iCurrentDeposit'], AuthenticationManager::getCurrentUser()->isFinanceEnabled()));
        $depositsMenu->addCounter(new MenuCounter('iCurrentDeposit', 'bg-green', $_SESSION['iCurrentDeposit']));

        $adminMenu = new MenuItem(gettext('Admin'), '', AuthenticationManager::getCurrentUser()->isAdmin());
        $adminMenu->addSubMenu(new MenuItem(gettext('Envelope Manager'), 'ManageEnvelopes.php', AuthenticationManager::getCurrentUser()->isAdmin()));
        $adminMenu->addSubMenu(new MenuItem(gettext('Donation Funds'), 'DonationFundEditor.php', AuthenticationManager::getCurrentUser()->isAdmin()));

        $depositsMenu->addSubMenu($adminMenu);

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
        $reportsMenu->addSubMenu(new MenuItem(gettext('Canvass Automation'), 'CanvassAutomation.php'));
        $reportsMenu->addSubMenu(new MenuItem(gettext('Query Menu'), 'QueryList.php'));

        return $reportsMenu;
    }

    private static function addGroupSubMenus($menuName, $groupId, string $viewURl): ?MenuItem
    {
        $groups = GroupQuery::Create()->filterByType($groupId)->orderByName()->find();
        if (!$groups->isEmpty()) {
            $unassignedGroups = new MenuItem($menuName, '', true, 'fa-tag');
            foreach ($groups as $group) {
                $unassignedGroups->addSubMenu(new MenuItem($group->getName(), $viewURl . $group->getID(), true, 'fa-user-tag'));
            }

            return $unassignedGroups;
        }

        return null;
    }

    private static function getAdminMenu(): MenuItem
    {
        $menu = new MenuItem(gettext('Admin'), '', true, 'fa-tools');
        $menu->addSubMenu(new MenuItem(gettext('Edit General Settings'), 'SystemSettings.php', AuthenticationManager::getCurrentUser()->isAdmin()));
        $menu->addSubMenu(new MenuItem(gettext('System Users'), 'UserList.php', AuthenticationManager::getCurrentUser()->isAdmin()));
        $menu->addSubMenu(new MenuItem(gettext('Property Types'), 'PropertyTypeList.php', AuthenticationManager::getCurrentUser()->isAdmin()));
        $menu->addSubMenu(new MenuItem(gettext('Restore Database'), 'RestoreDatabase.php', AuthenticationManager::getCurrentUser()->isAdmin()));
        $menu->addSubMenu(new MenuItem(gettext('Backup Database'), 'BackupDatabase.php', AuthenticationManager::getCurrentUser()->isAdmin()));
        $menu->addSubMenu(new MenuItem(gettext('CSV Import'), 'CSVImport.php', AuthenticationManager::getCurrentUser()->isAdmin()));
        $menu->addSubMenu(new MenuItem(gettext('CSV Export Records'), 'CSVExport.php', AuthenticationManager::getCurrentUser()->isCSVExport()));
        $menu->addSubMenu(new MenuItem(gettext('Kiosk Manager'), 'KioskManager.php', AuthenticationManager::getCurrentUser()->isAdmin()));
        $menu->addSubMenu(new MenuItem(gettext('Debug'), 'v2/admin/debug', AuthenticationManager::getCurrentUser()->isAdmin()));
        $menu->addSubMenu(new MenuItem(gettext('Custom Menus'), 'v2/admin/menus', AuthenticationManager::getCurrentUser()->isAdmin()));
        $menu->addSubMenu(new MenuItem(gettext('Reset System'), 'v2/admin/database/reset', AuthenticationManager::getCurrentUser()->isAdmin()));

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
