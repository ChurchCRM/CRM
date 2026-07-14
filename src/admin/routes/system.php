<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Authentication\Exceptions\PasswordChangeException;
use ChurchCRM\data\Countries;
use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\LocaleInfo;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Emails\TestEmail;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\UserQuery;
use ChurchCRM\Service\AppIntegrityService;
use ChurchCRM\Service\LocaleService;
use ChurchCRM\Service\UserService;
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
                ['label' => gettext('Add User'), 'url' => '/admin/system/users/new', 'icon' => 'fa-user-plus'],
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

        $breadcrumbParent = match ($mode) {
            'grptypes', 'grproles', 'groupcustom' => [gettext('Groups'), '/groups/dashboard'],
            'famroles', 'famcustom' => [gettext('People'), '/people/dashboard'],
            'classes', 'custom' => [gettext('People'), '/people/dashboard'],
            default => [gettext('Admin'), '/admin/'],
        };
        $pageArgs = [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => $listConfig['title'],
            'sPageSubtitle' => sprintf(gettext('Manage %s options'), $listConfig['noun']),
            'aBreadcrumbs' => PageHeader::breadcrumbs([
                $breadcrumbParent,
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

        // Detect "running ahead of stable" — e.g. a prerelease or dev build installed
        // that is newer than the latest stable release known from GitHub.
        $isAheadOfStable = false;
        if ($latestGitHubVersion !== null && !$isUpdateAvailable) {
            $isAheadOfStable = version_compare($currentVersion, $latestGitHubVersion) > 0;
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
            'isAheadOfStable'       => $isAheadOfStable,
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
            'sChurchWebSite'   => SystemConfig::getValue('sChurchWebSite'),
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
                'sChurchWebSite'   => $body['sChurchWebSite'] ?? '',
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
        SystemConfig::setValue('sChurchWebSite', $body['sChurchWebSite'] ?? '');
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
        'sChurchWebSite'  => 'text',
        'sDefaultCity'    => 'text',
        'sDefaultState'   => 'text',
        'sDefaultZip'     => 'text',
        'sDefaultCountry' => 'text',
    ]));

    // ── Localization & Formats ───────────────────────────────────────────────
    // System-wide locale, date/time formats, and phone number formats. These
    // are not church-identity data, so they live on their own page.
    $group->get('/localization', function (Request $request, Response $response): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');

        $sGlobalMessage      = '';
        $sGlobalMessageClass = 'success';
        if (isset($_SESSION['sGlobalMessage'])) {
            $sGlobalMessage      = $_SESSION['sGlobalMessage'];
            $sGlobalMessageClass = $_SESSION['sGlobalMessageClass'] ?? 'success';
            unset($_SESSION['sGlobalMessage'], $_SESSION['sGlobalMessageClass']);
        }

        $localeSettings = [
            'sLanguage'              => SystemConfig::getValue('sLanguage'),
            'sTimeZone'              => SystemConfig::getValue('sTimeZone'),
            'sDistanceUnit'          => SystemConfig::getValue('sDistanceUnit') ?: 'miles',
            // Date & Time Formats — fall back to declared defaults when unset.
            'sDateFormatLong'        => SystemConfig::getValue('sDateFormatLong')        ?: SystemConfig::getConfigItem('sDateFormatLong')->getDefault(),
            'sDateFormatNoYear'      => SystemConfig::getValue('sDateFormatNoYear')      ?: SystemConfig::getConfigItem('sDateFormatNoYear')->getDefault(),
            'sDateTimeFormat'        => SystemConfig::getValue('sDateTimeFormat')        ?: SystemConfig::getConfigItem('sDateTimeFormat')->getDefault(),
            'sDateFilenameFormat'    => SystemConfig::getValue('sDateFilenameFormat')    ?: SystemConfig::getConfigItem('sDateFilenameFormat')->getDefault(),
            'sDatePickerFormat'      => SystemConfig::getValue('sDatePickerFormat')      ?: SystemConfig::getConfigItem('sDatePickerFormat')->getDefault(),
            'sDatePickerPlaceHolder' => SystemConfig::getValue('sDatePickerPlaceHolder') ?: SystemConfig::getConfigItem('sDatePickerPlaceHolder')->getDefault(),
            // Phone Number Formats
            'sPhoneFormat'           => SystemConfig::getValue('sPhoneFormat')           ?: SystemConfig::getConfigItem('sPhoneFormat')->getDefault(),
            'sPhoneFormatWithExt'    => SystemConfig::getValue('sPhoneFormatWithExt')    ?: SystemConfig::getConfigItem('sPhoneFormatWithExt')->getDefault(),
            'sPhoneFormatCell'       => SystemConfig::getValue('sPhoneFormatCell')       ?: SystemConfig::getConfigItem('sPhoneFormatCell')->getDefault(),
        ];

        // Per-locale stats for the Display Preview: translation completeness %
        // (from locale/poeditor.json) + whether the GNU locale is installed on
        // the host OS (so the admin knows date/number formatting will work).
        // The system-locale check shells out via LocaleService; if exec() is
        // disabled we degrade gracefully (systemAvailable = null = "unknown").
        $localeStats        = [];
        $systemCheckEnabled = true;
        $availableNormalized = [];
        try {
            $availableSystem = LocaleService::getAvailableSystemLocales();
            $availableNormalized = array_unique(array_map(
                static fn ($l) => preg_replace('/(\.[^.]*)?(@.*)?$/', '', $l),
                $availableSystem
            ));
        } catch (\Throwable $e) {
            $systemCheckEnabled = false;
        }

        foreach (LocaleService::getSupportedLocales() as $displayName => $cfg) {
            $code = $cfg['locale'] ?? '';
            if ($code === '') {
                continue;
            }
            $info         = new LocaleInfo($code, null);
            $languageCode = $cfg['languageCode'] ?? '';

            $systemAvailable = null;
            if ($systemCheckEnabled) {
                $systemAvailable = in_array($code, $availableNormalized, true)
                    || in_array($languageCode, $availableNormalized, true)
                    || in_array(str_replace('_', '-', $code), $availableNormalized, true);
            }

            // Translation % comes from locale/poeditor.json; guard so a missing
            // file on a given install degrades to "not tracked" instead of 500.
            $showPercentage = false;
            $percentage     = 0;
            try {
                $showPercentage = $info->shouldShowTranslationPercentage();
                $percentage     = $showPercentage ? $info->getTranslationPercentage() : 100;
            } catch (\Throwable $e) {
                $showPercentage = false;
            }

            $localeStats[$code] = [
                'name'            => $displayName,
                'nativeName'      => $info->getNativeName(),
                'flag'            => $info->getCountryFlagCode(),
                'percentage'      => $percentage,
                'showPercentage'  => $showPercentage,
                'systemAvailable' => $systemAvailable,
            ];
        }

        $pageArgs = [
            'sRootPath'          => SystemURLs::getRootPath(),
            'sPageTitle'         => gettext('Localization & Formats'),
            'sPageSubtitle'      => gettext('System language, time zone, date/time formats, and phone number formats'),
            'aBreadcrumbs'       => PageHeader::breadcrumbs([
                [gettext('Admin'), '/admin/'],
                [gettext('Localization & Formats')],
            ]),
            'localeSettings'     => $localeSettings,
            'localeStats'        => $localeStats,
            'systemCheckEnabled' => $systemCheckEnabled,
            'timezones'          => timezone_identifiers_list(),
            'sGlobalMessage'     => $sGlobalMessage,
            'sGlobalMessageClass' => $sGlobalMessageClass,
        ];

        return $renderer->render($response, 'localization.php', $pageArgs);
    });

    $group->post('/localization', function (Request $request, Response $response): Response {
        // Body fields have already been sanitized by InputSanitizationMiddleware
        $body = $request->getParsedBody();

        $supportedLocales = array_keys(LocaleService::getSupportedLocales());
        $lang = $body['sLanguage'] ?? 'en_US';
        SystemConfig::setValue('sLanguage', in_array($lang, $supportedLocales, true) ? $lang : 'en_US');
        $tz = trim((string)($body['sTimeZone'] ?? ''));
        SystemConfig::setValue('sTimeZone', in_array($tz, timezone_identifiers_list(), true) ? $tz : date_default_timezone_get());
        $distanceUnit = $body['sDistanceUnit'] ?? 'miles';
        SystemConfig::setValue('sDistanceUnit', in_array($distanceUnit, ['miles', 'kilometers'], true) ? $distanceUnit : 'miles');

        // Date & Time Formats — fall back to each key's declared default when
        // empty/whitespace. An empty string passed to PHP's date() produces
        // empty output app-wide.
        foreach (['sDateFormatLong', 'sDateFormatNoYear', 'sDateTimeFormat', 'sDateFilenameFormat', 'sDatePickerFormat', 'sDatePickerPlaceHolder'] as $key) {
            $val = trim((string) ($body[$key] ?? ''));
            SystemConfig::setValue($key, $val !== '' ? $val : SystemConfig::getConfigItem($key)->getDefault());
        }
        // Phone Number Formats — same empty-value guard.
        foreach (['sPhoneFormat', 'sPhoneFormatWithExt', 'sPhoneFormatCell'] as $key) {
            $val = trim((string) ($body[$key] ?? ''));
            SystemConfig::setValue($key, $val !== '' ? $val : SystemConfig::getConfigItem($key)->getDefault());
        }

        $_SESSION['sGlobalMessage']      = gettext('Localization settings saved successfully');
        $_SESSION['sGlobalMessageClass'] = 'success';

        return $response
            ->withHeader('Location', SystemURLs::getRootPath() . '/admin/system/localization')
            ->withStatus(303);
    })->add(new InputSanitizationMiddleware([
        'sLanguage'       => 'text',
        'sTimeZone'       => 'text',
        'sDistanceUnit'   => 'text',
        'sDateFormatLong'      => 'text',
        'sDateFormatNoYear'    => 'text',
        'sDateTimeFormat'      => 'text',
        'sDateFilenameFormat'  => 'text',
        'sDatePickerFormat'    => 'text',
        'sDatePickerPlaceHolder' => 'text',
        'sPhoneFormat'         => 'text',
        'sPhoneFormatWithExt'  => 'text',
        'sPhoneFormatCell'     => 'text',
    ]));

    // User editor — create new user (GET shows form, POST processes it)
    $group->get('/users/new', 'adminUserEditorNew');
    $group->post('/users/new', 'adminUserEditorNew')->add(new CSRFMiddleware('user_editor'));

    // User editor — edit existing user (GET shows form, POST processes it)
    $group->get('/users/{personId:[0-9]+}/edit', 'adminUserEditorEdit');
    $group->post('/users/{personId:[0-9]+}/edit', 'adminUserEditorEdit')->add(new CSRFMiddleware('user_editor'));

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

