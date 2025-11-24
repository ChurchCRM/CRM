<?php

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Emails\TestEmail;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Service\AppIntegrityService;
use ChurchCRM\Service\TaskService;
use ChurchCRM\Utils\VersionUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/system', function (RouteCollectorProxy $group): void {
    
    $group->get('/reset', function (Request $request, Response $response): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');
        
        $pageArgs = [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('Database Reset Functions'),
        ];
        
        return $renderer->render($response, 'system-reset.php', $pageArgs);
    });

    $group->get('/maintenance', function (Request $request, Response $response): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');
        
        $pageArgs = [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('System Maintenance'),
            'isFreshInstall' => PersonQuery::create()->count() === 1,
        ];
        
        return $renderer->render($response, 'system-maintenance.php', $pageArgs);
    });

    // System Logs page (moved from v2/admin/logs)
    $group->get('/logs', function (Request $request, Response $response): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');

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
    });

    // Debug page (moved from v2/admin/debug)
    $group->get('/debug', function (Request $request, Response $response): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');

        $pageArgs = [
            'sRootPath'  => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('Debug'),
        ];

        return $renderer->render($response, 'debug.php', $pageArgs);
    });

    // Email Debug page
    $group->get('/debug/email', function (Request $request, Response $response): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../../v2/templates/email/');
        $message = '';

        if (empty(SystemConfig::getValue('sSMTPHost'))) {
            $message = gettext('SMTP Host is not setup, please visit the settings page');
        } elseif (empty(ChurchMetaData::getChurchEmail())) {
            $message = gettext('Church Email not set, please visit the settings page');
        } else {
            $email = new TestEmail([ChurchMetaData::getChurchEmail()]);
        }

        $pageArgs = [
            'sRootPath'  => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('Debug Email Connection'),
            'mailer'     => $email,
            'message'    => $message,
        ];

        return $renderer->render($response, 'debug.php', $pageArgs);
    });

    // Menus page
    $group->get('/menus', function (Request $request, Response $response): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');

        $pageArgs = [
            'sRootPath'  => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('Custom Menus'),
        ];

        return $renderer->render($response, 'menus.php', $pageArgs);
    });

    // Upgrade page
    $group->get('/upgrade', function (Request $request, Response $response): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');
        
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
    });

});
