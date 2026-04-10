<?php

use ChurchCRM\dto\Photo;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\view\PageHeader;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

// entity can be a person, family, or business
$app->group('/people', function (RouteCollectorProxy $group): void {
    $group->get('/verify', 'viewPeopleVerify');
    $group->get('/photos', 'viewPeoplePhotoGallery');
    $group->get('/', 'listPeople');
    $group->get('', 'listPeople');
});

function viewPeopleVerify(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/people/');

    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
    ];

    if ($request->getQueryParams()['EmailsError']) {
        $errorArgs = [
            'sGlobalMessage' => gettext('Error sending email(s)') . ' - ' . gettext('Please check logs for more information'),
            'sGlobalMessageClass' => 'danger'
        ];
        $pageArgs = array_merge($pageArgs, $errorArgs);
    }

    $queryParam = $request->getQueryParams()['AllPDFsEmailed'];
    if ($queryParam) {
        $headerArgs = ['sGlobalMessage' => sprintf(gettext('PDFs successfully emailed to %s families.'), $queryParam),
            'sGlobalMessageClass'       => 'success'];
        $pageArgs = array_merge($pageArgs, $headerArgs);
    }

    return $renderer->render($response, 'people-verify-view.php', $pageArgs);
}

function listPeople(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/people/');
    // Filter received user input as needed
    // Classification
    // Gender
    // FamilyRole

    $members = PersonQuery::create();
    // set default sMode
    $sMode = 'Person';
    // by default show only active families
    $familyActiveStatus = 'active';
    if ($_GET['familyActiveStatus'] === 'inactive') {
        $familyActiveStatus = 'inactive';
    } elseif ($_GET['familyActiveStatus'] === 'all') {
        $familyActiveStatus = 'all';
    }

    $sInactiveClassificationIds = SystemConfig::getValue('sInactiveClassification');

    if ($sInactiveClassificationIds === '') {
        //works the same if group doesn't exist and keeps queries tidier
        $sInactiveClassificationIds = '-1';
    }

    //parsing the string and reconstruct it back should be enough to mitigate the sql injection vector in here.
    $aInactiveClassificationIds = explode(',', $sInactiveClassificationIds);
    $aInactiveClasses = array_filter($aInactiveClassificationIds, fn ($k): bool => is_numeric($k));

    if (count($aInactiveClassificationIds) !== count($aInactiveClasses)) {
        LoggerUtils::getAppLogger()->warning('Encountered invalid configuration(s) for sInactiveClassification, please fix this');
    }

    $sInactiveClasses = implode(',', $aInactiveClasses);

    // Always retrieve all families - filtering will be done client-side by the Family Status filter
    // Family Status column will show Active or Inactive, allowing client-side filtering like other filters
    $members->leftJoinFamily();

    $members->find();

    $filterByClsId = '';
    $filterByClsOptionId = '';
    if (isset($_GET['Classification'])) {
        $id = InputUtils::filterInt($_GET['Classification']);
        $filterByClsOptionId = (string) $id;
        $option = ListOptionQuery::create()->filterById(1)->filterByOptionId($id)->findOne();
        if ($id === 0) {
            $filterByClsId = gettext('Unassigned');
            $sMode = $filterByClsId;
        } else {
            $filterByClsId = $option->getOptionName();
            $sMode = $filterByClsId;
        }
    }

    $filterByFmrId = '';
    $filterByFmrOptionId = '';
    if (isset($_GET['FamilyRole'])) {
        $id = InputUtils::filterInt($_GET['FamilyRole']);
        $filterByFmrOptionId = (string) $id;
        $option = ListOptionQuery::create()->filterById(2)->filterByOptionId($id)->findOne();

        if ($id === 0) {
            $filterByFmrId = gettext('Unassigned');
            $sMode = $filterByFmrId;
        } else {
            $filterByFmrId = $option->getOptionName();
            $sMode = $filterByFmrId;
        }
    }

    $filterByGender = '';
    if (isset($_GET['Gender'])) {
        $id = InputUtils::filterInt($_GET['Gender']);

        switch ($id) {
            case 0:
                $filterByGender = gettext('Unassigned');
                $sMode = $sMode . ' - ' . $filterByGender;
                break;
            case 1:
                $filterByGender = gettext('Male');
                $sMode = $sMode . ' - ' . $filterByGender;
                break;
            case 2:
                $filterByGender = gettext('Female');
                $sMode = $sMode . ' - ' . $filterByGender;
                break;
        }
    }

    // Family status is computed on-demand in templates via Family::isActive()

    $pageArgs = [
        'sMode'                           => $sMode,
        'sRootPath'                       => SystemURLs::getRootPath(),
        'sPageTitle'                      => gettext('Person Listing'),
        'sPageSubtitle'                   => gettext('Browse all people in your congregation with filtering and search'),
        'aBreadcrumbs'                    => PageHeader::breadcrumbs([
            [gettext('People'), '/people/dashboard'],
            [gettext('Person Listing')],
        ]),
        'members'                         => $members,
        'filterByClsId'                   => $filterByClsId,
        'filterByClsOptionId'             => $filterByClsOptionId,
        'filterByFmrId'                   => $filterByFmrId,
        'filterByFmrOptionId'             => $filterByFmrOptionId,
        'filterByGender'                  => $filterByGender,
        'familyActiveStatus'              => $familyActiveStatus,
        // no precomputed familyStatusMap: templates will call Family::isActive()
    ];

    return $renderer->render($response, 'person-list.php', $pageArgs);
}

