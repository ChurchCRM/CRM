<?php

use ChurchCRM\Utils\PdfResponse;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Service\EnvelopeReportService;
use ChurchCRM\Service\MpdfRenderer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/reports', function (RouteCollectorProxy $group): void {
    $group->get('/envelope-numbers', function (Request $request, Response $response): Response {
        $pdf = (new MpdfRenderer())->renderToString('envelope-numbers', ['rows' => EnvelopeReportService::assignments()]);

        $fileLabel = 'EnvelopeAssignments-' . date(SystemConfig::getValue('sDateFilenameFormat'));
        return PdfResponse::create($response, $pdf, $fileLabel);
    });
});
