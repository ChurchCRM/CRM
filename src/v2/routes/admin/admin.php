<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\AppIntegrityService;
use ChurchCRM\Service\TaskService;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Utils\ChurchCRMReleaseManager;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\VersionUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/admin', function (RouteCollectorProxy $group): void {
    $group->get('/debug', 'debugPage');
    $group->get('/menus', 'menuPage');
    $group->get('/database/reset', 'dbResetPage');
    $group->get('/logs', 'logsPage');
    $group->get('/upgrade', 'upgradePage');
})->add(AdminRoleAuthMiddleware::class);

function debugPage(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/admin/');

    $pageArgs = [
        'sRootPath'  => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('Debug'),
    ];

    return $renderer->render($response, 'debug.php', $pageArgs);
}

function menuPage(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/admin/');

    $pageArgs = [
        'sRootPath'  => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('Custom Menus'),
    ];

    return $renderer->render($response, 'menus.php', $pageArgs);
}

function dbResetPage(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/admin/');

    $pageArgs = [
        'sRootPath'  => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('Database Reset Functions'),
    ];

    return $renderer->render($response, 'database-reset.php', $pageArgs);
}

function logsPage(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/admin/');

    $logsDir = SystemURLs::getDocumentRoot() . '/logs';
    $logFiles = [];

    if (is_dir($logsDir)) {
        $files = scandir($logsDir, SCANDIR_SORT_DESCENDING);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && $file !== '.htaccess' && pathinfo($file, PATHINFO_EXTENSION) === 'log') {
                $logFiles[] = [
                    'name' => $file,
                    'path' => $logsDir . '/' . $file,
                    'size' => filesize($logsDir . '/' . $file),
                    'modified' => filemtime($logsDir . '/' . $file),
                ];
            }
        }
    }

    $pageArgs = [
        'sRootPath'  => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('System Logs'),
        'logFiles'   => $logFiles,
    ];

    return $renderer->render($response, 'logs.php', $pageArgs);
}

function upgradePage(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/admin/');
    
    // Get pre-upgrade tasks
    $taskService = new TaskService();
    $preUpgradeTasks = $taskService->getActivePreUpgradeTasks();
    
    // Check for warnings: either pre-upgrade tasks OR integrity check failures
    $hasPreUpgradeTasks = count($preUpgradeTasks) > 0;
    $integrityCheckFailed = AppIntegrityService::getIntegrityCheckStatus() === gettext("Failed");
    $hasWarnings = $hasPreUpgradeTasks || $integrityCheckFailed;
    
    // Get integrity check data if failed
    $integrityCheckData = [];
    if ($integrityCheckFailed) {
        $integrityCheckData = [
            'status' => AppIntegrityService::getIntegrityCheckStatus(),
            'message' => AppIntegrityService::getIntegrityCheckMessage(),
            'files' => AppIntegrityService::getFilesFailingIntegrityCheck(),
        ];
    }
    
    // Get version information
    $currentVersion = VersionUtils::getInstalledVersion();
    $availableVersion = null;
    $isUpdateAvailable = false;
    
    // Check if update information is available in session
    if (isset($_SESSION['systemUpdateAvailable']) && $_SESSION['systemUpdateAvailable'] === true) {
        $isUpdateAvailable = true;
        if (isset($_SESSION['systemUpdateVersion']) && $_SESSION['systemUpdateVersion'] !== null) {
            $availableVersion = $_SESSION['systemUpdateVersion']->__toString();
        }
    }
    
    // Get pre-release upgrade setting info
    $prereleaseConfig = SystemConfig::getConfigItem('bAllowPrereleaseUpgrade');
    $allowPrereleaseUpgrade = SystemConfig::getBooleanValue('bAllowPrereleaseUpgrade');
    
    $pageArgs = [
        'sRootPath'             => SystemURLs::getRootPath(),
        'sPageTitle'            => gettext('System Upgrade'),
        'preUpgradeTasks'       => $preUpgradeTasks,
        'hasWarnings'           => $hasWarnings,
        'hasPreUpgradeTasks'    => $hasPreUpgradeTasks,
        'integrityCheckFailed'  => $integrityCheckFailed,
        'integrityCheckData'    => $integrityCheckData,
        'currentVersion'        => $currentVersion,
        'availableVersion'      => $availableVersion,
        'isUpdateAvailable'     => $isUpdateAvailable,
        'prereleaseConfig'      => $prereleaseConfig,
        'allowPrereleaseUpgrade' => $allowPrereleaseUpgrade,
    ];

    return $renderer->render($response, 'upgrade.php', $pageArgs);
}
