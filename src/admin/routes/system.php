<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Authentication\Exceptions\PasswordChangeException;
use ChurchCRM\data\Countries;
use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Emails\TestEmail;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\UserQuery;
use ChurchCRM\Service\AppIntegrityService;
use ChurchCRM\Slim\Middleware\CSRFMiddleware;
use ChurchCRM\Slim\Middleware\InputSanitizationMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\ChurchCRMReleaseManager;
use ChurchCRM\Utils\GeoUtils;
use ChurchCRM\Utils\InputUtils;
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

    // Admin change user password (GET shows form, POST processes it)
    $group->get('/user/{id}/changePassword', 'adminChangeUserPassword');
    $group->post('/user/{id}/changePassword', 'adminChangeUserPassword')->add(new CSRFMiddleware('admin_change_password'));

    // Option Manager page — manage list options (classifications, family roles,
    // group types, security groups, custom field options, etc.)
    $group->get('/options', function (Request $request, Response $response): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');
        $queryParams = $request->getQueryParams();
        // Mode is a simple string — use sanitizeText (no SQL context needed).
        // Avoid legacyFilterInput which depends on the $cnInfoCentral global.
        $mode = InputUtils::sanitizeText((string) ($queryParams['mode'] ?? ''));

        $customListId = (int) ($queryParams['ListID'] ?? 0);

        // Determine list config based on mode
        $listConfig = match ($mode) {
            'famroles' => ['listId' => 2, 'title' => gettext('Family Roles Editor'), 'noun' => gettext('Family Role')],
            'classes' => ['listId' => 1, 'title' => gettext('Person Classifications Editor'), 'noun' => gettext('Person Classification')],
            'grptypes' => ['listId' => 3, 'title' => gettext('Group Types Editor'), 'noun' => gettext('Group Type')],
            'securitygrp' => ['listId' => 5, 'title' => gettext('Security Groups Editor'), 'noun' => gettext('Security Group')],
            'grproles' => $customListId > 0 && GroupQuery::create()->findOneByRoleListId($customListId) !== null
                ? ['listId' => $customListId, 'title' => gettext('Group Member Roles Editor'), 'noun' => gettext('Group Member Role')]
                : null,
            'custom' => $customListId > 0 && ListOptionQuery::create()->filterById($customListId)->count() > 0
                ? ['listId' => $customListId, 'title' => gettext('Person Custom List Options Editor'), 'noun' => gettext('Custom Option')]
                : null,
            'groupcustom' => $customListId > 0 && ListOptionQuery::create()->filterById($customListId)->count() > 0
                ? ['listId' => $customListId, 'title' => gettext('Custom List Options Editor'), 'noun' => gettext('Custom Option')]
                : null,
            'famcustom' => $customListId > 0 && ListOptionQuery::create()->filterById($customListId)->count() > 0
                ? ['listId' => $customListId, 'title' => gettext('Family Custom List Options Editor'), 'noun' => gettext('Custom Option')]
                : null,
            default => null,
        };

        if ($listConfig === null) {
            return SlimUtils::renderRedirect($response, SystemURLs::getRootPath() . '/admin/');
        }

        // Load options server-side for initial render
        $optionRows = \ChurchCRM\model\ChurchCRM\ListOptionQuery::create()
            ->filterById($listConfig['listId'])
            ->orderByOptionSequence()
            ->find();

        // For classifications mode, compute which options are marked inactive
        $inactiveClasses = [];
        if ($mode === 'classes') {
            $inactiveRaw = (string) SystemConfig::getValue('sInactiveClassification');
            if ($inactiveRaw !== '') {
                $inactiveClasses = array_filter(
                    explode(',', $inactiveRaw),
                    fn($k) => is_numeric($k)
                );
            }
        }

        $pageArgs = [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => $listConfig['title'],
            'sPageSubtitle' => sprintf(gettext('Manage %s options'), $listConfig['noun']),
            'aBreadcrumbs' => PageHeader::breadcrumbs([
                [gettext('Admin'), '/admin/'],
                [$listConfig['title']],
            ]),
            'mode' => $mode,
            'listId' => $listConfig['listId'],
            'noun' => $listConfig['noun'],
            'optionRows' => $optionRows,
            'inactiveClasses' => $inactiveClasses,
        ];

        return $renderer->render($response, 'option-manager.php', $pageArgs);
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

    // Email Debug page — sends a test message to the church's own email
    // address so an admin can confirm the SMTP settings work end-to-end.
    $group->get('/debug/email', function (Request $request, Response $response): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../../v2/templates/email/');

        $configError = null;
        $configErrorFixUrl = null; // page where the setting actually lives
        $churchEmail = ChurchMetaData::getChurchEmail();
        if (!SystemConfig::getBooleanValue('bEnabledEmail')) {
            $configError = gettext('Email is disabled. Enable it in the Email Dashboard settings before testing.');
            $configErrorFixUrl = SystemURLs::getRootPath() . '/v2/email/dashboard?settings=open';
        } elseif (empty(SystemConfig::getValue('sSMTPHost'))) {
            $configError = gettext('SMTP Host is not configured.');
            $configErrorFixUrl = SystemURLs::getRootPath() . '/v2/email/dashboard?settings=open';
        } elseif (empty($churchEmail)) {
            // Church email is configured on the Church Information page, not
            // the Email Dashboard — point the admin at the right page.
            $configError = gettext('Church Email address is not set — the test email has nowhere to go.');
            $configErrorFixUrl = SystemURLs::getRootPath() . '/admin/system/church-info';
        }

        // Gather SMTP settings up-front so the page can render them even when
        // we abort before the send (config errors) — admins still get the
        // summary of what IS configured.
        $smtpSettings = [
            'host'     => SystemConfig::getValue('sSMTPHost') ?: '(not set)',
            'secure'   => SystemConfig::getValue('sPHPMailerSMTPSecure') ?: gettext('(none)'),
            'auth'     => SystemConfig::getBooleanValue('bSMTPAuth') ? gettext('Yes') : gettext('No'),
            'username' => SystemConfig::getBooleanValue('bSMTPAuth') ? SystemConfig::getValue('sSMTPUser') : null,
            'autoTLS'  => SystemConfig::getBooleanValue('bPHPMailerAutoTLS') ? gettext('Yes') : gettext('No'),
            'timeout'  => SystemConfig::getIntValue('iSMTPTimeout') . 's',
        ];

        $sendResult = [
            'attempted' => false,
            'success'   => false,
            'error'     => null,
            'debugLog'  => '',
            'from'      => null,
            'fromName'  => null,
            'to'        => null,
        ];

        if ($configError === null) {
            $email = new TestEmail([$churchEmail]);
            $sendResult['attempted'] = true;
            $sendResult['from']     = $churchEmail;
            $sendResult['fromName'] = ChurchMetaData::getChurchName();
            $sendResult['to']       = $churchEmail;

            // Capture PHPMailer's HTML debug output (Debugoutput='html' echoes
            // directly during send) so we can show it inline instead of bleeding
            // into the rendered page.
            ob_start();
            try {
                $sendResult['success'] = $email->send();
            } catch (\Throwable $e) {
                $sendResult['success'] = false;
                $sendResult['error']   = $e->getMessage();
            }
            $sendResult['debugLog'] = ob_get_clean() ?: '';

            if (!$sendResult['success'] && empty($sendResult['error'])) {
                $sendResult['error'] = $email->getError();
            }
        }

        $pageArgs = [
            'sRootPath'    => SystemURLs::getRootPath(),
            'sPageTitle'   => gettext('Email Debug'),
            'sPageSubtitle' => gettext('Send a test message to verify your SMTP configuration'),
            'aBreadcrumbs' => PageHeader::breadcrumbs([
                [gettext('Admin'), '/admin/'],
                [gettext('Email Dashboard'), '/v2/email/dashboard'],
                [gettext('Debug')],
            ]),
            'configError'  => $configError,
            'configErrorFixUrl' => $configErrorFixUrl,
            'smtpSettings' => $smtpSettings,
            'sendResult'   => $sendResult,
            // ?settings=open matches the dashboard tile behavior — opens the
            // settings panel so the admin lands directly on the fields they
            // need to edit, not the dashboard landing view.
            'emailDashboardUrl' => SystemURLs::getRootPath() . '/v2/email/dashboard?settings=open',
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

        // iChurchLatitude / iChurchLongitude are stored as strings in
        // config_cfg but are semantically floats. Cast on read so the view
        // can compare numerically without "0.0 vs '0' string" surprises, and
        // emit empty string when unset so the form input renders blank
        // instead of "0".
        $rawLat = (string) SystemConfig::getValue('iChurchLatitude');
        $rawLng = (string) SystemConfig::getValue('iChurchLongitude');
        $latFloat = $rawLat === '' ? 0.0 : (float) $rawLat;
        $lngFloat = $rawLng === '' ? 0.0 : (float) $rawLng;

        $churchInfo = [
            'sChurchName'      => SystemConfig::getValue('sChurchName'),
            'sChurchAddress'   => SystemConfig::getValue('sChurchAddress'),
            'sChurchCity'      => SystemConfig::getValue('sChurchCity'),
            'sChurchState'     => SystemConfig::getValue('sChurchState'),
            'sChurchZip'       => SystemConfig::getValue('sChurchZip'),
            'sChurchCountry'   => SystemConfig::getValue('sChurchCountry') ?: 'US',
            'sChurchPhone'     => SystemConfig::getValue('sChurchPhone'),
            'sChurchEmail'     => SystemConfig::getValue('sChurchEmail'),
            'iChurchLatitude'  => ($latFloat !== 0.0 || $lngFloat !== 0.0) ? (string) $latFloat : '',
            'iChurchLongitude' => ($latFloat !== 0.0 || $lngFloat !== 0.0) ? (string) $lngFloat : '',
            'sTimeZone'        => SystemConfig::getValue('sTimeZone'),
            'sChurchWebSite'   => SystemConfig::getValue('sChurchWebSite'),
            'sLanguage'        => SystemConfig::getValue('sLanguage'),
            'sDistanceUnit'    => SystemConfig::getValue('sDistanceUnit'),
            'sDefaultCity'     => SystemConfig::getValue('sDefaultCity'),
            'sDefaultState'    => SystemConfig::getValue('sDefaultState'),
            'sDefaultZip'      => SystemConfig::getValue('sDefaultZip'),
            'sDefaultCountry'  => SystemConfig::getValue('sDefaultCountry'),
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

        // Lat/long are not in the sanitization allow-list — they're numeric.
        // Validate via filter_var below before saving. Empty string is allowed
        // and means "let me geocode from the address" (fallback path).
        $rawLatInput = trim((string) ($body['iChurchLatitude'] ?? ''));
        $rawLngInput = trim((string) ($body['iChurchLongitude'] ?? ''));

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

        // Coordinate validation — only if the user provided one or both fields.
        // Both must be present together (you can't have lat without lng), and
        // both must parse as floats in valid earth ranges.
        $manualLat = null;
        $manualLng = null;
        if ($validationError === '' && ($rawLatInput !== '' || $rawLngInput !== '')) {
            if ($rawLatInput === '' || $rawLngInput === '') {
                $validationError = gettext('Both latitude and longitude must be provided together (or both left blank for automatic detection).');
            } else {
                $parsedLat = filter_var($rawLatInput, FILTER_VALIDATE_FLOAT);
                $parsedLng = filter_var($rawLngInput, FILTER_VALIDATE_FLOAT);
                if ($parsedLat === false || $parsedLat < -90.0 || $parsedLat > 90.0) {
                    $validationError = gettext('Latitude must be a number between -90 and 90.');
                } elseif ($parsedLng === false || $parsedLng < -180.0 || $parsedLng > 180.0) {
                    $validationError = gettext('Longitude must be a number between -180 and 180.');
                } else {
                    $manualLat = $parsedLat;
                    $manualLng = $parsedLng;
                }
            }
        }

        if (!empty($validationError)) {
            // Re-render with validation error via the system-wide notify.
            // Echo the user's lat/long input so they can correct it (rather
            // than wiping their entry and showing the previously-saved value).
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
                'iChurchLatitude'  => $rawLatInput !== '' ? $rawLatInput : (string) (float) SystemConfig::getValue('iChurchLatitude'),
                'iChurchLongitude' => $rawLngInput !== '' ? $rawLngInput : (string) (float) SystemConfig::getValue('iChurchLongitude'),
                'sTimeZone'        => $body['sTimeZone'] ?? '',
                'sChurchWebSite'   => $body['sChurchWebSite'] ?? '',
                'sLanguage'        => $body['sLanguage'] ?? '',
                'sDistanceUnit'    => $body['sDistanceUnit'] ?? 'miles',
                'sDefaultCity'     => $body['sDefaultCity'] ?? '',
                'sDefaultState'    => $body['sDefaultState'] ?? '',
                'sDefaultZip'      => $body['sDefaultZip'] ?? '',
                'sDefaultCountry'  => $body['sDefaultCountry'] ?? '',
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

        // Coordinate resolution — manual entry always wins over auto-detection.
        // 1. If admin entered both lat AND lng manually → use those, skip Nominatim
        // 2. Otherwise → try to geocode from the address via GeoUtils (Nominatim)
        // 3. If geocoding fails (returns 0,0 for a non-empty address) → surface a
        //    warning flash so the admin knows to enter coordinates manually
        $latitude  = '';
        $longitude = '';
        $geocodingFailed = false;

        if ($manualLat !== null && $manualLng !== null) {
            $latitude  = (string) $manualLat;
            $longitude = (string) $manualLng;
        } elseif ($address !== '') {
            $coords = GeoUtils::getLatLong($address, $city, $state, $zip, $country);
            if ($coords['Latitude'] !== 0.0 || $coords['Longitude'] !== 0.0) {
                $latitude  = (string) $coords['Latitude'];
                $longitude = (string) $coords['Longitude'];
            } else {
                // Geocoding silently returned no result — preserve any
                // previously-saved coordinates (don't wipe them) and tell
                // the admin to enter coordinates manually.
                $previousLat = (float) SystemConfig::getValue('iChurchLatitude');
                $previousLng = (float) SystemConfig::getValue('iChurchLongitude');
                $latitude  = $previousLat !== 0.0 ? (string) $previousLat : '';
                $longitude = $previousLng !== 0.0 ? (string) $previousLng : '';
                $geocodingFailed = true;
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
        $distanceUnit = $body['sDistanceUnit'] ?? 'miles';
        SystemConfig::setValue('sDistanceUnit', in_array($distanceUnit, ['miles', 'kilometers'], true) ? $distanceUnit : 'miles');
        SystemConfig::setValue('sDefaultCity', $body['sDefaultCity'] ?? '');
        SystemConfig::setValue('sDefaultState', $body['sDefaultState'] ?? '');
        SystemConfig::setValue('sDefaultZip', $body['sDefaultZip'] ?? '');
        SystemConfig::setValue('sDefaultCountry', $body['sDefaultCountry'] ?? '');

        // Flash success via the system-wide notify. If geocoding silently
        // failed (Nominatim returned no result for a non-empty address) we
        // still saved the rest of the form, but warn the admin so they know
        // to enter coordinates manually.
        if ($geocodingFailed) {
            $_SESSION['sGlobalMessage']      = gettext('Church information saved, but the address could not be auto-located. Please enter the latitude and longitude manually under Map Coordinates.');
            $_SESSION['sGlobalMessageClass'] = 'warning';
        } else {
            $_SESSION['sGlobalMessage']      = gettext('Church information saved successfully');
            $_SESSION['sGlobalMessageClass'] = 'success';
        }

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
        'sTimeZone'       => 'text',
        'sChurchWebSite'  => 'text',
        'sLanguage'       => 'text',
        'sDistanceUnit'   => 'text',
        'sDefaultCity'    => 'text',
        'sDefaultState'   => 'text',
        'sDefaultZip'     => 'text',
        'sDefaultCountry' => 'text',
    ]));

});

function adminChangeUserPassword(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer(__DIR__ . '/../views/');
    $userId = (int) $args['id'];
    $curUser = AuthenticationManager::getCurrentUser();

    $user = UserQuery::create()->findPk($userId);

    if (empty($user)) {
        return SlimUtils::renderRedirect($response, SystemURLs::getRootPath() . '/v2/user/not-found?id=' . $args['id']);
    }

    if ($user->equals($curUser)) {
        // Don't allow the current user (if admin) to set their new password
        // make the user go through the "self-service" password change procedure
        return SlimUtils::renderRedirect($response, SystemURLs::getRootPath() . '/v2/user/current/changepassword');
    }

    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'user' => $user,
        'sPageTitle' => gettext('Change Password') . ': ' . InputUtils::escapeHTML($user->getFullName()),
        'aBreadcrumbs' => PageHeader::breadcrumbs([
            [gettext('Admin'), '/admin/'],
            [gettext('System Users'), '/admin/system/users'],
            [gettext('Change Password')],
        ]),
    ];

    if ($request->getMethod() === 'POST') {
        $loginRequestBody = $request->getParsedBody();

        try {
            $user->adminSetUserPassword($loginRequestBody['NewPassword1']);

            $pageArgs['sPasswordChangeSuccess'] = true;

            return $renderer->render($response, 'adminchangepassword.php', $pageArgs);
        } catch (PasswordChangeException $pwChangeExc) {
            $pageArgs['s' . $pwChangeExc->AffectedPassword . 'PasswordError'] = $pwChangeExc->getMessage();
        }
    }

    return $renderer->render($response, 'adminchangepassword.php', $pageArgs);
}
