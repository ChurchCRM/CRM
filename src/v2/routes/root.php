<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\EventAttendQuery;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Service\ClassificationService;
use ChurchCRM\Service\PersonService;
use ChurchCRM\Service\UpgradeAPIService;
use ChurchCRM\Slim\Middleware\CSRFMiddleware;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\view\PageHeader;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

$app->get('/dashboard', 'viewDashboard');
$app->get('/community-feedback', 'viewCommunityFeedback');
$app->get('/access-denied', 'viewAccessDenied');
$app->get('/releases', 'viewReleases');
$app->post('/releases/dismiss-update-notice', 'dismissUpdateNotice')->add(new CSRFMiddleware());

function viewAccessDenied(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/common/');

    // Allowed role codes that can be displayed on the access-denied page
    $allowedRoles = [
        'Admin',
        'Finance',
        'ManageGroups',
        'EditRecords',
        'DeleteRecords',
        'AddRecords',
        'Notes',
        'AddEvent',
        'Authentication',
        // Volunteer Management v2 (#9704). The role middlewares that emit these
        // arrive with #9706; listing them now keeps the access-denied page from
        // silently blanking the role name when they do.
        'ManageMinistries',
        'VolunteerCoordinator',
    ];

    $queryParams = $request->getQueryParams();
    $missingRole = in_array($queryParams['role'] ?? '', $allowedRoles, true)
        ? $queryParams['role']
        : '';

    $pageArgs = [
        'sRootPath'   => SystemURLs::getRootPath(),
        'sPageTitle'  => gettext('Access Denied'),
        'missingRole' => $missingRole,
    ];

    return $renderer->render($response, 'access-denied.php', $pageArgs);
}

function viewDashboard(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/root/');

    $dashboardCounts = [];

    $dashboardCounts['families'] = FamilyQuery::create()
        ->filterByDateDeactivated()
        ->count();

    $dashboardCounts['People'] = (new ClassificationService())->excludeInactive(PersonQuery::create())
        ->leftJoinWithFamily()
        ->where('Family.DateDeactivated is null')
        ->filterByLiving()
        ->count();

    // Redirect admin users with no people to the setup dashboard
    if (AuthenticationManager::getCurrentUser()->isAdmin() && $dashboardCounts['People'] === 1) {
        return $response
            ->withStatus(302)
            ->withHeader('Location', SystemURLs::getRootPath() . '/admin');
    }

    $dashboardCounts['SundaySchool'] = GroupQuery::create()
        ->filterByType(4)
        ->count();

    $dashboardCounts['Groups'] = GroupQuery::create()
        ->count();

    // Count only attendances that are checked-in and not checked-out
    // and that are linked to an existing, active event. This prevents
    // orphaned or invalid event_attend rows (for example event_id=0)
    // from inflating the dashboard number.
    $dashboardCounts['events'] = EventAttendQuery::create()
        ->filterByCheckinDate(null, Criteria::NOT_EQUAL)
        ->filterByCheckoutDate(null, Criteria::EQUAL)
        ->useEventQuery()
            ->filterByInActive(0)
        ->endUse()
        ->count();

    // Data quality checks for people
    $personService = new PersonService();
    $genderDataCheckCount = $personService->getMissingGenderDataCount();
    $roleDataCheckCount = $personService->getMissingRoleDataCount();
    $classificationDataCheckCount = $personService->getMissingClassificationDataCount();

    $pageArgs = [
        'sRootPath'                       => SystemURLs::getRootPath(),
        'sPageTitle'                      => gettext('Welcome to') . ' ' . ChurchMetaData::getChurchName(),
        'aBreadcrumbs'                    => PageHeader::breadcrumbs([
            [gettext('Dashboard')],
        ]),
        'sPageHeaderButtons'              => PageHeader::buttons([
            ['label' => gettext('Admin Dashboard'), 'url' => '/admin/', 'icon' => 'fa-screwdriver-wrench'],
        ]),
        'dashboardCounts'                 => $dashboardCounts,
        'sundaySchoolEnabled'             => SystemConfig::getBooleanValue('bEnabledSundaySchool'),
        'depositEnabled'                  => AuthenticationManager::getCurrentUser()->isFinanceEnabled(),
        'canReviewSelfRegistrations'      => AuthenticationManager::getCurrentUser()->isEditRecordsEnabled(),
        'eventsEnabled'                   => SystemConfig::getBooleanValue('bEnabledEvents'),
        'genderDataCheckCount'            => $genderDataCheckCount,
        'roleDataCheckCount'              => $roleDataCheckCount,
        'classificationDataCheckCount'    => $classificationDataCheckCount,
    ];

    return $renderer->render($response, 'dashboard.php', $pageArgs);
}

function viewCommunityFeedback(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/root/');

    $pageArgs = [
        'sRootPath'       => SystemURLs::getRootPath(),
        'sPageTitle'      => gettext('Review what we are considering'),
        'aBreadcrumbs'    => PageHeader::breadcrumbs([
            [gettext('Review what we are considering')],
        ]),
        'sDiscussionsUrl' => 'https://github.com/ChurchCRM/CRM/discussions',
    ];

    return $renderer->render($response, 'community-feedback.php', $pageArgs);
}

/**
 * Read-only release history for any authenticated staff member.
 * Uses the same preview data as the admin upgrade wizard.
 */
function viewReleases(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/root/');
    try {
        $preview = UpgradeAPIService::getUpgradePreview();
    } catch (\Throwable $e) {
        LoggerUtils::getAppLogger()->warning('Release history unavailable', ['error' => $e->getMessage()]);
        $preview = [
            'installedVersion' => '',
            'upgradePath' => [],
            'unavailable' => true,
        ];
    }

    return $renderer->render($response, 'releases.php', [
        'sRootPath' => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('What\'s New'),
        'aBreadcrumbs' => PageHeader::breadcrumbs([[gettext('Releases')]]),
        'preview' => $preview,
    ]);
}

/** Dismiss only the system-update notice until this staff session ends. */
function dismissUpdateNotice(Request $request, Response $response, array $args): Response
{
    $_SESSION['systemUpdateNoticeDismissed'] = true;
    return $response->withStatus(204);
}
