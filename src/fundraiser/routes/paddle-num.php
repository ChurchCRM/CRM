<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\FundRaiserQuery;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\model\ChurchCRM\PaddlenumPnQuery;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

// GET /fundraiser/{fundraiserId}/paddle-numbers — buyer list (migrated from PaddleNumList.php)
$app->get('/{fundraiserId}/paddle-numbers', function (Request $request, Response $response, array $args): Response {
    $fundraiserId = (int) $args['fundraiserId'];

    $fundraiser = FundRaiserQuery::create()->findOneById($fundraiserId);
    if ($fundraiser === null) {
        return $response
            ->withHeader('Location', SystemURLs::getRootPath() . '/fundraiser/')
            ->withStatus(302);
    }

    $_SESSION['iCurrentFundraiser'] = $fundraiserId;

    $paddleNums = [];
    $sSQL = "SELECT pn_ID, pn_fr_ID, pn_Num, pn_per_ID,
                    a.per_FirstName as buyerFirstName, a.per_LastName as buyerLastName
             FROM paddlenum_pn
             LEFT JOIN person_per a ON pn_per_ID=a.per_ID
             WHERE pn_FR_ID = '" . $fundraiserId . "' ORDER BY pn_Num";
    $rsPaddleNums = RunQuery($sSQL);
    while ($aRow = mysqli_fetch_array($rsPaddleNums)) {
        $paddleNums[] = $aRow;
    }

    $params = $request->getQueryParams();
    $selectAll = isset($params['selectAll']);

    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    return $renderer->render($response, 'paddle-num-list.php', [
        'sRootPath'     => SystemURLs::getRootPath(),
        'sPageTitle'    => gettext('Buyers for this fundraiser:'),
        'sPageSubtitle' => gettext('View buyer numbers and paddle assignments'),
        'aBreadcrumbs'  => PageHeader::breadcrumbs([
            [gettext('Fundraiser'), '/fundraiser/'],
            [gettext('Edit Fundraiser'), '/fundraiser/editor/' . $fundraiserId],
            [gettext('Buyers')],
        ]),
        'fundraiserId' => $fundraiserId,
        'fundraiser'   => $fundraiser,
        'paddleNums'   => $paddleNums,
        'selectAll'    => $selectAll,
    ]);
});

