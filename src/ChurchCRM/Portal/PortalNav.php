<?php

namespace ChurchCRM\Portal;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\User;
use Throwable;

/**
 * The Member Portal navigation model: what this member may see, in order.
 *
 * Design §5 fixes the order — Home · Calendar · Volunteering · My Teams ·
 * My Family · Profile — and says an entry is hidden when its feature is off or
 * the member has nothing there. MP2 shipped the skeleton; MP6 adds Volunteering,
 * and MP4/MP5/MP7 add their own entries to this one model as those pages land.
 *
 * A nav entry is `{id, label, url, icon, active, badge}`; `url` is already
 * prefixed with the install root path, and `icon` is a Font Awesome class.
 */
class PortalNav
{
    public const HOME = 'home';
    public const VOLUNTEER = 'volunteer';

    /**
     * @return array<int, array{id: string, label: string, url: string, icon: string, active: bool, badge: string}>
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
            ],
        ];

        if (self::isVolunteeringVisible()) {
            $entries[] = [
                'id' => self::VOLUNTEER,
                // "Volunteering", not "My Volunteer Schedule": the entry covers
                // both pages, and the volunteer design §5.6 keeps the member's
                // vocabulary plain.
                'label' => gettext('Volunteering'),
                'url' => $rootPath . '/portal/volunteer/schedule',
                'icon' => 'fa-solid fa-handshake-angle',
            ];
        }

        return array_map(
            static fn (array $entry): array => $entry + [
                'active' => $activeId === $entry['id'],
                'badge' => '',
            ],
            $entries
        );
    }

    /**
     * Does this installation offer the volunteering pages in the portal (MP6)?
     *
     * Two switches, both of which must be on:
     *
     *  - `User::isVolunteerV2Enabled()` — the Volunteer v2 rollout flag. The
     *    member pages are V2's; with the flag on `v1` they do not exist.
     *  - `bPortalShowVolunteer` — the administrator's own "show volunteering in
     *    the portal" switch on Admin → Member Portal.
     *
     * The second is read defensively because MP3 is what declares it: until that
     * issue lands, `SystemConfig::getBooleanValue()` throws for the name, and an
     * undeclared switch means "not switched off" rather than "off".
     *
     * The route handler and the nav entry both call this, so the page a member
     * is offered and the page they may open can never disagree.
     */
    public static function isVolunteeringVisible(): bool
    {
        if (!User::isVolunteerV2Enabled()) {
            return false;
        }

        try {
            return SystemConfig::getBooleanValue('bPortalShowVolunteer');
        } catch (Throwable) {
            return true;
        }
    }
}
