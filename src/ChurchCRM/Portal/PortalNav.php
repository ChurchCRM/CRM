<?php

namespace ChurchCRM\Portal;

use ChurchCRM\dto\SystemURLs;

/**
 * The Member Portal navigation model: what this member may see, in order.
 *
 * Design §5 fixes the order — Home · Calendar · Volunteering · My Teams ·
 * My Family · Profile — and says an entry is hidden when its feature is off or
 * the member has nothing there. MP2 shipped Home; MP4 adds My Family and
 * Profile in their fixed places at the end. The calendar, volunteering and
 * teams entries slot in between as MP5–MP7 land.
 *
 * A nav entry is `{id, label, url, icon, active, badge}`; `url` is already
 * prefixed with the install root path, and `icon` is a Font Awesome class.
 *
 * Section switches (#9864): the Calendar entry MP5 adds belongs behind
 * `bPortalShowCalendar`, and the Volunteering / My Teams entries MP6 adds
 * behind `bPortalShowVolunteer` — both are already exposed to templates as
 * `portal.showCalendar` / `portal.showVolunteer`. There is nothing to hide
 * yet: Home is the only entry and it is never optional.
 */
class PortalNav
{
    public const HOME = 'home';
    public const FAMILY = 'family';
    public const PROFILE = 'profile';

    /**
     * @return array<int, array{id: string, label: string, url: string, icon: string, active: bool, badge: string}>
     */
    public static function build(?string $activeId = null): array
    {
        $rootPath = SystemURLs::getRootPath();

        return [
            [
                'id' => self::HOME,
                'label' => gettext('Home'),
                'url' => $rootPath . '/portal/',
                'icon' => 'fa-solid fa-house',
                'active' => $activeId === self::HOME,
                'badge' => '',
            ],
            [
                'id' => self::FAMILY,
                'label' => gettext('My Family'),
                'url' => $rootPath . '/portal/family',
                'icon' => 'fa-solid fa-people-roof',
                'active' => $activeId === self::FAMILY,
                'badge' => '',
            ],
            [
                'id' => self::PROFILE,
                'label' => gettext('Profile'),
                'url' => $rootPath . '/portal/profile',
                'icon' => 'fa-solid fa-user',
                'active' => $activeId === self::PROFILE,
                'badge' => '',
            ],
        ];
    }
}
