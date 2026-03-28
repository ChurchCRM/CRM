<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\PeopleCustomField;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\FamilyCustomMasterQuery;
use ChurchCRM\model\ChurchCRM\FamilyCustomQuery;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\PropertyQuery;
use ChurchCRM\Service\FinancialService;
use ChurchCRM\Service\TimelineService;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\FiscalYearUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\view\PageHeader;
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

    // Geocoding status filter: 'unverified' = has address but no lat/lon
    $filterGeocoded = '';
    if (!empty($queryParams['geocoded'])) {
        $filterGeocoded = InputUtils::legacyFilterInput($queryParams['geocoded']);
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

    if ($filterGeocoded === 'unverified') {
        // Has a street address entered but latitude/longitude are not set
        // Matches hasLatitudeAndLongitude(): consider unverified when either coordinate is missing
        $familiesQuery->filterByAddress1('', Criteria::NOT_EQUAL);
        $familiesQuery->where('(family_fam.fam_Latitude IS NULL OR family_fam.fam_Latitude = 0) OR (family_fam.fam_Longitude IS NULL OR family_fam.fam_Longitude = 0)');
        $sMode = $sMode . ' - ' . gettext('Unverified Addresses');
    }

    $families = $familiesQuery->find();
    $pageArgs = [
        'sMode' => $sMode,
        'sRootPath' => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('Families'),
        'sPageSubtitle' => gettext('Browse and search all families in your congregation'),
        'aBreadcrumbs' => PageHeader::breadcrumbs([
            [gettext('People'), '/people/dashboard'],
            [gettext('Families')],
        ]),
        'families' => $families,
        'filterCity' => $filterCity,
        'filterState' => $filterState,
        'filterGeocoded' => $filterGeocoded,
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
        'sPageTitle' => gettext('Family') . ': ' . InputUtils::escapeHTML($family->getName()),
        'sPageSubtitle' => gettext('View family details, members, and timeline'),
        'aBreadcrumbs' => PageHeader::breadcrumbs([
            [gettext('People'), '/people/dashboard'],
            [gettext('Families'), '/v2/family'],
            [InputUtils::escapeHTML($family->getName())],
        ]),
        'family' => $family,
        'familyTimeline' => $timelineService->getForFamily($family->getId()),
        'allFamilyProperties' => $allFamilyProperties,
        'familyCustom' => $familyCustom,
        'currentFY' => FinancialService::formatFiscalYear(FiscalYearUtils::getCurrentFiscalYearId()),
        'taxYears' => [],
    ];

    // Pre-compute available tax years for Finance users (used by Tax Doc action menu items)
    if (AuthenticationManager::getCurrentUser()->isFinanceEnabled()) {
        $finService = new FinancialService();
        $maxTaxYears = SystemConfig::getIntValue('iMaxTaxYears');
        $pageArgs['taxYears'] = $finService->getFamilyPaymentYears($familyId, $maxTaxYears > 0 ? $maxTaxYears : 0);
    }

    return $renderer->render($response, 'family-view.php', $pageArgs);
}
