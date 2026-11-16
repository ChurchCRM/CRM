<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\MissingPledgesService;
use ChurchCRM\Service\MpdfRenderer;
use ChurchCRM\Utils\CsvExporter;
use ChurchCRM\Utils\FiscalYearUtils;
use ChurchCRM\Utils\PdfResponse;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/reports/missing-pledges', function (RouteCollectorProxy $group): void {
    $page = static function (Response $response, array $criteria, string $message = ''): Response {
        return (new PhpRenderer(__DIR__ . '/../views/'))->render($response, 'reports/missing-pledges.php', [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('Missing Pledges'),
            'sPageSubtitle' => gettext('Find families that pledged in one fiscal year and not in another.'),
            'aBreadcrumbs' => PageHeader::breadcrumbs([
                [gettext('Financial Reports'), '/finance/reports'],
                [gettext('Missing Pledges')],
            ]),
            'fiscalYears' => MissingPledgesService::fiscalYears(),
            'criteria' => $criteria,
            'message' => $message,
        ]);
    };

    $group->get('', function (Request $request, Response $response) use ($page): Response {
        return $page($response, MissingPledgesService::criteriaFromQuery($request->getQueryParams()));
    });

    $group->get('/download', function (Request $request, Response $response) use ($page): Response {
        $query = $request->getQueryParams();
        $criteria = MissingPledgesService::criteriaFromQuery($query);
        $families = MissingPledgesService::families($criteria['pledgedFyId'], $criteria['missingFyId']);
        if ($families === []) {
            return $page($response, $criteria, sprintf(
                gettext('No active family pledged for %s without a pledge for %s.'),
                FiscalYearUtils::formatFiscalYearLabel($criteria['pledgedFyId']),
                FiscalYearUtils::formatFiscalYearLabel($criteria['missingFyId'])
            ));
        }

        $fileLabel = MissingPledgesService::fileLabel($criteria['pledgedFyId'], $criteria['missingFyId']);
        if (($query['output'] ?? 'pdf') === 'csv') {
            [$headers, $rows] = MissingPledgesService::csv($families);
            CsvExporter::create($headers, $rows, $fileLabel);

            return $response;
        }

        $pdf = (new MpdfRenderer())->renderToString(
            'missing-pledges',
            MissingPledgesService::document($families, $criteria['pledgedFyId'], $criteria['missingFyId'])
        );

        return PdfResponse::create($response, $pdf, $fileLabel);
    });
});
