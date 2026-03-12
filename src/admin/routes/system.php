<?php

use ChurchCRM\data\Countries;
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

    // Church Information page
    $group->get('/church-info', function (Request $request, Response $response): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');

        $queryParams = $request->getQueryParams();
        $saved = isset($queryParams['saved']) && $queryParams['saved'] === '1';

        $churchInfo = [
            'sChurchName'      => SystemConfig::getValue('sChurchName'),
            'sChurchAddress'   => SystemConfig::getValue('sChurchAddress'),
            'sChurchCity'      => SystemConfig::getValue('sChurchCity'),
            'sChurchState'     => SystemConfig::getValue('sChurchState'),
            'sChurchZip'       => SystemConfig::getValue('sChurchZip'),
            'sChurchCountry'   => SystemConfig::getValue('sChurchCountry'),
            'sChurchPhone'     => SystemConfig::getValue('sChurchPhone'),
            'sChurchEmail'     => SystemConfig::getValue('sChurchEmail'),
            'iChurchLatitude'  => SystemConfig::getValue('iChurchLatitude'),
            'iChurchLongitude' => SystemConfig::getValue('iChurchLongitude'),
            'sTimeZone'        => SystemConfig::getValue('sTimeZone'),
            'sChurchWebSite'   => SystemConfig::getValue('sChurchWebSite'),
        ];

        $pageArgs = [
            'sRootPath'  => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('Church Information'),
            'churchInfo' => $churchInfo,
            'countries'  => Countries::getNames(),
            'timezones'  => timezone_identifiers_list(),
            'saved'      => $saved,
        ];

        return $renderer->render($response, 'church-info.php', $pageArgs);
    });

    $group->post('/church-info', function (Request $request, Response $response): Response {
        $body = $request->getParsedBody();

        $churchName = trim($body['sChurchName'] ?? '');

        if (empty($churchName)) {
            // Re-render with validation error
            $renderer = new PhpRenderer(__DIR__ . '/../views/');

            $churchInfo = [
                'sChurchName'      => $churchName,
                'sChurchAddress'   => trim($body['sChurchAddress'] ?? ''),
                'sChurchCity'      => trim($body['sChurchCity'] ?? ''),
                'sChurchState'     => trim($body['sChurchState'] ?? ''),
                'sChurchZip'       => trim($body['sChurchZip'] ?? ''),
                'sChurchCountry'   => trim($body['sChurchCountry'] ?? ''),
                'sChurchPhone'     => trim($body['sChurchPhone'] ?? ''),
                'sChurchEmail'     => trim($body['sChurchEmail'] ?? ''),
                'iChurchLatitude'  => trim($body['iChurchLatitude'] ?? ''),
                'iChurchLongitude' => trim($body['iChurchLongitude'] ?? ''),
                'sTimeZone'        => trim($body['sTimeZone'] ?? ''),
                'sChurchWebSite'   => trim($body['sChurchWebSite'] ?? ''),
            ];

            $pageArgs = [
                'sRootPath'       => SystemURLs::getRootPath(),
                'sPageTitle'      => gettext('Church Information'),
                'churchInfo'      => $churchInfo,
                'countries'       => Countries::getNames(),
                'timezones'       => timezone_identifiers_list(),
                'saved'           => false,
                'validationError' => gettext('Church name is required.'),
            ];

            return $renderer->render($response->withStatus(422), 'church-info.php', $pageArgs);
        }

        SystemConfig::setValue('sChurchName', $churchName);
        SystemConfig::setValue('sChurchAddress', trim($body['sChurchAddress'] ?? ''));
        SystemConfig::setValue('sChurchCity', trim($body['sChurchCity'] ?? ''));
        SystemConfig::setValue('sChurchState', trim($body['sChurchState'] ?? ''));
        SystemConfig::setValue('sChurchZip', trim($body['sChurchZip'] ?? ''));
        SystemConfig::setValue('sChurchCountry', trim($body['sChurchCountry'] ?? ''));
        SystemConfig::setValue('sChurchPhone', trim($body['sChurchPhone'] ?? ''));
        SystemConfig::setValue('sChurchEmail', trim($body['sChurchEmail'] ?? ''));
        SystemConfig::setValue('iChurchLatitude', trim($body['iChurchLatitude'] ?? ''));
        SystemConfig::setValue('iChurchLongitude', trim($body['iChurchLongitude'] ?? ''));
        SystemConfig::setValue('sTimeZone', trim($body['sTimeZone'] ?? ''));
        SystemConfig::setValue('sChurchWebSite', trim($body['sChurchWebSite'] ?? ''));

        return $response
            ->withHeader('Location', SystemURLs::getRootPath() . '/admin/system/church-info?saved=1')
            ->withStatus(303);
    });

});
