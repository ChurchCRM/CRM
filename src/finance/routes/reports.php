<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\DonationFundQuery;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\DepositQuery;
use ChurchCRM\Service\FinancialService;
use ChurchCRM\Service\PdfRenderer\MpdfRenderer;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\InputUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/reports', function (RouteCollectorProxy $group): void {

    // Financial Reports selection page (migrated from FinancialReports.php)
    $group->get('', function (Request $request, Response $response): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');
        
        $pageArgs = [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('Financial Reports'),
        ];
        
        return $renderer->render($response, 'reports.php', $pageArgs);
    });

    // Tax Year Report (Giving Report) configuration form
    $group->get('/tax-statements', function (Request $request, Response $response): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');

        // Fetch classifications (lst_ID=1) via ORM
        $classifications = ListOptionQuery::create()
            ->filterById(1)
            ->orderByOptionSequence()
            ->find();

        // Fetch all donation funds via ORM
        $funds = DonationFundQuery::create()->find();

        // Fetch all families via ORM (ordered by name)
        $families = FamilyQuery::create()->orderByName()->find();

        // Fetch recent deposits (last 200) via ORM
        $deposits = DepositQuery::create()
            ->orderById(Criteria::DESC)
            ->limit(200)
            ->find();

        $today = date('Y-m-d');

        $pageArgs = [
            'sRootPath'       => SystemURLs::getRootPath(),
            'sPageTitle'      => gettext('Tax Statements (Giving Report)'),
            'today'           => $today,
            'classifications' => $classifications,
            'funds'           => $funds,
            'families'        => $families,
            'deposits'        => $deposits,
        ];

        return $renderer->render($response, 'reports/tax-statements.php', $pageArgs);
    });

    // POST: Generate Tax Statement PDF using mPDF renderer (migrated from Reports/TaxReport.php)
    $group->post('/tax-report', function (Request $request, Response $response): Response {
        $body = $request->getParsedBody() ?? [];

        // Sanitize inputs
        $letterhead = InputUtils::legacyFilterInput($body['letterhead'] ?? '');
        $remittance = ($body['remittance'] ?? 'no') === 'yes';
        $sDateStart = InputUtils::legacyFilterInput($body['DateStart'] ?? '', 'date');
        $sDateEnd   = InputUtils::legacyFilterInput($body['DateEnd'] ?? '', 'date');
        $iDepID     = (int) ($body['deposit'] ?? 0);
        $iMinimum   = (int) ($body['minimum'] ?? 0);
        $classList  = array_map('intval', (array) ($body['classList'] ?? []));
        $fundIds    = array_map('intval', (array) ($body['funds'] ?? []));
        $familyIds  = array_map('intval', (array) ($body['family'] ?? []));

        // Normalize date range
        $today = DateTimeUtils::getTodayDate();
        if (!$sDateEnd && $sDateStart) {
            $sDateEnd = $sDateStart;
        }
        if (!$sDateStart && $sDateEnd) {
            $sDateStart = $sDateEnd;
        }
        if (!$sDateStart && !$sDateEnd) {
            $sDateStart = $today;
            $sDateEnd   = $today;
        }
        if ($sDateStart > $sDateEnd) {
            [$sDateStart, $sDateEnd] = [$sDateEnd, $sDateStart];
        }

        // Fetch tax report data via FinancialService (auth check inside)
        $financialService = new FinancialService();
        $pledges = $financialService->getTaxReportData(
            $sDateStart,
            $sDateEnd,
            $iDepID > 0 ? $iDepID : null,
            $iMinimum > 0 ? $iMinimum : null,
            $fundIds,
            $familyIds,
            $classList
        );

        if (empty($pledges)) {
            return $response
                ->withHeader('Location', SystemURLs::getRootPath() . '/finance/reports?NoRows=1')
                ->withStatus(302);
        }

        // Group pledges by family into the structure expected by tax-report.html.twig
        $familiesMap = [];
        foreach ($pledges as $pledge) {
            $famId  = $pledge['FamId'] ?? 0;
            $family = $pledge['Family'] ?? null;

            if (!isset($familiesMap[$famId])) {
                $salutation = $family['Salutation'] ?? gettext('Dear Friend');
                $familiesMap[$famId] = [
                    'salutation'         => $salutation,
                    'recipientAddress1'  => $family['Address1'] ?? '',
                    'recipientAddress2'  => $family['Address2'] ?? '',
                    'recipientCity'      => $family['City'] ?? '',
                    'recipientState'     => $family['State'] ?? '',
                    'recipientZip'       => $family['Zip'] ?? '',
                    'recipientCountry'   => $family['Country'] ?? '',
                    'envelopeNumber'     => $family['Envelope'] ?? '',
                    'useDonationEnvelopes' => (bool) SystemConfig::getBooleanValue('bUseDonationEnvelopes'),
                    'dateRange'          => formatTaxReportDateRange($sDateStart, $sDateEnd),
                    'remittance'         => $remittance,
                    'payments'           => [],
                    'totalAmount'        => 0.0,
                    'totalNonDeductible' => 0.0,
                ];
            }

            // Truncate long strings to match legacy display behaviour
            $checkNo = $pledge['CheckNo'] ?? '';
            if (strlen($checkNo) > 8) {
                $checkNo = '...' . mb_substr($checkNo, -8);
            }
            $fundName = $pledge['DonationFund']['Name'] ?? gettext('Undesignated');
            if (strlen($fundName) > 25) {
                $fundName = mb_substr($fundName, 0, 25) . '...';
            }
            $memo = $pledge['Comment'] ?? '';
            if (strlen($memo) > 25) {
                $memo = mb_substr($memo, 0, 25) . '...';
            }

            $amount = (float) ($pledge['Amount'] ?? 0);
            $nonDeductible = (float) ($pledge['Nondeductible'] ?? 0);

            $familiesMap[$famId]['payments'][] = [
                'date'     => $pledge['Date'] ?? '',
                'checkNo'  => $checkNo,
                'method'   => $pledge['Method'] ?? '',
                'fundName' => $fundName,
                'memo'     => $memo,
                'amount'   => number_format($amount, 2),
            ];
            $familiesMap[$famId]['totalAmount']        += $amount;
            $familiesMap[$famId]['totalNonDeductible'] += $nonDeductible;
        }

        // Build template data
        $data = [
            // Letterhead config
            'letterhead'       => $letterhead,
            'churchName'       => SystemConfig::getValue('sChurchName'),
            'churchAddress'    => SystemConfig::getValue('sChurchAddress'),
            'churchCity'       => SystemConfig::getValue('sChurchCity'),
            'churchState'      => SystemConfig::getValue('sChurchState'),
            'churchZip'        => SystemConfig::getValue('sChurchZip'),
            'churchPhone'      => SystemConfig::getValue('sChurchPhone'),
            'churchEmail'      => SystemConfig::getValue('sChurchEmail'),
            'letterheadImage'  => SystemConfig::getValue('bDirLetterHead'),
            'today'            => date(SystemConfig::getValue('sDateFormatLong')),
            'defaultCountry'   => SystemConfig::getValue('sDefaultCountry'),

            // Report text
            'taxReport1'       => SystemConfig::getValue('sTaxReport1'),
            'taxReport2'       => SystemConfig::getValue('sTaxReport2'),
            'taxReport3'       => SystemConfig::getValue('sTaxReport3'),
            'confirmSincerely' => SystemConfig::getValue('sConfirmSincerely'),
            'taxSigner'        => SystemConfig::getValue('sTaxSigner'),

            // Families (reset array keys)
            'families' => array_values($familiesMap),
        ];

        $filename = 'TaxReport' . date(SystemConfig::getValue('sDateFilenameFormat'));

        // Render PDF and stream to browser (exits/sends output internally)
        $renderer = new MpdfRenderer();
        $renderer->render('tax-report', $data, $filename);

        // MpdfRenderer::render() calls mpdf->Output() which exits.
        // Return the (unused) response to satisfy the Slim handler signature.
        return $response;
    });

});

/**
 * Format a date range string for display in tax report letters.
 * Returns a single date if start == end, otherwise a range.
 */
function formatTaxReportDateRange(string $dateStart, string $dateEnd): string
{
    if ($dateStart === $dateEnd) {
        return date('F j, Y', strtotime($dateStart));
    }
    return date('M j, Y', strtotime($dateStart)) . ' – ' . date('M j, Y', strtotime($dateEnd));
}
