<?php

namespace ChurchCRM\Config\Menu;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\VolunteerOccurrenceQuery;
use ChurchCRM\model\ChurchCRM\VolunteerScheduleQuery;
use ChurchCRM\Plugin\Hook\HookManager;
use ChurchCRM\Plugin\Hooks;
use ChurchCRM\Plugin\PluginManager;
use ChurchCRM\Service\FundRaiserService;
use ChurchCRM\Volunteer\Service\VolunteerAuthorizationService;

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
        $isVolunteerV1Enabled = User::isVolunteerV1Enabled();
        // #9706: the Volunteer menu mirrors VolunteerCoordinatorRoleAuthMiddleware exactly.
        // Computed once here like every other visibility boolean; the predicate memoises
        // the scope query on the User instance so the route gate reuses it.
        $isVolunteerCoordinator = $currentUser->isVolunteerCoordinatorEnabled();
        $menus = [
            'Dashboard'    => new MenuItem(gettext('Dashboard'), 'v2/dashboard', true, 'fa-gauge'),
            'Calendar'     => self::getCalendarMenu($canViewEvents),
            'People'       => self::getPeopleMenu($isAdmin, $isMenuOptions, $currentUser->isAddRecordsEnabled(), $isVolunteerV1Enabled),
            'Groups'       => self::getGroupMenu($isAdmin, $isMenuOptions, $isManageGroups),
            'SundaySchool' => self::getSundaySchoolMenu($isAdmin, $isManageGroups),
            'Communication' => self::getCommunicationMenu($currentUser->isEmailEnabled()),
            'Events'       => self::getEventsMenu($currentUser->isAddEventEnabled(), $canViewEvents, $currentUser->canWriteEvents()),
            // No "Volunteer" heading: the member surface lives only in the
            // Member Portal now (#9867, Member Portal design P16). "Ministries"
            // below is the administration surface and is unchanged.
            'Ministries'   => self::getMinistriesMenu($currentUser, $isVolunteerCoordinator),
            'Deposits'     => self::getDepositsMenu($isAdmin, $currentUser->isFinanceEnabled()),
            'Fundraiser'   => self::getFundraisersMenu($currentUser->isManageFundraisersEnabled()),
            'Reports'      => self::getReportsMenu($isAdmin),
        ];
        
        // Backward compatibility: plugins that declare parent 'Email' still attach to Communication
        if (isset($menus['Communication'])) {
            $menus['Email'] = $menus['Communication'];
        }

        // Admin menu is always last (at bottom of nav)
        if ($isAdmin) {
            $menus['Admin'] = self::getAdminMenu($isAdmin);
        }

        // Add plugin menu items to their parent menus (must run after Admin menu is set,
        // so plugins can attach submenu items to the Admin menu)
        self::addPluginMenuItems($menus);

        // Remove the backward-compat alias so it doesn't appear as a duplicate menu
        unset($menus['Email']);
        
        // Allow plugins to add top-level menus via the MENU_BUILDING hook
        $menus = HookManager::applyFilters(Hooks::MENU_BUILDING, $menus);

        // Ensure Admin is always last (at bottom of nav) by moving it to the end
        if ($isAdmin && isset($menus['Admin'])) {
            $admin = $menus['Admin'];
            unset($menus['Admin']);
            $menus['Admin'] = $admin;
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

    private static function getPeopleMenu(bool $isAdmin, bool $isMenuOptions, bool $isAddRecordsEnabled, bool $isVolunteerV1Enabled): MenuItem
    {
        $peopleMenu = new MenuItem(gettext('People'), '', true, 'fa-people-group');
        $peopleMenu->addSubMenu(new MenuItem(gettext('Dashboard'), 'people/dashboard', true, 'fa-gauge'));
        $peopleMenu->addSubMenu(new MenuItem(gettext('Add New Person'), 'PersonEditor.php', $isAddRecordsEnabled, 'fa-user-plus'));
        $peopleMenu->addSubMenu(new MenuItem(gettext('Person Listing'), 'people/list', true, 'fa-person-half-dress'));
        $peopleMenu->addSubMenu(new MenuItem(gettext('Photo Directory'), 'people/photos', true, 'fa-images'));
        $peopleMenu->addSubMenu(new MenuItem(gettext('Add New Family'), 'FamilyEditor.php', $isAddRecordsEnabled, 'fa-people-roof'));
        $peopleMenu->addSubMenu(new MenuItem(gettext('Family Listing'), 'people/family', true, 'fa-people-roof'));
        $peopleMenu->addSubMenu(new MenuItem(gettext('Family Map'), 'people/map', true, 'fa-map'));

        if ($isAdmin || $isMenuOptions) {
            $adminMenu = new MenuItem(gettext('Admin'), '', true);
            $adminMenu->addSubMenu(new MenuItem(gettext('Family Roles'), 'admin/system/options?mode=famroles', $isAdmin, 'fa-people-roof'));
            $adminMenu->addSubMenu(new MenuItem(gettext('Family Properties'), 'PropertyList.php?Type=f', $isMenuOptions, 'fa-people-roof'));
            $adminMenu->addSubMenu(new MenuItem(gettext('Family Custom Fields'), 'FamilyCustomFieldsEditor.php', $isAdmin, 'fa-sliders'));
            $adminMenu->addSubMenu(new MenuItem(gettext('Person Classifications'), 'admin/system/options?mode=classes', $isAdmin, 'fa-tags'));
            $adminMenu->addSubMenu(new MenuItem(gettext('Person Properties'), 'PropertyList.php?Type=p', $isMenuOptions, 'fa-person-half-dress'));
            $adminMenu->addSubMenu(new MenuItem(gettext('Person Custom Fields'), 'PersonCustomFieldsEditor.php', $isAdmin, 'fa-sliders'));
            $adminMenu->addSubMenu(new MenuItem(gettext('Volunteer Opportunities'), 'VolunteerOpportunityEditor.php', $isAdmin && $isVolunteerV1Enabled, 'fa-handshake-angle'));
    
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
            $adminMenu->addSubMenu(new MenuItem(gettext('Kiosk Manager'), 'kiosk/admin', $isManageGroups, 'fa-desktop'));

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

    /**
     * @param bool $isAddEventEnabled the global AddEvent right — gates the Event Types admin entry
     * @param bool $canViewEvents     the Events module is on
     * @param bool $canWriteEvents    AddEvent **or** a volunteer-ministry coordinator (#9713,
     *                                design §4.6). Menu visibility must mirror the route
     *                                middleware exactly (§3.5, A11), and `/event/editor` is
     *                                gated by AddEventsOrMinistryRoleAuthMiddleware, which asks
     *                                exactly this question.
     */
    private static function getEventsMenu(bool $isAddEventEnabled, bool $canViewEvents, bool $canWriteEvents): MenuItem
    {
        $eventsMenu = new MenuItem(gettext('Events'), '', $canViewEvents, 'fa-ticket');
        $eventsMenu->addSubMenu(new MenuItem(gettext('Events Dashboard'), 'event/dashboard', true, 'fa-gauge'));
        $eventsMenu->addSubMenu(new MenuItem(gettext('Add Church Event'), 'event/editor', $canWriteEvents, 'fa-circle-plus'));
        $eventsMenu->addSubMenu(new MenuItem(gettext('Check-in and Check-out'), 'event/checkin', true, 'fa-user-check'));

        if ($isAddEventEnabled) {
            $adminMenu = new MenuItem(gettext('Admin'), '', true);
            $adminMenu->addSubMenu(new MenuItem(gettext('Event Types'), 'event/types', true, 'fa-tags'));
            $eventsMenu->addSubMenu($adminMenu);
        }

        return $eventsMenu;
    }

    // There is no getVolunteerMenu() any more (#9867, Member Portal design P16).
    //
    // The "Volunteer" heading held exactly two items — *My Volunteer Schedule*
    // and *Open Opportunities* — and both pages moved into the Member Portal,
    // where they are reached from its own "Volunteering" nav entry
    // (ChurchCRM\Portal\PortalNav). Member-facing volunteer functionality now
    // exists in one place, and the admin sidebar carries only the
    // administration surface: the "Ministries" heading below, unchanged.
    //
    // Staff who also volunteer reach their own schedule through the Member
    // Portal, which their user menu links to.
    //
    // The legacy "Volunteer Opportunities" item under People → Admin covers the
    // 'v1' and 'both' rollout states and is unaffected.

    /**
     * Volunteer Management v2 — the ADMINISTRATION surface (epic #9701).
     *
     * A heading of its own, built the way the Groups block builds its per-group
     * entries: a Dashboard entry and then one entry per ministry, by name, each
     * linking straight to `/ministries/{id}`. It replaces the retired
     * "My ministries and teams" list page — a list of the same links, one click
     * further away.
     *
     * $isCoordinator is User::isVolunteerCoordinatorEnabled() — the SAME predicate
     * VolunteerCoordinatorRoleAuthMiddleware calls — so nothing here advertises a
     * page that 302s away and nothing openable is hidden (§3.5, A11). It already
     * carries the rollout state, the administrator bypass, the global-manager flag,
     * the EditSelf-exclusive short-circuit and the ministry/team scope lookup.
     *
     * A pure **team leader** passes that predicate and gets the heading with
     * Dashboard alone: `getManageableMinistries()` returns nothing for them,
     * because leading a team is not administering the ministry above it (§4.6) and
     * the ministry page would refuse them. The dashboard is their entry point, and
     * its "My ministries and teams" card names the teams they lead. A manager who
     * has not created a ministry yet sees the same Dashboard-only heading, and the
     * dashboard's "New ministry" action is right there. Only ACTIVE ministries are
     * listed here; deactivated ones move to the Deactivated Ministries heading.
     *
     * One query per request, ids and names only — see
     * `VolunteerAuthorizationService::getManageableMinistries()` for the memo and
     * the reason it is static. The early return keeps even that query off every
     * page load for the overwhelming majority of users, who coordinate nothing.
     */
    private static function getMinistriesMenu(User $currentUser, bool $isCoordinator): MenuItem
    {
        $ministriesMenu = new MenuItem(gettext('Ministries'), '', $isCoordinator, 'fa-sitemap');
        if (!$isCoordinator) {
            // Every /volunteer coordinator route is behind
            // VolunteerCoordinatorRoleAuthMiddleware; skip the lookup entirely.
            return $ministriesMenu;
        }

        $ministriesMenu->addSubMenu(new MenuItem(gettext('Dashboard'), 'ministries/dashboard', true, 'fa-gauge'));
        self::addMinistryEntries($ministriesMenu, $currentUser, true);
        // Last under the heading: the nested Deactivated Ministries group, which
        // MenuItem::isVisible() drops whenever it would be empty.
        $ministriesMenu->addSubMenu(self::getDeactivatedMinistriesMenu($currentUser));

        return $ministriesMenu;
    }

    /**
     * **Deactivated Ministries** — the lifecycle group NESTED under the Ministries
     * heading, after the active entries (product-owner decision, 2026-09-17; the
     * Groups heading's per-type sub-groups are the precedent, and MenuRenderer
     * recurses). A ministry is deactivated from its page, leaves the list of
     * active entries and appears here; its page then offers Reactivate and, to a
     * manager, Delete. The group has no fixed content, so `MenuItem::isVisible()`
     * drops it whenever the viewer manages no deactivated ministry — for most
     * installations, most of the time. `openMenu()` recurses too, so opening a
     * deactivated ministry expands both levels.
     *
     * Same memoised query as the active entries: the group costs nothing extra.
     */
    private static function getDeactivatedMinistriesMenu(User $currentUser): MenuItem
    {
        $menu = new MenuItem(gettext('Deactivated Ministries'), '', true, 'fa-box-archive');
        self::addMinistryEntries($menu, $currentUser, false);

        return $menu;
    }

    /**
     * One entry per ministry the viewer may administer whose active flag matches,
     * by name, linking to `/ministries/{id}`. The name is data, rendered
     * by MenuRenderer through `InputUtils::escapeHTML()` like every other label.
     */
    private static function addMinistryEntries(MenuItem $heading, User $currentUser, bool $active): void
    {
        $currentMinistryId = self::getVolunteerMinistryIdForCurrentRoute();
        foreach ((new VolunteerAuthorizationService())->getManageableMinistries($currentUser) as $ministryId => $ministry) {
            if ($ministry['active'] !== $active) {
                continue;
            }
            $item = new MenuItem($ministry['name'], 'ministries/' . $ministryId, true, 'fa-handshake-angle');
            if ($currentMinistryId === $ministryId) {
                $item->setActiveOverride(true);
            }
            $heading->addSubMenu($item);
        }
    }

    /**
     * The ministry the current request belongs to when the URL does not name it.
     *
     * `/ministries/{id}` needs nothing: `MenuItem::isActive()` matches it
     * against the entry's own URI. `/ministries/occurrences/{id}` does — an
     * occurrence belongs to a schedule and a schedule to a ministry, and without
     * this the sidebar would show nothing highlighted on the one page a coordinator
     * spends the most time. Two primary-key lookups of a single column, and only on
     * that route: every other page returns before touching the database.
     */
    private static function getVolunteerMinistryIdForCurrentRoute(): ?int
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (!is_string($path) || preg_match('{/ministries/occurrences/([0-9]+)$}', $path, $matches) !== 1) {
            return null;
        }

        $scheduleId = VolunteerOccurrenceQuery::create()
            ->filterById((int) $matches[1])
            ->select('ScheduleId')
            ->findOne();
        if ($scheduleId === null) {
            return null;
        }

        $ministryId = VolunteerScheduleQuery::create()
            ->filterById((int) $scheduleId)
            ->select('MinistryId')
            ->findOne();

        return $ministryId === null ? null : (int) $ministryId;
    }

    private static function getDepositsMenu(bool $isAdmin, bool $isFinanceEnabled): MenuItem
    {
        // $isFinanceEnabled already includes admin bypass and checks bEnabledFinance
        $depositsMenu = new MenuItem(gettext('Finance'), '', $isFinanceEnabled, 'fa-cash-register');

        // Open-deposit count badge — initialized to 0, loaded dynamically via JavaScript
        // on page load (matches Fundraiser badge pattern). See CRMJSOM.js loadOpenDepositCount().
        $depositsMenu->addCounter(new MenuCounter(
            'openDeposits',
            'bg-blue',
            0,
            gettext('Open Deposits')
        ));

        $depositsMenu->addSubMenu(new MenuItem(gettext('Dashboard'), 'finance/', $isFinanceEnabled, 'fa-gauge'));
        $depositsMenu->addSubMenu(new MenuItem(gettext('View All Deposits'), 'finance/deposit/search', $isFinanceEnabled, 'fa-list'));
        $depositsMenu->addSubMenu(new MenuItem(gettext('Deposit Reports'), 'finance/reports', $isFinanceEnabled, 'fa-file-invoice'));
        $depositsMenu->addSubMenu(new MenuItem(gettext('Pledge Dashboard'), 'finance/pledge/dashboard', $isFinanceEnabled, 'fa-handshake'));
        $depositsMenu->addSubMenu(new MenuItem(gettext('Edit Deposit Slip'), 'DepositSlipEditor.php?DepositSlipID=' . $_SESSION['iCurrentDeposit'], $isFinanceEnabled, 'fa-pen-to-square'));

        if ($isFinanceEnabled) {
            $adminMenu = new MenuItem(gettext('Admin'), '', $isFinanceEnabled);
            $adminMenu->addSubMenu(new MenuItem(gettext('Envelope Manager'), 'ManageEnvelopes.php', $isFinanceEnabled, 'fa-envelope'));
            $adminMenu->addSubMenu(new MenuItem(gettext('Donation Funds'), 'finance/funds', $isFinanceEnabled, 'fa-piggy-bank'));

            $depositsMenu->addSubMenu($adminMenu);
        }
        return $depositsMenu;
    }

    private static function getFundraisersMenu(bool $canManageFundraisers): MenuItem
    {
        // Menu shows clean dashboard-style links; no single "current fundraiser" context.
        // The Fundraiser Dashboard lets users navigate to any specific fundraiser from there.
        $fundraiserMenu = new MenuItem(gettext('Fundraiser'), '', $canManageFundraisers, 'fa-money-bill-1');
        $fundraiserMenu->addSubMenu(new MenuItem(gettext('Dashboard'), 'fundraiser/', true, 'fa-list'));
        $fundraiserMenu->addSubMenu(new MenuItem(gettext('Create New Fundraiser'), 'fundraiser/editor', true, 'fa-circle-plus'));

        // Active-fundraiser count badge — initialized to 0, loaded dynamically via JavaScript
        // on page load (matches Calendar badge pattern). See CRMJSOM.js loadFundraiserCount().
        $fundraiserMenu->addCounter(new MenuCounter(
            'activeFundraisers',
            'bg-blue',
            0,
            gettext('Active Fundraisers')
        ));

        return $fundraiserMenu;
    }

    private static function getReportsMenu(bool $isAdmin): MenuItem
    {
        // Query Menu is the only entry, so link straight to it rather than nesting a single child.
        // GHSA-6rgg-mrx3-92w7: QueryList.php now requires isAdmin(); hide from non-admins.
        return new MenuItem(gettext('Data/Reports'), 'QueryList.php', $isAdmin, 'fa-database');
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
        $menu->addSubMenu(new MenuItem(gettext('Member Portal'), 'admin/member-portal', $isAdmin, 'fa-house-user'));
        $menu->addSubMenu(new MenuItem(gettext('Ministry Settings'), 'admin/ministry-settings', $isAdmin, 'fa-handshake-angle'));
        $menu->addSubMenu(new MenuItem(gettext('System Settings'), 'SystemSettings.php', $isAdmin, 'fa-gear'));
        $menu->addSubMenu(new MenuItem(gettext('Plugins'), 'plugins/management', $isAdmin, 'fa-plug'));
        $menu->addSubMenu(new MenuItem(gettext('Export'), 'admin/export', $isAdmin, 'fa-file-export'));

        return $menu;
    }
}
