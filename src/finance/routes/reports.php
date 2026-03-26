<?php

use ChurchCRM\dto\ReportConfig;
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

    /**
     * @OA\Get(
     *     path="/finance/reports/tax-statements",
     *     operationId="getTaxStatementForm",
     *     summary="Tax Statement configuration form",
     *     description="Renders the Giving Report configuration form. Requires Finance role.",
     *     tags={"Reports"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(
     *         name="NoRows",
     *         in="query",
     *         required=false,
     *         description="When set to 1, displays a 'No Data Found' alert (set by redirect from tax-report POST)",
     *         @OA\Schema(type="integer", enum={0, 1})
     *     ),
     *     @OA\Response(response=200, description="HTML configuration form page"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Finance role required")
     * )
     */
    $group->get('/tax-statements', function (Request $request, Response $response): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');

        // Fetch classifications (lst_ID=1) via ORM
        $classifications = ListOptionQuery::create()
            ->filterById(1)
            ->orderByOptionSequence()
            ->find();

        // Fetch all donation funds via ORM
        $funds = DonationFundQuery::create()->find();

        // Fetch all families via ORM (ordered by name).
        // @todo For large churches this loads all families to populate a <select>.
        //       Replace with a lazy-loaded typeahead using /api/families once that
        //       endpoint supports name-search queries.
        $families = FamilyQuery::create()->orderByName()->find();

        // Fetch recent deposits (last 200) via ORM
        $deposits = DepositQuery::create()
            ->orderById(Criteria::DESC)
            ->limit(200)
            ->find();

        $queryParams = $request->getQueryParams();

        $pageArgs = [
            'sRootPath'       => SystemURLs::getRootPath(),
            'sPageTitle'      => gettext('Tax Statements (Giving Report)'),
            'today'           => DateTimeUtils::getTodayDate(),
            'datePickerFormat' => SystemConfig::getValue('sDatePickerFormat'),
            'noRows'          => !empty($queryParams['NoRows']),
            'classifications' => $classifications,
            'funds'           => $funds,
            'families'        => $families,
            'deposits'        => $deposits,
        ];

        return $renderer->render($response, 'reports/tax-statements.php', $pageArgs);
    });

    /**
     * @OA\Post(
     *     path="/finance/reports/tax-report",
     *     operationId="generateTaxReportPdf",
     *     summary="Generate Tax Statement PDF",
     *     description="Generates a Giving Report / Tax Statement PDF via mPDF. Requires Finance role. Redirects to tax-statements?NoRows=1 when no matching data is found.",
     *     tags={"Reports"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 @OA\Property(property="DateStart", type="string", format="date", description="Report start date"),
     *                 @OA\Property(property="DateEnd", type="string", format="date", description="Report end date"),
     *                 @OA\Property(property="letterhead", type="string", enum={"graphic", "address", "none"}, description="Letterhead style"),
     *                 @OA\Property(property="remittance", type="string", enum={"yes", "no"}, description="Include remittance slip"),
     *                 @OA\Property(property="minimum", type="integer", description="Minimum giving amount filter"),
     *                 @OA\Property(property="deposit", type="integer", description="Filter by deposit ID (0 = all)"),
     *                 @OA\Property(property="classList", type="array", @OA\Items(type="integer"), description="Classification IDs to include"),
     *                 @OA\Property(property="funds", type="array", @OA\Items(type="integer"), description="Fund IDs to include"),
     *                 @OA\Property(property="family", type="array", @OA\Items(type="integer"), description="Family IDs to include")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="PDF binary stream (application/pdf)"),
     *     @OA\Response(response=302, description="Redirect to tax-statements?NoRows=1 when no data matches"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Finance role required")
     * )
     *
     * @todo Register MpdfRenderer in the Slim 4 DI container so it can be injected
     *       rather than instantiated with `new` here.
     */
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
        $defaultDate = DateTimeUtils::getTodayDate();
        if (!$sDateEnd && $sDateStart) {
            $sDateEnd = $sDateStart;
        }
        if (!$sDateStart && $sDateEnd) {
            $sDateStart = $sDateEnd;
        }
        if (!$sDateStart && !$sDateEnd) {
            $sDateStart = $defaultDate;
            $sDateEnd   = $defaultDate;
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
                ->withHeader('Location', SystemURLs::getRootPath() . '/finance/reports/tax-statements?NoRows=1')
                ->withStatus(302);
        }

        // Group pledges by family into the structure expected by tax-report.html.twig
        $reportConfig = new ReportConfig();
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
                    'useDonationEnvelopes' => $reportConfig->useDonationEnvelopes,
                    'dateRange'          => DateTimeUtils::formatDateRange($sDateStart, $sDateEnd),
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

        // Build template data using the ReportConfig already created above
        $data = array_merge($reportConfig->toTaxReportArray(), [
            'letterhead' => $letterhead,
            'families'   => array_values($familiesMap),
        ]);

        $filename = 'TaxReport' . date($reportConfig->dateFilenameFormat);

        // Render PDF via PSR-7 response using MpdfRenderer::render()
        $renderer = new MpdfRenderer();

        return $renderer->render('tax-report', $data, $filename, $response);
    });

});
