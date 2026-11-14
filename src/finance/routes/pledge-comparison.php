<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\MpdfRenderer;
use ChurchCRM\Service\PledgeComparisonService;
use ChurchCRM\Utils\FiscalYearUtils;
use ChurchCRM\Utils\PdfResponse;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/reports', function (RouteCollectorProxy $group): void {
    $group->get('/pledge-comparison', function (Request $request, Response $response): Response {
        $criteria = PledgeComparisonService::criteriaFromQuery($request->getQueryParams());
        $renderer = new PhpRenderer(__DIR__ . '/../views/');

        return $renderer->render($response, 'reports/pledge-comparison.php', [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('Pledge Comparison'),
            'sPageSubtitle' => gettext('Compare family pledge totals for two fiscal years.'),
            'aBreadcrumbs' => PageHeader::breadcrumbs([
                [gettext('Financial Reports'), '/finance/reports'],
                [gettext('Pledge Comparison')],
            ]),
            'fiscalYears' => PledgeComparisonService::fiscalYears(),
            'criteria' => $criteria,
        ]);
    });

    $group->get('/pledge-comparison/download', function (Request $request, Response $response): Response {
        $criteria = PledgeComparisonService::criteriaFromQuery($request->getQueryParams());
        $report = PledgeComparisonService::report($criteria['firstFyId'], $criteria['secondFyId']);
        $pdf = (new MpdfRenderer())->renderToString('pledge-comparison', $report + [
            'first_fiscal_year' => FiscalYearUtils::formatFiscalYearLabel($criteria['firstFyId']),
            'second_fiscal_year' => FiscalYearUtils::formatFiscalYearLabel($criteria['secondFyId']),
        ]);

        return PdfResponse::create(
            $response,
            $pdf,
            'PledgeComparison-' . $criteria['firstFyId'] . '-' . $criteria['secondFyId']
        );
    });
});
