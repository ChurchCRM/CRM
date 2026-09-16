<?php

namespace ChurchCRM\Portal;

use ChurchCRM\dto\SystemURLs;

/**
 * The Member Portal navigation model: what this member may see, in order.
 *
 * Design §5 fixes the order — Home · Calendar · Volunteering · My Teams ·
 * My Family · Profile — and says an entry is hidden when its feature is off or
 * the member has nothing there. MP2 ships the skeleton, so only Home exists;
 * MP4–MP7 add their own entries to this one model as those pages land.
 *
 * A nav entry is `{id, label, url, icon, active, badge}`; `url` is already
 * prefixed with the install root path, and `icon` is a Font Awesome class.
 */
class PortalNav
{
    public const HOME = 'home';

    /**
     * @return array<int, array{id: string, label: string, url: string, icon: string, active: bool, badge: string}>
     */
    public static function build(?string $activeId = null): array
    {
        $rootPath = SystemURLs::getRootPath();

        $items = [
            [
                'id' => self::HOME,
                'label' => gettext('Home'),
                'url' => $rootPath . '/portal/',
                'icon' => 'fa-solid fa-house',
                'active' => false,
                'badge' => '',
            ],
        ];

        foreach ($items as $index => $item) {
            $items[$index]['active'] = $item['id'] === $activeId;
        }

        return $items;
    }
}
