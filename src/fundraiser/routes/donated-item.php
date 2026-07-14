<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\DonatedItem;
use ChurchCRM\model\ChurchCRM\DonatedItemQuery;
use ChurchCRM\model\ChurchCRM\FundRaiserQuery;
use ChurchCRM\model\ChurchCRM\PaddleNumQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\MiscUtils;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

// GET /fundraiser/{fundraiserId}/donated-items/editor[/{itemId}] — editor (migrated from DonatedItemEditor.php)
$app->get('/{fundraiserId}/donated-items/editor[/{itemId}]', function (Request $request, Response $response, array $args): Response {
    $fundraiserId = (int) $args['fundraiserId'];
    $itemId       = (int) ($args['itemId'] ?? 0);

    $fundraiser = FundRaiserQuery::create()->findOneById($fundraiserId);
    if ($fundraiser === null) {
        return $response
            ->withHeader('Location', SystemURLs::getRootPath() . '/fundraiser/')
            ->withStatus(302);
    }

    $_SESSION['iCurrentFundraiser'] = $fundraiserId;

    // Defaults
    $sItem         = '';
    $bMultibuy     = 0;
    $iDonor        = 0;
    $iBuyer        = 0;
    $sTitle        = '';
    $sDescription  = '';
    $nSellPrice    = 0.0;
    $nEstPrice     = 0.0;
    $nMaterialValue = 0.0;
    $nMinimumPrice = 0.0;
    $sPictureURL   = '';

    if ($itemId > 0) {
        // Scope to fundraiserId to prevent cross-fundraiser item editing
        $donatedItemRecord = DonatedItemQuery::create()
            ->filterById($itemId)
            ->filterByFrId($fundraiserId)
            ->findOne();
        if ($donatedItemRecord === null) {
            return $response
                ->withHeader('Location', SystemURLs::getRootPath() . '/fundraiser/editor/' . $fundraiserId)
                ->withStatus(302);
        }
        $sItem         = $donatedItemRecord->getItem();
        $bMultibuy     = $donatedItemRecord->getMultibuy();
        $iDonor        = $donatedItemRecord->getDonorId();
        $iBuyer        = $donatedItemRecord->getBuyerId();
        $sTitle        = $donatedItemRecord->getTitle();
        $sDescription  = $donatedItemRecord->getDescription();
        $nSellPrice    = $donatedItemRecord->getSellprice();
        $nEstPrice     = $donatedItemRecord->getEstprice();
        $nMaterialValue = $donatedItemRecord->getMaterialValue();
        $nMinimumPrice = $donatedItemRecord->getMinimum();
        $sPictureURL   = $donatedItemRecord->getPicture();
    }

    // Inner join (mirrors legacy JOIN family_fam): people without a family record are excluded.
    $people = [];
    foreach (
        PersonQuery::create()
            ->joinFamily()
            ->orderByLastName()
            ->orderByFirstName()
            ->find() as $person
    ) {
        $family  = $person->getFamily();
        $people[] = [
            'per_ID'        => $person->getId(),
            'per_FirstName' => $person->getFirstName(),
            'per_LastName'  => $person->getLastName(),
            'fam_Address1'  => $family->getAddress1(),
            'fam_City'      => $family->getCity(),
            'fam_State'     => $family->getState(),
        ];
    }

    $paddleModels = [];
    $buyerIds     = [];
    foreach (PaddleNumQuery::create()->filterByPnFrId($fundraiserId)->orderByPnNum()->find() as $paddle) {
        $paddleModels[] = $paddle;
        $buyerIds[]     = $paddle->getPnPerId();
    }
    $buyerPeople = [];
    foreach (PersonQuery::create()->filterById($buyerIds)->find() as $buyerPerson) {
        $buyerPeople[$buyerPerson->getId()] = $buyerPerson;
    }
    $buyers = [];
    foreach ($paddleModels as $paddle) {
        $buyerPerson = $buyerPeople[$paddle->getPnPerId()] ?? null;
        $buyers[] = [
            'pn_ID'          => $paddle->getPnId(),
            'pn_Num'         => $paddle->getPnNum(),
            'pn_per_ID'      => $paddle->getPnPerId(),
            'buyerFirstName' => $buyerPerson?->getFirstName() ?? '',
            'buyerLastName'  => $buyerPerson?->getLastName() ?? '',
        ];
    }

    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    return $renderer->render($response, 'donated-item-editor.php', [
        'sRootPath'      => SystemURLs::getRootPath(),
        'sPageTitle'     => gettext('Donated Item Editor'),
        'sPageSubtitle'  => gettext('Add or edit items donated for fundraising events'),
        'aBreadcrumbs'   => PageHeader::breadcrumbs([
            [gettext('Fundraiser'), '/fundraiser/'],
            [gettext('Edit Fundraiser'), '/fundraiser/editor/' . $fundraiserId],
            [gettext('Donated Item')],
        ]),
        'fundraiserId'   => $fundraiserId,
        'itemId'         => $itemId,
        'sItem'          => $sItem,
        'bMultibuy'      => $bMultibuy,
        'iDonor'         => $iDonor,
        'iBuyer'         => $iBuyer,
        'sTitle'         => $sTitle,
        'sDescription'   => $sDescription,
        'nSellPrice'     => $nSellPrice,
        'nEstPrice'      => $nEstPrice,
        'nMaterialValue' => $nMaterialValue,
        'nMinimumPrice'  => $nMinimumPrice,
        'sPictureURL'    => $sPictureURL,
        'people'         => $people,
        'buyers'         => $buyers,
        'canAddRecords'  => AuthenticationManager::getCurrentUser()->isAddRecordsEnabled(),
    ]);
});