// GET /fundraiser/{fundraiserId}/paddle-numbers/editor[/{paddleId}] — editor (migrated from PaddleNumEditor.php)
$app->get('/{fundraiserId}/paddle-numbers/editor[/{paddleId}]', function (Request $request, Response $response, array $args): Response {
    $fundraiserId = (int) $args['fundraiserId'];
    $paddleId     = (int) ($args['paddleId'] ?? 0);

    $fundraiser = FundRaiserQuery::create()->findOneById($fundraiserId);
    if ($fundraiser === null) {
        return $response
            ->withHeader('Location', SystemURLs::getRootPath() . '/fundraiser/')
            ->withStatus(302);
    }

    $_SESSION['iCurrentFundraiser'] = $fundraiserId;

    $sMultibuyItemsSQL = "SELECT di_ID, di_title FROM donateditem_di WHERE di_multibuy='1' AND di_FR_ID=" . $fundraiserId;

    $iNum  = 0;
    $iPerID = 0;

    if ($paddleId > 0) {
        $sSQL = "SELECT pn_ID, pn_fr_ID, pn_Num, pn_per_ID,
                        a.per_FirstName as buyerFirstName, a.per_LastName as buyerLastName
                 FROM paddlenum_pn
                 LEFT JOIN person_per a ON pn_per_ID=a.per_ID
                 WHERE pn_ID = '" . $paddleId . "'";
        $rsPaddleNum = RunQuery($sSQL);
        $thePaddleNum = mysqli_fetch_array($rsPaddleNum);
        $iNum   = (int) ($thePaddleNum['pn_Num'] ?? 0);
        $iPerID = (int) ($thePaddleNum['pn_per_ID'] ?? 0);
    } else {
        // Adding: set default next paddle number
        $sSQL = 'SELECT COUNT(*) AS topNum FROM paddlenum_pn WHERE pn_fr_ID=' . $fundraiserId;
        $rsGetMaxNum = RunQuery($sSQL);
        $row = mysqli_fetch_array($rsGetMaxNum);
        $iNum = (int) $row['topNum'] + 1;
    }

    // Get multibuy counts for this person
    $multibuyItems = [];
    $rsMBItems = RunQuery($sMultibuyItemsSQL);
    while ($aRow = mysqli_fetch_array($rsMBItems)) {
        $di_ID    = $aRow['di_ID'];
        $di_title = $aRow['di_title'];
        $mbCount  = 0;
        if ($iPerID > 0) {
            $sqlNumBought = 'SELECT mb_count from multibuy_mb WHERE mb_per_ID=' . $iPerID . ' AND mb_item_ID=' . (int) $di_ID;
            $rsNumBought  = RunQuery($sqlNumBought);
            $numBoughtRow = mysqli_fetch_array($rsNumBought);
            $mbCount      = $numBoughtRow ? (int) $numBoughtRow['mb_count'] : 0;
        }
        $multibuyItems[] = [
            'di_ID'    => $di_ID,
            'di_title' => $di_title,
            'mb_count' => $mbCount,
        ];
    }

    // Get people for drop-down
    $sPeopleSQL = 'SELECT per_ID, per_FirstName, per_LastName, fam_Address1, fam_City, fam_State FROM person_per JOIN family_fam on per_fam_id=fam_id ORDER BY per_LastName, per_FirstName';
    $people     = [];
    $rsPeople   = RunQuery($sPeopleSQL);
    while ($aRow = mysqli_fetch_array($rsPeople)) {
        $people[] = $aRow;
    }

    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    return $renderer->render($response, 'paddle-num-editor.php', [
        'sRootPath'      => SystemURLs::getRootPath(),
        'sPageTitle'     => gettext('Buyer Number Editor'),
        'sPageSubtitle'  => gettext('Assign and manage fundraiser buyer numbers'),
        'aBreadcrumbs'   => PageHeader::breadcrumbs([
            [gettext('Fundraiser'), '/fundraiser/'],
            [gettext('Buyers'), '/fundraiser/' . $fundraiserId . '/paddle-numbers'],
            [gettext('Edit Buyer')],
        ]),
        'fundraiserId'   => $fundraiserId,
        'paddleId'       => $paddleId,
        'fundraiser'     => $fundraiser,
        'iNum'           => $iNum,
        'iPerID'         => $iPerID,
        'multibuyItems'  => $multibuyItems,
        'people'         => $people,
        'canAddRecords'  => AuthenticationManager::getCurrentUser()->isAddRecordsEnabled(),
    ]);
});

