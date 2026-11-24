<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Service\DashboardLandingService;
use ChurchCRM\Service\FlashService;
use ChurchCRM\Service\InstallSetupService;
use ChurchCRM\Service\MemberSignInSettingsService;
use ChurchCRM\Service\SystemService;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

// Get Started — onboarding wizard page
$app->get('/get-started', function (Request $request, Response $response) {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    // Determine whether the database already contains user data. The demo
    // data import API only runs on a truly fresh install (exactly 1 person,
    // no families). When data is present we surface a warning in the UI
    // *before* the user clicks, instead of letting them discover the 403.
    // Count ALL persons (including deceased) so that a DB containing only
    // deceased records still triggers the "existing data" warning and prevents
    // an inadvertent demo-data import over real data.
    $personCount = PersonQuery::create()->count();
    $familyCount = FamilyQuery::create()->count();
    $hasExistingData = $personCount > 1 || $familyCount > 0;

    $pageArgs = [
        'sRootPath'  => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('Get Your Data Into ChurchCRM'),
        'sPageSubtitle' => gettext('Choose how you\'d like to populate your database. You can always use a different method later.'),
        'aBreadcrumbs' => PageHeader::breadcrumbs([
            [gettext('Admin'), '/admin/'],
            [gettext('Get Started')],
        ]),
        'hasExistingData' => $hasExistingData,
        'personCount'     => $personCount,
        'familyCount'     => $familyCount,
    ];

    return $renderer->render($response, 'get-started.php', $pageArgs);
});

$app->get('/setup', function (Request $request, Response $response) {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');
    $personCount = PersonQuery::create()->count();
    $familyCount = FamilyQuery::create()->count();
    $dashboardUrl = DashboardLandingService::urlFor(AuthenticationManager::getCurrentUser());
    $windowOpen = InstallSetupService::isWindowOpen();
    $needsChurchSetup = InstallSetupService::needsChurchSetup();
    $flash = FlashService::take();

    return $renderer->render($response, 'setup.php', [
        'sRootPath'           => SystemURLs::getRootPath(),
        'sPageTitle'          => gettext('Setup Dashboard'),
        'sPageSubtitle'       => gettext('Explore ChurchCRM with demo data, or set it up for your church'),
        'sPageHeaderButtons'  => $needsChurchSetup ? '' : PageHeader::buttons([
            ['label' => gettext('Admin Dashboard'), 'url' => '/admin/', 'icon' => 'fa-gauge'],
            ...($windowOpen ? [[
                'label' => gettext('Finish setup'),
                'js'    => true,
                'id'    => 'finishSetup',
                'class' => 'btn-success',
                'icon'  => 'fa-flag-checkered',
                'data'  => ['dashboard-url' => $dashboardUrl],
            ]] : []),
        ]),
        'sGlobalMessage'      => $flash['message'] ?? '',
        'sGlobalMessageClass' => $flash['class'] ?? 'success',
        'needsChurchSetup'    => $needsChurchSetup,
        'demoLoaded'          => InstallSetupService::isDemoDataLoaded(),
        'hasExistingData'     => $personCount > 1 || $familyCount > 0,
        'personCount'         => $personCount,
        'familyCount'         => $familyCount,
        'steps'               => InstallSetupService::checklist(),
        'windowOpen'          => $windowOpen,
        'dashboardUrl'        => $dashboardUrl,
    ]);
});

