<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\FundRaiserQuery;
use ChurchCRM\Service\FundraiserItemService;
use ChurchCRM\Service\MpdfRenderer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// ---------------------------------------------------------------------------
// GET /fundraiser/{fundraiserId}/reports/bid-sheets — PDF bid sheets
// ---------------------------------------------------------------------------
$app->get('/{fundraiserId}/reports/bid-sheets', function (Request $request, Response $response, array $args): Response {
    $fundraiserId = (int) $args['fundraiserId'];

    $fundraiser = FundRaiserQuery::create()->findOneById($fundraiserId);
    if ($fundraiser === null) {
        return $response
            ->withHeader('Location', \ChurchCRM\dto\SystemURLs::getRootPath() . '/fundraiser/')
            ->withStatus(302);
    }

    // Bid sheets are a silent-auction bidding artifact — not applicable to other types.
    if (!in_array($fundraiser->getType() ?: 'Auction', ['Auction', 'Silent Auction'], true)) {
        return $response
            ->withHeader('Location', \ChurchCRM\dto\SystemURLs::getRootPath() . '/fundraiser/view/' . $fundraiserId)
            ->withStatus(302);
    }

    $pdf = (new MpdfRenderer())->renderToString('fundraiser-bid-sheets', [
        'items' => FundraiserItemService::items($fundraiser),
    ]);

    $fileName = 'FundraiserBidSheets-' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.pdf';
    $response->getBody()->write($pdf);

    return $response
        ->withHeader('Content-Type', 'application/pdf')
        ->withHeader('Content-Disposition', (SystemConfig::getIntValue('iPDFOutputType') === 1 ? 'attachment' : 'inline') . '; filename="' . $fileName . '"');
});

// ---------------------------------------------------------------------------
// GET /fundraiser/{fundraiserId}/reports/certificates — PDF certificates
// ---------------------------------------------------------------------------
$app->get('/{fundraiserId}/reports/certificates', function (Request $request, Response $response, array $args): Response {
    $fundraiserId = (int) $args['fundraiserId'];

    $fundraiser = FundRaiserQuery::create()->findOneById($fundraiserId);
    if ($fundraiser === null) {
        return $response
            ->withHeader('Location', \ChurchCRM\dto\SystemURLs::getRootPath() . '/fundraiser/')
            ->withStatus(302);
    }

    // Certificates are a pre-event item display artifact — not applicable to a raffle drawing.
    if (($fundraiser->getType() ?: 'Auction') === 'Raffle') {
        return $response
            ->withHeader('Location', \ChurchCRM\dto\SystemURLs::getRootPath() . '/fundraiser/view/' . $fundraiserId)
            ->withStatus(302);
    }

    $pdf = (new MpdfRenderer())->renderToString('fundraiser-certificates', [
        'fundraiser_title' => $fundraiser->getTitle(),
        'fundraiser_description' => $fundraiser->getDescription(),
        'items' => FundraiserItemService::items($fundraiser),
    ]);

    $fileName = 'FundraiserCertificates-' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.pdf';
    $response->getBody()->write($pdf);

    return $response
        ->withHeader('Content-Type', 'application/pdf')
        ->withHeader('Content-Disposition', (SystemConfig::getIntValue('iPDFOutputType') === 1 ? 'attachment' : 'inline') . '; filename="' . $fileName . '"');
});

// ---------------------------------------------------------------------------
// GET /fundraiser/{fundraiserId}/reports/catalog — PDF catalog
// ---------------------------------------------------------------------------
$app->get('/{fundraiserId}/reports/catalog', function (Request $request, Response $response, array $args): Response {
    $fundraiserId = (int) $args['fundraiserId'];

    $fundraiser = FundRaiserQuery::create()->findOneById($fundraiserId);
    if ($fundraiser === null) {
        return $response
            ->withHeader('Location', \ChurchCRM\dto\SystemURLs::getRootPath() . '/fundraiser/')
            ->withStatus(302);
    }

    // Catalog is a pre-event browsable bid-display artifact — not applicable to a raffle drawing.
    if (($fundraiser->getType() ?: 'Auction') === 'Raffle') {
        return $response
            ->withHeader('Location', \ChurchCRM\dto\SystemURLs::getRootPath() . '/fundraiser/view/' . $fundraiserId)
            ->withStatus(302);
    }

    $pdf = (new MpdfRenderer())->renderToString('fundraiser-catalog', [
        'fundraiser_title' => $fundraiser->getTitle(),
        'fundraiser_description' => $fundraiser->getDescription(),
        'items' => FundraiserItemService::items($fundraiser),
    ]);

    $fileName = 'FundraiserCatalog-' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.pdf';
    $response->getBody()->write($pdf);

    return $response
        ->withHeader('Content-Type', 'application/pdf')
        ->withHeader('Content-Disposition', (SystemConfig::getIntValue('iPDFOutputType') === 1 ? 'attachment' : 'inline') . '; filename="' . $fileName . '"');
});