// POST /fundraiser/{fundraiserId}/paddle-numbers/editor[/{paddleId}] — save (migrated from PaddleNumEditor.php)
$app->post('/{fundraiserId}/paddle-numbers/editor[/{paddleId}]', function (Request $request, Response $response, array $args): Response {
    $fundraiserId = (int) $args['fundraiserId'];
    $paddleId     = (int) ($args['paddleId'] ?? 0);
    $body         = (array) $request->getParsedBody();

    $fundraiser = FundRaiserQuery::create()->findOneById($fundraiserId);
    if ($fundraiser === null) {
        return $response
            ->withHeader('Location', SystemURLs::getRootPath() . '/fundraiser/')
            ->withStatus(302);
    }

    $_SESSION['iCurrentFundraiser'] = $fundraiserId;

    if (!CSRFUtils::verifyRequest($body, 'paddle_num_editor')) {
        $response->getBody()->write(gettext('Invalid security token. Please try again.'));
        return $response->withStatus(400)->withHeader('Content-Type', 'text/plain');
    }

    $iNum   = (int) InputUtils::legacyFilterInput($body['Num'] ?? '0');
    $iPerID = (int) InputUtils::legacyFilterInput($body['PerID'] ?? '0');

    // Handle multibuy items
    $sMultibuyItemsSQL = "SELECT di_ID, di_title FROM donateditem_di WHERE di_multibuy='1' AND di_FR_ID=" . $fundraiserId;
    $rsMBItems         = RunQuery($sMultibuyItemsSQL);
    while ($aRow = mysqli_fetch_array($rsMBItems)) {
        $di_ID    = $aRow['di_ID'];
        $mbName   = 'MBItem' . $di_ID;
        $iMBCount = (int) InputUtils::legacyFilterInput($body[$mbName] ?? '0', 'int');

        if ($iMBCount > 0) {
            $sqlNumBought = 'SELECT mb_count from multibuy_mb WHERE mb_per_ID=' . $iPerID . ' AND mb_item_ID=' . (int) $di_ID;
            $rsNumBought  = RunQuery($sqlNumBought);
            $numBoughtRow = mysqli_fetch_array($rsNumBought);
            if ($numBoughtRow) {
                $sSQL = 'UPDATE multibuy_mb SET mb_count=' . $iMBCount . ' WHERE mb_per_ID=' . $iPerID . ' AND mb_item_ID=' . (int) $di_ID;
                RunQuery($sSQL);
            } else {
                $sSQL = 'INSERT INTO multibuy_mb (mb_per_ID, mb_item_ID, mb_count) VALUES (' . $iPerID . ',' . (int) $di_ID . ',' . $iMBCount . ')';
                RunQuery($sSQL);
            }
        } else {
            $sSQL = 'DELETE FROM multibuy_mb WHERE mb_per_ID=' . $iPerID . ' AND mb_item_ID=' . (int) $di_ID;
            RunQuery($sSQL);
        }
    }

    if ($paddleId <= 0) {
        // New paddle number
        $sSQL       = 'INSERT INTO paddlenum_pn (pn_fr_ID, pn_Num, pn_per_ID) VALUES (' . $fundraiserId . ',' . $iNum . ',' . $iPerID . ')';
        RunQuery($sSQL);
        // Get the new paddle ID
        $rsNew    = RunQuery('SELECT MAX(pn_ID) AS iPaddleNumID FROM paddlenum_pn');
        $newRow   = mysqli_fetch_array($rsNew);
        $paddleId = (int) $newRow['iPaddleNumID'];
    } else {
        $sSQL = 'UPDATE paddlenum_pn SET pn_fr_ID = ' . $fundraiserId . ', pn_Num = ' . $iNum . ', pn_per_ID = ' . $iPerID . ' WHERE pn_ID = ' . $paddleId;
        RunQuery($sSQL);
    }

    $action = '';
    if (isset($body['PaddleNumSubmit'])) {
        $action = 'save';
    } elseif (isset($body['PaddleNumSubmitAndAdd'])) {
        $action = 'add';
    } elseif (isset($body['GenerateStatement'])) {
        $action = 'statement';
    }

    return match ($action) {
        'save'      => $response->withHeader('Location', SystemURLs::getRootPath() . '/fundraiser/' . $fundraiserId . '/paddle-numbers/editor/' . $paddleId)->withStatus(302),
        'add'       => $response->withHeader('Location', SystemURLs::getRootPath() . '/fundraiser/' . $fundraiserId . '/paddle-numbers/editor')->withStatus(302),
        'statement' => $response->withHeader('Location', SystemURLs::getRootPath() . '/fundraiser/' . $fundraiserId . '/reports/statement?paddleId=' . $paddleId)->withStatus(302),
        default     => $response->withHeader('Location', SystemURLs::getRootPath() . '/fundraiser/' . $fundraiserId . '/paddle-numbers')->withStatus(302),
    };
});

// POST /fundraiser/{fundraiserId}/paddle-numbers/{paddleId}/delete — delete a paddle number
// Module middleware covers ManageFundraisers; inline guard adds DeleteRecords.
$app->post('/{fundraiserId}/paddle-numbers/{paddleId}/delete', function (Request $request, Response $response, array $args): Response {
    $currentUser = AuthenticationManager::getCurrentUser();
    if (!$currentUser->isDeleteRecordsEnabled()) {
        return $response
            ->withHeader('Location', SystemURLs::getRootPath() . '/v2/access-denied?role=DeleteRecords')
            ->withStatus(302);
    }

    $body = (array) $request->getParsedBody();

    if (!CSRFUtils::verifyRequest($body, 'paddle_num_delete')) {
        $response->getBody()->write(gettext('Invalid security token. Please try again.'));
        return $response->withStatus(403)->withHeader('Content-Type', 'text/plain');
    }

    $fundraiserId = (int) $args['fundraiserId'];
    $paddleId     = (int) $args['paddleId'];

    if ($paddleId > 0 && $fundraiserId > 0) {
        PaddlenumPnQuery::create()
            ->filterByPnId($paddleId)
            ->filterByPnFrId($fundraiserId)
            ->delete();
    }

    return $response
        ->withHeader('Location', SystemURLs::getRootPath() . '/fundraiser/' . $fundraiserId . '/paddle-numbers')
        ->withStatus(302);
});