// POST /fundraiser/{fundraiserId}/donated-items/editor[/{itemId}] — save (migrated from DonatedItemEditor.php)
$app->post('/{fundraiserId}/donated-items/editor[/{itemId}]', function (Request $request, Response $response, array $args): Response {
    $fundraiserId = (int) $args['fundraiserId'];
    $itemId       = (int) ($args['itemId'] ?? 0);
    $body         = (array) $request->getParsedBody();

    $fundraiser = FundRaiserQuery::create()->findOneById($fundraiserId);
    if ($fundraiser === null) {
        return $response
            ->withHeader('Location', SystemURLs::getRootPath() . '/fundraiser/')
            ->withStatus(302);
    }

    $_SESSION['iCurrentFundraiser'] = $fundraiserId;

    if (!CSRFUtils::verifyRequest($body, 'donated_item_editor')) {
        $response->getBody()->write(gettext('Invalid security token. Please try again.'));
        return $response->withStatus(400)->withHeader('Content-Type', 'text/plain');
    }

    $sItem         = InputUtils::legacyFilterInput($body['Item'] ?? '');
    $bMultibuy     = (int) InputUtils::legacyFilterInput($body['Multibuy'] ?? '0', 'int');
    $iDonor        = (int) InputUtils::legacyFilterInput($body['Donor'] ?? '0', 'int');
    $iBuyer        = (int) InputUtils::legacyFilterInput($body['Buyer'] ?? '0', 'int');
    $sTitle        = InputUtils::legacyFilterInput($body['Title'] ?? '');
    $sDescription  = InputUtils::legacyFilterInput($body['Description'] ?? '');
    $nSellPrice    = InputUtils::legacyFilterInput($body['SellPrice'] ?? '');
    $nEstPrice     = InputUtils::legacyFilterInput($body['EstPrice'] ?? '');
    $nMaterialValue = InputUtils::legacyFilterInput($body['MaterialValue'] ?? '');
    $nMinimumPrice = InputUtils::legacyFilterInput($body['MinimumPrice'] ?? '');
    $sPictureURL   = InputUtils::legacyFilterInput($body['PictureURL'] ?? '');

    if (!$bMultibuy) { $bMultibuy = 0; }
    if (!$iBuyer)    { $iBuyer    = 0; }

    $currentUser = AuthenticationManager::getCurrentUser();

    if ($itemId < 1) {
        $donatedItem = new DonatedItem();
        $donatedItem
            ->setFrId($fundraiserId)
            ->setItem($sItem)
            ->setMultibuy($bMultibuy)
            ->setDonorId($iDonor)
            ->setBuyerId($iBuyer)
            ->setTitle(html_entity_decode($sTitle))
            ->setDescription(html_entity_decode($sDescription))
            ->setSellprice($nSellPrice)
            ->setEstprice($nEstPrice)
            ->setMaterialValue($nMaterialValue)
            ->setMinimum($nMinimumPrice)
            ->setPicture($sPictureURL)
            ->setEnteredby($currentUser->getId())
            ->setEntereddate(date('YmdHis'));
        $donatedItem->save();
        $itemId = $donatedItem->getId();
    } else {
        // Scope to fundraiserId to prevent cross-fundraiser writes or moving items between fundraisers
        $donatedItem = DonatedItemQuery::create()
            ->filterById($itemId)
            ->filterByFrId($fundraiserId)
            ->findOne();
        if ($donatedItem === null) {
            return $response
                ->withHeader('Location', SystemURLs::getRootPath() . '/fundraiser/editor/' . $fundraiserId)
                ->withStatus(302);
        }
        $donatedItem
            ->setItem($sItem)
            ->setMultibuy($bMultibuy)
            ->setDonorId($iDonor)
            ->setBuyerId($iBuyer)
            ->setTitle(html_entity_decode($sTitle))
            ->setDescription(html_entity_decode($sDescription))
            ->setSellprice($nSellPrice)
            ->setEstprice($nEstPrice)
            ->setMaterialValue($nMaterialValue)
            ->setMinimum($nMinimumPrice)
            ->setPicture($sPictureURL)
            ->setEnteredby($currentUser->getId())
            ->setEntereddate(date('YmdHis'));
        $donatedItem->save();
    }

    if (isset($body['DonatedItemSubmitAndAdd'])) {
        return $response
            ->withHeader('Location', SystemURLs::getRootPath() . '/fundraiser/' . $fundraiserId . '/donated-items/editor')
            ->withStatus(302);
    }

    return $response
        ->withHeader('Location', SystemURLs::getRootPath() . '/fundraiser/editor/' . $fundraiserId)
        ->withStatus(302);
});

