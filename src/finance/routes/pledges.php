<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\FamilyPledgeSummaryService;
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
            'familyPledges' => $pledgeData['families'],
            'fundTotals' => $pledgeData['fund_totals'],
            'totalPledges' => $pledgeData['total_pledges'],
            'availableYears' => $availableYears,
            'selectedFyid' => $fyid,
            'currentFyid' => $currentFyid,
        ];
        
        return $renderer->render($response, 'pledges/family-summary.php', $pageArgs);
    });

});
