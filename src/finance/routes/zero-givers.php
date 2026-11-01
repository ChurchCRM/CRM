<?php

use ChurchCRM\Utils\PdfResponse;
use ChurchCRM\Service\MpdfRenderer;
use ChurchCRM\Service\ZeroGiversService;
use ChurchCRM\Utils\CsvExporter;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/reports', function (RouteCollectorProxy $group): void {
    $group->map(['GET', 'POST'], '/zero-givers/download', function (Request $request, Response $response): Response {
        $input = SlimUtils::requestInput($request);
        $criteria = ZeroGiversService::criteriaFromQuery($input);
        $families = ZeroGiversService::families($criteria['dateStart'], $criteria['dateEnd']);
        if ($families === []) {
            RedirectUtils::noReportRows('Zero Givers');
        }

        if (($input['output'] ?? 'pdf') === 'csv') {
            [$headers, $rows] = ZeroGiversService::csv($families);
            CsvExporter::create($headers, $rows, 'ZeroGivers', 'UTF-8', true);

            return $response;
        }

        $document = ZeroGiversService::document($families, $criteria['dateStart'], $criteria['dateEnd'], $criteria['letterhead']);
        $pdf = (new MpdfRenderer())->renderToString('zero-givers', $document);

        return PdfResponse::create($response, $pdf, ZeroGiversService::fileLabel());
    });
});
