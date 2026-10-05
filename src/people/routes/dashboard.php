<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\DashboardService;
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
    $familyCount      = $dashboardService->getFamilyCount();
    $groupStats       = $dashboardService->getGroupStats();
    $dashboardStats   = $dashboardService->getDashboardStats();

    $personCount         = $dashboardStats['personCount'];
    $classificationStats = $dashboardStats['classificationStats'];
    $genderStats         = $dashboardStats['genderStats'];
    $simpleGenderStats   = $dashboardStats['simpleGenderStats'];
    $ageGroupStats       = $dashboardStats['ageGroupStats'];
    $familyRoleStats     = $dashboardStats['familyRoleStats'];

    $currentUser = AuthenticationManager::getCurrentUser();
    $isAdmin     = $currentUser->isAdmin();

    $pageArgs = [
        'sRootPath'          => SystemURLs::getRootPath(),
        'sPageTitle'         => gettext('People Dashboard'),
        'sPageSubtitle'      => gettext('Manage families, people, and demographic information'),
        'aBreadcrumbs'       => PageHeader::breadcrumbs([
            [gettext('People')],
        ]),
        'sPageHeaderButtons' => PageHeader::peopleSettingsButton(),
        'familyCount'        => $familyCount,
        'groupStats'         => $groupStats,
        'personCount'        => $personCount,
        'classificationStats' => $classificationStats,
        'simpleGenderStats'  => $simpleGenderStats,
        'ageGroupStats'      => $ageGroupStats,
        'familyRoleStats'    => $familyRoleStats,
        'isAdmin'            => $isAdmin,
        'canEmail'           => $currentUser->isEmailEnabled(),
        'canLetters'         => $currentUser->isMenuOptionsEnabled(),
    ];

    return $renderer->render($response, 'dashboard.php', $pageArgs);
});
