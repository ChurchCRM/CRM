<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\DonatedItemQuery;
use ChurchCRM\model\ChurchCRM\DonationFundQuery;
use ChurchCRM\model\ChurchCRM\FundRaiser;
use ChurchCRM\model\ChurchCRM\FundRaiserQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Service\FundRaiserService;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\view\PageHeader;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

// GET /fundraiser/ — fundraiser listing
$app->get('/', function (Request $request, Response $response): Response {
    $params      = $request->getQueryParams();
    $sDateFormat = SystemConfig::getValue('sDatePickerFormat');

    $fundraisersQuery = FundRaiserQuery::create()->orderByDate('desc');

    $dateStart    = '';
    $dateEnd      = '';
    $filterStatus = '';
    $filterType   = '';

    if (!empty($params['dateStart'])) {
        $dateStart = InputUtils::legacyFilterInput($params['dateStart']);
        if ($dateStart !== '') {
            $dateStartObj = \DateTime::createFromFormat($sDateFormat, $dateStart);
            if ($dateStartObj !== false) {
                $fundraisersQuery->filterByDate($dateStartObj, Criteria::GREATER_EQUAL);
            }
        }
    }

    if (!empty($params['dateEnd'])) {
        $dateEnd = InputUtils::legacyFilterInput($params['dateEnd']);
        if ($dateEnd !== '') {
            $dateEndObj = \DateTime::createFromFormat($sDateFormat, $dateEnd);
            if ($dateEndObj !== false) {
                $fundraisersQuery->filterByDate($dateEndObj, Criteria::LESS_EQUAL);
            }
        }
    }

    if (!empty($params['filterStatus'])) {
        $filterStatus = InputUtils::legacyFilterInput($params['filterStatus']);
        if (in_array($filterStatus, ['Planning', 'Active', 'Closed'], true)) {
            $fundraisersQuery->filterByStatus($filterStatus);
        }
    }

    if (!empty($params['filterType'])) {
        $filterType = InputUtils::legacyFilterInput($params['filterType']);
        $allowedTypes = ['Auction', 'Silent Auction', 'Live Auction', 'Raffle', 'Gala', 'Mixed'];
        if (in_array($filterType, $allowedTypes, true)) {
            $fundraisersQuery->filterByType($filterType);
        } else {
            $filterType = ''; // ignore invalid type
        }
    }

    $allFundraisers = $fundraisersQuery->find();

    // Split into active vs archived using DateTimeUtils so the church timezone is applied.
    // When a status filter is explicitly active the user intends to see all matching rows
    // in the active table (no date-based override into archive).
    $today            = DateTimeUtils::getToday();
    $activeFundraisers   = [];
    $archivedFundraisers = [];
    foreach ($allFundraisers as $fr) {
        $status = $fr->getStatus() ?? 'Active';
        $effectiveEnd = $fr->getEndDate() ?? $fr->getDate();
        $isArchived = $filterStatus === ''
            && ($status === 'Closed' || ($effectiveEnd !== null && $effectiveEnd < $today));
        if ($isArchived) {
            $archivedFundraisers[] = $fr;
        } else {
            $activeFundraisers[] = $fr;
        }
    }

    // Aggregates (single grouped queries — no N+1).
    // Pass the filtered ID list so the service skips unrelated rows on large installs.
    $service     = new FundRaiserService();
    $allIds      = array_merge(
        array_map(fn($fr) => (int) $fr->getId(), $activeFundraisers),
        array_map(fn($fr) => (int) $fr->getId(), $archivedFundraisers)
    );
    $summaries   = $service->getListSummaries($allIds);
    $widgetStats = $service->getWidgetStats();

    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    return $renderer->render($response, 'fundraiser-list.php', [
        'sRootPath'           => SystemURLs::getRootPath(),
        'sPageTitle'          => gettext('Fundraiser Listing'),
        'sPageSubtitle'       => gettext('Browse and search fundraiser campaigns'),
        'aBreadcrumbs'        => PageHeader::breadcrumbs([
            [gettext('Fundraiser')],
        ]),
        'activeFundraisers'   => $activeFundraisers,
        'archivedFundraisers' => $archivedFundraisers,
        'summaries'           => $summaries,
        'widgetStats'         => $widgetStats,
        'sDateFormat'         => $sDateFormat,
        'dateStart'           => $dateStart,
        'dateEnd'             => $dateEnd,
        'filterStatus'        => $filterStatus,
        'filterType'          => $filterType,
    ]);
});

