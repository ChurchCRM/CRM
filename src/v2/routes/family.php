<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\PeopleCustomField;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\FamilyCustomMasterQuery;
use ChurchCRM\model\ChurchCRM\FamilyCustomQuery;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\PropertyQuery;
use ChurchCRM\Service\TimelineService;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\InputUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/family', function (RouteCollectorProxy $group): void {
    $group->get('/not-found', 'viewFamilyNotFound');
    $group->get('/{id}', 'viewFamily');
    $group->get('/', 'listFamilies');
    $group->get('', 'listFamilies');
});

function listFamilies(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/people/');
    $sMode = 'Active';

    // Read filters from query parameters (support both legacy $_GET and PSR-7)
    $queryParams = $request->getQueryParams();

    // Family active status - support `familyActiveStatus=active|inactive|all` or `active=1|0`
    $familyActiveStatus = 'active';
    // Backwards compatibility: support legacy `mode` query param (e.g., ?mode=inactive)
    if (!empty($queryParams['mode'])) {
        $modeVal = strtolower(InputUtils::legacyFilterInput($queryParams['mode']));
        if ($modeVal === 'inactive') {
            $familyActiveStatus = 'inactive';
        } elseif ($modeVal === 'all') {
            $familyActiveStatus = 'all';
        } else {
            $familyActiveStatus = 'active';
        }
    }
    if (isset($queryParams['familyActiveStatus'])) {
        $familyActiveStatus = strtolower(InputUtils::legacyFilterInput($queryParams['familyActiveStatus']));
    } elseif (isset($queryParams['active'])) {
        // backward compatible: active=1 -> active, active=0 -> inactive
        $familyActiveStatus = ($queryParams['active'] === '0' || $queryParams['active'] === 'false') ? 'inactive' : 'active';
    }

    // City / State filters (optional free-text)
    $filterCity = '';
    if (!empty($queryParams['City'])) {
        $filterCity = InputUtils::legacyFilterInput($queryParams['City']);
    }

    $filterState = '';
    if (!empty($queryParams['State'])) {
        $filterState = InputUtils::legacyFilterInput($queryParams['State']);
    }

    // Build the base query and apply filters
    $familiesQuery = FamilyQuery::create()->orderByName();

    if ($familyActiveStatus === 'active') {
        $familiesQuery->filterByDateDeactivated(null);
        $sMode = 'Active';
    } elseif ($familyActiveStatus === 'inactive') {
        $familiesQuery->filterByDateDeactivated(null, Criteria::ISNOTNULL);
        $sMode = 'Inactive';
    } else {
        // 'all' - no active/inactive filtering
        $sMode = 'All';
    }

    if ($filterCity !== '') {
        // use LIKE for partial matches
        $familiesQuery->filterByCity('%' . $filterCity . '%', Criteria::LIKE);
        $sMode = $sMode . ' - ' . $filterCity;
    }

    if ($filterState !== '') {
        $familiesQuery->filterByState('%' . $filterState . '%', Criteria::LIKE);
        $sMode = $sMode . ' - ' . $filterState;
    }

    $families = $familiesQuery->find();
    $pageArgs = [
        'sMode' => $sMode,
        'sRootPath' => SystemURLs::getRootPath(),
        'families' => $families,
        'filterCity' => $filterCity,
        'filterState' => $filterState,
        'familyActiveStatus' => $familyActiveStatus,
    ];

    return $renderer->render($response, 'family-list.php', $pageArgs);
}

function viewFamilyNotFound(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/common/');

    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'memberType' => 'Family',
        'id' => SlimUtils::getURIParamInt($request, 'id'),
    ];

    return $renderer->render($response, 'not-found-view.php', $pageArgs);
}

function viewFamily(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/people/');

    $familyId = (int)$args['id'];
    $family = FamilyQuery::create()->findPk($familyId);

    if (empty($family)) {
        return SlimUtils::renderRedirect($response, SystemURLs::getRootPath() . '/v2/family/not-found?id=' . $familyId);
    }

    $timelineService = new TimelineService();

    $allFamilyProperties = PropertyQuery::create()->findByProClass('f');

    $allFamilyCustomFields = FamilyCustomMasterQuery::create()->find();

    // get family with all the extra columns created
    $rawQry = FamilyCustomQuery::create();
    foreach ($allFamilyCustomFields as $customfield) {
        $rawQry->withColumn($customfield->getField());
    }
    $appFamilyCustomFields = $rawQry->findOneByFamId($familyId);

    $familyCustom = [];
    if ($appFamilyCustomFields) {
        foreach ($allFamilyCustomFields as $customfield) {
            if (AuthenticationManager::getCurrentUser()->isEnabledSecurity($customfield->getFieldSecurity())) {
                $value = $appFamilyCustomFields->getVirtualColumn($customfield->getField());
                if (!empty($value)) {
                    $item = new PeopleCustomField($customfield, $value);
                    $familyCustom[] = $item;
                }
            }
        }
    }

    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'family' => $family,
        'familyTimeline' => $timelineService->getForFamily($family->getId()),
        'allFamilyProperties' => $allFamilyProperties,
        'familyCustom' => $familyCustom,
    ];

    return $renderer->render($response, 'family-view.php', $pageArgs);
}
