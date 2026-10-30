<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Service\EnvelopeReportService;
use ChurchCRM\Service\MpdfRenderer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/reports', function (RouteCollectorProxy $group): void {
    $group->get('/envelope-numbers', function (Request $request, Response $response): Response {
        $pdf = (new MpdfRenderer())->renderToString('envelope-numbers', ['rows' => EnvelopeReportService::assignments()]);

        $filename = 'EnvelopeAssignments-' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.pdf';
        $response->getBody()->write($pdf);

        return $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', (SystemConfig::getIntValue('iPDFOutputType') === 1 ? 'attachment' : 'inline') . '; filename="' . $filename . '"');
    });
});