// GET /fundraiser/view/{fundraiserId} — read-only summary page
$app->get('/view/{fundraiserId:[0-9]+}', function (Request $request, Response $response, array $args): Response {
    $fundraiserId = (int) $args['fundraiserId'];

    $fundraiser = FundRaiserQuery::create()->findOneById($fundraiserId);
    if ($fundraiser === null) {
        return $response
            ->withHeader('Location', SystemURLs::getRootPath() . '/fundraiser/')
            ->withStatus(302);
    }

    $_SESSION['iCurrentFundraiser'] = $fundraiserId;

    // Load donated items with donor/buyer person map
    $donatedItems = DonatedItemQuery::create()
        ->filterByFrId($fundraiserId)
        ->addAscendingOrderByColumn('SUBSTR(di_item,1,1)')
        ->addAscendingOrderByColumn('cast(SUBSTR(di_item,2) as unsigned integer)')
        ->addAscendingOrderByColumn('SUBSTR(di_item,4)')
        ->find();

    $personIds = [];
    foreach ($donatedItems as $item) {
        if ($item->getDonorId()) {
            $personIds[] = (int) $item->getDonorId();
        }
        if ($item->getBuyerId()) {
            $personIds[] = (int) $item->getBuyerId();
        }
    }
    $personMap = [];
    if (!empty($personIds)) {
        foreach (PersonQuery::create()->findPks(array_unique($personIds)) as $person) {
            $personMap[$person->getId()] = $person;
        }
    }

    // Resolve linked donation fund name
    $fundName = null;
    if ($fundraiser->getFundId() !== null) {
        $fund = DonationFundQuery::create()->findPk($fundraiser->getFundId());
        if ($fund !== null) {
            $fundName = $fund->getName();
        }
    }

    // Aggregate stats (no N+1)
    $service   = new FundRaiserService();
    $viewModel = $service->getViewModel($fundraiserId);

    $currentUser  = AuthenticationManager::getCurrentUser();
    $canEdit      = $currentUser->isManageFundraisersEnabled();
    $sDateFormat  = SystemConfig::getValue('sDatePickerFormat');

    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    return $renderer->render($response, 'fundraiser-view.php', [
        'sRootPath'     => SystemURLs::getRootPath(),
        'sPageTitle'    => $fundraiser->getTitle(),
        'sPageSubtitle' => gettext('Fundraiser Overview'),
        'aBreadcrumbs'  => PageHeader::breadcrumbs([
            [gettext('Fundraiser'), '/fundraiser/'],
            [$fundraiser->getTitle()],
        ]),
        'fundraiser'    => $fundraiser,
        'fundraiserId'  => $fundraiserId,
        'donatedItems'  => $donatedItems,
        'personMap'     => $personMap,
        'fundName'      => $fundName,
        'viewModel'     => $viewModel,
        'canEdit'       => $canEdit,
        'sDateFormat'   => $sDateFormat,
    ]);
});

