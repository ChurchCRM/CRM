<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\FamilyPledgeReportService;
use ChurchCRM\Service\FinanceReportOptions;
use ChurchCRM\Service\FinancialService;
use ChurchCRM\Service\MpdfRenderer;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\FiscalYearUtils;
use ChurchCRM\Utils\PdfResponse;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/reports/family-pledge-summary', function (RouteCollectorProxy $group): void {
    $page = static function (Response $response, array $criteria, string $message = ''): Response {
        return (new PhpRenderer(__DIR__ . '/../views/'))->render($response, 'reports/family-pledge-summary.php', [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('Pledge Family Summary'),
            'sPageSubtitle' => gettext('Detailed breakdown of pledges and payments by family.'),
            'aBreadcrumbs' => PageHeader::breadcrumbs([
                [gettext('Financial Reports'), '/finance/reports'],
                [gettext('Pledge Family Summary')],
            ]),
            'criteria' => $criteria,
            'message' => $message,
            'fiscalYears' => FiscalYearUtils::buildFiscalYearList(min((new FinancialService())->getOldestPledgeFyId(), $criteria['fiscalYearId'])),
            'classifications' => FinanceReportOptions::classifications(),
            'families' => FinanceReportOptions::families(),
            'fundGroups' => FinanceReportOptions::fundGroups(),
        ]);
    };

    $group->get('', function (Request $request, Response $response) use ($page): Response {
        return $page($response, FamilyPledgeReportService::criteriaFromInput(
            $request->getQueryParams() + ['FYID' => $_SESSION['idefaultFY'] ?? '']
        ));
    });

    $group->map(['GET', 'POST'], '/download', function (Request $request, Response $response) use ($page): Response {
        $input = $request->getMethod() === 'POST' ? (array) $request->getParsedBody() : $request->getQueryParams();
        $criteria = FamilyPledgeReportService::criteriaFromInput($input);
        $fiscalYearId = $criteria['fiscalYearId'];
        $_SESSION['idefaultFY'] = $fiscalYearId;

        $report = FamilyPledgeReportService::report($fiscalYearId, $criteria['classificationIds'], $criteria['familyIds'], $criteria['fundIds']);
        if ($report['rows'] === []) {
            return $page($response, $criteria, sprintf(
                gettext('No family matches these filters for %s.'),
                FiscalYearUtils::formatFiscalYearLabel($fiscalYearId)
            ));
        }

        $fy = FiscalYearUtils::getFiscalYearDatesById($fiscalYearId);
        $pdf = (new MpdfRenderer())->renderToString('family-pledge-summary', $report + [
            'fiscal_year' => FiscalYearUtils::formatFiscalYearLabel($fiscalYearId),
            'date_range' => DateTimeUtils::formatDate($fy['startDate']) . ' – ' . DateTimeUtils::formatDate($fy['endDate']),
        ]);

        $fileLabel = 'FamilyPledgeSummary-' . date(SystemConfig::getValue('sDateFilenameFormat'));
        return PdfResponse::create($response, $pdf, $fileLabel);
    });
});
