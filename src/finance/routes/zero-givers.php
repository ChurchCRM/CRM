<?php

use ChurchCRM\Service\MpdfRenderer;
use ChurchCRM\Service\ZeroGiversService;
use ChurchCRM\Utils\CsvExporter;
use ChurchCRM\Utils\RedirectUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/reports', function (RouteCollectorProxy $group): void {
    $group->map(['GET', 'POST'], '/zero-givers/download', function (Request $request, Response $response): Response {
        $input = $request->getMethod() === 'POST' ? (array) $request->getParsedBody() : $request->getQueryParams();
        $criteria = ZeroGiversService::criteriaFromQuery($input);
        $families = ZeroGiversService::families($criteria['dateStart'], $criteria['dateEnd']);
        if ($families === []) {
            RedirectUtils::redirect('FinancialReports.php?ReturnMessage=NoRows&ReportType=Zero%20Givers');
        }

        if (($input['output'] ?? 'pdf') === 'csv') {
            [$headers, $rows] = ZeroGiversService::csv($families);
            CsvExporter::create($headers, $rows, 'ZeroGivers', 'UTF-8', true);

            return $response;
        }

        $document = ZeroGiversService::document($families, $criteria['dateStart'], $criteria['dateEnd'], $criteria['letterhead']);
        $response->getBody()->write((new MpdfRenderer())->renderToString('zero-givers', $document));

        return $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', ZeroGiversService::contentDisposition());
    });
});
