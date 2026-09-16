<?php

namespace ChurchCRM\Portal;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\User;
use Throwable;

/**
 * The Member Portal navigation model: what this member may see, in order.
 *
 * Design §5 fixes the order — Home · Calendar · Volunteering · My Teams ·
 * My Family · Profile — and says an entry is hidden when its feature is off or
 * the member has nothing there. MP2 shipped the skeleton; MP4 adds My Family
 * and Profile in their fixed places at the end, MP6 adds Volunteering before
 * them and MP7 adds My Teams between the two. The calendar entry slots into the
 * same list when MP5 lands.
 *
 * A nav entry is `{id, label, url, icon, active, badge}`; `url` is already
 * prefixed with the install root path, and `icon` is a Font Awesome class.
 *
 * Section switches (#9864): the Calendar entry MP5 adds belongs behind
 * `bPortalShowCalendar`, and the Volunteering / My Teams entries behind
 * `bPortalShowVolunteer` — both are already exposed to templates as
 * `portal.showCalendar` / `portal.showVolunteer`. Volunteering reads it
 * through `isVolunteeringVisible()` below; Home, My Family and Profile are
 * never optional.
 */
class PortalNav
{
    public const HOME = 'home';
    public const VOLUNTEER = 'volunteer';
    public const TEAMS = 'teams';
    public const FAMILY = 'family';
    public const PROFILE = 'profile';

    /**
     * @return array<int, array{id: string, label: string, url: string, icon: string, active: bool, badge: string}>
     */
    public static function build(?string $activeId = null): array
    {
        $rootPath = SystemURLs::getRootPath();

        // Built in the design's order — Home · Calendar · Volunteering ·
        // My Teams · My Family · Profile — so an entry a later issue switches
        // on lands in its fixed place rather than at the end of the list.
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

        if (self::isMyTeamsVisible()) {
            $entries[] = [
                'id' => self::TEAMS,
                // "My Teams", the member's own phrase for the teams they run —
                // never "Ministries", which is the admin shell's word for the
                // level above and is not what this entry opens (P16).
                'label' => gettext('My Teams'),
                'url' => $rootPath . '/portal/teams',
                'icon' => 'fa-solid fa-people-group',
            ];
        }

        $entries[] = [
            'id' => self::FAMILY,
            'label' => gettext('My Family'),
            'url' => $rootPath . '/portal/family',
            'icon' => 'fa-solid fa-people-roof',
        ];

        $entries[] = [
            'id' => self::PROFILE,
            'label' => gettext('Profile'),
            'url' => $rootPath . '/portal/profile',
            'icon' => 'fa-solid fa-user',
        ];

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

    /**
     * Does this member get a "My Teams" entry (MP7, #9868)?
     *
     * Everything `isVolunteeringVisible()` requires — the volunteering section is
     * where My Teams belongs — plus one more thing: the member has to actually
     * lead a team. `User::isVolunteerTeamLeaderEnabled()` is exactly that question
     * and is true for a member login as well as a staff one, which is the whole of
     * the D14 revision (Member Portal P17).
     *
     * A ministry coordinator, a global manager and an administrator are FALSE here
     * even though `/portal/teams` would let them in: they are not team leaders
     * (volunteer design §4.4), their own way into a team is the ministry page in
     * the admin shell, and putting a "My Teams" entry in their portal navigation
     * would claim the opposite. The route is deliberately more generous than the
     * menu; the menu is what the design fixes (§5).
     */
    public static function isMyTeamsVisible(): bool
    {
        if (!self::isVolunteeringVisible()) {
            return false;
        }

        $user = AuthenticationManager::getCurrentUser();

        return $user instanceof User && $user->isVolunteerTeamLeaderEnabled();
    }
}
