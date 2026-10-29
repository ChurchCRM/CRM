<?php

use ChurchCRM\Service\AdvancedDepositService;
use ChurchCRM\Service\MpdfRenderer;
use ChurchCRM\Utils\CsvExporter;
use ChurchCRM\Utils\RedirectUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/reports', function (RouteCollectorProxy $group): void {
    $group->map(['GET', 'POST'], '/advanced-deposit/download', function (Request $request, Response $response): Response {
        $input = $request->getMethod() === 'POST' ? (array) $request->getParsedBody() : $request->getQueryParams();
        $criteria = AdvancedDepositService::criteriaFromQuery($input);
        $records = AdvancedDepositService::records($criteria);
        if ($records === []) {
            RedirectUtils::redirect('FinancialReports.php?' . http_build_query([
                'ReturnMessage' => 'NoRows',
                'ReportType' => 'Advanced Deposit Report',
                'DateStart' => $criteria['dateStart'],
                'DateEnd' => $criteria['dateEnd'],
                'datetype' => $criteria['datetype'],
            ]));
        }

        if (($input['output'] ?? 'pdf') === 'csv') {
            [$headers, $rows] = AdvancedDepositService::csv($records);
            CsvExporter::create($headers, $rows, 'AdvancedDepositReport', 'UTF-8', true);

            return $response;
        }

        $response->getBody()->write((new MpdfRenderer())->renderToString('advanced-deposit', AdvancedDepositService::document($records, $criteria)));

        return $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', AdvancedDepositService::contentDisposition());
    });
});
