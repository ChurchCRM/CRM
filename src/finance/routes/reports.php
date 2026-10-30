<?php

use ChurchCRM\Emails\GivingStatementEmail;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\Service\MpdfRenderer;
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

    // Giving Statement: PDF or CSV for the chosen filters, from the report form or a family link.
    $group->map(['GET', 'POST'], '/giving-statements/download', function (Request $request, Response $response): Response {
        $input = $request->getMethod() === 'POST' ? (array) $request->getParsedBody() : $request->getQueryParams();
        $criteria = GivingStatementService::criteriaFromQuery($input);
        $groups = GivingStatementService::paymentsByFamily($criteria);
        if ($groups === []) {
            RedirectUtils::redirect('FinancialReports.php?ReturnMessage=NoRows&ReportType=Giving%20Report');
        }

        if (($input['output'] ?? 'pdf') === 'csv') {
            [$headers, $rows] = GivingStatementService::csv($groups);
            CsvExporter::create($headers, $rows, 'GivingStatement', 'UTF-8', true);

            return $response;
        }

        [$dateStart, $dateEnd] = GivingStatementService::period($criteria);
        $families = GivingStatementService::families($groups, SystemConfig::getBooleanValue('bUseDonationEnvelopes'));
        $document = GivingStatementService::document($families, $dateStart, $dateEnd, $criteria['letterhead'], $criteria['remittance']);
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
