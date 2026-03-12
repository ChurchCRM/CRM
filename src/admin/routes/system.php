<?php

use ChurchCRM\data\Countries;
use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Emails\TestEmail;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\UserQuery;
use ChurchCRM\Service\AppIntegrityService;
use ChurchCRM\Slim\Middleware\InputSanitizationMiddleware;
use ChurchCRM\Utils\ChurchCRMReleaseManager;
use ChurchCRM\Utils\GeoUtils;
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

        // Read flash message from session (set by POST handler)
        $sGlobalMessage      = '';
        $sGlobalMessageClass = 'success';
        if (isset($_SESSION['sGlobalMessage'])) {
            $sGlobalMessage      = $_SESSION['sGlobalMessage'];
            $sGlobalMessageClass = $_SESSION['sGlobalMessageClass'] ?? 'success';
            unset($_SESSION['sGlobalMessage'], $_SESSION['sGlobalMessageClass']);
        }

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
            'sRootPath'          => SystemURLs::getRootPath(),
            'sPageTitle'         => gettext('Church Information'),
            'churchInfo'         => $churchInfo,
            'countries'          => Countries::getNames(),
            'timezones'          => timezone_identifiers_list(),
            'sGlobalMessage'     => $sGlobalMessage,
            'sGlobalMessageClass' => $sGlobalMessageClass,
        ];

        return $renderer->render($response, 'church-info.php', $pageArgs);
    });

    $group->post('/church-info', function (Request $request, Response $response): Response {
        // Body fields have already been sanitized by InputSanitizationMiddleware
        $body = $request->getParsedBody();

        $churchName = trim($body['sChurchName'] ?? '');

        if (empty($churchName)) {
            // Re-render with validation error via the system-wide notify
            $renderer = new PhpRenderer(__DIR__ . '/../views/');

            $churchInfo = [
                'sChurchName'      => $churchName,
                'sChurchAddress'   => $body['sChurchAddress'] ?? '',
                'sChurchCity'      => $body['sChurchCity'] ?? '',
                'sChurchState'     => $body['sChurchState'] ?? '',
                'sChurchZip'       => $body['sChurchZip'] ?? '',
                'sChurchCountry'   => $body['sChurchCountry'] ?? '',
                'sChurchPhone'     => $body['sChurchPhone'] ?? '',
                'sChurchEmail'     => $body['sChurchEmail'] ?? '',
                'iChurchLatitude'  => $body['iChurchLatitude'] ?? '',
                'iChurchLongitude' => $body['iChurchLongitude'] ?? '',
                'sTimeZone'        => $body['sTimeZone'] ?? '',
                'sChurchWebSite'   => $body['sChurchWebSite'] ?? '',
            ];

            $pageArgs = [
                'sRootPath'          => SystemURLs::getRootPath(),
                'sPageTitle'         => gettext('Church Information'),
                'churchInfo'         => $churchInfo,
                'countries'          => Countries::getNames(),
                'timezones'          => timezone_identifiers_list(),
                'sGlobalMessage'     => gettext('Church name is required.'),
                'sGlobalMessageClass' => 'danger',
                'validationError'    => gettext('Church name is required.'),
            ];

            return $renderer->render($response->withStatus(422), 'church-info.php', $pageArgs);
        }

        $address   = $body['sChurchAddress'] ?? '';
        $city      = $body['sChurchCity'] ?? '';
        $state     = $body['sChurchState'] ?? '';
        $zip       = $body['sChurchZip'] ?? '';
        $country   = $body['sChurchCountry'] ?? '';
        $latitude  = trim($body['iChurchLatitude'] ?? '');
        $longitude = trim($body['iChurchLongitude'] ?? '');

        // Auto-geocode using GeoUtils when coordinates are absent but an address exists.
        // GeoUtils uses Nominatim (OpenStreetMap) — no API key required.
        if (($latitude === '' || $longitude === '') && $address !== '') {
            $coords = GeoUtils::getLatLong($address, $city, $state, $zip, $country);
            if ($coords['Latitude'] !== 0.0 || $coords['Longitude'] !== 0.0) {
                $latitude  = (string) $coords['Latitude'];
                $longitude = (string) $coords['Longitude'];
            }
            // If geocoding fails (returns 0,0), leave coordinates empty so the user
            // can enter them manually without being overwritten with an invalid value.
        }

        SystemConfig::setValue('sChurchName', $churchName);
        SystemConfig::setValue('sChurchAddress', $address);
        SystemConfig::setValue('sChurchCity', $city);
        SystemConfig::setValue('sChurchState', $state);
        SystemConfig::setValue('sChurchZip', $zip);
        SystemConfig::setValue('sChurchCountry', $country);
        SystemConfig::setValue('sChurchPhone', $body['sChurchPhone'] ?? '');
        SystemConfig::setValue('sChurchEmail', $body['sChurchEmail'] ?? '');
        SystemConfig::setValue('iChurchLatitude', $latitude);
        SystemConfig::setValue('iChurchLongitude', $longitude);
        SystemConfig::setValue('sTimeZone', $body['sTimeZone'] ?? '');
        SystemConfig::setValue('sChurchWebSite', $body['sChurchWebSite'] ?? '');

        // Flash success via the system-wide notify
        $_SESSION['sGlobalMessage']     = gettext('Church information saved successfully.');
        $_SESSION['sGlobalMessageClass'] = 'success';

        return $response
            ->withHeader('Location', SystemURLs::getRootPath() . '/admin/system/church-info')
            ->withStatus(303);
    })->add(new InputSanitizationMiddleware([
        'sChurchName'      => 'text',
        'sChurchAddress'   => 'text',
        'sChurchCity'      => 'text',
        'sChurchState'     => 'text',
        'sChurchZip'       => 'text',
        'sChurchCountry'   => 'text',
        'sChurchPhone'     => 'text',
        'sChurchEmail'     => 'text',
        'iChurchLatitude'  => 'text',
        'iChurchLongitude' => 'text',
        'sTimeZone'        => 'text',
        'sChurchWebSite'   => 'text',
    ]));

});
