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

    $queryParams  = $request->getQueryParams();
    $currentFyid  = FiscalYearUtils::getCurrentFiscalYearId();
    // 0 = All Time sentinel; positive int = specific FY; default = current FY
    $remembered   = FiscalYearUtils::rememberedEndYear();
    $selectedFyid = isset($queryParams['fyid'])
        ? (int) $queryParams['fyid']
        : ($remembered !== null ? FiscalYearUtils::getFiscalYearIdForCalendarYear($remembered) : $currentFyid);
    if (isset($queryParams['fyid']) && $selectedFyid > 0) {
        FiscalYearUtils::rememberEndYear(FiscalYearUtils::endYearForId($selectedFyid));
    }

    $financialService = new FinancialService();
    $dashboardData    = $financialService->getDashboardData($selectedFyid);

    $statementYear = YearEndGivingService::selectedYear();
    $dashboardData['yearEnd'] = [
        'year' => $statementYear,
        'label' => FiscalYearUtils::getFiscalYearDatesForCalendarYear($statementYear)['label'],
        'openDeposits' => YearEndGivingService::openDeposits($statementYear),
    ];

    $savedDays = (int) AuthenticationManager::getCurrentUser()->getSettingValue('ui.dashboard.deposit.days');
    $depositDays = in_array($savedDays, [30, 90, 365], true) ? $savedDays : 90;

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
        'depositDays' => $depositDays,
    ];

    return $renderer->render($response, 'dashboard.php', $pageArgs);
});