// The admin dashboard and all other /admin routes require an authenticated administrator.
$app->get('/', function (Request $request, Response $response) {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    $setupChecklist = InstallSetupService::checklist();
    $completedSteps = count(array_filter(array_column($setupChecklist, 'done')));
    $totalSteps     = count($setupChecklist);
    $hasEmail       = SystemConfig::hasValidMailServerSettings();

    // Scheduled-task health (#9724). The timer jobs only run on a page load
    // unless the administrator has installed the cron entry, so surface it here
    // rather than letting birthday emails silently stop going out.
    $timerJobsLastRun = SystemService::getLastTimerJobsRun();

    $searchLabels = [
        'bSearchIncludePersons' => [gettext('Search People'), gettext('Include people in quick global search')],
        'bSearchIncludeFamilies' => [gettext('Search Families'), gettext('Include families in quick global search')],
        'bSearchIncludeGroups' => [gettext('Search Groups'), gettext('Include groups in quick global search')],
        'bSearchIncludeDeposits' => [gettext('Search Deposits'), gettext('Include deposits in quick global search')],
        'bSearchIncludePayments' => [gettext('Search Payments'), gettext('Include payments in quick global search')],
        'bSearchIncludeAddresses' => [gettext('Search Addresses'), gettext('Include addresses in quick global search')],
        'bSearchIncludeCalendarEvents' => [gettext('Search Calendar Events'), gettext('Include calendar events in quick global search')],
        'bSearchIncludeFamilyCustomProperties' => [gettext('Search Family Custom Properties'), gettext('Include custom family properties in quick global search')],
    ];

    $searchKeys = ['bSearchIncludePersons', 'bSearchIncludePersonsMax', 'bSearchIncludeAddresses', 'bSearchIncludeAddressesMax', 'bSearchIncludeFamilies', 'bSearchIncludeFamiliesMax', 'bSearchIncludeFamilyHOH', 'bSearchIncludeFamilyHOHMax', 'bSearchIncludeGroups', 'bSearchIncludeGroupsMax', 'bSearchIncludeFamilyCustomProperties'];
    if (SystemConfig::getBooleanValue('bEnabledFinance')) {
        array_push($searchKeys, 'bSearchIncludeDeposits', 'bSearchIncludeDepositsMax', 'bSearchIncludePayments', 'bSearchIncludePaymentsMax');
    }
    if (SystemConfig::getBooleanValue('bEnabledEvents')) {
        array_push($searchKeys, 'bSearchIncludeCalendarEvents', 'bSearchIncludeCalendarEventsMax');
    }

    $pageArgs = [
        'searchSettings' => array_map(static function (array $setting) use ($searchLabels): array {
            if (isset($searchLabels[$setting['name']])) {
                [$setting['label'], $setting['tooltip']] = $searchLabels[$setting['name']];
            }
            if (str_ends_with($setting['name'], 'Max')) {
                $setting['type'] = 'number';
            }
            return $setting;
        }, SystemConfig::getSettingsConfig($searchKeys)),
        'sRootPath'        => SystemURLs::getRootPath(),
        'sPageTitle'       => gettext('Admin Dashboard'),
        'sPageSubtitle'    => gettext("Let's get your system set up and ready to use"),
        'aBreadcrumbs'     => PageHeader::breadcrumbs([
            [gettext('Admin')],
        ]),
        'sPageHeaderButtons' => PageHeader::buttons([
            ...(InstallSetupService::isWindowOpen() ? [['label' => gettext('Setup Dashboard'), 'url' => '/admin/setup', 'icon' => 'fa-list-check']] : []),
            ['label' => gettext('System Settings'), 'url' => '/SystemSettings.php', 'icon' => 'fa-cog'],
            ['label' => gettext('Church Info'), 'url' => '/admin/system/church-info', 'icon' => 'fa-church'],
            ['label' => gettext('Users'), 'url' => '/admin/system/users', 'icon' => 'fa-user-shield'],
        ]),
        'pdfSettings' => array_map(static function (array $setting): array {
            $setting['label'] = gettext('PDF Temporary Directory');
            $setting['tooltip'] = gettext($setting['tooltip']);
            return $setting;
        }, SystemConfig::getSettingsConfig(['sPDFTempDir'])),
        'setupSettings' => array_map(static function (array $setting): array {
            $setting['label'] = gettext('Setup Dashboard Days');
            $setting['tooltip'] = gettext($setting['tooltip']);
            return $setting;
        }, SystemConfig::getSettingsConfig([InstallSetupService::DAYS_SETTING])),
        'setupChecklist'   => $setupChecklist,
        'completedSteps'   => $completedSteps,
        'totalSteps'       => $totalSteps,
        'allDone'          => $completedSteps === $totalSteps,
        'timerJobsStale'       => SystemService::isTimerJobsRunStale(),
        'timerJobsStaleHours'  => SystemService::getTimerJobsStaleHours(),
        'timerJobsLastRun'     => $timerJobsLastRun?->format('Y-m-d H:i:s'),
        'timerJobsCronCommand' => '/usr/bin/php ' . SystemURLs::getDocumentRoot() . '/cli/timerjobs.php',
        'formerPortalAccounts' => (new MemberSignInSettingsService())->formerAccountsToNotify(),
        'hasEmail'             => $hasEmail,
        'csrfToken'            => CSRFUtils::generateToken(),
    ];

    return $renderer->render($response, 'dashboard.php', $pageArgs);
});
