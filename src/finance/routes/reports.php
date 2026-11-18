<?php

use ChurchCRM\Utils\PdfResponse;
use ChurchCRM\Utils\PdfFilename;
use ChurchCRM\Emails\GivingStatementEmail;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\Service\FinanceReportOptions;
use ChurchCRM\Service\MpdfRenderer;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Service\GivingStatementService;
use ChurchCRM\Service\YearEndGivingService;
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

    // Year-end giving: the finance team's starting point for a year's statements.
    $group->get('/year-end', function (Request $request, Response $response): Response {
        $years = YearEndGivingService::years();
        $requested = InputUtils::filterInt($request->getQueryParams()['year'] ?? '');
        $year = YearEndGivingService::chooseYear(
            $years,
            $requested,
            FiscalYearUtils::rememberedEndYear(),
            FiscalYearUtils::endYearForId(FiscalYearUtils::getCurrentFiscalYearId()) - 1
        );
        if (in_array($requested, $years, true)) {
            FiscalYearUtils::rememberEndYear($requested);
        }

        $renderer = new PhpRenderer(__DIR__ . '/../views/');

        return $renderer->render($response, 'reports/year-end.php', [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('Year-End Giving'),
            'sPageSubtitle' => gettext('Check the year, set up the statement, then download.'),
            'aBreadcrumbs' => PageHeader::breadcrumbs([
                [gettext('Finance'), '/finance/'],
                [gettext('Year-End Giving')],
            ]),
            'sPageHeaderButtons' => PageHeader::buttons([
                ['label' => gettext('Statement Settings'), 'url' => '/finance/statements/settings', 'icon' => 'fa-cog', 'adminOnly' => false],
            ]),
            'years' => $years,
            'overview' => YearEndGivingService::overview($year),
        ]);
    });

    // Giving Statements page. The Canadian download renders it again, with the
    // treasurer's answers kept, when a receipt cannot be generated.
    $renderStatements = function (Response $response, array $query, string $canadianError = '', int $status = 200): Response {
        $canadianEnabled = GivingStatementService::isCanadianChurch();
        $canadian = ($query['template'] ?? '') === 'canadian' && $canadianEnabled;
        $canadianReview = [];
        if ($canadian && (($query['review'] ?? '') === '1' || $canadianError !== '')) {
            // Check Again and a failed download re-check every reviewed family, not only the included ones.
            $reviewQuery = $query;
            if (!empty($query['reviewed'])) {
                $reviewQuery['family'] = $query['reviewed'];
            }
            try {
                $canadianReview = GivingStatementService::canadianReview(GivingStatementService::canadianOptions($reviewQuery));
            } catch (InvalidArgumentException $error) {
                $canadianError = $canadianError !== '' ? $canadianError : $error->getMessage();
            }
        }
        $remembered = FiscalYearUtils::rememberedEndYear();
        if ($remembered !== null && ($query['year'] ?? '') === '' && ($query['DateStart'] ?? '') === '' && ($query['DateEnd'] ?? '') === '') {
            $query['year'] = $remembered;
        }
        $criteria = GivingStatementService::criteriaFromQuery($query);
        $summary = ($query['preview'] ?? '') === '1'
            ? GivingStatementService::summary(GivingStatementService::paymentsByFamily($criteria))
            : null;

        $renderer = new PhpRenderer(__DIR__ . '/../views/');

        return $renderer->render($response->withStatus($status), 'reports/giving-statements.php', [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('Giving Statements'),
            'sPageSubtitle' => gettext('Generate annual tax-deductible giving statements for donors. Can be printed or emailed.'),
            'aBreadcrumbs' => PageHeader::breadcrumbs([
                [gettext('Year-End Giving'), '/finance/reports/year-end'],
                [gettext('Giving Statements')],
            ]),
            'sPageHeaderButtons' => PageHeader::buttons([
                ['label' => gettext('Statement Settings'), 'url' => '/finance/statements/settings', 'icon' => 'fa-cog', 'adminOnly' => false],
                ['label' => gettext('Report Settings'), 'url' => '/admin/system/report-settings', 'icon' => 'fa-signature'],
            ]),
            'canadianEnabled' => $canadianEnabled,
            'canadian' => $canadian,
            'canadianReview' => $canadianReview,
            'canadianError' => $canadianError,
            'query' => $query,
            'criteria' => $criteria,
            'summary' => $summary,
            'downloadQuery' => http_build_query(array_diff_key($query, ['preview' => true, 'output' => true])),
            'classifications' => FinanceReportOptions::classifications(),
            'families' => FinanceReportOptions::families(),
            'fundGroups' => FinanceReportOptions::fundGroups(),
            'deposits' => FinanceReportOptions::deposits(),
        ]);
    };

    // Giving Statements: choose filters, preview the result, then download.
    $group->map(['GET', 'POST'], '/giving-statements', function (Request $request, Response $response) use ($renderStatements): Response {
        $query = $request->getMethod() === 'POST' ? (array) $request->getParsedBody() : $request->getQueryParams();
        if (($query['review'] ?? '') === '1' && !CSRFUtils::verifyRequest($query)) {
            return SlimUtils::renderErrorJSON($response, gettext('Invalid or missing CSRF token'), [], 403);
        }

        return $renderStatements($response, $query);
    });

    // The preview popup: the same summary as ?preview=1. The filters arrive in a POST body so a long family selection does not hit a URL length limit.
    $group->map(['GET', 'POST'], '/giving-statements/preview', function (Request $request, Response $response): Response {
        $input = $request->getMethod() === 'POST' ? (array) $request->getParsedBody() : $request->getQueryParams();

        return SlimUtils::renderJSON($response, GivingStatementService::summary(
            GivingStatementService::paymentsByFamily(GivingStatementService::criteriaFromQuery($input))
        ));
    });

    $group->get('/giving-statements/sample', function (Request $request, Response $response): Response {
        $document = ($request->getQueryParams()['template'] ?? '') === 'canadian'
            ? GivingStatementService::canadianSampleDocument() : GivingStatementService::sampleDocument();
        $pdf = (new MpdfRenderer())->renderToString('tax-report', $document);

        return PdfResponse::create($response, $pdf, 'GivingStatement-Sample');
    });

    // Giving Statement: PDF or CSV for the chosen filters, from the report form or a family link.
    $group->map(['GET', 'POST'], '/giving-statements/download', function (Request $request, Response $response) use ($renderStatements): Response {
        $input = $request->getMethod() === 'POST' ? (array) $request->getParsedBody() : $request->getQueryParams();
        if (($input['template'] ?? '') === 'canadian') {
            if ($request->getMethod() !== 'POST' || !CSRFUtils::verifyRequest($input)) {
                return SlimUtils::renderErrorJSON($response, gettext('A reviewed POST with a valid CSRF token is required.'), [], 403);
            }
            try {
                $document = GivingStatementService::canadianDocument($input);
                $pdf = (new MpdfRenderer())->renderToString('tax-report', $document);
                return PdfResponse::create($response, $pdf, 'CanadianReceipts-' . $document['gift_year']);
            } catch (InvalidArgumentException $error) {
                // Show the review page again, with the answers kept and the reason named.
                return $renderStatements($response, $input, $error->getMessage(), 400);
            }
        }
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
        return PdfResponse::create($response, $pdf, $label);
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

        $filename = PdfFilename::create(GivingStatementService::fileLabel($year, (string) $family->getName()));
        $mail = new GivingStatementEmail($emails, (string) $family->getName(), $year, $pdf, $filename);

        if ($mail->send()) {
            RedirectUtils::redirect('people/family/' . $familyId . '?TaxEmailSent=' . $year);
        }
        LoggerUtils::getAppLogger()->error('Giving statement email failed to send for family ' . $familyId . ' year ' . $year);
        RedirectUtils::redirect('people/family/' . $familyId . '?TaxEmailError=SendFailed');

        return $response;
    });

});
