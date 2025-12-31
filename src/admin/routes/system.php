<?php

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Emails\TestEmail;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\UserQuery;
use ChurchCRM\Service\AppIntegrityService;
use ChurchCRM\Utils\ChurchCRMReleaseManager;
use ChurchCRM\Utils\VersionUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/system', function (RouteCollectorProxy $group): void {
    
    // Backup Database page
    $group->get('/backup', function (Request $request, Response $response): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');
        
        $pageArgs = [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('Backup Database'),
        ];
        
        return $renderer->render($response, 'backup.php', $pageArgs);
    });

    // Users page
    $group->get('/users', function (Request $request, Response $response): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');
        
        $pageArgs = [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('System Users'),
        ];
        
        return $renderer->render($response, 'users.php', $pageArgs);
    });

    // Restore Database page
    $group->get('/restore', function (Request $request, Response $response): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');
        
        $pageArgs = [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('Restore Database'),
        ];
        
        return $renderer->render($response, 'restore.php', $pageArgs);
    });

    $group->get('/reset', function (Request $request, Response $response): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');
        
        $pageArgs = [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('Database Reset Functions'),
        ];
        
        return $renderer->render($response, 'system-reset.php', $pageArgs);
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

    // Orphaned Files Management page
    $group->get('/orphaned-files', function (Request $request, Response $response): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');

        $orphanedFiles = AppIntegrityService::getOrphanedFiles();

        $pageArgs = [
            'sRootPath'      => SystemURLs::getRootPath(),
            'sPageTitle'     => gettext('Orphaned Files Management'),
            'orphanedFiles'  => $orphanedFiles,
            'orphanedCount'  => count($orphanedFiles),
        ];

        return $renderer->render($response, 'orphaned-files.php', $pageArgs);
    });

    // Upgrade page
    $group->get('/upgrade', function (Request $request, Response $response): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');
        
        // Ensure we have fresh release information
        ChurchCRMReleaseManager::checkForUpdates();
        
        // Recompute update availability with fresh data
        $updateInfo = ChurchCRMReleaseManager::checkSystemUpdateAvailable();
        $_SESSION['systemUpdateAvailable'] = $updateInfo['available'];
        $_SESSION['systemUpdateVersion'] = $updateInfo['version'];
        $_SESSION['systemLatestVersion'] = $updateInfo['latestVersion'];
        
        $integrityCheckFailed = AppIntegrityService::getIntegrityCheckStatus() === gettext("Failed");
        $orphanedFiles = AppIntegrityService::getOrphanedFiles();
        $hasOrphanedFiles = count($orphanedFiles) > 0;
        $hasWarnings = $integrityCheckFailed || $hasOrphanedFiles;
        
        // Get integrity check data if failed or orphaned files exist
        $integrityCheckData = [];
        if ($integrityCheckFailed || $hasOrphanedFiles) {
            $integrityCheckData = [
                'status' => AppIntegrityService::getIntegrityCheckStatus(),
                'message' => AppIntegrityService::getIntegrityCheckMessage(),
                'files' => AppIntegrityService::getFilesFailingIntegrityCheck(),
                'orphanedFiles' => $orphanedFiles,
            ];
        }
        
        // Get version information
        $currentVersion = VersionUtils::getInstalledVersion();
        $availableVersion = null;
        $latestGitHubVersion = null;
        $isUpdateAvailable = false;
        
        // Check if update information is available in session
        if (isset($_SESSION['systemUpdateAvailable']) && $_SESSION['systemUpdateAvailable'] === true) {
            $isUpdateAvailable = true;
            if (isset($_SESSION['systemUpdateVersion']) && $_SESSION['systemUpdateVersion'] !== null) {
                $availableVersion = $_SESSION['systemUpdateVersion']->__toString();
            }
        }
        
        // Get the latest GitHub version (always show this, even if no update available)
        if (isset($_SESSION['systemLatestVersion']) && $_SESSION['systemLatestVersion'] !== null) {
            $latestGitHubVersion = $_SESSION['systemLatestVersion']->__toString();
        }
        
        // Get pre-release upgrade setting info
        $prereleaseConfig = SystemConfig::getConfigItem('bAllowPrereleaseUpgrade');
        $allowPrereleaseUpgrade = SystemConfig::getBooleanValue('bAllowPrereleaseUpgrade');
        
        $pageArgs = [
            'sRootPath'             => SystemURLs::getRootPath(),
            'sPageTitle'            => gettext('System Upgrade'),
            'hasWarnings'           => $hasWarnings,
            'integrityCheckFailed'  => $integrityCheckFailed,
            'integrityCheckData'    => $integrityCheckData,
            'hasOrphanedFiles'      => $hasOrphanedFiles,
            'currentVersion'        => $currentVersion,
            'availableVersion'      => $availableVersion,
            'latestGitHubVersion'   => $latestGitHubVersion,
            'isUpdateAvailable'     => $isUpdateAvailable,
            'prereleaseConfig'      => $prereleaseConfig,
            'allowPrereleaseUpgrade' => $allowPrereleaseUpgrade,
        ];

        return $renderer->render($response, 'upgrade.php', $pageArgs);
    });

});
