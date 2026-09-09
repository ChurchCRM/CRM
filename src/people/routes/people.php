<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\Photo;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Service\ConfirmReportEmailResult;
use ChurchCRM\Service\ConfirmReportService;
use ChurchCRM\Service\PersonService;
use ChurchCRM\Slim\Middleware\CSRFMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\view\PageHeader;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/list', function (RouteCollectorProxy $group): void {
    $group->get('/', 'listPeople');
    $group->get('', 'listPeople');
});

$app->get('/verify', 'viewPeopleVerify');
$app->get('/photos', 'viewPeoplePhotoGallery');
$app->get('/report/verify', 'generateVerifyReport');
$app->post('/report/verify/email', 'sendVerifyReportEmail')->add(new CSRFMiddleware('people_report_verify_email'));

function viewPeopleVerify(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    $queryParams = $request->getQueryParams();

    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
    ];

    // Structured email-error alert (replaces generic ?EmailsError=true toast)
    if (!empty($queryParams['EmailsError'])) {
        $reason     = $queryParams['reason']     ?? 'unknown';
        $sentCount  = isset($queryParams['sent'])    ? (int) $queryParams['sent']    : 0;
        $failedCount = isset($queryParams['failed']) ? (int) $queryParams['failed']  : 0;

        $pageArgs['emailErrorReason']  = $reason;
        $pageArgs['emailErrorSent']    = $sentCount;
        $pageArgs['emailErrorFailed']  = $failedCount;
    }

    // Success: keep existing AllPDFsEmailed handling (shown via toast + inline)
    if (!empty($queryParams['AllPDFsEmailed'])) {
        $pageArgs['emailSuccessCount'] = (int) $queryParams['AllPDFsEmailed'];
    }

    return $renderer->render($response, 'people-verify-view.php', $pageArgs);
}

/**
 * Generate and stream a confirmation report PDF for download.
 *
 * Route: GET /people/report/verify[?familyId=<int>]
 *
 * Requires the "Create Directory" permission.
 */
function generateVerifyReport(Request $request, Response $response, array $args): Response
{
    AuthenticationManager::redirectHomeIfFalse(
        AuthenticationManager::getCurrentUser()->isMenuOptionsEnabled(),
        'MenuOptions'
    );

    $queryParams = $request->getQueryParams();
    $familyId = isset($queryParams['familyId']) && $queryParams['familyId'] !== ''
        ? InputUtils::filterInt($queryParams['familyId'])
        : null;

    try {
        $service = new ConfirmReportService();
        $result = $service->generateDownloadPDF($familyId);

        $disposition = SystemConfig::getIntValue('iPDFOutputType') === 1 ? 'attachment' : 'inline';
        $response->getBody()->write($result['bytes']);
        return $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', $disposition . '; filename="' . $result['filename'] . '"');
    } catch (\Throwable $e) {
        LoggerUtils::getAppLogger()->error('generateVerifyReport error: ' . $e->getMessage(), ['exception' => $e]);
        $response->getBody()->write('An error occurred while generating the PDF. Please check the application logs.');
        return $response->withStatus(500)->withHeader('Content-Type', 'text/plain');
    }
}

/**
 * Generate per-family confirmation PDFs and email them, then redirect.
 *
 * Route: POST /people/report/verify/email (CSRF-protected state-changing action)
 *
 * Requires the "Create Directory" permission.
 */
