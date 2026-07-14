<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\model\ChurchCRM\DonatedItemQuery;
use ChurchCRM\model\ChurchCRM\FundRaiserQuery;
use ChurchCRM\model\ChurchCRM\PaddleNum;
use ChurchCRM\model\ChurchCRM\PaddleNumQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

// GET /fundraiser/{fundraiserId}/donors — confirmation page (migrated from AddDonors.php)
$app->get('/{fundraiserId}/donors', function (Request $request, Response $response, array $args): Response {
    $fundraiserId = (int) $args['fundraiserId'];

    $fundraiser = FundRaiserQuery::create()->findOneById($fundraiserId);
    if ($fundraiser === null) {
        return $response
            ->withHeader('Location', SystemURLs::getRootPath() . '/fundraiser/')
            ->withStatus(302);
    }

    $_SESSION['iCurrentFundraiser'] = $fundraiserId;

    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    return $renderer->render($response, 'donors.php', [
        'sRootPath'     => SystemURLs::getRootPath(),
        'sPageTitle'    => gettext('Add Donors to Buyer List'),
        'sPageSubtitle' => gettext('Auto-assign paddle numbers to all item donors'),
        'aBreadcrumbs'  => PageHeader::breadcrumbs([
            [gettext('Fundraiser'), '/fundraiser/'],
            [gettext('Add Donors'), '/fundraiser/' . $fundraiserId . '/editor'],
            [gettext('Add to Buyer List')],
        ]),
        'fundraiserId' => $fundraiserId,
        'fundraiser'   => $fundraiser,
    ]);
});

// POST /fundraiser/{fundraiserId}/donors — add donor paddle numbers (migrated from AddDonors.php)
$app->post('/{fundraiserId}/donors', function (Request $request, Response $response, array $args): Response {
    $fundraiserId = (int) $args['fundraiserId'];
    $body = (array) $request->getParsedBody();

    if (!CSRFUtils::verifyRequest($body, 'add_donors')) {
        $response->getBody()->write(gettext('Invalid security token. Please try again.'));
        return $response->withStatus(400)->withHeader('Content-Type', 'text/plain');
    }

    if ($fundraiserId <= 0) {
        return $response
            ->withHeader('Location', SystemURLs::getRootPath() . '/fundraiser/')
            ->withStatus(302);
    }

    $_SESSION['iCurrentFundraiser'] = $fundraiserId;

    // Get all people listed as donors for this fundraiser
    $donorIds = [];
    foreach (DonatedItemQuery::create()->filterByFrId($fundraiserId)->orderByDonorId()->find() as $donatedItem) {
        $donorIds[] = $donatedItem->getDonorId();
    }

    // Donors that no longer exist as a person record are skipped (mirrors the
    // legacy LEFT JOIN against person_per, which produced a NULL donor id for them).
    $existingPersonIds = [];
    foreach (PersonQuery::create()->filterById($donorIds)->find() as $existingPerson) {
        $existingPersonIds[$existingPerson->getId()] = true;
    }

    // Get the next paddle number to use, and index existing buyers for O(1) lookups
    $extraPaddleNum = 1;
    $existingBuyerIds = [];
    foreach (PaddleNumQuery::create()->filterByPnFrId($fundraiserId)->find() as $existingPaddle) {
        $existingBuyerIds[$existingPaddle->getPnPerId()] = true;
        $extraPaddleNum = max($extraPaddleNum, $existingPaddle->getPnNum() + 1);
    }

    // For each donor who does not yet have a paddle number, add one
    foreach ($donorIds as $donorId) {
        if ($donorId > 0 && isset($existingPersonIds[$donorId]) && !isset($existingBuyerIds[$donorId])) {
            (new PaddleNum())
                ->setPnNum($extraPaddleNum)
                ->setPnFrId($fundraiserId)
                ->setPnPerId($donorId)
                ->save();
            $existingBuyerIds[$donorId] = true;
            $extraPaddleNum++;
        }
    }

    return $response
        ->withHeader('Location', SystemURLs::getRootPath() . '/fundraiser/' . $fundraiserId . '/paddle-numbers')
        ->withStatus(302);
});