/**
 * User editor — add a new user account.
 *
 * GET  /admin/system/users/new            — shows form (person picker or pre-selected person)
 *   ?personId=N                           — pre-selects person N and skips the picker
 * POST /admin/system/users/new            — creates the user; re-renders on validation error
 */
function adminUserEditorNew(Request $request, Response $response): Response
{
    $renderer    = new PhpRenderer(__DIR__ . '/../views/');
    $userService = new UserService();

    // Common page args
    $pageArgs = [
        'sRootPath'          => SystemURLs::getRootPath(),
        'sPageTitle'         => gettext('User Editor'),
        'sPageSubtitle'      => gettext('Create a new user account'),
        'aBreadcrumbs'       => PageHeader::breadcrumbs([
            [gettext('Admin'), '/admin/'],
            [gettext('Users'), '/admin/system/users'],
            [gettext('New User')],
        ]),
        'sPageHeaderButtons' => PageHeader::buttons([
            ['label' => gettext('User List'), 'url' => '/admin/system/users', 'icon' => 'fa-users'],
        ]),
        'isNew'        => true,
        'configRows'   => [],
        'bEmailEnabled' => SystemConfig::isEmailEnabled(),
    ];

    if ($request->getMethod() === 'POST') {
        $body       = (array) $request->getParsedBody();
        $personId   = (int) ($body['PersonID'] ?? 0);
        $userName   = InputUtils::sanitizeText((string) ($body['UserName'] ?? ''));
        $perms      = $userService->normalizeAccessMode($body);

        // Re-load person name for re-render on error
        $person = $personId > 0 ? PersonQuery::create()->findPk($personId) : null;
        $sUser  = $person ? ($person->getLastName() . ', ' . $person->getFirstName()) : '';

        $pageArgs['editorPersonId']      = $personId;
        $pageArgs['sUser']             = $sUser;
        $pageArgs['sUserName']         = $userName;
        $pageArgs['perms']             = $perms;
        $pageArgs['showPersonSelect']  = false;
        $pageArgs['people']            = [];
        $pageArgs['formAction']        = SystemURLs::getRootPath() . '/admin/system/users/new';

        if ($personId <= 0) {
            $pageArgs['sErrorText']       = gettext('Please select a person to create a user account for.');
            $pageArgs['showPersonSelect'] = true;
            $pageArgs['people']           = $userService->getAssignablePeople();
            $pageArgs['sUserName']        = '';
            $pageArgs['perms']            = ['admin' => 0, 'editSelf' => 0, 'addRecords' => 0,
                                             'editRecords' => 0, 'deleteRecords' => 0,
                                             'menuOptions' => 0, 'manageGroups' => 0,
                                             'finance' => 0, 'manageFundraisers' => 0, 'notes' => 0];
            return $renderer->render($response, 'user-editor.php', $pageArgs);
        }

        try {
            $userService->createUser($personId, $perms, $userName);
            return SlimUtils::renderRedirect($response, SystemURLs::getRootPath() . '/admin/system/users');
        } catch (\Throwable $e) {
            \ChurchCRM\Utils\LoggerUtils::getAppLogger()->error('createUser failed: ' . $e->getMessage(), [
                'personId'  => $personId,
                'userName'  => $userName,
                'exception' => get_class($e),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
            ]);
            $pageArgs['sErrorText'] = $e->getMessage();
            return $renderer->render($response, 'user-editor.php', $pageArgs);
        }
    }

    // GET — show the form
    $personId = (int) ($request->getQueryParams()['personId'] ?? 0);

    if ($personId > 0) {
        // Pre-selected person (from People view "Make User")
        $person = PersonQuery::create()->findPk($personId);
        if ($person === null) {
            return SlimUtils::renderRedirect($response, SystemURLs::getRootPath() . '/admin/system/users');
        }
        $sUser    = $person->getLastName() . ', ' . $person->getFirstName();
        $email    = $person->getEmail();
        $userName = !empty($email) ? $email : $person->getFirstName() . '.' . $person->getLastName();
        $pageArgs['editorPersonId']        = $personId;
        $pageArgs['sUser']            = $sUser;
        $pageArgs['sUserName']        = $userName;
        $pageArgs['showPersonSelect'] = false;
        $pageArgs['people']           = [];
    } else {
        // No person pre-selected — show person picker
        $pageArgs['editorPersonId']        = 0;
        $pageArgs['sUser']            = '';
        $pageArgs['sUserName']        = '';
        $pageArgs['showPersonSelect'] = true;
        $pageArgs['people']           = $userService->getAssignablePeople();
    }

    $pageArgs['perms'] = [
        'admin' => 0, 'editSelf' => 0, 'addRecords' => 0,
        'editRecords' => 0, 'deleteRecords' => 0,
        'menuOptions' => 0, 'manageGroups' => 0,
        'finance' => 0, 'manageFundraisers' => 0, 'notes' => 0,
    ];
    $pageArgs['formAction'] = SystemURLs::getRootPath() . '/admin/system/users/new';
    $pageArgs['sErrorText'] = '';

    return $renderer->render($response, 'user-editor.php', $pageArgs);
}

