<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\DonatedItemQuery;
use ChurchCRM\model\ChurchCRM\FundRaiserQuery;
use ChurchCRM\model\ChurchCRM\Multibuy;
use ChurchCRM\model\ChurchCRM\MultibuyQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\model\ChurchCRM\PaddleNum;
use ChurchCRM\model\ChurchCRM\PaddleNumQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
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

    $paddleModels = [];
    $buyerIds     = [];
    foreach (PaddleNumQuery::create()->filterByPnFrId($fundraiserId)->orderByPnNum()->find() as $paddle) {
        $paddleModels[] = $paddle;
        $buyerIds[]     = $paddle->getPnPerId();
    }
    $buyers = [];
    foreach (PersonQuery::create()->filterById($buyerIds)->find() as $buyer) {
        $buyers[$buyer->getId()] = $buyer;
    }
    $paddleNums = [];
    foreach ($paddleModels as $paddle) {
        $buyer = $buyers[$paddle->getPnPerId()] ?? null;
        $paddleNums[] = [
            'pn_ID'          => $paddle->getPnId(),
            'pn_fr_ID'       => $paddle->getPnFrId(),
            'pn_Num'         => $paddle->getPnNum(),
            'pn_per_ID'      => $paddle->getPnPerId(),
            'buyerFirstName' => $buyer?->getFirstName() ?? '',
            'buyerLastName'  => $buyer?->getLastName() ?? '',
        ];
    }

    $params = $request->getQueryParams();
    $selectAll = isset($params['selectAll']);

    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    return $renderer->render($response, 'paddle-num-list.php', [
        'sRootPath'     => SystemURLs::getRootPath(),
        'sPageTitle'    => gettext('Buyers for this fundraiser'),
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

    $iNum  = 0;
    $iPerID = 0;

    if ($paddleId > 0) {
        // Scope to fundraiserId to prevent cross-fundraiser editing
        $thePaddleNum = PaddleNumQuery::create()
            ->filterByPnId($paddleId)
            ->filterByPnFrId($fundraiserId)
            ->findOne();
        if ($thePaddleNum === null) {
            return $response
                ->withHeader('Location', SystemURLs::getRootPath() . '/fundraiser/' . $fundraiserId . '/paddle-numbers')
                ->withStatus(302);
        }
        $iNum   = $thePaddleNum->getPnNum();
        $iPerID = $thePaddleNum->getPnPerId();
    } else {
        // Adding: set default next paddle number
        $iNum = PaddleNumQuery::create()->filterByPnFrId($fundraiserId)->count() + 1;
    }

    // Get multibuy counts for this person
    $multibuyItems = [];
    foreach (DonatedItemQuery::create()->filterByFrId($fundraiserId)->filterByMultibuy(1)->find() as $multibuyItem) {
        $diId    = $multibuyItem->getId();
        $mbCount = 0;
        if ($iPerID > 0) {
            $numBought = MultibuyQuery::create()
                ->filterByMbPerId($iPerID)
                ->filterByMbItemId($diId)
                ->findOne();
            $mbCount = $numBought !== null ? (int) $numBought->getMbCount() : 0;
        }
        $multibuyItems[] = [
            'di_ID'    => $diId,
            'di_title' => $multibuyItem->getTitle(),
            'mb_count' => $mbCount,
        ];
    }

    // Get people for drop-down. Inner join (mirrors legacy JOIN family_fam):
    // people without a family record are excluded.
    $people = [];
    foreach (
        PersonQuery::create()
            ->joinFamily()
            ->orderByLastName()
            ->orderByFirstName()
            ->find() as $person
    ) {
        $family   = $person->getFamily();
        $people[] = [
            'per_ID'        => $person->getId(),
            'per_FirstName' => $person->getFirstName(),
            'per_LastName'  => $person->getLastName(),
            'fam_Address1'  => $family->getAddress1(),
            'fam_City'      => $family->getCity(),
            'fam_State'     => $family->getState(),
        ];
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

    $iNum   = (int) InputUtils::legacyFilterInput($body['Num'] ?? '0');
    $iPerID = (int) InputUtils::legacyFilterInput($body['PerID'] ?? '0');

    // On reassignment: delete the old owner's multibuy rows so their statement
    // no longer shows items from a paddle they no longer hold.
    if ($paddleId > 0) {
        $currentPaddle = PaddleNumQuery::create()
            ->filterByPnId($paddleId)
            ->filterByPnFrId($fundraiserId)
            ->findOne();
        $iOldPerID = $currentPaddle !== null ? (int) $currentPaddle->getPnPerId() : 0;
        if ($iOldPerID > 0 && $iOldPerID !== $iPerID) {
            $multibuyItemIds = DonatedItemQuery::create()
                ->filterByFrId($fundraiserId)
                ->filterByMultibuy(1)
                ->select(['Id'])
                ->find()
                ->toArray();
            if (!empty($multibuyItemIds)) {
                MultibuyQuery::create()
                    ->filterByMbItemId($multibuyItemIds)
                    ->filterByMbPerId($iOldPerID)
                    ->delete();
            }
        }
    }

    // Handle multibuy items
    foreach (DonatedItemQuery::create()->filterByFrId($fundraiserId)->filterByMultibuy(1)->find() as $multibuyItem) {
        $diId     = $multibuyItem->getId();
        $mbName   = 'MBItem' . $diId;
        $iMBCount = (int) InputUtils::legacyFilterInput($body[$mbName] ?? '0', 'int');

        $numBought = MultibuyQuery::create()
            ->filterByMbPerId($iPerID)
            ->filterByMbItemId($diId)
            ->findOne();

        if ($iMBCount > 0) {
            $numBought ??= (new Multibuy())->setMbPerId($iPerID)->setMbItemId($diId);
            $numBought->setMbCount($iMBCount);
            $numBought->save();
        } elseif ($numBought !== null) {
            $numBought->delete();
        }
    }

    if ($paddleId <= 0) {
        // New paddle number
        $newPaddle = (new PaddleNum())
            ->setPnFrId($fundraiserId)
            ->setPnNum($iNum)
            ->setPnPerId($iPerID);
        $newPaddle->save();
        $paddleId = $newPaddle->getPnId();
    } else {
        // Scope update to fundraiserId to prevent cross-fundraiser writes
        $existingPaddle = PaddleNumQuery::create()
            ->filterByPnId($paddleId)
            ->filterByPnFrId($fundraiserId)
            ->findOne();
        if ($existingPaddle !== null) {
            $existingPaddle
                ->setPnFrId($fundraiserId)
                ->setPnNum($iNum)
                ->setPnPerId($iPerID)
                ->save();
        }
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

    $fundraiserId = (int) $args['fundraiserId'];
    $paddleId     = (int) $args['paddleId'];

    if ($paddleId > 0 && $fundraiserId > 0) {
        PaddleNumQuery::create()
            ->filterByPnId($paddleId)
            ->filterByPnFrId($fundraiserId)
            ->delete();
    }

    return $response
        ->withHeader('Location', SystemURLs::getRootPath() . '/fundraiser/' . $fundraiserId . '/paddle-numbers')
        ->withStatus(302);
});
