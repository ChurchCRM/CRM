<?php

use ChurchCRM\Utils\PdfResponse;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\FundRaiserQuery;
use ChurchCRM\Service\FundRaiserStatementService;
use ChurchCRM\Service\MpdfRenderer;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\PdfFilename;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// GET ?paddleId=N: one buyer. GET without paddleId: every buyer of the fundraiser.
// POST: the buyers ticked on the paddle number list (Chk<id> fields).
$app->map(['GET', 'POST'], '/{fundraiserId}/reports/statement', function (Request $request, Response $response, array $args): Response {
    $fundraiserId = (int) $args['fundraiserId'];

    if (FundRaiserQuery::create()->findOneById($fundraiserId) === null) {
        return $response
            ->withHeader('Location', SystemURLs::getRootPath() . '/fundraiser/')
            ->withStatus(302);
    }

    $paddleIds = null;
    if ($request->getMethod() === 'POST') {
        $paddleIds = [];
        foreach (array_keys((array) $request->getParsedBody()) as $field) {
            if (preg_match('/^Chk(\d+)$/', (string) $field, $matches) === 1) {
                $paddleIds[] = (int) $matches[1];
            }
        }
    } else {
        $paddleId = InputUtils::filterInt($request->getQueryParams()['paddleId'] ?? '');
        if ($paddleId > 0) {
            $paddleIds = [$paddleId];
        }
    }

    $pdf = (new MpdfRenderer())->renderToString('fundraiser-statement', FundRaiserStatementService::document($fundraiserId, $paddleIds));

    $fileLabel = PdfFilename::dated('FundraiserStatement');
    return PdfResponse::create($response, $pdf, $fileLabel);
});
