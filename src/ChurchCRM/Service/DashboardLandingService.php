<?php

namespace ChurchCRM\Service;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\Utils\RedirectUtils;

/**
 * Which dashboards a staff user may open, and which one they land on after sign-in.
 * A tab shows exactly when its route lets the user in, so a tab never leads to a 403.
 */
class DashboardLandingService
{
    public const SETTING = 'ui.landing';
    public const DEFAULT_KEY = 'people';
    public const DEFAULT_PATH = '/people/dashboard';
    public const SETUP_PATH = '/admin/setup';

    /**
     * @return array<string, array{0: string, 1: string, 2: string}> key => [path, label, icon]
     */
    public static function tabsFor(User $user): array
    {
        $tabs = ['people' => [self::DEFAULT_PATH, gettext('People'), 'fa-users']];
        if ($user->isManageGroupsEnabled()) {
            $tabs['groups'] = ['/groups/dashboard', gettext('Groups'), 'fa-user-group'];
            if (SystemConfig::getBooleanValue('bEnabledSundaySchool')) {
                $tabs['sundayschool'] = ['/groups/sundayschool/dashboard', gettext('Sunday School'), 'fa-children'];
            }
        }
        if ($user->canViewEvents() && $user::isEventsEnabled()) {
            $tabs['events'] = ['/event/dashboard', gettext('Events'), 'fa-calendar-days'];
        }
        if ($user->isFinanceEnabled()) {
            $tabs['finance'] = ['/finance/', gettext('Finance'), 'fa-circle-dollar-to-slot'];
            $tabs['deposits'] = ['/finance/deposit/search', gettext('Deposits'), 'fa-vault'];
        }

        return $tabs;
    }

    public static function keyFor(User $user): string
    {
        $saved = (string) $user->getSettingValue(self::SETTING);

        return isset(self::tabsFor($user)[$saved]) ? $saved : self::DEFAULT_KEY;
    }

    /** Path relative to the install root, e.g. /people/dashboard. */
    public static function pathFor(User $user): string
    {
        return self::tabsFor($user)[self::keyFor($user)][0];
    }

    /** Where sign-in lands: the Setup Dashboard for an administrator until the church is set up and while the setup window is open. */
    public static function signInPathFor(User $user): string
    {
        $setup = InstallSetupService::needsChurchSetup() || InstallSetupService::isWindowOpen();

        return $user->isAdmin() && $setup ? self::SETUP_PATH : self::pathFor($user);
    }

    public static function urlFor(User $user): string
    {
        return SystemURLs::getRootPath() . self::pathFor($user);
    }

    /** The signed-in user's dashboard path, or the default when nobody is signed in. */
    public static function currentPath(): string
    {
        return AuthenticationManager::isUserAuthenticated()
            ? self::pathFor(AuthenticationManager::getCurrentUser())
            : self::DEFAULT_PATH;
    }

    /** Send the signed-in user to the dashboard they chose. */
    public static function redirect(?User $user = null): never
    {
        RedirectUtils::redirect($user ? self::pathFor($user) : self::currentPath());
    }
}
