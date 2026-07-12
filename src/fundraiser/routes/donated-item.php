<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\DonatedItem;
use ChurchCRM\model\ChurchCRM\DonatedItemQuery;
use ChurchCRM\model\ChurchCRM\FundRaiserQuery;
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
        $donatedItemRecord = DonatedItemQuery::create()->findPk($itemId);
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

    $sPeopleSQL = 'SELECT per_ID, per_FirstName, per_LastName, fam_Address1, fam_City, fam_State FROM person_per JOIN family_fam on per_fam_id=fam_id ORDER BY per_LastName, per_FirstName';
    $people     = [];
    $rsPeople   = RunQuery($sPeopleSQL);
    while ($aRow = mysqli_fetch_array($rsPeople)) {
        $people[] = $aRow;
    }

    $sPaddleSQL = 'SELECT pn_ID, pn_Num, pn_per_ID,
                          a.per_FirstName AS buyerFirstName,
                          a.per_LastName AS buyerLastName
                   FROM paddlenum_pn
                   LEFT JOIN person_per a on a.per_ID=pn_per_ID
                   WHERE pn_fr_ID=' . $fundraiserId . ' ORDER BY pn_Num';
    $buyers     = [];
    $rsBuyers   = RunQuery($sPaddleSQL);
    while ($aRow = mysqli_fetch_array($rsBuyers)) {
        $buyers[] = $aRow;
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
        $donatedItem = DonatedItemQuery::create()->findOneById($itemId);
        if ($donatedItem === null) {
            return $response
                ->withHeader('Location', SystemURLs::getRootPath() . '/fundraiser/editor/' . $fundraiserId)
                ->withStatus(302);
        }
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
    $iCount       = (int) InputUtils::legacyFilterInput($body['Count'] ?? '0', 'int');

    $donatedItem = DonatedItemQuery::create()->findPk($itemId);
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
        $sSQL  = 'INSERT INTO donateditem_di (di_item,di_FR_ID,di_donor_ID,di_multibuy,di_title,di_description,di_sellprice,di_estprice,di_minimum,di_materialvalue,di_EnteredBy,di_EnteredDate,di_picture)';
        $sSQL .= " SELECT '" . $startItem . chr($letterNum) . "',di_FR_ID,di_donor_ID,di_multibuy,di_title,di_description,di_sellprice,di_estprice,di_minimum,di_materialvalue,";
        $sSQL .= $currentUser->getId() . ",'" . date('YmdHis') . "',";
        $sSQL .= 'di_picture';
        $sSQL .= ' FROM donateditem_di WHERE di_ID=' . $itemId;
        RunQuery($sSQL);
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
        return $response
            ->withHeader('Location', SystemURLs::getRootPath() . '/fundraiser/editor/' . $args['fundraiserId'])
            ->withStatus(302);
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
