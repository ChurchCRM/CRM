<?php

namespace ChurchCRM\Config\Menu;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\FundRaiserQuery;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\Plugin\Hook\HookManager;
use ChurchCRM\Plugin\Hooks;
use ChurchCRM\Plugin\PluginManager;

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
        $canViewEvents = $currentUser->canViewEvents();
        $menus = [
            'Dashboard'    => new MenuItem(gettext('Dashboard'), 'v2/dashboard', true, 'fa-gauge'),
            'Calendar'     => self::getCalendarMenu($canViewEvents),
            'People'       => self::getPeopleMenu($isAdmin, $isMenuOptions, $currentUser->isAddRecordsEnabled()),
            'Groups'       => self::getGroupMenu($isAdmin, $isMenuOptions, $isManageGroups),
            'SundaySchool' => self::getSundaySchoolMenu($isAdmin, $isManageGroups),
            'Communication' => self::getCommunicationMenu($currentUser->isEmailEnabled()),
            'Events'       => self::getEventsMenu($currentUser->isAddEventEnabled(), $canViewEvents),
            'Deposits'     => self::getDepositsMenu($isAdmin, $currentUser->isFinanceEnabled()),
            'Fundraiser'   => self::getFundraisersMenu($currentUser->isManageFundraisersEnabled()),
            'Reports'      => self::getReportsMenu(),
        ];
        
        // Backward compatibility: plugins that declare parent 'Email' still attach to Communication
        if (isset($menus['Communication'])) {
            $menus['Email'] = $menus['Communication'];
        }

        // Add plugin menu items to their parent menus
        self::addPluginMenuItems($menus);

        // Remove the backward-compat alias so it doesn't appear as a duplicate menu
        unset($menus['Email']);
        
        // Allow plugins to add top-level menus via the MENU_BUILDING hook
        $menus = HookManager::applyFilters(Hooks::MENU_BUILDING, $menus);
        
        // Admin menu is always last (at bottom of nav)
        if ($isAdmin) {
            $menus['Admin'] = self::getAdminMenu($isAdmin);
        }
        
        return $menus;

    }

    private static function getCalendarMenu(bool $canViewEvents): MenuItem
    {
        $calendarMenu = new MenuItem(gettext('Calendar'), 'event/calendars', $canViewEvents, 'fa-calendar');
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
        $peopleMenu = new MenuItem(gettext('People'), '', true, 'fa-people-group');
        $peopleMenu->addSubMenu(new MenuItem(gettext('Dashboard'), 'people/dashboard', true, 'fa-gauge'));
        $peopleMenu->addSubMenu(new MenuItem(gettext('Add New') . ' ' . gettext('Person'), 'PersonEditor.php', $isAddRecordsEnabled, 'fa-user-plus'));
        $peopleMenu->addSubMenu(new MenuItem(gettext('Person Listing'), 'people/list', true, 'fa-person-half-dress'));
        $peopleMenu->addSubMenu(new MenuItem(gettext('Photo Directory'), 'people/photos', true, 'fa-images'));
        $peopleMenu->addSubMenu(new MenuItem(gettext('Add New') . ' ' . gettext('Family'), 'FamilyEditor.php', $isAddRecordsEnabled, 'fa-people-roof'));
        $peopleMenu->addSubMenu(new MenuItem(gettext('Family Listing'), 'people/family', true, 'fa-people-roof'));
        $peopleMenu->addSubMenu(new MenuItem(gettext('Family Map'), 'v2/map', true, 'fa-map'));

        if ($isAdmin || $isMenuOptions) {
            $adminMenu = new MenuItem(gettext('Admin'), '', true);
            $adminMenu->addSubMenu(new MenuItem(gettext('Family Roles'), 'admin/system/options?mode=famroles', $isAdmin, 'fa-people-roof'));
            $adminMenu->addSubMenu(new MenuItem(gettext('Family Properties'), 'PropertyList.php?Type=f', $isMenuOptions, 'fa-people-roof'));
            $adminMenu->addSubMenu(new MenuItem(gettext('Family Custom Fields'), 'FamilyCustomFieldsEditor.php', $isAdmin, 'fa-sliders'));
            $adminMenu->addSubMenu(new MenuItem(gettext('Person Classifications'), 'admin/system/options?mode=classes', $isAdmin, 'fa-tags'));
            $adminMenu->addSubMenu(new MenuItem(gettext('Person Properties'), 'PropertyList.php?Type=p', $isMenuOptions, 'fa-person-half-dress'));
            $adminMenu->addSubMenu(new MenuItem(gettext('Person Custom Fields'), 'PersonCustomFieldsEditor.php', $isAdmin, 'fa-sliders'));
            $adminMenu->addSubMenu(new MenuItem(gettext('Volunteer Opportunities'), 'VolunteerOpportunityEditor.php', $isAdmin, 'fa-handshake-angle'));
    
            $peopleMenu->addSubMenu($adminMenu);
        }

        return $peopleMenu;
    }

    private static function getGroupMenu(bool $isAdmin, bool $isMenuOptions, bool $isManageGroups): MenuItem
    {
        $groupMenu = new MenuItem(gettext('Groups'), '', $isManageGroups, 'fa-users');
        if (!$isManageGroups) {
            // Every /groups route is behind ManageGroupRoleAuthMiddleware; skip the lookups.
            return $groupMenu;
        }

        $groupMenu->addSubMenu(new MenuItem(gettext('Dashboard'), 'groups/dashboard', true, 'fa-gauge'));
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
                $tmpMenu = self::addGroupSubMenus($listOption['OptionName'], $optionId, 'groups/view/', $groupsByType);
                if ($tmpMenu instanceof MenuItem) {
                    $groupMenu->addSubMenu($tmpMenu);
                }
            }
        }

        // now add the unclassified groups from the batched map
        $tmpMenu = self::addGroupSubMenus(gettext('Unassigned'), 0, 'groups/view/', $groupsByType);
        if ($tmpMenu instanceof MenuItem) {
            $groupMenu->addSubMenu($tmpMenu);
        }

        $canSeeGroupAdmin = $isAdmin || $isMenuOptions || $isManageGroups;
        if ($canSeeGroupAdmin) {
            $adminMenu = new MenuItem(gettext('Admin'), '', true);
            $adminMenu->addSubMenu(new MenuItem(gettext('Group Properties'), 'PropertyList.php?Type=g', true, 'fa-users'));
            $adminMenu->addSubMenu(new MenuItem(gettext('Group Types'), 'admin/system/options?mode=grptypes', $isAdmin, 'fa-tags'));
            $adminMenu->addSubMenu(new MenuItem(gettext('Kiosk Manager'), 'kiosk/admin', $isAdmin, 'fa-desktop'));

            $groupMenu->addSubMenu($adminMenu);
        }

        return $groupMenu;
    }

    private static function getSundaySchoolMenu(bool $isAdmin, bool $isManageGroups): MenuItem
    {
        $isEnabled = $isManageGroups && ($isAdmin || SystemConfig::getBooleanValue('bEnabledSundaySchool'));
        $sundaySchoolMenu = new MenuItem(gettext('Sunday School'), '', $isEnabled, 'fa-school');
        if (!$isEnabled) {
            // Sunday School pages live under /groups/sundayschool, behind ManageGroupRoleAuthMiddleware.
            return $sundaySchoolMenu;
        }

        $sundaySchoolMenu->addSubMenu(new MenuItem(gettext('Dashboard'), 'groups/sundayschool/dashboard', true, 'fa-gauge'));
        $classes = GroupQuery::create()->filterByType(4)->orderByName()->select(['Id','Name'])->find()->toArray();
        if (!empty($classes)) {
            foreach ($classes as $group) {
                $sundaySchoolMenu->addSubMenu(new MenuItem($group['Name'], 'groups/sundayschool/class/' . $group['Id'], true, 'fa-chalkboard'));
            }
        }

        return $sundaySchoolMenu;
    }

    private static function getCommunicationMenu(bool $isEmailEnabled): MenuItem
    {
        $commMenu = new MenuItem(gettext('Communication'), '', $isEmailEnabled, 'fa-comments');
        $commMenu->addSubMenu(new MenuItem(gettext('Email'), 'v2/email/dashboard', $isEmailEnabled, 'fa-envelope'));
        $commMenu->addSubMenu(new MenuItem(gettext('Text'), 'v2/text/dashboard', $isEmailEnabled, 'fa-comment-sms'));

        return $commMenu;
    }

    /**
     * Add plugin menu items to their parent menus.
     *
     * Plugins can register menu items via getMenuItems() which specify a 'parent' key.
     * This method merges those items into the appropriate parent menu.
     *
     * @param array<string, MenuItem> $menus The main menu array to modify
     */
    private static function addPluginMenuItems(array &$menus): void
    {
        try {
            $pluginMenuItems = PluginManager::getPluginMenuItems();
            
            foreach ($pluginMenuItems as $parentKey => $items) {
                // Find the parent menu (case-insensitive match)
                $parentMenu = null;
                foreach ($menus as $menuKey => $menu) {
                    if (strtolower($menuKey) === $parentKey) {
                        $parentMenu = $menu;
                        break;
                    }
                }
                
                if ($parentMenu === null) {
                    // Parent menu not found, skip these items
                    continue;
                }
                
                // Add each plugin menu item as a submenu
                foreach ($items as $item) {
                    $label = $item['label'] ?? '';
                    $url = $item['url'] ?? '';
                    $icon = $item['icon'] ?? 'fa-plug';
                    
                    if (!empty($label) && !empty($url)) {
                        $parentMenu->addSubMenu(new MenuItem($label, $url, true, $icon));
                    }
                }
            }
        } catch (\Throwable $e) {
            // Don't let plugin errors break the menu
            // Silently fail - plugins may not be initialized yet
        }
    }

    private static function getEventsMenu(bool $isAddEventEnabled, bool $canViewEvents): MenuItem
    {
        $eventsMenu = new MenuItem(gettext('Events'), '', $canViewEvents, 'fa-ticket');
        $eventsMenu->addSubMenu(new MenuItem(gettext('Events Dashboard'), 'event/dashboard', true, 'fa-gauge'));
        $eventsMenu->addSubMenu(new MenuItem(gettext('Add Church Event'), 'event/editor', $isAddEventEnabled, 'fa-circle-plus'));
        $eventsMenu->addSubMenu(new MenuItem(gettext('Check-in and Check-out'), 'event/checkin', true, 'fa-user-check'));

        if ($isAddEventEnabled) {
            $adminMenu = new MenuItem(gettext('Admin'), '', true);
            $adminMenu->addSubMenu(new MenuItem(gettext('Event Types'), 'event/types', true, 'fa-tags'));
            $eventsMenu->addSubMenu($adminMenu);
        }

        return $eventsMenu;
    }

    private static function getDepositsMenu(bool $isAdmin, bool $isFinanceEnabled): MenuItem
    {
        // $isFinanceEnabled already includes admin bypass and checks bEnabledFinance
        $depositsMenu = new MenuItem(gettext('Finance'), '', $isFinanceEnabled, 'fa-cash-register');
        $depositsMenu->addSubMenu(new MenuItem(gettext('Dashboard'), 'finance/', $isFinanceEnabled, 'fa-gauge'));
        $depositsMenu->addSubMenu(new MenuItem(gettext('View All Deposits'), 'FindDepositSlip.php', $isFinanceEnabled, 'fa-list'));
        $depositsMenu->addSubMenu(new MenuItem(gettext('Deposit Reports'), 'finance/reports', $isFinanceEnabled, 'fa-file-invoice'));
        $depositsMenu->addSubMenu(new MenuItem(gettext('Pledge Dashboard'), 'finance/pledge/dashboard', $isFinanceEnabled, 'fa-handshake'));
        $depositsMenu->addSubMenu(new MenuItem(gettext('Edit Deposit Slip'), 'DepositSlipEditor.php?DepositSlipID=' . $_SESSION['iCurrentDeposit'], $isFinanceEnabled, 'fa-pen-to-square'));

        if ($isAdmin) {
            $adminMenu = new MenuItem(gettext('Admin'), '', $isAdmin);
            $adminMenu->addSubMenu(new MenuItem(gettext('Envelope Manager'), 'ManageEnvelopes.php', $isAdmin, 'fa-envelope'));
            $adminMenu->addSubMenu(new MenuItem(gettext('Donation Funds'), 'DonationFundEditor.php', $isAdmin, 'fa-piggy-bank'));

            $depositsMenu->addSubMenu($adminMenu);
        }
        return $depositsMenu;
    }

    private static function getFundraisersMenu(bool $canManageFundraisers): MenuItem
    {
        $iCurrentFundraiser = 0;
        if (array_key_exists('iCurrentFundraiser', $_SESSION)) {
            $iCurrentFundraiser = (int) $_SESSION['iCurrentFundraiser'];
        }

        // Build context-aware URLs for actions that require an active fundraiser
        $addDonorsUrl   = $iCurrentFundraiser > 0 ? 'fundraiser/' . $iCurrentFundraiser . '/donors' : 'fundraiser/';
        $viewBuyersUrl  = $iCurrentFundraiser > 0 ? 'fundraiser/' . $iCurrentFundraiser . '/paddle-numbers' : 'fundraiser/';

        $fundraiserMenu = new MenuItem(gettext('Fundraiser'), '', $canManageFundraisers, 'fa-money-bill-1');
        $fundraiserMenu->addSubMenu(new MenuItem(gettext('Dashboard'), 'fundraiser/', true, 'fa-list'));
        $fundraiserMenu->addSubMenu(new MenuItem(gettext('Create New Fundraiser'), 'fundraiser/editor', true, 'fa-circle-plus'));
        $fundraiserMenu->addSubMenu(new MenuItem(gettext('Add Donors to Buyer List'), $addDonorsUrl, true, 'fa-user-plus'));
        $fundraiserMenu->addSubMenu(new MenuItem(gettext('View Buyers'), $viewBuyersUrl, true, 'fa-users'));
        // Show count of active/planning fundraisers instead of a raw session ID.
        // Cached in $_SESSION to avoid a DB query on every page load; the landing
        // page route and the editor/delete routes reset it on state changes.
        if (!isset($_SESSION['iFundraiserActiveCount'])) {
            try {
                $_SESSION['iFundraiserActiveCount'] = FundRaiserQuery::create()
                    ->filterByStatus(['Active', 'Planning'])
                    ->count();
            } catch (\Throwable $e) {
                $_SESSION['iFundraiserActiveCount'] = 0;
            }
        }
        $activeFundraiserCount = (int) $_SESSION['iFundraiserActiveCount'];

        $fundraiserMenu->addCounter(new MenuCounter('activeFundraisers', 'bg-blue', $activeFundraiserCount, gettext('Active Fundraisers')));

        return $fundraiserMenu;
    }

    private static function getReportsMenu(): MenuItem
    {
        // Query Menu is the only entry, so link straight to it rather than nesting a single child.
        return new MenuItem(gettext('Data/Reports'), 'QueryList.php', true, 'fa-database');
    }

    private static function addGroupSubMenus($menuName, $groupId, string $viewURl, ?array $groupsByType = null): ?MenuItem
    {
        // If a pre-built groups map is provided, use it to avoid DB queries
        if (is_array($groupsByType) && array_key_exists((int)$groupId, $groupsByType) && count($groupsByType[(int)$groupId]) > 0) {
            $items = $groupsByType[(int)$groupId];
            $menu = new MenuItem($menuName, '', true, 'fa-tag');
            foreach ($items as $group) {
                $menu->addSubMenu(new MenuItem($group['Name'], $viewURl . $group['Id'], true, 'fa-users'));
            }
            return $menu;
        }

        // Fallback to per-type query if no groups map was provided
        $groups = GroupQuery::create()->filterByType($groupId)->orderByName()->select(['Id','Name'])->find()->toArray();
        if (!empty($groups)) {
            $unassignedGroups = new MenuItem($menuName, '', true, 'fa-tag');
            foreach ($groups as $group) {
                $unassignedGroups->addSubMenu(new MenuItem($group['Name'], $viewURl . $group['Id'], true, 'fa-users'));
            }
            return $unassignedGroups;
        }

        return null;
    }

    private static function getAdminMenu(bool $isAdmin): MenuItem
    {
        $menu = new MenuItem(gettext('Admin'), '', true, 'fa-screwdriver-wrench');
        $menu->addSubMenu(new MenuItem(gettext('Admin Dashboard'), 'admin/', $isAdmin, 'fa-gauge'));
        $menu->addSubMenu(new MenuItem(gettext('Church Information'), 'admin/system/church-info', $isAdmin, 'fa-church'));
        $menu->addSubMenu(new MenuItem(gettext('Localization & Formats'), 'admin/system/localization', $isAdmin, 'fa-globe'));
        $menu->addSubMenu(new MenuItem(gettext('Get Started'), 'admin/get-started', $isAdmin, 'fa-rocket'));
        $menu->addSubMenu(new MenuItem(gettext('System Users'), 'admin/system/users', $isAdmin, 'fa-user-gear'));
        $menu->addSubMenu(new MenuItem(gettext('System Settings'), 'SystemSettings.php', $isAdmin, 'fa-gear'));
        $menu->addSubMenu(new MenuItem(gettext('Plugins'), 'plugins/management', $isAdmin, 'fa-plug'));
        $menu->addSubMenu(new MenuItem(gettext('Export'), 'admin/export', $isAdmin, 'fa-file-export'));

        return $menu;
    }
}
