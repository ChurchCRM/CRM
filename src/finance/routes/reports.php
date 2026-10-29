<?php

use ChurchCRM\Emails\GivingStatementEmail;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\Service\FinanceReportOptions;
use ChurchCRM\Service\MpdfRenderer;
use ChurchCRM\Service\YearEndGivingService;
use ChurchCRM\Service\GivingStatementService;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\Utils\CsvExporter;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/reports', function (RouteCollectorProxy $group): void {

    // Financial Reports selection page
    $group->get('', function (Request $request, Response $response): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');

        $pageArgs = [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('Financial Reports'),
            'sPageSubtitle' => gettext('Generate reports for tax statements, pledge tracking, and financial analysis.'),
            'aBreadcrumbs' => PageHeader::breadcrumbs([
                [gettext('Financial Reports')],
            ]),
        ];

        return $renderer->render($response, 'reports.php', $pageArgs);
    });

    // Year-end giving: the finance team's starting point for a calendar year's statements.
    $group->get('/year-end', function (Request $request, Response $response): Response {
        $years = YearEndGivingService::years();
        $requested = InputUtils::filterInt($request->getQueryParams()['year'] ?? '');
        $year = in_array($requested, $years, true) ? $requested : $years[0];

        $renderer = new PhpRenderer(__DIR__ . '/../views/');

        return $renderer->render($response, 'reports/year-end.php', [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('Year-End Giving'),
            'sPageSubtitle' => gettext('Review the year, then print or email giving statements.'),
            'aBreadcrumbs' => PageHeader::breadcrumbs([
                [gettext('Financial Reports'), '/finance/reports'],
                [gettext('Year-End Giving')],
            ]),
            'years' => $years,
            'overview' => YearEndGivingService::overview($year),
        ]);
    });

    // Giving Report (tax statements): choose filters, preview the result, then download.
    $group->get('/tax-statements', function (Request $request, Response $response): Response {
        $query = $request->getQueryParams();
        $criteria = GivingStatementService::criteriaFromQuery($query);
        $summary = ($query['preview'] ?? '') === '1'
            ? GivingStatementService::summary(GivingStatementService::paymentsByFamily($criteria))
            : null;

        $renderer = new PhpRenderer(__DIR__ . '/../views/');

        return $renderer->render($response, 'reports/tax-statements.php', [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('Giving Report (Tax Statements)'),
            'sPageSubtitle' => gettext('Generate annual tax-deductible giving statements for donors. Can be printed or emailed.'),
            'aBreadcrumbs' => PageHeader::breadcrumbs([
                [gettext('Financial Reports'), '/finance/reports'],
                [gettext('Giving Report (Tax Statements)')],
            ]),
            'query' => $query,
            'criteria' => $criteria,
            'summary' => $summary,
            'downloadQuery' => http_build_query(array_diff_key($query, ['preview' => true])),
            'classifications' => FinanceReportOptions::classifications(),
            'families' => FinanceReportOptions::families(),
            'fundGroups' => FinanceReportOptions::fundGroups(),
            'deposits' => FinanceReportOptions::deposits(),
        ]);
    });

    $group->get('/tax-statements/pdf', function (Request $request, Response $response): Response {
        $query = $request->getQueryParams();
        $criteria = GivingStatementService::criteriaFromQuery($query);
        $groups = GivingStatementService::paymentsByFamily($criteria);
        if ($groups === []) {
            RedirectUtils::redirect('finance/reports/tax-statements?' . http_build_query(array_merge($query, ['preview' => 1])));
        }

        [$dateStart, $dateEnd] = GivingStatementService::period($criteria);
        $families = GivingStatementService::families($groups, SystemConfig::getBooleanValue('bUseDonationEnvelopes'));
        $document = GivingStatementService::document($families, $dateStart, $dateEnd, $criteria['letterhead'], $criteria['remittance']);
        $pdf = (new MpdfRenderer())->renderToString('tax-report', $document);

        $label = GivingStatementService::fileLabel((int) substr($dateStart, 0, 4), count($families) === 1 ? $families[0]['salutation'] : '');
        $response->getBody()->write($pdf);

        return $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', GivingStatementService::contentDisposition($label));
    });

    $group->get('/tax-statements/csv', function (Request $request, Response $response): Response {
        $query = $request->getQueryParams();
        $groups = GivingStatementService::paymentsByFamily(GivingStatementService::criteriaFromQuery($query));
        if ($groups === []) {
            RedirectUtils::redirect('finance/reports/tax-statements?' . http_build_query(array_merge($query, ['preview' => 1])));
        }

        [$headers, $rows] = GivingStatementService::csv($groups);
        CsvExporter::create($headers, $rows, 'TaxReport', 'UTF-8', true);

        return $response;
    });

    // Email one family's statement for a calendar year (Family profile → Finance menu).
    $group->post('/tax-statements/email', function (Request $request, Response $response): Response {
        $body = (array) $request->getParsedBody();
        $familyId = InputUtils::filterInt($body['familyId'] ?? '');
        $year = InputUtils::filterInt($body['year'] ?? '');
        $token = (string) ($body['csrf_token'] ?? '');

        if ($familyId <= 0 || $year < 1991 || $year > (int) date('Y') || !CSRFUtils::validateToken($token, 'tax_email_' . $year, true)) {
            RedirectUtils::redirect('finance/reports');
        }

        $family = FamilyQuery::create()->findPk($familyId);
        if ($family === null) {
            RedirectUtils::redirect('people/family/not-found?id=' . $familyId);
        }
        $emails = $family->getEmails();
        if (empty($emails)) {
            RedirectUtils::redirect('people/family/' . $familyId . '?TaxEmailError=NoEmail');
        }

        $criteria = GivingStatementService::criteriaFromQuery(['familyId' => $familyId, 'year' => $year]);
        $groups = GivingStatementService::paymentsByFamily($criteria);
        if ($groups === []) {
            RedirectUtils::redirect('people/family/' . $familyId . '?TaxEmailError=NoData');
        }

        $families = GivingStatementService::families($groups);
        $document = GivingStatementService::document($families, $criteria['dateStart'], $criteria['dateEnd']);
        $pdf = (new MpdfRenderer())->renderToString('tax-report', $document);

        $filename = 'TaxStatement-' . preg_replace('/[^\p{L}\p{N}._-]/u', '_', (string) $family->getName()) . '-' . $year . '.pdf';
        $mail = new GivingStatementEmail($emails, (string) $family->getName(), $year, $pdf, $filename);

        if ($mail->send()) {
            RedirectUtils::redirect('people/family/' . $familyId . '?TaxEmailSent=' . $year);
        }
        LoggerUtils::getAppLogger()->error('Giving statement email failed to send for family ' . $familyId . ' year ' . $year);
        RedirectUtils::redirect('people/family/' . $familyId . '?TaxEmailError=SendFailed');

        return $response;
    });

});