// GET /fundraiser/editor[/{fundraiserId}] — editor / creator form (migrated from FundRaiserEditor.php)
$app->get('/editor[/{fundraiserId}]', function (Request $request, Response $response, array $args): Response {
    $fundraiserId = (int) ($args['fundraiserId'] ?? 0);

    $fundraiser   = null;
    $donatedItems = null;
    $personMap    = [];
    $sPageTitle   = gettext('Create New Fund Raiser');
    $dDate        = date_create('now');
    $sTitle       = '';
    $sDescription = '';

    if ($fundraiserId > 0) {
        $fundraiser = FundRaiserQuery::create()->findOneById($fundraiserId);
        if ($fundraiser === null) {
            return $response
                ->withHeader('Location', SystemURLs::getRootPath() . '/fundraiser/')
                ->withStatus(302);
        }

        $sPageTitle   = gettext('Fundraiser') . ' #' . $fundraiserId . ' ' . $fundraiser->getTitle();
        $dDate        = $fundraiser->getDate();
        $sTitle       = $fundraiser->getTitle();
        $sDescription = $fundraiser->getDescription();

        $_SESSION['iCurrentFundraiser'] = $fundraiserId;

        $donatedItems = DonatedItemQuery::create()
            ->filterByFrId($fundraiserId)
            ->addAscendingOrderByColumn('di_multibuy')
            ->addAscendingOrderByColumn('SUBSTR(di_item,1,1)')
            ->addAscendingOrderByColumn('cast(SUBSTR(di_item,2) as unsigned integer)')
            ->addAscendingOrderByColumn('SUBSTR(di_item,4)')
            ->find();

        $personIds = [];
        foreach ($donatedItems as $item) {
            if ($item->getDonorId()) {
                $personIds[] = (int) $item->getDonorId();
            }
            if ($item->getBuyerId()) {
                $personIds[] = (int) $item->getBuyerId();
            }
        }
        if (!empty($personIds)) {
            $persons = PersonQuery::create()->findPks(array_unique($personIds));
            foreach ($persons as $person) {
                $personMap[$person->getId()] = $person;
            }
        }
    }

    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    return $renderer->render($response, 'fundraiser-editor.php', [
        'sRootPath'     => SystemURLs::getRootPath(),
        'sPageTitle'    => $sPageTitle,
        'sPageSubtitle' => gettext('Set up a new fundraiser campaign or event'),
        'aBreadcrumbs'  => PageHeader::breadcrumbs([
            [gettext('Fundraiser'), '/fundraiser/'],
            [gettext('Edit Fundraiser')],
        ]),
        'fundraiserId'  => $fundraiserId,
        'fundraiser'    => $fundraiser,
        'dDate'         => $dDate,
        'sTitle'        => $sTitle,
        'sDescription'  => $sDescription,
        'donatedItems'  => $donatedItems,
        'personMap'     => $personMap,
        'sDateError'    => '',
    ]);
});

