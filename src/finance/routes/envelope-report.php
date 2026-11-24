<?php

use ChurchCRM\Utils\PdfResponse;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Service\EnvelopeReportService;
use ChurchCRM\Service\MpdfRenderer;
use ChurchCRM\Utils\InputUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/reports', function (RouteCollectorProxy $group): void {
    $group->get('/envelope-numbers', function (Request $request, Response $response): Response {
        // The report matches the Envelope Manager's current view: its classification
        // filter and its sort. Direct links with no parameters keep the old behaviour
        // (every family, envelope order).
        $query = $request->getQueryParams();
        $classificationId = max(0, InputUtils::filterInt($query['classification'] ?? 0));
        $sortBy = ($query['sortBy'] ?? '') === 'name' ? 'name' : 'envelope';
        $pdf = (new MpdfRenderer())->renderToString('envelope-numbers', ['rows' => EnvelopeReportService::assignments($classificationId, $sortBy)]);

        $fileLabel = 'EnvelopeAssignments-' . date(SystemConfig::getValue('sDateFilenameFormat'));
        return PdfResponse::create($response, $pdf, $fileLabel);
    });
});
