<?php

namespace ChurchCRM\Portal;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Plugin\PluginManager;
use ChurchCRM\Portal\Auth\PortalContext;
use ChurchCRM\Portal\Auth\PortalPrincipal;
use ChurchCRM\Portal\Permissions\CorePortalCapabilities;
use ChurchCRM\Portal\Permissions\PortalAuthorizationService;

/**
 * The Member Portal navigation model: what this member may see, in order.
 *
 * Design §5 fixes the order — Home · Calendar · Volunteering · My Teams ·
 * My Family · Profile — and member sign-in row 7 adds Sign-in and devices. Each entry names the portal capability it needs and is
 * shown when the member holds it on at least one scope (member sign-in design
 * §8.2), so the menu and the routes ask the same question. Home needs nothing,
 * and My Family stays for a member with no family, whose page says so. Active
 * plugins add their own entries before My Family.
 *
 * A nav entry is `{id, label, url, icon, capability, active, badge}`; `url` is
 * already prefixed with the install root path, and `icon` is a Font Awesome
 * class.
 */
class PortalNav
{
    public const HOME = 'home';
    public const CALENDAR = 'calendar';
    public const VOLUNTEER = 'volunteer';
    public const TEAMS = 'teams';
    public const FAMILY = 'family';
    public const PROFILE = 'profile';
    public const DEVICES = 'devices';

    /**
     * @return array<int, array{id: string, label: string, url: string, icon: string, capability: string|null, active: bool, badge: string}>
     */
    public static function build(?string $activeId = null): array
    {
        $rootPath = SystemURLs::getRootPath();

        $entries = [
            [
                'id' => self::HOME,
                'label' => gettext('Home'),
                'url' => $rootPath . '/portal/',
                'icon' => 'fa-solid fa-house',
                'capability' => null,
            ],
            [
                'id' => self::CALENDAR,
                'label' => gettext('Calendar'),
                'url' => $rootPath . '/portal/calendar',
                'icon' => 'fa-solid fa-calendar-days',
                'capability' => CorePortalCapabilities::CALENDAR_VIEW,
            ],
            [
                'id' => self::VOLUNTEER,
                // "Volunteering", not "My Volunteer Schedule": the entry covers
                // both pages, and the volunteer design §5.6 keeps the member's
                // vocabulary plain.
                'label' => gettext('Volunteering'),
                'url' => $rootPath . '/portal/volunteer/schedule',
                'icon' => 'fa-solid fa-handshake-angle',
                'capability' => CorePortalCapabilities::VOLUNTEER_USE,
            ],
            [
                'id' => self::TEAMS,
                // "My Teams", never "Ministries", the admin shell's word for the
                // level above (P16).
                'label' => gettext('My Teams'),
                'url' => $rootPath . '/portal/teams',
                'icon' => 'fa-solid fa-people-group',
                'capability' => CorePortalCapabilities::TEAM_MANAGE,
            ],
            [
                'id' => self::FAMILY,
                'label' => gettext('My Family'),
                'url' => $rootPath . '/portal/family',
                'icon' => 'fa-solid fa-people-roof',
                'capability' => CorePortalCapabilities::FAMILY_VIEW,
            ],
            [
                'id' => self::PROFILE,
                'label' => gettext('Profile'),
                'url' => $rootPath . '/portal/profile',
                'icon' => 'fa-solid fa-user',
                'capability' => CorePortalCapabilities::PROFILE_EDIT,
            ],
            [
                'id' => self::DEVICES,
                'label' => gettext('Sign-in and devices'),
                'url' => $rootPath . '/portal/devices',
                'icon' => 'fa-solid fa-mobile-screen-button',
                'capability' => CorePortalCapabilities::DEVICES_MANAGE,
            ],
        ];

        // Plugin entries (member sign-in design §8.5) go before My Family.
        array_splice($entries, (int) array_search(self::FAMILY, array_column($entries, 'id'), true), 0, PluginManager::getPortalNavItems());

        $principal = PortalContext::principal();
        $authz = new PortalAuthorizationService();
        // A member with no family keeps My Family: the page tells them so and how
        // to reach the office (Member Portal design §5.2).
        $hasNoFamily = (int) PortalContext::person()?->getFamId() === 0;
        $visible = array_filter(
            $entries,
            static fn (array $entry): bool => $entry['capability'] === null
                || ($entry['id'] === self::FAMILY && $hasNoFamily)
                || ($principal instanceof PortalPrincipal && $authz->scopesFor($principal, $entry['capability']) !== [])
        );

        return array_values(array_map(
            static fn (array $entry): array => $entry + [
                'active' => $activeId === $entry['id'],
                'badge' => '',
            ],
            $visible
        ));
    }
}