// POST /fundraiser/editor[/{fundraiserId}] — save fundraiser (migrated from FundRaiserEditor.php)
$app->post('/editor[/{fundraiserId}]', function (Request $request, Response $response, array $args): Response {
    $fundraiserId = (int) ($args['fundraiserId'] ?? 0);
    $body         = (array) $request->getParsedBody();

    $dDate        = InputUtils::legacyFilterInput($body['Date'] ?? '');
    $sTitle       = InputUtils::legacyFilterInput($body['Title'] ?? '');
    $sDescription = InputUtils::legacyFilterInput($body['Description'] ?? '');

    $bErrorFlag = false;
    $sDateError = '';

    if (strlen($dDate) > 0) {
        [$iYear, $iMonth, $iDay] = sscanf($dDate, '%04d-%02d-%02d');
        if (!checkdate((int) $iMonth, (int) $iDay, (int) $iYear)) {
            $sDateError = gettext('Not a valid date');
            $bErrorFlag = true;
        }
    }

    if ($bErrorFlag) {
        $fundraiser   = $fundraiserId > 0 ? FundRaiserQuery::create()->findOneById($fundraiserId) : null;
        $donatedItems = null;
        $personMap    = [];

        if ($fundraiser) {
            $donatedItems = DonatedItemQuery::create()
                ->filterByFrId($fundraiserId)
                ->addAscendingOrderByColumn('di_multibuy')
                ->addAscendingOrderByColumn('SUBSTR(di_item,1,1)')
                ->addAscendingOrderByColumn('cast(SUBSTR(di_item,2) as unsigned integer)')
                ->addAscendingOrderByColumn('SUBSTR(di_item,4)')
                ->find();
            $personIds = [];
            foreach ($donatedItems as $item) {
                if ($item->getDonorId()) {
                    $personIds[] = (int) $item->getDonorId();
                }
                if ($item->getBuyerId()) {
                    $personIds[] = (int) $item->getBuyerId();
                }
            }
            if (!empty($personIds)) {
                $persons = PersonQuery::create()->findPks(array_unique($personIds));
                foreach ($persons as $person) {
                    $personMap[$person->getId()] = $person;
                }
            }
        }

        $renderer = new PhpRenderer(__DIR__ . '/../views/');

        return $renderer->render($response, 'fundraiser-editor.php', [
            'sRootPath'     => SystemURLs::getRootPath(),
            'sPageTitle'    => $fundraiserId > 0 ? gettext('Edit Fundraiser') : gettext('Create New Fund Raiser'),
            'sPageSubtitle' => gettext('Set up a new fundraiser campaign or event'),
            'aBreadcrumbs'  => PageHeader::breadcrumbs([
                [gettext('Fundraiser'), '/fundraiser/'],
                [gettext('Edit Fundraiser')],
            ]),
            'fundraiserId'  => $fundraiserId,
            'fundraiser'    => $fundraiser,
            'dDate'         => $dDate,
            'sTitle'        => $sTitle,
            'sDescription'  => $sDescription,
            'donatedItems'  => $donatedItems,
            'personMap'     => $personMap,
            'sDateError'    => $sDateError,
        ]);
    }

    $currentUser = AuthenticationManager::getCurrentUser();

    if ($fundraiserId <= 0) {
        $fundraiser = new FundRaiser();
        $fundraiser
            ->setDate($dDate)
            ->setTitle($sTitle)
            ->setDescription($sDescription)
            ->setEnteredBy($currentUser->getId())
            ->setEnteredDate(DateTimeUtils::getToday()->format('YmdHis'));
        $fundraiser->save();
        $fundraiser->reload();
        $fundraiserId = $fundraiser->getId();
    } else {
        $fundraiser = FundRaiserQuery::create()->findOneById($fundraiserId);
        if ($fundraiser === null) {
            return $response
                ->withHeader('Location', SystemURLs::getRootPath() . '/fundraiser/')
                ->withStatus(302);
        }
        $fundraiser
            ->setDate($dDate)
            ->setTitle($sTitle)
            ->setDescription($sDescription)
            ->setEnteredBy($currentUser->getId())
            ->setEnteredDate(DateTimeUtils::getToday()->format('YmdHis'));
        $fundraiser->save();
    }

    // Invalidate the session-cached active count so Menu.php refreshes it.
    unset($_SESSION['iFundraiserActiveCount']);

    $_SESSION['iCurrentFundraiser'] = $fundraiserId;

    return $response
        ->withHeader('Location', SystemURLs::getRootPath() . '/fundraiser/editor/' . $fundraiserId)
        ->withStatus(302);
});

// POST /fundraiser/{fundraiserId}/delete — delete a fundraiser
// Module middleware covers ManageFundraisers; inline guard adds DeleteRecords.
// (Migrated from FundRaiserDelete.php per PR #9078 permission model.)
$app->post('/{fundraiserId}/delete', function (Request $request, Response $response, array $args): Response {
    $currentUser = AuthenticationManager::getCurrentUser();
    if (!$currentUser->isDeleteRecordsEnabled()) {
        return $response
            ->withHeader('Location', SystemURLs::getRootPath() . '/v2/access-denied?role=DeleteRecords')
            ->withStatus(302);
    }

    $fundraiserId = (int) $args['fundraiserId'];
    if ($fundraiserId > 0) {
        $fundraiser = FundRaiserQuery::create()->findPk($fundraiserId);
        if ($fundraiser) {
            $fundraiser->delete();
        }
    }

    // Invalidate the session-cached active count so Menu.php refreshes it.
    unset($_SESSION['iFundraiserActiveCount']);

    return $response
        ->withHeader('Location', SystemURLs::getRootPath() . '/fundraiser/')
        ->withStatus(302);
});
