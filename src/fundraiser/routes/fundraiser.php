<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\DonatedItemQuery;
use ChurchCRM\model\ChurchCRM\FundRaiser;
use ChurchCRM\model\ChurchCRM\FundRaiserQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\view\PageHeader;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

// GET /fundraiser/ — fundraiser listing (migrated from FindFundRaiser.php)
$app->get('/', function (Request $request, Response $response): Response {
    $params = $request->getQueryParams();
    $sDateFormat = SystemConfig::getValue('sDatePickerFormat');

    $fundraisersQuery = FundRaiserQuery::create()->orderByDate('desc');

    $dateStart = '';
    $dateEnd = '';

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

    $fundraisers = $fundraisersQuery->find();

    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    return $renderer->render($response, 'fundraiser-list.php', [
        'sRootPath'     => SystemURLs::getRootPath(),
        'sPageTitle'    => gettext('Fundraiser Listing'),
        'sPageSubtitle' => gettext('Browse and search fundraiser campaigns'),
        'aBreadcrumbs'  => PageHeader::breadcrumbs([
            [gettext('Fundraiser')],
        ]),
        'fundraisers'  => $fundraisers,
        'sDateFormat'  => $sDateFormat,
        'dateStart'    => $dateStart,
        'dateEnd'      => $dateEnd,
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

    if (!CSRFUtils::verifyRequest($body, 'fundraiser_editor')) {
        $response->getBody()->write(gettext('Invalid security token. Please try again.'));
        return $response->withStatus(400)->withHeader('Content-Type', 'text/plain');
    }

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

    $body = (array) $request->getParsedBody();

    if (!CSRFUtils::verifyRequest($body, 'fundraiser_delete')) {
        $response->getBody()->write(gettext('Invalid security token. Please try again.'));
        return $response->withStatus(403)->withHeader('Content-Type', 'text/plain');
    }

    $fundraiserId = (int) $args['fundraiserId'];
    if ($fundraiserId > 0) {
        $fundraiser = FundRaiserQuery::create()->findPk($fundraiserId);
        if ($fundraiser) {
            $fundraiser->delete();
        }
    }

    return $response
        ->withHeader('Location', SystemURLs::getRootPath() . '/fundraiser/')
        ->withStatus(302);
});
