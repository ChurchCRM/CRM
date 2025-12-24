<?php

namespace ChurchCRM\Config\Menu;

use ChurchCRM\Authentication\AuthenticationManager;
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
        $isMenuOptions = $currentUser->isMenuOptionsEnabled();
        $isManageGroups = $currentUser->isManageGroupsEnabled();
        $menus = [
            'Dashboard'    => new MenuItem(gettext('Dashboard'), 'v2/dashboard', true, 'fa-tachometer-alt'),
            'Calendar'     => self::getCalendarMenu(),
            'People'       => self::getPeopleMenu($isAdmin, $isMenuOptions, $currentUser->isAddRecordsEnabled()),
            'Groups'       => self::getGroupMenu($isAdmin, $isMenuOptions, $isManageGroups),
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

    private static function getPeopleMenu(bool $isAdmin, bool $isMenuOptions, bool $isAddRecordsEnabled): MenuItem
    {
        $peopleMenu = new MenuItem(gettext('People'), '', true, 'fa-user');
        $peopleMenu->addSubMenu(new MenuItem(gettext('Dashboard'), 'PeopleDashboard.php', true, 'fa-tachometer-alt'));
        $peopleMenu->addSubMenu(new MenuItem(gettext('Add New') . ' ' . gettext('Person'), 'PersonEditor.php', $isAddRecordsEnabled, 'fa-user-plus'));
        $peopleMenu->addSubMenu(new MenuItem(gettext('Person Listing'), 'v2/people', true, 'fa-list'));
        $peopleMenu->addSubMenu(new MenuItem(gettext('Add New') . ' ' . gettext('Family'), 'FamilyEditor.php', $isAddRecordsEnabled, 'fa-user-friends'));
        $peopleMenu->addSubMenu(new MenuItem(gettext('Family Listing'), 'v2/family', true, 'fa-home'));

        if ($isAdmin || $isMenuOptions) {
            $adminMenu = new MenuItem(gettext('Admin'), '', true);
            $adminMenu->addSubMenu(new MenuItem(gettext('Family Roles'), 'OptionManager.php?mode=famroles', $isMenuOptions, 'fa-id-badge'));
            $adminMenu->addSubMenu(new MenuItem(gettext('Family Properties'), 'PropertyList.php?Type=f', $isMenuOptions, 'fa-th-list'));
            $adminMenu->addSubMenu(new MenuItem(gettext('Family Custom Fields'), 'FamilyCustomFieldsEditor.php', $isAdmin, 'fa-sliders-h'));
            $adminMenu->addSubMenu(new MenuItem(gettext('Person Classifications'), 'OptionManager.php?mode=classes', $isMenuOptions, 'fa-tags'));
            $adminMenu->addSubMenu(new MenuItem(gettext('Person Properties'), 'PropertyList.php?Type=p', $isMenuOptions, 'fa-list-alt'));
            $adminMenu->addSubMenu(new MenuItem(gettext('Person Custom Fields'), 'PersonCustomFieldsEditor.php', $isAdmin, 'fa-sliders-h'));
            $adminMenu->addSubMenu(new MenuItem(gettext('Volunteer Opportunities'), 'VolunteerOpportunityEditor.php', $isAdmin, 'fa-hands-helping'));
    
            $peopleMenu->addSubMenu($adminMenu);
        }

        return $peopleMenu;
    }

    private static function getGroupMenu(bool $isAdmin, bool $isMenuOptions, bool $isManageGroups): MenuItem
    {
        $groupMenu = new MenuItem(gettext('Groups'), '', true, 'fa-users');
        $groupMenu->addSubMenu(new MenuItem(gettext('List Groups'), 'GroupList.php', true, 'fa-list'));
        // fetch list options lightweight (only name/id)
        $listOptions = ListOptionQuery::create()
            ->filterById(3)
            ->orderByOptionSequence()
            ->select(['OptionName', 'OptionId'])
            ->find()
            ->toArray();

        // collect types we will need groups for (include unassigned = 0)
        $types = [];
        foreach ($listOptions as $opt) {
            $types[] = (int)$opt['OptionId'];
        }
        $types[] = 0;

        // batch fetch groups for all needed types (Id, Name, Type only)
        $groups = GroupQuery::create()
            ->filterByType($types)
            ->orderByType()
            ->orderByName()
            ->select(['Id', 'Name', 'Type'])
            ->find()
            ->toArray();

        // build map grouped by type
        $groupsByType = [];
        foreach ($groups as $g) {
            $type = (int)$g['Type'];
            $groupsByType[$type][] = $g;
        }

        // build submenus using in-memory groups map (skip sunday school option id=4)
        foreach ($listOptions as $listOption) {
            $optionId = (int)$listOption['OptionId'];
            if ($optionId !== 4) {
                $tmpMenu = self::addGroupSubMenus($listOption['OptionName'], $optionId, 'GroupView.php?GroupID=', $groupsByType);
                if ($tmpMenu instanceof MenuItem) {
                    $groupMenu->addSubMenu($tmpMenu);
                }
            }
        }

        // now add the unclassified groups from the batched map
        $tmpMenu = self::addGroupSubMenus(gettext('Unassigned'), 0, 'GroupView.php?GroupID=', $groupsByType);
        if ($tmpMenu instanceof MenuItem) {
            $groupMenu->addSubMenu($tmpMenu);
        }

        $canSeeGroupAdmin = $isAdmin || $isMenuOptions || $isManageGroups;
        if ($canSeeGroupAdmin) {
            $adminMenu = new MenuItem(gettext('Admin'), '', true);
            $adminMenu->addSubMenu(new MenuItem(gettext('Group Properties'), 'PropertyList.php?Type=g', true, 'fa-th-list'));
            $adminMenu->addSubMenu(new MenuItem(gettext('Group Types'), 'OptionManager.php?mode=grptypes', true, 'fa-tags'));

            $groupMenu->addSubMenu($adminMenu);
        }

        return $groupMenu;
    }

    private static function getSundaySchoolMenu(): MenuItem
    {
        $sundaySchoolMenu = new MenuItem(gettext('Sunday School'), '', SystemConfig::getBooleanValue('bEnabledSundaySchool'), 'fa-children');
        $sundaySchoolMenu->addSubMenu(new MenuItem(gettext('Dashboard'), 'sundayschool/SundaySchoolDashboard.php', true, 'fa-chalkboard-teacher'));
        // fetch classes (type 4) using lightweight select so this is cheap
        $classes = GroupQuery::create()->filterByType(4)->orderByName()->select(['Id','Name'])->find()->toArray();
        if (!empty($classes)) {
            foreach ($classes as $group) {
                $sundaySchoolMenu->addSubMenu(new MenuItem($group['Name'], 'sundayschool/SundaySchoolClassView.php?groupId=' . $group['Id'], true, 'fa-user-tag'));
            }
        }

        return $sundaySchoolMenu;
    }

    private static function getEventsMenu(bool $isAddEventEnabled): MenuItem
    {
        $eventsMenu = new MenuItem(gettext('Events'), '', SystemConfig::getBooleanValue('bEnabledEvents'), 'fa-ticket-alt');
        $eventsMenu->addSubMenu(new MenuItem(gettext('List Church Events'), 'ListEvents.php', true, 'fa-list'));
        $eventsMenu->addSubMenu(new MenuItem(gettext('Add Church Event'), 'EventEditor.php', $isAddEventEnabled, 'fa-plus-circle'));
        $eventsMenu->addSubMenu(new MenuItem(gettext('Check-in and Check-out'), 'Checkin.php', true, 'fa-user-check'));
        $eventsMenu->addSubMenu(new MenuItem(gettext('List Event Types'), 'EventNames.php', $isAddEventEnabled, 'fa-tags'));

        return $eventsMenu;
    }

    private static function getDepositsMenu(bool $isAdmin, bool $isFinanceEnabled): MenuItem
    {
        $depositsMenu = new MenuItem(gettext('Finance'), '', SystemConfig::getBooleanValue('bEnabledFinance') && $isFinanceEnabled, 'fa-cash-register');
        $depositsMenu->addSubMenu(new MenuItem(gettext('Dashboard'), 'finance/', $isFinanceEnabled, 'fa-tachometer-alt'));
        $depositsMenu->addSubMenu(new MenuItem(gettext('View All Deposits'), 'FindDepositSlip.php', $isFinanceEnabled, 'fa-list'));
        $depositsMenu->addSubMenu(new MenuItem(gettext('Deposit Reports'), 'finance/reports', $isFinanceEnabled, 'fa-file-invoice'));
        $depositsMenu->addSubMenu(new MenuItem(gettext('Edit Deposit Slip'), 'DepositSlipEditor.php?DepositSlipID=' . $_SESSION['iCurrentDeposit'], $isFinanceEnabled, 'fa-edit'));

        if ($isAdmin) {
            $adminMenu = new MenuItem(gettext('Admin'), '', $isAdmin);
            $adminMenu->addSubMenu(new MenuItem(gettext('Envelope Manager'), 'ManageEnvelopes.php', $isAdmin, 'fa-envelope'));
            $adminMenu->addSubMenu(new MenuItem(gettext('Donation Funds'), 'DonationFundEditor.php', $isAdmin, 'fa-piggy-bank'));

            $depositsMenu->addSubMenu($adminMenu);
        }
        return $depositsMenu;
    }

    private static function getFundraisersMenu(): MenuItem
    {
        $fundraiserMenu = new MenuItem(gettext('Fundraiser'), '', SystemConfig::getBooleanValue('bEnabledFundraiser'), 'fa-money-bill-alt');
        $fundraiserMenu->addSubMenu(new MenuItem(gettext('Create New Fundraiser'), 'FundRaiserEditor.php?FundRaiserID=-1', true, 'fa-plus-circle'));
        $fundraiserMenu->addSubMenu(new MenuItem(gettext('View All Fundraisers'), 'FindFundRaiser.php', true, 'fa-list'));
        $fundraiserMenu->addSubMenu(new MenuItem(gettext('Edit Fundraiser'), 'FundRaiserEditor.php', true, 'fa-edit'));
        $fundraiserMenu->addSubMenu(new MenuItem(gettext('Add Donors to Buyer List'), 'AddDonors.php', true, 'fa-user-plus'));
        $fundraiserMenu->addSubMenu(new MenuItem(gettext('View Buyers'), 'PaddleNumList.php', true, 'fa-users'));
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
        $reportsMenu->addSubMenu(new MenuItem(gettext('Query Menu'), 'QueryList.php', true, 'fa-search'));

        return $reportsMenu;
    }

    private static function addGroupSubMenus($menuName, $groupId, string $viewURl, ?array $groupsByType = null): ?MenuItem
    {
        // If a pre-built groups map is provided, use it to avoid DB queries
        if (is_array($groupsByType) && array_key_exists((int)$groupId, $groupsByType) && count($groupsByType[(int)$groupId]) > 0) {
            $items = $groupsByType[(int)$groupId];
            $menu = new MenuItem($menuName, '', true, 'fa-tag');
            foreach ($items as $group) {
                $menu->addSubMenu(new MenuItem($group['Name'], $viewURl . $group['Id'], true, 'fa-user-tag'));
            }
            return $menu;
        }

        // Fallback to per-type query if no groups map was provided
        $groups = GroupQuery::create()->filterByType($groupId)->orderByName()->select(['Id','Name'])->find()->toArray();
        if (!empty($groups)) {
            $unassignedGroups = new MenuItem($menuName, '', true, 'fa-tag');
            foreach ($groups as $group) {
                $unassignedGroups->addSubMenu(new MenuItem($group['Name'], $viewURl . $group['Id'], true, 'fa-user-tag'));
            }
            return $unassignedGroups;
        }

        return null;
    }

    private static function getAdminMenu(bool $isAdmin): MenuItem
    {
        $menu = new MenuItem(gettext('Admin'), '', true, 'fa-tools');
        $menu->addSubMenu(new MenuItem(gettext('Admin Dashboard'), 'admin/', $isAdmin, 'fa-tachometer-alt'));
        $menu->addSubMenu(new MenuItem(gettext('System Users'), 'admin/system/users', $isAdmin, 'fa-user-cog'));
        $menu->addSubMenu(new MenuItem(gettext('System Settings'), 'SystemSettings.php', $isAdmin, 'fa-cog'));
        $menu->addSubMenu(new MenuItem(gettext('CSV Import'), 'CSVImport.php', $isAdmin, 'fa-file-import'));
        $menu->addSubMenu(new MenuItem(gettext('CSV Export Records'), 'CSVExport.php', $isAdmin, 'fa-file-export'));
        $menu->addSubMenu(new MenuItem(gettext('Property Types'), 'PropertyTypeList.php', $isAdmin, 'fa-th-list'));
        $menu->addSubMenu(new MenuItem(gettext('Kiosk Manager'), 'KioskManager.php', $isAdmin, 'fa-desktop'));
        $menu->addSubMenu(new MenuItem(gettext('Custom Menus'), 'admin/system/menus', $isAdmin, 'fa-list-ul'));
        return $menu;
    }

    private static function getCustomMenu(): MenuItem
    {
        $menu = new MenuItem(gettext('Links'), '', SystemConfig::getBooleanValue('bEnabledMenuLinks'), 'fa-link');
        $menuLinks = MenuLinkQuery::create()->orderByOrder()->select(['Name','Uri'])->find()->toArray();
        foreach ($menuLinks as $link) {
            $menu->addSubMenu(new MenuItem($link['Name'], $link['Uri'], true, 'fa-external-link-alt'));
        }

        return $menu;
    }
}
