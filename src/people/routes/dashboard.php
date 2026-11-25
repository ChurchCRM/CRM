<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Portal\Auth\MemberSignInRule;
use ChurchCRM\Service\DashboardService;
use ChurchCRM\Service\NoteService;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

// Redirect /people root to /people/dashboard
$app->get('/', function (Request $request, Response $response): Response {
    return $response
        ->withHeader('Location', SystemURLs::getRootPath() . '/people/dashboard')
        ->withStatus(302);
});

// People Dashboard (replaces PeopleDashboard.php)
$app->get('/dashboard', function (Request $request, Response $response): Response {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    $dashboardService = new DashboardService();
    $dashboardStats   = $dashboardService->getDashboardStats();

    $personCount         = $dashboardStats['personCount'];
    $classificationStats = $dashboardStats['classificationStats'];
    $genderStats         = $dashboardStats['genderStats'];
    $simpleGenderStats   = $dashboardStats['simpleGenderStats'];
    $ageGroupStats       = $dashboardStats['ageGroupStats'];
    $familyRoleStats     = $dashboardStats['familyRoleStats'];

    $currentUser = AuthenticationManager::getCurrentUser();
    $isAdmin     = $currentUser->isAdmin();

    $canFixSignInEmail = $currentUser->isEditRecordsEnabled()
        && MemberSignInRule::idList(SystemConfig::getValue('sMemberSignInClassifications')) !== [];

    $dashboardCounts = $dashboardService->getOverviewCounts();
    if ($isAdmin && $dashboardCounts['People'] === 1) {
        return $response->withStatus(302)->withHeader('Location', SystemURLs::getRootPath() . '/admin');
    }

    $savedDays = (int) $currentUser->getSettingValue('ui.dashboard.people.days');
    $peopleDays = in_array($savedDays, [7, 14, 30, 60, 90, 180], true) ? $savedDays : 30;

    $pageArgs = [
        'sRootPath'          => SystemURLs::getRootPath(),
        'sPageTitle'         => gettext('Welcome to') . ' ' . ChurchMetaData::getChurchName(),
        'sPageSubtitle'      => gettext('Manage families, people, and demographic information'),
        'aBreadcrumbs'       => PageHeader::breadcrumbs([
            [gettext('People')],
        ]),
        'sPageHeaderButtons' => PageHeader::buttons(array_values(array_filter([
            $canFixSignInEmail ? ['label' => gettext('Email needs attention'), 'url' => '/people/sign-in-email', 'icon' => 'fa-envelope-circle-check', 'adminOnly' => false, 'id' => 'signInEmailAttentionLink', 'class' => 'btn-outline-warning'] : null,
            ['label' => gettext('Verify People'), 'url' => '/people/verify', 'icon' => 'fa-clipboard-check', 'adminOnly' => false, 'editRecords' => true],
            $currentUser->isEmailEnabled() ? ['label' => gettext('Email All'), 'js' => true, 'id' => 'emailAllPeople', 'icon' => 'fa-envelope', 'adminOnly' => false, 'data' => ['email-composer' => '', 'email-endpoint' => 'people/emails', 'email-title' => gettext('Email All Members')]] : null,
            ['label' => gettext('People Reports'), 'url' => '/people/reports', 'icon' => 'fa-file-lines', 'adminOnly' => false],
            ['label' => gettext('Admin Dashboard'), 'url' => '/admin/', 'icon' => 'fa-screwdriver-wrench'],
            PageHeader::peopleSettingsLink(),
        ]))),
        'dashboardCounts'    => $dashboardCounts,
        'sundaySchoolEnabled' => SystemConfig::getBooleanValue('bEnabledSundaySchool'),
        'eventsEnabled'      => SystemConfig::getBooleanValue('bEnabledEvents'),
        'canViewEvents'      => $currentUser->canViewEvents(),
        'canReviewSelfRegistrations' => $currentUser->isEditRecordsEnabled(),
        'notesEnabled'       => $currentUser->canReadNotes(),
        'peopleDays'         => $peopleDays,
        'latestNotes'        => (new NoteService())->latestNotesFor($currentUser, 200, $peopleDays),
        'personCount'        => $personCount,
        'classificationStats' => $classificationStats,
        'simpleGenderStats'  => $simpleGenderStats,
        'ageGroupStats'      => $ageGroupStats,
        'familyRoleStats'    => $familyRoleStats,
        'isAdmin'            => $isAdmin,
        'canEmail'           => $currentUser->isEmailEnabled(),
        'canEdit'            => $currentUser->isEditRecordsEnabled(),
    ];

    return $renderer->render($response, 'dashboard.php', $pageArgs);
});
