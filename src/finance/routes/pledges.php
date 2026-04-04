<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\DepositQuery;
use ChurchCRM\model\ChurchCRM\DonationFundQuery;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\Service\FinancialService;
use ChurchCRM\Service\FamilyPledgeSummaryService;
use ChurchCRM\utils\FiscalYearUtils;
use ChurchCRM\view\PageHeader;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/pledge', function (RouteCollectorProxy $group): void {

    // Pledge Dashboard - Family pledge summary with fund breakdown
    $group->get('/dashboard', function (Request $request, Response $response): Response {
        $service = new FamilyPledgeSummaryService();
        
        // Get fiscal year from query param or use current
        $queryParams = $request->getQueryParams();
        $fyid = isset($queryParams['fyid']) ? (int) $queryParams['fyid'] : $service->getCurrentFiscalYearId();
        
        // Get the data
        $pledgeData = $service->getFamilyPledgesByFiscalYear($fyid);
        $availableYears = $service->getAvailableFiscalYears();
        $currentFyid = $service->getCurrentFiscalYearId();
        
        $renderer = new PhpRenderer(__DIR__ . '/../views/');
        
        $pageArgs = [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('Pledge Dashboard'),
            'sPageSubtitle' => gettext('Track family pledges and fund contributions by fiscal year'),
            'aBreadcrumbs' => PageHeader::breadcrumbs([
                [gettext('Finance'), '/finance/'],
                [gettext('Pledge Dashboard')],
            ]),
            'familyPledges' => $pledgeData['families'],
            'fundTotals' => $pledgeData['fund_totals'],
            'totalPledges' => $pledgeData['total_pledges'],
            'totalPayments' => $pledgeData['total_payments'],
            'availableYears' => $availableYears,
            'selectedFyid' => $fyid,
            'currentFyid' => $currentFyid,
        ];

        return $renderer->render($response, 'reports/pledge-dashboard.php', $pageArgs);
    });

    // Family Pledge Summary page (alternative simpler view)
    $group->get('/family-summary', function (Request $request, Response $response): Response {
        $service = new FamilyPledgeSummaryService();
        
        // Get fiscal year from query param or use current
        $queryParams = $request->getQueryParams();
        $fyid = isset($queryParams['fyid']) ? (int) $queryParams['fyid'] : $service->getCurrentFiscalYearId();
        
        // Get the data
        $pledgeData = $service->getFamilyPledgesByFiscalYear($fyid);
        $availableYears = $service->getAvailableFiscalYears();
        $currentFyid = $service->getCurrentFiscalYearId();
        
        $renderer = new PhpRenderer(__DIR__ . '/../views/');
        
        $pageArgs = [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('Pledge Dashboard'),
            'sPageSubtitle' => gettext('View family pledge summaries and fund allocations'),
            'aBreadcrumbs' => PageHeader::breadcrumbs([
                [gettext('Finance'), '/finance/'],
                [gettext('Family Pledge Summary')],
            ]),
            'familyPledges' => $pledgeData['families'],
            'fundTotals' => $pledgeData['fund_totals'],
            'totalPledges' => $pledgeData['total_pledges'],
            'availableYears' => $availableYears,
            'selectedFyid' => $fyid,
            'currentFyid' => $currentFyid,
        ];

        return $renderer->render($response, 'pledges/family-summary.php', $pageArgs);
    });

    /**
     * Pledge/Payment editor — create mode.
     * GET /finance/pledge/new[?type=Pledge|Payment][&depositId=N][&familyId=N]
     */
    $group->get('/new', function (Request $request, Response $response): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');

        $queryParams = $request->getQueryParams();
        $type = isset($queryParams['type']) && $queryParams['type'] === 'Pledge' ? 'Pledge' : 'Payment';
        $depositId = isset($queryParams['depositId']) ? (int) $queryParams['depositId'] : 0;
        $familyId = isset($queryParams['familyId']) ? (int) $queryParams['familyId'] : 0;

        // Active donation funds
        $funds = DonationFundQuery::create()
            ->filterByActive('true')
            ->orderByOrder()
            ->find();

        // Open deposits for dropdown (Payments only)
        $openDeposits = DepositQuery::create()
            ->filterByClosed(0)
            ->orderByDate(Criteria::DESC)
            ->find();

        // Fiscal years for dropdown
        $currentFyId = FiscalYearUtils::getCurrentFiscalYearId();
        $fiscalYears = [];
        for ($fy = 1; $fy <= $currentFyId + 1; $fy++) {
            $fiscalYears[$fy] = \ChurchCRM\Service\FinancialService::formatFiscalYear($fy);
        }

        // Pre-populate family name if familyId given
        $familyName = '';
        if ($familyId) {
            $family = FamilyQuery::create()->findPk($familyId);
            if ($family !== null) {
                $familyName = $family->getName();
            }
        }

        $enableNonDeductible = SystemConfig::getBooleanValue('bEnableNonDeductible');

        $pageArgs = [
            'sRootPath'          => SystemURLs::getRootPath(),
            'sPageTitle'         => $type === 'Pledge' ? gettext('New Pledge') : gettext('New Payment'),
            'sPageSubtitle'      => gettext('Record new pledges and payments'),
            'aBreadcrumbs'       => PageHeader::breadcrumbs([
                [gettext('Finance'), '/finance/'],
                [gettext('Pledges & Payments'), '/finance/pledge/dashboard'],
                [$type === 'Pledge' ? gettext('New Pledge') : gettext('New Payment')],
            ]),
            'type'               => $type,
            'groupKey'           => '',
            'familyId'           => $familyId,
            'familyName'         => $familyName,
            'depositId'          => $depositId,
            'funds'              => $funds,
            'openDeposits'       => $openDeposits,
            'fiscalYears'        => $fiscalYears,
            'currentFyId'        => $currentFyId,
            'enableNonDeductible' => $enableNonDeductible,
            'isEdit'             => false,
            'pledge'             => null,
        ];

        return $renderer->render($response, 'pledges/editor.php', $pageArgs);
    });

    /**
     * Pledge/Payment detail view.
     * GET /finance/pledge/{groupKey}
     */
    $group->get('/{groupKey}', function (Request $request, Response $response, array $args): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');

        $groupKey = $args['groupKey'];

        $financialService = new FinancialService();
        try {
            $pledge = $financialService->getPledgesByGroupKey($groupKey);
        } catch (\InvalidArgumentException $e) {
            return $response->withStatus(404);
        }

        $pageArgs = [
            'sRootPath'     => SystemURLs::getRootPath(),
            'sPageTitle'    => $pledge['pledgeOrPayment'] === 'Pledge'
                ? gettext('Pledge Details')
                : gettext('Payment Details'),
            'sPageSubtitle' => gettext('View pledge or payment details'),
            'aBreadcrumbs'  => PageHeader::breadcrumbs([
                [gettext('Finance'), '/finance/'],
                [gettext('Pledges & Payments'), '/finance/pledge/dashboard'],
                [$pledge['pledgeOrPayment'] === 'Pledge' ? gettext('Pledge') : gettext('Payment')],
            ]),
            'pledge'        => $pledge,
        ];

        return $renderer->render($response, 'pledges/detail.php', $pageArgs);
    });

    /**
     * Pledge/Payment editor — edit mode.
     * GET /finance/pledge/{groupKey}/edit
     */
    $group->get('/{groupKey}/edit', function (Request $request, Response $response, array $args): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');

        $groupKey = $args['groupKey'];

        $financialService = new FinancialService();
        try {
            $pledge = $financialService->getPledgesByGroupKey($groupKey);
        } catch (\InvalidArgumentException $e) {
            return $response->withStatus(404);
        }

        $type = $pledge['pledgeOrPayment'];
        $familyId = $pledge['familyId'];
        $familyName = $pledge['familyName'];
        $depositId = $pledge['depositId'] ?? 0;

        // Active donation funds
        $funds = DonationFundQuery::create()
            ->filterByActive('true')
            ->orderByOrder()
            ->find();

        // Open deposits for dropdown (Payments only)
        $openDeposits = DepositQuery::create()
            ->filterByClosed(0)
            ->orderByDate(Criteria::DESC)
            ->find();

        // Fiscal years for dropdown
        $currentFyId = FiscalYearUtils::getCurrentFiscalYearId();
        $fiscalYears = [];
        for ($fy = 1; $fy <= $currentFyId + 1; $fy++) {
            $fiscalYears[$fy] = \ChurchCRM\Service\FinancialService::formatFiscalYear($fy);
        }

        $enableNonDeductible = SystemConfig::getBooleanValue('bEnableNonDeductible');

        $pageArgs = [
            'sRootPath'          => SystemURLs::getRootPath(),
            'sPageTitle'         => $type === 'Pledge' ? gettext('Edit Pledge') : gettext('Edit Payment'),
            'sPageSubtitle'      => gettext('Edit existing pledge or payment'),
            'aBreadcrumbs'       => PageHeader::breadcrumbs([
                [gettext('Finance'), '/finance/'],
                [gettext('Pledges & Payments'), '/finance/pledge/dashboard'],
                [$type === 'Pledge' ? gettext('Edit Pledge') : gettext('Edit Payment')],
            ]),
            'type'               => $type,
            'groupKey'           => $groupKey,
            'familyId'           => $familyId,
            'familyName'         => $familyName,
            'depositId'          => $depositId,
            'funds'              => $funds,
            'openDeposits'       => $openDeposits,
            'fiscalYears'        => $fiscalYears,
            'currentFyId'        => $currentFyId,
            'enableNonDeductible' => $enableNonDeductible,
            'isEdit'             => true,
            'pledge'             => $pledge,
        ];

        return $renderer->render($response, 'pledges/editor.php', $pageArgs);
    });

});