// POST /fundraiser/{fundraiserId}/donated-items/{itemId}/replicate — replicate (migrated from DonatedItemReplicate.php)
$app->post('/{fundraiserId}/donated-items/{itemId}/replicate', function (Request $request, Response $response, array $args): Response {
    $fundraiserId = (int) $args['fundraiserId'];
    $itemId       = (int) $args['itemId'];
    $body         = (array) $request->getParsedBody();
    if (!CSRFUtils::verifyRequest($body, 'donated_item_replicate')) {
        $response->getBody()->write(gettext('Invalid security token. Please try again.'));
        return $response->withStatus(400)->withHeader('Content-Type', 'text/plain');
    }

    $iCount       = (int) InputUtils::legacyFilterInput($body['Count'] ?? '0', 'int');

    // Scope to fundraiserId to prevent cross-fundraiser replication
    $donatedItem = DonatedItemQuery::create()
        ->filterById($itemId)
        ->filterByFrId($fundraiserId)
        ->findOne();
    if ($donatedItem === null) {
        return $response
            ->withHeader('Location', SystemURLs::getRootPath() . '/fundraiser/editor/' . $fundraiserId)
            ->withStatus(302);
    }

    $startItem = $donatedItem->getItem();

    if (strlen((string) $startItem) === 2) {
        $letter    = mb_substr((string) $startItem, 0, 1);
        $number    = mb_substr((string) $startItem, 1, 1);
        $startItem = $letter . '0' . $number;
    }

    $letterNum   = ord('a');
    $currentUser = AuthenticationManager::getCurrentUser();

    for ($i = 0; $i < $iCount; $i++) {
        // Use Propel clone instead of INSERT…SELECT string interpolation to avoid
        // second-order SQL injection via the stored di_item value.
        $newItem = $donatedItem->copy();
        $newItem->setItem($startItem . chr($letterNum));
        $newItem->setEnteredby($currentUser->getId());
        $newItem->setEntereddate(date('YmdHis'));
        $newItem->save();
        $letterNum++;
    }

    return $response
        ->withHeader('Location', SystemURLs::getRootPath() . '/fundraiser/editor/' . $fundraiserId)
        ->withStatus(302);
});

// POST /fundraiser/{fundraiserId}/donated-items/{itemId}/delete — delete (migrated from DonatedItemDelete.php)
// Module middleware covers ManageFundraisers; inline guard adds DeleteRecords.
$app->post('/{fundraiserId}/donated-items/{itemId}/delete', function (Request $request, Response $response, array $args): Response {
    $currentUser = AuthenticationManager::getCurrentUser();
    if (!$currentUser->isDeleteRecordsEnabled()) {
        return $response
            ->withHeader('Location', SystemURLs::getRootPath() . '/v2/access-denied?role=DeleteRecords')
            ->withStatus(302);
    }

    $body = (array) $request->getParsedBody();

    if (!CSRFUtils::verifyRequest($body, 'donated_item_delete')) {
        $response->getBody()->write(gettext('Invalid security token. Please try again.'));
        return $response->withStatus(403)->withHeader('Content-Type', 'text/plain');
    }

    $fundraiserId = (int) $args['fundraiserId'];
    $itemId       = (int) $args['itemId'];

    if ($itemId > 0 && $fundraiserId > 0) {
        DonatedItemQuery::create()
            ->filterById($itemId)
            ->filterByFrId($fundraiserId)
            ->delete();
    }

    return $response
        ->withHeader('Location', SystemURLs::getRootPath() . '/fundraiser/editor/' . $fundraiserId)
        ->withStatus(302);
});
