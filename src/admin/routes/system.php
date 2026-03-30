<?php

use ChurchCRM\data\Countries;
use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Emails\TestEmail;
use ChurchCRM\Service\AppIntegrityService;
use ChurchCRM\Slim\Middleware\InputSanitizationMiddleware;
use ChurchCRM\Utils\ChurchCRMReleaseManager;
use ChurchCRM\Utils\GeoUtils;
use ChurchCRM\Utils\VersionUtils;
use ChurchCRM\view\PageHeader;
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
            'sPageSubtitle' => gettext('Create a backup of your church database'),
            'aBreadcrumbs' => PageHeader::breadcrumbs([
                [gettext('Admin'), '/admin/'],
                [gettext('Backup Database')],
            ]),
            'sPageHeaderButtons' => PageHeader::buttons([
                ['label' => gettext('Restore Database'), 'url' => '/admin/system/restore', 'icon' => 'fa-upload'],
            ]),
        ];
        
        return $renderer->render($response, 'backup.php', $pageArgs);
    });

    // Users page
    $group->get('/users', function (Request $request, Response $response): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');
        
        $pageArgs = [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('System Users'),
            'sPageSubtitle' => gettext('Manage system users, permissions, and two-factor authentication settings'),
            'aBreadcrumbs' => PageHeader::breadcrumbs([
                [gettext('Admin'), '/admin/'],
                [gettext('System Users')],
            ]),
            'sSettingsCollapseId' => 'userSettingsPanel',
            'sPageHeaderButtons' => PageHeader::buttons([
                ['label' => gettext('Settings'), 'icon' => 'fa-cog', 'collapse' => '#userSettingsPanel'],
                ['label' => gettext('Add User'), 'url' => '/UserEditor.php', 'icon' => 'fa-user-plus'],
            ]),
        ];
        
        return $renderer->render($response, 'users.php', $pageArgs);
    });

    // Restore Database page
    $group->get('/restore', function (Request $request, Response $response): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');

        $params = $request->getQueryParams();
        $isOnboarding = ($params['context'] ?? '') === 'onboarding';
        
        $pageArgs = [
            'sRootPath'    => SystemURLs::getRootPath(),
            'sPageTitle'   => gettext('Restore Database'),
            'sPageSubtitle' => gettext('Restore your church database from a backup file'),
            'aBreadcrumbs' => PageHeader::breadcrumbs([
                [gettext('Admin'), '/admin/'],
                [gettext('Restore Database')],
            ]),
            'sPageHeaderButtons' => PageHeader::buttons([
                ['label' => gettext('Create Backup'), 'url' => '/admin/system/backup', 'icon' => 'fa-download'],
            ]),
            'isOnboarding' => $isOnboarding,
        ];
        
        return $renderer->render($response, 'restore.php', $pageArgs);
    });

    $group->get('/reset', function (Request $request, Response $response): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');
        
        $pageArgs = [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('Database Reset Functions'),
            'sPageSubtitle' => gettext('Clear all data and start fresh'),
            'aBreadcrumbs' => PageHeader::breadcrumbs([
                [gettext('Admin'), '/admin/'],
                [gettext('Database Reset')],
            ]),
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
            'sPageSubtitle' => gettext('View and manage system log files for debugging'),
            'aBreadcrumbs' => PageHeader::breadcrumbs([
                [gettext('Admin'), '/admin/'],
                [gettext('System Logs')],
            ]),
            'sSettingsCollapseId' => 'logSettings',
            'sPageHeaderButtons' => PageHeader::buttons([
                ['label' => gettext('Settings'), 'icon' => 'fa-cog', 'collapse' => '#logSettings'],
            ]),
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
            'sPageSubtitle' => gettext('System diagnostic information and configuration'),
            'aBreadcrumbs' => PageHeader::breadcrumbs([
                [gettext('Admin'), '/admin/'],
                [gettext('Debug')],
            ]),
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
            'aBreadcrumbs' => PageHeader::breadcrumbs([
                [gettext('Admin'), '/admin/'],
                [gettext('Debug'), '/admin/system/debug'],
                [gettext('Email')],
            ]),
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
            'sPageSubtitle'  => gettext('Review and clean up files not associated with any record'),
            'aBreadcrumbs'   => PageHeader::breadcrumbs([
                [gettext('Admin'), '/admin/'],
                [gettext('Orphaned Files')],
            ]),
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
        
        $pageArgs = [
            'sRootPath'             => SystemURLs::getRootPath(),
            'sPageTitle'            => gettext('System Upgrade'),
            'sPageSubtitle'         => gettext('Check for updates and upgrade your ChurchCRM installation'),
            'aBreadcrumbs'          => PageHeader::breadcrumbs([
                [gettext('Admin'), '/admin/'],
                [gettext('System Upgrade')],
            ]),
            'sSettingsCollapseId'   => 'upgradeSettingsPanel',
            'sPageHeaderButtons'    => PageHeader::buttons([
                ['label' => gettext('Settings'), 'icon' => 'fa-cog', 'collapse' => '#upgradeSettingsPanel'],
            ]),
            'hasWarnings'           => $hasWarnings,
            'integrityCheckFailed'  => $integrityCheckFailed,
            'integrityCheckData'    => $integrityCheckData,
            'hasOrphanedFiles'      => $hasOrphanedFiles,
            'currentVersion'        => $currentVersion,
            'availableVersion'      => $availableVersion,
            'latestGitHubVersion'   => $latestGitHubVersion,
            'isUpdateAvailable'     => $isUpdateAvailable,
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
            'sLanguage'        => SystemConfig::getValue('sLanguage'),
        ];

        $pageArgs = [
            'sRootPath'          => SystemURLs::getRootPath(),
            'sPageTitle'         => gettext('Church Information'),
            'sPageSubtitle'      => gettext('Set your church name, address, and contact details'),
            'aBreadcrumbs'       => PageHeader::breadcrumbs([
                [gettext('Admin'), '/admin/'],
                [gettext('Church Information')],
            ]),
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

        $churchName    = trim($body['sChurchName'] ?? '');
        $churchAddress = trim($body['sChurchAddress'] ?? '');
        $churchCity    = trim($body['sChurchCity'] ?? '');
        $churchState   = trim($body['sChurchState'] ?? '');
        $churchZip     = trim($body['sChurchZip'] ?? '');
        $churchCountry = trim($body['sChurchCountry'] ?? '');
        $churchPhone   = trim($body['sChurchPhone'] ?? '');
        $churchEmail   = trim($body['sChurchEmail'] ?? '');

        // Validation: Required fields
        $validationError = '';
        if (empty($churchName)) {
            $validationError = gettext('Church name is required.');
        } elseif (empty($churchAddress)) {
            $validationError = gettext('Street address is required.');
        } elseif (empty($churchCity)) {
            $validationError = gettext('City is required.');
        } elseif (empty($churchState)) {
            $validationError = gettext('State is required.');
        } elseif (empty($churchZip)) {
            $validationError = gettext('ZIP code is required.');
        } elseif (empty($churchCountry)) {
            $validationError = gettext('Country is required.');
        } elseif (empty($churchPhone)) {
            $validationError = gettext('Phone number is required.');
        } elseif (empty($churchEmail)) {
            $validationError = gettext('Email address is required.');
        }

        if (!empty($validationError)) {
            // Re-render with validation error via the system-wide notify
            $renderer = new PhpRenderer(__DIR__ . '/../views/');

            $churchInfo = [
                'sChurchName'      => $churchName,
                'sChurchAddress'   => $churchAddress,
                'sChurchCity'      => $churchCity,
                'sChurchState'     => $churchState,
                'sChurchZip'       => $churchZip,
                'sChurchCountry'   => $churchCountry,
                'sChurchPhone'     => $churchPhone,
                'sChurchEmail'     => $churchEmail,
                'iChurchLatitude'  => SystemConfig::getValue('iChurchLatitude'),
                'iChurchLongitude' => SystemConfig::getValue('iChurchLongitude'),
                'sTimeZone'        => $body['sTimeZone'] ?? '',
                'sChurchWebSite'   => $body['sChurchWebSite'] ?? '',
                'sLanguage'        => $body['sLanguage'] ?? '',
            ];

            $pageArgs = [
                'sRootPath'          => SystemURLs::getRootPath(),
                'sPageTitle'         => gettext('Church Information'),
                'sPageSubtitle'      => gettext('Set your church name, address, and contact details'),
                'aBreadcrumbs'       => PageHeader::breadcrumbs([
                    [gettext('Admin'), '/admin/'],
                    [gettext('Church Information')],
                ]),
                'churchInfo'         => $churchInfo,
                'countries'          => Countries::getNames(),
                'timezones'          => timezone_identifiers_list(),
                'sGlobalMessage'     => $validationError,
                'sGlobalMessageClass' => 'danger',
                'validationError'    => $validationError,
            ];

            return $renderer->render($response->withStatus(422), 'church-info.php', $pageArgs);
        }

        $address = $churchAddress;
        $city    = $churchCity;
        $state   = $churchState;
        $zip     = $churchZip;
        $country = $churchCountry;

        // Always re-geocode from address using GeoUtils (Nominatim / OpenStreetMap,
        // no API key required). Coordinates are not exposed in the form — they are
        // derived from the address automatically on every save.
        $latitude  = '';
        $longitude = '';
        if ($address !== '') {
            $coords = GeoUtils::getLatLong($address, $city, $state, $zip, $country);
            if ($coords['Latitude'] !== 0.0 || $coords['Longitude'] !== 0.0) {
                $latitude  = (string) $coords['Latitude'];
                $longitude = (string) $coords['Longitude'];
            }
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
        SystemConfig::setValue('sLanguage', $body['sLanguage'] ?? 'en_US');

        // Flash success via the system-wide notify
        $_SESSION['sGlobalMessage']     = gettext('Church information saved successfully.');
        $_SESSION['sGlobalMessageClass'] = 'success';

        return $response
            ->withHeader('Location', SystemURLs::getRootPath() . '/admin/system/church-info')
            ->withStatus(303);
    })->add(new InputSanitizationMiddleware([
        'sChurchName'    => 'text',
        'sChurchAddress' => 'text',
        'sChurchCity'    => 'text',
        'sChurchState'   => 'text',
        'sChurchZip'     => 'text',
        'sChurchCountry' => 'text',
        'sChurchPhone'   => 'text',
        'sChurchEmail'   => 'text',
        'sTimeZone'      => 'text',
        'sChurchWebSite' => 'text',
        'sLanguage'      => 'text',
    ]));

});