/**
 * Photo Gallery view - displays medium-sized photos of all people with names.
 * Feature request: https://github.com/ChurchCRM/CRM/issues/7899
 */
function viewPeoplePhotoGallery(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/people/');

    // Get query parameters for filtering
    $queryParams = $request->getQueryParams();
    // Default to photos-only; explicitly pass photosOnly=0 to show all
    $showOnlyWithPhotos = !array_key_exists('photosOnly', $queryParams) || $queryParams['photosOnly'] !== '0';
    // -1 is a sentinel for "Unassigned" (cls_id IS NULL); null means no filter
    $classificationRaw    = $queryParams['classification'] ?? '';
    $filterUnassigned     = ($classificationRaw === '-1');
    $classificationFilter = (!$filterUnassigned && $classificationRaw !== '') ? InputUtils::filterInt($classificationRaw) : null;

    // Get classification list for filter dropdown
    $classifications = ListOptionQuery::create()
        ->filterById(1)
        ->orderByOptionSequence()
        ->find();

    // Get inactive classification IDs from config
    $sInactiveClassificationIds = SystemConfig::getValue('sInactiveClassification');
    $aInactiveClasses = [];
    if ($sInactiveClassificationIds !== '') {
        $aInactiveClassificationIds = explode(',', $sInactiveClassificationIds);
        $aInactiveClasses = array_filter($aInactiveClassificationIds, fn ($k): bool => is_numeric($k));
    }

    // Pagination parameters
    $page           = isset($queryParams['page']) ? max(1, InputUtils::filterInt($queryParams['page'])) : 1;
    $allowedLimits  = [20, 50, 100];
    $requestedLimit = isset($queryParams['perPage']) ? (int)$queryParams['perPage'] : 50;
    // 0 = show all; otherwise clamp to allowed values
    $limit          = ($requestedLimit === 0) ? 0 : (in_array($requestedLimit, $allowedLimits, true) ? $requestedLimit : 50);

    // Build base query
    $peopleQuery = PersonQuery::create()
        ->orderByLastName()
        ->orderByFirstName();

    // Exclude inactive classifications by default
    if (!empty($aInactiveClasses)) {
        $peopleQuery->filterByClsId($aInactiveClasses, Criteria::NOT_IN);
    }

    // Apply classification filter
    if ($filterUnassigned) {
        // Unassigned = cls_id = 0 (default) OR NULL
        $peopleQuery->where('Person.ClsId IS NULL OR Person.ClsId = 0');
    } elseif ($classificationFilter !== null) {
        $peopleQuery->filterByClsId($classificationFilter);
    }

    // When filtering by photos, photo availability is filesystem-based (not in DB),
    // so we must fetch all people, filter, then paginate in PHP.
    if ($showOnlyWithPhotos) {
        $allPeople     = $peopleQuery->find();
        $allPeopleData = [];
        foreach ($allPeople as $person) {
            $photo = new Photo('Person', $person->getId());
            if ($photo->hasUploadedPhoto()) {
                $allPeopleData[] = ['person' => $person, 'hasPhoto' => true];
            }
        }
        $totalMatched = count($allPeopleData);
        $totalPages   = ($limit === 0) ? 1 : (int) ceil($totalMatched / $limit);
        $peopleData   = ($limit === 0) ? $allPeopleData : array_slice($allPeopleData, ($page - 1) * $limit, $limit);
    } else {
        $totalMatched = (clone $peopleQuery)->count();
        $totalPages   = ($limit === 0) ? 1 : (int) ceil($totalMatched / $limit);
        $people       = ($limit === 0) ? $peopleQuery->find() : $peopleQuery->limit($limit)->offset(($page - 1) * $limit)->find();
        $peopleData   = [];
        foreach ($people as $person) {
            $photo = new Photo('Person', $person->getId());
            $peopleData[] = ['person' => $person, 'hasPhoto' => $photo->hasUploadedPhoto()];
        }
    }

    // Build a quick id→name map for use in the template
    $classificationMap = [];
    foreach ($classifications as $cls) {
        $classificationMap[$cls->getOptionId()] = $cls->getOptionName();
    }

    $pageArgs = [
        'sRootPath'            => SystemURLs::getRootPath(),
        'sPageTitle'           => gettext('Photo Directory'),
        'sPageSubtitle'        => gettext('Browse photos of congregation members'),
        'aBreadcrumbs'         => PageHeader::breadcrumbs([
            [gettext('People'), '/people/dashboard'],
            [gettext('Photo Directory')],
        ]),
        'peopleData'           => $peopleData,
        'classifications'      => $classifications,
        'classificationMap'    => $classificationMap,
        'showOnlyWithPhotos'   => $showOnlyWithPhotos,
        'classificationFilter' => $classificationFilter,
        'filterUnassigned'     => $filterUnassigned,
        'totalPeople'          => $totalMatched,
        'currentPage'          => $page,
        'totalPages'           => $totalPages,
        'perPage'              => $limit,
        'allowedLimits'        => $allowedLimits,
    ];

    return $renderer->render($response, 'photo-gallery.php', $pageArgs);
}