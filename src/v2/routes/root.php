<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\EventAttendQuery;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Service\FamilyService;
use ChurchCRM\Service\PersonService;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

$app->get('/dashboard', 'viewDashboard');
$app->get('/access-denied', 'viewAccessDenied');

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
        'MenuOptions',
        'Notes',
        'CreateDirectory',
        'AddEvent',
        'CSVExport',
        'Authentication',
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

    $sInactiveClassificationIds = SystemConfig::getValue('sInactiveClassification');
    if ($sInactiveClassificationIds === '') {
        $sInactiveClassificationIds = '-1';
    }
    $aInactiveClassificationIds = explode(',', $sInactiveClassificationIds);
    $dashboardCounts['People'] = PersonQuery::create()
        ->filterByClsId($aInactiveClassificationIds, Criteria::NOT_IN)
        ->leftJoinWithFamily()
        ->where('Family.DateDeactivated is null')
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

    $dashboardCounts['events'] = EventAttendQuery::create()
        ->filterByCheckinDate(null, Criteria::NOT_EQUAL)
        ->filterByCheckoutDate(null, Criteria::EQUAL)
        ->find()
        ->count();

    // Data quality checks for people
    $personService = new PersonService();
    $genderDataCheckCount = $personService->getMissingGenderDataCount();
    $roleDataCheckCount = $personService->getMissingRoleDataCount();
    $classificationDataCheckCount = $personService->getMissingClassificationDataCount();

    $familyService = new FamilyService();
    $familyCoordinatesCheckCount = $familyService->getMissingCoordinatesCount();

    $pageArgs = [
        'sRootPath'                       => SystemURLs::getRootPath(),
        'sPageTitle'                      => gettext('Welcome to') . ' ' . ChurchMetaData::getChurchName(),
        'dashboardCounts'                 => $dashboardCounts,
        'sundaySchoolEnabled'             => SystemConfig::getBooleanValue('bEnabledSundaySchool'),
        'depositEnabled'                  => AuthenticationManager::getCurrentUser()->isFinanceEnabled(),
        'eventsEnabled'                   => SystemConfig::getBooleanValue('bEnabledEvents'),
        'genderDataCheckCount'            => $genderDataCheckCount,
        'roleDataCheckCount'              => $roleDataCheckCount,
        'classificationDataCheckCount'    => $classificationDataCheckCount,
        'familyCoordinatesCheckCount'     => $familyCoordinatesCheckCount,
    ];

    return $renderer->render($response, 'dashboard.php', $pageArgs);
}
