<?php

use ChurchCRM\Emails\GivingStatementEmail;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\Service\FinanceReportOptions;
use ChurchCRM\Service\MpdfRenderer;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Service\GivingStatementService;
use ChurchCRM\Utils\FiscalYearUtils;
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
            'sPageSubtitle' => gettext('Generate reports for giving statements, pledge tracking, and financial analysis.'),
            'aBreadcrumbs' => PageHeader::breadcrumbs([
                [gettext('Financial Reports')],
            ]),
        ];

        return $renderer->render($response, 'reports.php', $pageArgs);
    });

    // Giving Statements: choose filters, preview the result, then download.
    $group->get('/giving-statements', function (Request $request, Response $response): Response {
        $query = $request->getQueryParams();
        $criteria = GivingStatementService::criteriaFromQuery($query);
        $summary = ($query['preview'] ?? '') === '1'
            ? GivingStatementService::summary(GivingStatementService::paymentsByFamily($criteria))
            : null;

        $renderer = new PhpRenderer(__DIR__ . '/../views/');

        return $renderer->render($response, 'reports/giving-statements.php', [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('Giving Statements'),
            'sPageSubtitle' => gettext('Generate annual tax-deductible giving statements for donors. Can be printed or emailed.'),
            'aBreadcrumbs' => PageHeader::breadcrumbs([
                [gettext('Financial Reports'), '/finance/reports'],
                [gettext('Giving Statements')],
            ]),
            'query' => $query,
            'criteria' => $criteria,
            'summary' => $summary,
            'downloadQuery' => http_build_query(array_diff_key($query, ['preview' => true, 'output' => true])),
            'classifications' => FinanceReportOptions::classifications(),
            'families' => FinanceReportOptions::families(),
            'fundGroups' => FinanceReportOptions::fundGroups(),
            'deposits' => FinanceReportOptions::deposits(),
        ]);
    });

    // The preview popup: the same summary as ?preview=1, plus the download links for these filters.
    $group->get('/giving-statements/preview', function (Request $request, Response $response): Response {
        $query = $request->getQueryParams();
        $summary = GivingStatementService::summary(GivingStatementService::paymentsByFamily(GivingStatementService::criteriaFromQuery($query)));
        $download = SystemURLs::getRootPath() . '/finance/reports/giving-statements/download?';
        $filters = http_build_query(array_diff_key($query, ['preview' => true, 'output' => true]));

        return SlimUtils::renderJSON($response, $summary + [
            'pdfUrl' => $download . 'output=pdf&' . $filters,
            'csvUrl' => $download . 'output=csv&' . $filters,
        ]);
    });

    // Giving Statement: PDF or CSV for the chosen filters, from the report form or a family link.
    $group->map(['GET', 'POST'], '/giving-statements/download', function (Request $request, Response $response): Response {
        $input = $request->getMethod() === 'POST' ? (array) $request->getParsedBody() : $request->getQueryParams();
        $criteria = GivingStatementService::criteriaFromQuery($input);
        $groups = GivingStatementService::paymentsByFamily($criteria);
        if ($groups === []) {
            RedirectUtils::redirect('finance/reports/giving-statements?' . http_build_query(array_merge($input, ['preview' => 1, 'output' => null])));
        }

        if (($input['output'] ?? 'pdf') === 'csv') {
            [$headers, $rows] = GivingStatementService::csv($groups);
            CsvExporter::create($headers, $rows, 'GivingStatement', 'UTF-8', true);

            return $response;
        }

        [$dateStart, $dateEnd] = GivingStatementService::period($criteria);
        $families = GivingStatementService::families($groups, SystemConfig::getBooleanValue('bUseDonationEnvelopes'));
        $document = GivingStatementService::document($families, $dateStart, $dateEnd, $criteria['remittance']);
        $pdf = (new MpdfRenderer())->renderToString('tax-report', $document);

        $label = GivingStatementService::fileLabel((int) substr($dateEnd, 0, 4), count($families) === 1 ? $families[0]['salutation'] : '');
        $response->getBody()->write($pdf);

        return $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', GivingStatementService::contentDisposition($label));
    });

    // Email one family's giving statement for a year (Family profile → Finance menu).
    $group->post('/giving-statements/email', function (Request $request, Response $response): Response {
        $body = (array) $request->getParsedBody();
        $familyId = InputUtils::filterInt($body['familyId'] ?? '');
        $year = InputUtils::filterInt($body['year'] ?? '');
        $token = (string) ($body['csrf_token'] ?? '');

        $currentEndYear = FiscalYearUtils::endYearForId(FiscalYearUtils::getCurrentFiscalYearId());
        if ($familyId <= 0 || $year < 1991 || $year > $currentEndYear || !CSRFUtils::validateToken($token, 'tax_email_' . $year, true)) {
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

        $filename = 'GivingStatement-' . preg_replace('/[^\p{L}\p{N}._-]/u', '_', (string) $family->getName()) . '-' . $year . '.pdf';
        $mail = new GivingStatementEmail($emails, (string) $family->getName(), $year, $pdf, $filename);

        if ($mail->send()) {
            RedirectUtils::redirect('people/family/' . $familyId . '?TaxEmailSent=' . $year);
        }
        LoggerUtils::getAppLogger()->error('Giving statement email failed to send for family ' . $familyId . ' year ' . $year);
        RedirectUtils::redirect('people/family/' . $familyId . '?TaxEmailError=SendFailed');

        return $response;
    });

});
