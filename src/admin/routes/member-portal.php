<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Portal\PortalCalendarService;
use ChurchCRM\Portal\PortalStatsService;
use ChurchCRM\Portal\ThemeManager;
use ChurchCRM\Portal\ThemeValidator;
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

    // Validate every discovered theme once, server-side, so each entry in the
    // dropdown and each row of the Themes tab can carry a badge on first paint.
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

    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('Member Portal'),
        'sPageSubtitle' => gettext('Choose the theme members see, turn portal sections on or off, and see who is using the portal.'),
        'aBreadcrumbs' => PageHeader::breadcrumbs([
            [gettext('Admin'), '/admin/'],
            [gettext('Member Portal')],
        ]),
        'sPageHeaderButtons' => PageHeader::buttons([
            ['label' => gettext('Open the Member Portal'), 'url' => '/portal/', 'icon' => 'fa-arrow-up-right-from-square'],
        ]),
        'themes' => $themes,
        'activeTheme' => ThemeManager::getActiveThemeName(),
        'activeThemeExists' => ThemeManager::themeExists(ThemeManager::getActiveThemeName()),
        'developerMode' => SystemConfig::getBooleanValue('bPortalDeveloperMode'),
        'showCalendar' => SystemConfig::getBooleanValue('bPortalShowCalendar'),
        'showVolunteer' => SystemConfig::getBooleanValue('bPortalShowVolunteer'),
        'allowBirthdayEdit' => SystemConfig::getBooleanValue('bPortalAllowBirthdayEdit'),
        'stats' => PortalStatsService::getStatistics(),
        // The Calendars tab (#9866): every church, ministry and system calendar
        // with the position of its "Show in Member Portal" switch, rendered
        // server-side so the tab needs no round trip on first paint.
        'portalCalendars' => PortalCalendarService::listChoices(),
    ];

    return $renderer->render($response, 'member-portal.php', $pageArgs);
};

$app->get('/member-portal', $memberPortalHandler);
$app->get('/member-portal/', $memberPortalHandler);

