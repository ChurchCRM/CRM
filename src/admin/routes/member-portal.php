<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\FamilyEmailMoveQuery;
use ChurchCRM\Portal\PortalCalendarService;
use ChurchCRM\Portal\PortalDirectoryService;
use ChurchCRM\Portal\PortalStatsService;
use ChurchCRM\Portal\ThemeManager;
use ChurchCRM\Portal\ThemeValidator;
use ChurchCRM\Service\MemberSignInListService;
use ChurchCRM\Service\MemberSignInSettingsService;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

// Served at /admin/member-portal — the '/admin' prefix comes from
// MvcAppFactory::create(), and AdminRoleAuthMiddleware gates the whole app, so
// this page is administrators-only without any check of its own.
//
// Design: .agents/skills/churchcrm/member-portal-design.md §4 (decision P9) —
// the portal's settings live here rather than on the frozen System Settings
// page, together with theme validation and the portal statistics.
$memberPortalHandler = function (Request $request, Response $response): Response {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    // Validate every discovered theme once, server-side, so each row of the
    // Themes tab can carry a badge on first paint.
    $themes = [];
    foreach (ThemeManager::listThemes() as $theme) {
        $findings = ThemeManager::validate($theme['id']);
        $templatesPath = ThemeManager::getTemplatesPath($theme['id']);

        $theme['findings'] = $findings;
        $theme['status'] = ThemeValidator::summarize($findings);
        $theme['overriddenTemplates'] = $templatesPath === null
            ? 0
            : count(ThemeValidator::listTemplates($templatesPath));
        $theme['isDefault'] = $theme['id'] === ThemeManager::DEFAULT_THEME;
        $themes[] = $theme;
    }

    $signInSettings = new MemberSignInSettingsService();
    $signInTooltips = [];
    foreach (MemberSignInSettingsService::KEYS as $key) {
        $signInTooltips[$key] = SystemConfig::getTooltip($key);
    }

    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('Member Portal'),
        'sPageSubtitle' => gettext('Choose the theme members see, turn portal sections on or off, and see who is using the portal.'),
        'aBreadcrumbs' => PageHeader::breadcrumbs([
            [gettext('People'), '/people/dashboard'],
            [gettext('People Settings'), '/admin/people'],
            [gettext('Member Portal')],
        ]),
        'sPageHeaderButtons' => PageHeader::buttons([
            PageHeader::peopleSettingsLink(),
            ['label' => gettext('Open the Member Portal'), 'url' => '/portal/', 'icon' => 'fa-arrow-up-right-from-square'],
        ]),
        'themes' => $themes,
        'activeTheme' => ThemeManager::getActiveThemeName(),
        'activeThemeExists' => ThemeManager::themeExists(ThemeManager::getActiveThemeName()),
        'stats' => PortalStatsService::getStatistics(),
        // The Calendars tab (#9866): every church, ministry and system calendar
        // with the position of its "Show in Member Portal" switch, rendered
        // server-side so the tab needs no round trip on first paint.
        'portalCalendars' => PortalCalendarService::listChoices(),
        'directory' => [
            'settings' => PortalDirectoryService::settings(),
            'classifications' => $signInSettings->classificationChoices(),
        ],
        // The Sign-in tab (member sign-in design §11.1).
        'signIn' => [
            'values' => $signInSettings->values(),
            'tooltips' => $signInTooltips,
            'emailProblem' => $signInSettings->emailProblem(),
            'passkeyProblem' => $signInSettings->passkeyProblem(),
            'familyRoles' => $signInSettings->familyRoleChoices(),
            'classifications' => $signInSettings->classificationChoices(),
            'officeRecipients' => $signInSettings->officeRecipients(),
            'maySignInCount' => $signInSettings->maySignInCount(),
            'sharedAddresses' => count((new MemberSignInListService())->sharedEmails()),
        ],
        // The Members tab (member sign-in design §11.2): which lists to offer;
        // their rows are read a page at a time.
        'memberLists' => memberPortalListChoices(),
        'csrfToken' => CSRFUtils::generateToken(),
    ];

    return $renderer->render($response, 'member-portal.php', $pageArgs);
};

/**
 * The Members tab's lists, in the order the design gives them. The one-time
 * "Family emails not moved" list shows only while the 7.9.0 move left rows.
 *
 * @return list<array{id: string, label: string, icon: string}>
 */
function memberPortalListChoices(): array
{
    $lists = [
        ['id' => MemberSignInListService::MAY_SIGN_IN, 'label' => gettext('May sign in now'), 'icon' => 'fa-user-check'],
        ['id' => MemberSignInListService::SIGNED_IN_RECENTLY, 'label' => gettext('Signed in, last 30 days'), 'icon' => 'fa-right-to-bracket'],
        ['id' => MemberSignInListService::NEVER_SIGNED_IN, 'label' => gettext('Never signed in'), 'icon' => 'fa-user-clock'],
        ['id' => MemberSignInListService::EMAIL_ATTENTION, 'label' => gettext('Email needs attention'), 'icon' => 'fa-envelope-circle-check'],
        ['id' => MemberSignInListService::FAMILIES_CONFIRMED, 'label' => gettext('Families confirmed, last 30 days'), 'icon' => 'fa-house-circle-check'],
    ];
    if (FamilyEmailMoveQuery::create()->count() > 0) {
        $lists[] = ['id' => MemberSignInListService::FAMILY_EMAILS_NOT_MOVED, 'label' => gettext('Family emails not moved'), 'icon' => 'fa-envelope-open'];
    }

    return $lists;
}

$app->get('/member-portal', $memberPortalHandler);
$app->get('/member-portal/', $memberPortalHandler);