function sendVerifyReportEmail(Request $request, Response $response, array $args): Response
{
    AuthenticationManager::redirectHomeIfFalse(
        AuthenticationManager::getCurrentUser()->isMenuOptionsEnabled(),
        'MenuOptions'
    );

    // Detect AJAX requests — these get a JSON response instead of a redirect
    $isAjax = $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest'
        || str_contains(strtolower($request->getHeaderLine('Accept')), 'application/json');

    if (!SystemConfig::isEmailEnabled()) {
        if ($isAjax) {
            return SlimUtils::renderErrorJSON($response, gettext('Email is not configured. Please configure SMTP settings in System Settings.'), [], 400);
        }
        return SlimUtils::renderRedirect($response, SystemURLs::getRootPath() . '/people/verify?EmailsError=1&reason=' . ConfirmReportEmailResult::STATUS_EMAIL_DISABLED);
    }

    $params = array_merge($request->getQueryParams(), (array) ($request->getParsedBody() ?? []));
    $familyId = isset($params['familyId']) && $params['familyId'] !== ''
        ? InputUtils::filterInt($params['familyId'])
        : null;
    $updated = !empty($params['updated']);

    try {
        $service = new ConfirmReportService();
        $result  = $service->sendFamilyEmails($familyId, $updated);
    } catch (\Throwable $e) {
        LoggerUtils::getAppLogger()->error('sendVerifyReportEmail error: ' . $e->getMessage(), ['exception' => $e]);
        if ($isAjax) {
            return SlimUtils::renderErrorJSON($response, gettext('Unexpected error while sending emails. Please check logs.'), [], 500, $e, $request);
        }
        return SlimUtils::renderRedirect($response, SystemURLs::getRootPath() . '/people/verify?EmailsError=1&reason=unexpected');
    }

    // Single-family path: on success redirect to the family page; on failure redirect to
    // /people/verify which already handles all EmailsError display logic.
    if ($familyId !== null) {
        if (!$result->isSuccess()) {
            $query = http_build_query([
                'EmailsError' => '1',
                'reason'      => $result->status,
                'sent'        => $result->sentCount,
                'failed'      => $result->failedCount,
            ]);
            return SlimUtils::renderRedirect($response, SystemURLs::getRootPath() . '/people/verify?' . $query);
        }
        return SlimUtils::renderRedirect(
            $response,
            SystemURLs::getRootPath() . '/people/family/' . $familyId . '?PDFEmailed=' . $result->sentCount
        );
    }

    if ($isAjax) {
        // toMessage() centralises all status→text logic on the value object
        $message = $result->toMessage();
        return SlimUtils::renderJSON($response, array_merge($result->toArray(), ['message' => $message]));
    }

    // Full-page redirect — encode enough info to render a specific inline alert
    if ($result->isSuccess()) {
        return SlimUtils::renderRedirect(
            $response,
            SystemURLs::getRootPath() . '/people/verify?AllPDFsEmailed=' . $result->sentCount
        );
    }

    $query = http_build_query([
        'EmailsError' => '1',
        'reason'      => $result->status,
        'sent'        => $result->sentCount,
        'failed'      => $result->failedCount,
    ]);
    return SlimUtils::renderRedirect($response, SystemURLs::getRootPath() . '/people/verify?' . $query);
}


function listPeople(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer(__DIR__ . '/../views/');
    // Filter received user input as needed
    $members = PersonQuery::create();
    $sMode = 'Person';
    $familyActiveStatus = 'active';
    if (($_GET['familyActiveStatus'] ?? '') === 'inactive') {
        $familyActiveStatus = 'inactive';
    } elseif (($_GET['familyActiveStatus'] ?? '') === 'all') {
        $familyActiveStatus = 'all';
    }

    $sInactiveClassificationIds = SystemConfig::getValue('sInactiveClassification');

    if ($sInactiveClassificationIds === '') {
        $sInactiveClassificationIds = '-1';
    }

    $aInactiveClassificationIds = explode(',', $sInactiveClassificationIds);
    $aInactiveClasses = array_filter($aInactiveClassificationIds, fn ($k): bool => is_numeric($k));

    if (count($aInactiveClassificationIds) !== count($aInactiveClasses)) {
        LoggerUtils::getAppLogger()->warning('Encountered invalid configuration(s) for sInactiveClassification, please fix this');
    }

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

    $personService = new PersonService();

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
        'genderDataCheckCount'            => $personService->getMissingGenderDataCount(),
        'roleDataCheckCount'              => $personService->getMissingRoleDataCount(),
        'classificationDataCheckCount'    => $personService->getMissingClassificationDataCount(),
    ];

    return $renderer->render($response, 'person-list.php', $pageArgs);
}

function viewPeoplePhotoGallery(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    $queryParams = $request->getQueryParams();
    $showOnlyWithPhotos = !array_key_exists('photosOnly', $queryParams) || $queryParams['photosOnly'] !== '0';
    $classificationRaw    = $queryParams['classification'] ?? '';
    $filterUnassigned     = ($classificationRaw === '-1');
    $classificationFilter = (!$filterUnassigned && $classificationRaw !== '') ? InputUtils::filterInt($classificationRaw) : null;

    $classifications = ListOptionQuery::create()
        ->filterById(1)
        ->orderByOptionSequence()
        ->find();

    $sInactiveClassificationIds = SystemConfig::getValue('sInactiveClassification');
    $aInactiveClasses = [];
    if ($sInactiveClassificationIds !== '') {
        $aInactiveClassificationIds = explode(',', $sInactiveClassificationIds);
        $aInactiveClasses = array_filter($aInactiveClassificationIds, fn ($k): bool => is_numeric($k));
    }

    $page           = isset($queryParams['page']) ? max(1, InputUtils::filterInt($queryParams['page'])) : 1;
    $allowedLimits  = [20, 50, 100];
    $requestedLimit = isset($queryParams['perPage']) ? (int)$queryParams['perPage'] : 50;
    $limit          = ($requestedLimit === 0) ? 0 : (in_array($requestedLimit, $allowedLimits, true) ? $requestedLimit : 50);

    $peopleQuery = PersonQuery::create()
        ->orderByLastName()
        ->orderByFirstName();

    if (!empty($aInactiveClasses)) {
        $peopleQuery->filterByClsId($aInactiveClasses, Criteria::NOT_IN);
    }

    if ($filterUnassigned) {
        $peopleQuery->where('Person.ClsId IS NULL OR Person.ClsId = 0');
    } elseif ($classificationFilter !== null) {
        $peopleQuery->filterByClsId($classificationFilter);
    }

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

