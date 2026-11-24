<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\FinancialService;
use ChurchCRM\Service\YearEndGivingService;
use ChurchCRM\Utils\FiscalYearUtils;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

// Match /finance root path - Finance Dashboard
$app->get('/', function (Request $request, Response $response) {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    $currentUser  = AuthenticationManager::getCurrentUser();
    $queryParams  = $request->getQueryParams();
    $currentFyid  = FiscalYearUtils::getCurrentFiscalYearId();
    $saved        = (string) $currentUser->getSettingValue('ui.dashboard.finance.range');
    $explicit     = isset($queryParams['days']) || isset($queryParams['fyid']);

    $days = null;
    if (isset($queryParams['days'])) {
        $days = in_array((int) $queryParams['days'], [30, 90, 365], true) ? (int) $queryParams['days'] : null;
    } elseif (!$explicit && preg_match('/^days:(30|90|365)$/', $saved, $matches) === 1) {
        $days = (int) $matches[1];
    }

    // 0 = All Time sentinel; positive int = specific FY; default = remembered or current FY
    $remembered   = FiscalYearUtils::rememberedEndYear();
    if (isset($queryParams['fyid'])) {
        $selectedFyid = (int) $queryParams['fyid'];
    } elseif (!$explicit && $saved === 'all') {
        $selectedFyid = 0;
    } else {
        $selectedFyid = $remembered !== null ? FiscalYearUtils::getFiscalYearIdForCalendarYear($remembered) : $currentFyid;
    }
    if (isset($queryParams['fyid']) && $selectedFyid > 0) {
        FiscalYearUtils::rememberEndYear(FiscalYearUtils::endYearForId($selectedFyid));
    }

    if ($explicit) {
        $currentUser->setSetting('ui.dashboard.finance.range', $days !== null ? 'days:' . $days : ($selectedFyid === 0 ? 'all' : 'fy'));
    }

    $financialService = new FinancialService();
    $dashboardData    = $financialService->getDashboardData($selectedFyid, $days);

    $statementYear = YearEndGivingService::selectedYear();
    $dashboardData['yearEnd'] = [
        'year' => $statementYear,
        'label' => FiscalYearUtils::getFiscalYearDatesForCalendarYear($statementYear)['label'],
        'openDeposits' => YearEndGivingService::openDeposits($statementYear),
    ];

    $pageArgs = [
        'sRootPath'  => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('Finance Dashboard'),
        'sPageSubtitle' => gettext('Manage donations, pledges, and financial records'),
        'aBreadcrumbs' => PageHeader::breadcrumbs([
            [gettext('Finance')],
        ]),
        'sSettingsCollapseId' => 'financialSettings',
        'sPageHeaderButtons' => PageHeader::buttons([
            // Finance role users can reach this dashboard (FinanceRoleAuthMiddleware),
            // so all finance-management buttons are available to them — no Admin required.
            // Financial Settings panel content is still Admin-only (backend API requires Admin),
            // so this button stays adminOnly: true (default) to hide it from Finance-only users.
            ['label' => gettext('Financial Settings'), 'icon' => 'fa-cog', 'collapse' => '#financialSettings'],
            ['label' => gettext('Donation Funds'), 'url' => '/finance/funds', 'icon' => 'fa-hand-holding-dollar', 'adminOnly' => false],
            ['label' => gettext('Manage Envelopes'), 'url' => '/finance/envelopes', 'icon' => 'fa-envelope', 'adminOnly' => false],
        ]),
        'dashboardData' => $dashboardData,
    ];

    return $renderer->render($response, 'dashboard.php', $pageArgs);
});