/**
 * User editor — edit an existing user account.
 *
 * GET  /admin/system/users/{personId}/edit  — shows the edit form
 * POST /admin/system/users/{personId}/edit  — saves account + per-user config; re-renders on error
 */
function adminUserEditorEdit(Request $request, Response $response, array $args): Response
{
    $renderer    = new PhpRenderer(__DIR__ . '/../views/');
    $userService = new UserService();
    $personId    = (int) $args['personId'];

    $user = UserQuery::create()->findPk($personId);
    if ($user === null) {
        return SlimUtils::renderRedirect($response, SystemURLs::getRootPath() . '/admin/system/users');
    }

    $person = $user->getPerson();
    $sUser  = $person ? ($person->getLastName() . ', ' . $person->getFirstName()) : '';

    // Common page args
    $pageArgs = [
        'sRootPath'          => SystemURLs::getRootPath(),
        'sPageTitle'         => gettext('User Editor'),
        'sPageSubtitle'      => gettext('Manage user account details and permissions'),
        'aBreadcrumbs'       => PageHeader::breadcrumbs([
            [gettext('Admin'), '/admin/'],
            [gettext('Users'), '/admin/system/users'],
            [gettext('Edit User')],
        ]),
        'sPageHeaderButtons' => PageHeader::buttons(array_filter([
            ['label' => gettext('View User'), 'url' => '/v2/user/' . $personId, 'icon' => 'fa-eye'],
            ['label' => gettext('User List'), 'url' => '/admin/system/users', 'icon' => 'fa-users'],
        ])),
        'isNew'            => false,
        'editorPersonId'   => $personId,
        'sUser'            => $sUser,
        'showPersonSelect' => false,
        'people'           => [],
        'bEmailEnabled'    => SystemConfig::isEmailEnabled(),
        'formAction'       => SystemURLs::getRootPath() . '/admin/system/users/' . $personId . '/edit',
        'sErrorText'       => '',
    ];

    if ($request->getMethod() === 'POST') {
        $body     = (array) $request->getParsedBody();
        $userName = InputUtils::sanitizeText((string) ($body['UserName'] ?? ''));
        $perms    = $userService->normalizeAccessMode($body);

        $pageArgs['sUserName'] = $userName;
        $pageArgs['perms']     = $perms;
        // configRows is initialised to [] here so the template always has the
        // key; getUserConfigRows() is called inside the try so that a DB error
        // during the load is also caught and surfaced via sErrorText.
        $pageArgs['configRows'] = [];

        try {
            $pageArgs['configRows'] = $userService->getUserConfigRows($personId);
            $newValue      = (array) ($body['new_value'] ?? []);
            $newPermission = (array) ($body['new_permission'] ?? []);
            $types         = (array) ($body['type'] ?? []);
            $userService->updateUserWithConfig($personId, $perms, $userName, $newValue, $newPermission, $types);
            return SlimUtils::renderRedirect($response, SystemURLs::getRootPath() . '/admin/system/users');
        } catch (\Throwable $e) {
            $pageArgs['sErrorText'] = $e->getMessage();
            return $renderer->render($response, 'user-editor.php', $pageArgs);
        }
    }

    // GET — load current values
    $pageArgs['sUserName']  = $user->getUserName();
    $pageArgs['perms']      = [
        'admin'        => $user->getAdmin(),
        'editSelf'     => $user->getEditSelf(),
        'addRecords'   => $user->getAddRecords(),
        'editRecords'  => $user->getEditRecords(),
        'deleteRecords' => $user->getDeleteRecords(),
        'menuOptions'  => $user->getMenuOptions(),
        'manageGroups'       => $user->getManageGroups(),
        'finance'            => $user->getFinance(),
        'manageFundraisers'  => $user->getManageFundraisers(),
        'notes'              => $user->getNotes(),
    ];
    $pageArgs['configRows'] = $userService->getUserConfigRows($personId);

    return $renderer->render($response, 'user-editor.php', $pageArgs);
}
