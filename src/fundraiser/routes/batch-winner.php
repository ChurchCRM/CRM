<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\model\ChurchCRM\DonatedItemQuery;
use ChurchCRM\model\ChurchCRM\FundRaiserQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

// GET /fundraiser/{fundraiserId}/batch-winner — form (migrated from BatchWinnerEntry.php)
$app->get('/{fundraiserId}/batch-winner', function (Request $request, Response $response, array $args): Response {
    $fundraiserId = (int) $args['fundraiserId'];

    $fundraiser = FundRaiserQuery::create()->findOneById($fundraiserId);
    if ($fundraiser === null) {
        return $response
            ->withHeader('Location', SystemURLs::getRootPath() . '/fundraiser/')
            ->withStatus(302);
    }

    $_SESSION['iCurrentFundraiser'] = $fundraiserId;

    $sDonatedItemsSQL = "SELECT di_ID, di_Item, di_title
                         FROM donateditem_di
                         WHERE di_FR_ID = '" . $fundraiserId . "' ORDER BY SUBSTR(di_Item,1,1), CONVERT(SUBSTR(di_Item,2,3),SIGNED)";
    $donatedItems     = [];
    $rsDonatedItems   = RunQuery($sDonatedItemsSQL);
    while ($itemArr = mysqli_fetch_array($rsDonatedItems)) {
        $donatedItems[] = $itemArr;
    }

    $sPaddleSQL = 'SELECT pn_Num, pn_per_ID,
                          a.per_FirstName AS buyerFirstName,
                          a.per_LastName AS buyerLastName
                   FROM paddlenum_pn
                   LEFT JOIN person_per a on a.per_ID=pn_per_ID
                   WHERE pn_fr_ID=' . $fundraiserId . ' ORDER BY pn_Num';
    $paddles    = [];
    $rsPaddles  = RunQuery($sPaddleSQL);
    while ($paddleArr = mysqli_fetch_array($rsPaddles)) {
        $paddles[] = $paddleArr;
    }

    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    return $renderer->render($response, 'batch-winner.php', [
        'sRootPath'     => SystemURLs::getRootPath(),
        'sPageTitle'    => gettext('Batch Winner Entry'),
        'sPageSubtitle' => gettext('Record multiple fundraiser drawing winners at once'),
        'aBreadcrumbs'  => PageHeader::breadcrumbs([
            [gettext('Fundraiser'), '/fundraiser/'],
            [gettext('Edit Fundraiser'), '/fundraiser/editor/' . $fundraiserId],
            [gettext('Batch Winner Entry')],
        ]),
        'fundraiserId' => $fundraiserId,
        'donatedItems' => $donatedItems,
        'paddles'      => $paddles,
    ]);
});

// POST /fundraiser/{fundraiserId}/batch-winner — enter winners (migrated from BatchWinnerEntry.php)
$app->post('/{fundraiserId}/batch-winner', function (Request $request, Response $response, array $args): Response {
    $fundraiserId = (int) $args['fundraiserId'];
    $body         = (array) $request->getParsedBody();

    if (!CSRFUtils::verifyRequest($body, 'batch_winner_entry')) {
        $response->getBody()->write(gettext('Invalid security token. Please try again.'));
        return $response->withStatus(400)->withHeader('Content-Type', 'text/plain');
    }

    $fundraiser = FundRaiserQuery::create()->findOneById($fundraiserId);
    if ($fundraiser === null) {
        return $response
            ->withHeader('Location', SystemURLs::getRootPath() . '/fundraiser/')
            ->withStatus(302);
    }

    $_SESSION['iCurrentFundraiser'] = $fundraiserId;

    for ($row = 0; $row < 10; $row++) {
        $buyer = (int) ($body["Paddle$row"] ?? 0);
        $di    = (int) ($body["Item$row"] ?? 0);
        $price = InputUtils::legacyFilterInput($body["SellPrice$row"] ?? '0');

        if ($buyer > 0 && $di > 0 && (float) $price > 0) {
            // Scope to fundraiserId to prevent cross-fundraiser item updates
            $donatedItem = DonatedItemQuery::create()
                ->filterById($di)
                ->filterByFrId($fundraiserId)
                ->findOne();
            if ($donatedItem !== null) {
                $donatedItem
                    ->setBuyerId($buyer)
                    ->setSellprice($price);
                $donatedItem->save();
            }
        }
    }

    return $response
        ->withHeader('Location', SystemURLs::getRootPath() . '/fundraiser/editor/' . $fundraiserId)
        ->withStatus(302);
});
