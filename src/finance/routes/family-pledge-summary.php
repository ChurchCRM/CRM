<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Service\FamilyPledgeReportService;
use ChurchCRM\Service\MpdfRenderer;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\FiscalYearUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/reports', function (RouteCollectorProxy $group): void {
    $group->map(['GET', 'POST'], '/family-pledge-summary/download', function (Request $request, Response $response): Response {
        $input = $request->getMethod() === 'POST' ? (array) $request->getParsedBody() : $request->getQueryParams();
        $ids = static fn (string $key): array => array_values(array_filter(
            array_map([InputUtils::class, 'filterInt'], (array) ($input[$key] ?? [])),
            static fn (int $id): bool => $id > 0
        ));

        $fiscalYearId = InputUtils::filterInt($input['FYID'] ?? '') ?: FiscalYearUtils::getCurrentFiscalYearId();
        $_SESSION['idefaultFY'] = $fiscalYearId;

        $report = FamilyPledgeReportService::report($fiscalYearId, $ids('classList'), $ids('family'), $ids('funds'));
        if ($report['rows'] === []) {
            RedirectUtils::redirect('FinancialReports.php?ReturnMessage=NoRows&ReportType=Pledge%20Family%20Summary');
        }

        $fy = FiscalYearUtils::getFiscalYearDatesById($fiscalYearId);
        $pdf = (new MpdfRenderer())->renderToString('family-pledge-summary', $report + [
            'fiscal_year' => FiscalYearUtils::formatFiscalYearLabel($fiscalYearId),
            'date_range' => DateTimeUtils::formatDate($fy['startDate']) . ' – ' . DateTimeUtils::formatDate($fy['endDate']),
        ]);

        $filename = 'FamilyPledgeSummary' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.pdf';
        $response->getBody()->write($pdf);

        return $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Cache-Control', 'private, no-store')
            ->withHeader('Content-Disposition', (SystemConfig::getIntValue('iPDFOutputType') === 1 ? 'attachment' : 'inline') . '; filename="' . $filename . '"');
    });
});
